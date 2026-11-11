<?php

namespace App\Services\Users;

use App\Enums\NotificationTopic;
use App\Mail\EmpleadoAccesoCreadoMail;
use App\Models\Empleado;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\Permissions\AccessScope;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\Scope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reglas compartidas para crear y administrar cuentas de acceso, tanto desde
 * el alta unificada de Colaboradores como desde la edición de una cuenta.
 * Las contraseñas temporales solo se envían por correo; nunca se muestran.
 */
class CuentasDeAcceso
{
    public function __construct(
        private AdministratorGuard $guard,
        private NotificationService $notifications,
    ) {}

    /** Solo se asignan roles cuyos permisos también tiene quien asigna. */
    public function canAssign(User $actor, Role $role): bool
    {
        $role->loadMissing('permissions:id,name');
        $mine = collect($actor->permissionNames());

        // Los permisos legados (ocultos) no cuentan: ya no autorizan nada.
        return $role->permissions->pluck('name')
            ->intersect(PermissionCatalog::all())
            ->diff($mine)
            ->isEmpty();
    }

    public function assertRoleAssignable(User $actor, Role $role, string $field = 'role_id'): void
    {
        if (! $this->canAssign($actor, $role)) {
            throw ValidationException::withMessages([$field => 'No puedes asignar un rol con permisos que tú no tienes.']);
        }
    }

    /**
     * Roles que se ofrecen en el formulario: los asignables por quien edita y
     * el rol actual de la cuenta (para mostrarlo aunque no pueda asignarlo).
     *
     * @return Collection<int, array{id: int, name: string, descripcion: ?string}>
     */
    public function rolesPara(User $actor, ?User $target = null): Collection
    {
        $target?->loadMissing('roles:id,name');

        return Role::query()->where('guard_name', 'web')->with('permissions:id,name')->orderBy('name')->get(['id', 'name', 'descripcion'])
            ->filter(fn (Role $r) => $this->canAssign($actor, $r) || $target?->roles->contains('id', $r->id))
            ->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name, 'descripcion' => $r->descripcion])
            ->values();
    }

    /** Permisos efectivos de una cuenta agrupados por módulo (para el resumen). */
    public function permisosEfectivos(?User $user): Collection
    {
        $effective = $user ? $user->permissionNames() : [];

        return collect(PermissionCatalog::grouped())
            ->map(fn ($m) => [
                'label' => $m['label'],
                'permissions' => collect($m['permissions'])->whereIn('name', $effective)->pluck('label')->values(),
            ])
            ->filter(fn ($m) => $m['permissions']->isNotEmpty())
            ->values();
    }

    /**
     * Sucursales activas (de un corporativo activo) dentro del alcance de
     * Usuarios: ahí puede quedar el colaborador de una cuenta que se crea.
     */
    public function sucursalesPermitidas(User $actor)
    {
        $q = Sucursal::query()->where('activo', true)->whereHas('corporativo', fn ($c) => $c->where('activo', true));

        return match (AccessScope::for($actor, 'usuarios')) {
            Scope::Global => $q,
            Scope::Corporativo => $q->where('corporativo_id', AccessScope::corporativoId($actor) ?? 0),
            Scope::Sucursal => $q->whereKey(AccessScope::sucursalId($actor) ?? 0),
            default => $q->whereRaw('1 = 0'),
        };
    }

    /**
     * Verifica que quien registra pueda crear la cuenta de una persona de la
     * sucursal indicada con ese rol. Devuelve el rol listo para asignar.
     *
     * @param  array<string, string>  $fields  nombres de campo para los errores
     */
    public function prepararAlta(User $actor, int $roleId, int $sucursalId, array $fields = []): Role
    {
        $fields += ['role_id' => 'role_id', 'sucursal_id' => 'sucursal_id'];
        abort_unless($actor->can('usuarios.registrar'), 403, 'No tienes permiso para dar acceso al sistema.');

        if (! $this->sucursalesPermitidas($actor)->whereKey($sucursalId)->exists()) {
            throw ValidationException::withMessages([$fields['sucursal_id'] => 'No puedes dar acceso a personas de esta sucursal: está fuera de tu alcance de usuarios.']);
        }

        $role = Role::with('permissions:id,name')->findOrFail($roleId);
        $this->assertRoleAssignable($actor, $role, $fields['role_id']);

        return $role;
    }

    /**
     * Valida los cambios de rol, estado y correo de una cuenta existente con
     * los mismos permisos que la edición de Usuarios. No guarda nada.
     *
     * @param  array<string, string>  $fields
     * @return bool ¿Cambia el rol?
     */
    public function validarCambios(User $actor, User $user, Role $role, bool $activo, ?string $email, array $fields = []): bool
    {
        $fields += ['role_id' => 'role_id', 'activo' => 'activo'];
        $user->loadMissing('roles:id,name');

        if ($email !== null && $email !== $user->email) {
            abort_unless($actor->can('update', $user), 403, 'No tienes permiso para cambiar el correo de esta cuenta.');
        }

        $roleChanges = ! $user->roles->contains('id', $role->id) || $user->roles->count() !== 1;
        if ($roleChanges) {
            abort_unless($actor->can('changeRole', $user), 403, 'No tienes permiso para cambiar el rol de esta cuenta.');
            $this->assertRoleAssignable($actor, $role, $fields['role_id']);
            $this->guard->assertCanChangeRole($user, $role, $fields['role_id']);
        }

        if ($user->activo && ! $activo) {
            abort_unless($actor->can('delete', $user), 403, 'No tienes permiso para desactivar cuentas.');
            if ($user->is($actor)) {
                throw ValidationException::withMessages([$fields['activo'] => 'No puedes desactivar tu propia cuenta.']);
            }
            $this->guard->assertCanDeactivate($user, $fields['activo']);
        }
        if (! $user->activo && $activo) {
            abort_unless($actor->can('restore', $user), 403, 'No tienes permiso para reactivar cuentas.');
        }

        return $roleChanges;
    }

    /**
     * Crea la cuenta con una contraseña temporal. Llamar dentro de la
     * transacción del alta; el correo se envía después con enviarAcceso().
     *
     * @param  array{name: string, email: string, empleado_id: ?int, activo: bool}  $attrs
     */
    public function crear(array $attrs, Role $role, string $plainPassword): User
    {
        $user = new User([
            'name' => $attrs['name'],
            'email' => $attrs['email'],
            'empleado_id' => $attrs['empleado_id'],
            'activo' => $attrs['activo'],
        ]);
        $user->password = Hash::make($plainPassword);
        $user->syncLegacyRol($role);
        $user->save();
        $user->syncRoles([$role]);

        return $user;
    }

    /** Asigna el rol (y el campo legado) a una cuenta existente. */
    public function asignarRol(User $user, Role $role): void
    {
        $user->syncLegacyRol($role);
        $user->save();
        $user->syncRoles([$role]);
    }

    /** Nombre de la cuenta a partir de los datos del colaborador. */
    public static function nombreDe(Empleado|array $persona): string
    {
        $p = $persona instanceof Empleado ? $persona->only(['nombre', 'apellido_paterno']) : $persona;

        return mb_substr(trim(($p['nombre'] ?? '').' '.($p['apellido_paterno'] ?? '')), 0, 150);
    }

    public function temporaryPassword(): string
    {
        return Str::password(12, symbols: false);
    }

    /** Envía la contraseña temporal. Devuelve false si el correo falló. */
    public function enviarAcceso(User $user, string $plainPassword): bool
    {
        return $this->notifications->mail($user->email, new EmpleadoAccesoCreadoMail($user, $plainPassword));
    }

    /** @param  list<User>  $direct */
    public function notificar(User $actor, string $message, array $direct = [], ?User $subject = null): void
    {
        $this->notifications->notify(
            topic: NotificationTopic::Seguridad,
            event: 'seguridad.usuario_actualizado',
            title: 'Cambio en cuentas de usuario',
            message: "{$actor->name}: {$message}",
            severity: 'info',
            url: route('usuarios.index', [], false),
            direct: $direct,
            actor: $actor,
            canSee: fn (User $u) => $subject ? $u->can('view', $subject) : AccessScope::for($u, 'usuarios') === Scope::Global,
        );
    }
}
