<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Http\Requests\Usuarios\UsuarioRequest;
use App\Mail\EmpleadoAccesoCreadoMail;
use App\Models\Empleado;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Users\AdministratorGuard;
use App\Support\Permissions\AccessScope;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Usuarios: cuentas autenticables. Opcionalmente vinculadas (uno a uno) a un
 * colaborador. Las contraseñas temporales solo se envían por correo y nunca
 * se registran ni se muestran.
 */
class UsuarioController extends Controller
{
    public function __construct(
        private AdministratorGuard $guard,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $estado = in_array($request->query('estado'), ['activos', 'inactivos'], true) ? $request->query('estado') : 'todos';
        $roleId = $request->integer('role_id') ?: null;

        $actor = $request->user();
        $base = fn () => User::query()->visibleTo($actor);

        $users = $base()
            ->with(['roles:id,name', 'empleado:id,nombre,apellido_paterno,apellido_materno,puesto'])
            ->when($q !== '', fn ($qq) => $qq->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhereHas('empleado', fn ($e) => $e->where('nombre', 'like', "%{$q}%")->orWhere('apellido_paterno', 'like', "%{$q}%"))))
            ->when($estado === 'activos', fn ($qq) => $qq->where('activo', true))
            ->when($estado === 'inactivos', fn ($qq) => $qq->where('activo', false))
            ->when($roleId, fn ($qq) => $qq->whereHas('roles', fn ($r) => $r->whereKey($roleId)))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $users->getCollection()->transform(fn (User $u) => $this->present($u, $actor) + [
            'can' => [
                'editar' => $actor->can('update', $u),
                'desactivar' => $actor->can('delete', $u) && ! $u->is($actor),
                'reactivar' => $actor->can('restore', $u),
            ],
        ]);

        return Inertia::render('Usuarios/Index', [
            'users' => $users,
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
            'filters' => ['q' => $q, 'estado' => $estado, 'role_id' => $roleId],
            'counts' => [
                'total' => $base()->count(),
                'activos' => $base()->where('activo', true)->count(),
                'inactivos' => $base()->where('activo', false)->count(),
            ],
            'can' => [
                'registrar' => $request->user()->can('usuarios.registrar'),
                'editar' => $request->user()->can('usuarios.editar'),
                'desactivar' => $request->user()->can('usuarios.desactivar'),
                'reactivar' => $request->user()->can('usuarios.reactivar'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Usuarios/Form', $this->formProps($request, null));
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        return Inertia::render('Usuarios/Form', $this->formProps($request, $user));
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $role = Role::with('permissions:id,name')->findOrFail($data['role_id']);
        $this->assertRoleAssignable($request->user(), $role);
        $this->assertEmpleadoPermitido($request->user(), $data['empleado_id'] ?? null);
        $plainPassword = $this->temporaryPassword();

        $user = DB::transaction(function () use ($data, $role, $plainPassword) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'empleado_id' => $data['empleado_id'] ?? null,
                'activo' => $data['activo'],
            ]);
            $user->password = Hash::make($plainPassword);
            $user->syncLegacyRol($role);
            $user->save();
            $user->syncRoles([$role]);

            return $user;
        });

        $this->notifySecurity($request, "Se creó la cuenta de {$user->name} con el rol «{$role->name}».", [], $user);

        // Correo fuera de la transacción: si falla, la cuenta queda creada sin datos inconsistentes.
        $mailed = $this->notifications->mail($user->email, new EmpleadoAccesoCreadoMail($user, $plainPassword));

        return redirect()->route('usuarios.index')->with(
            $mailed ? 'success' : 'warning',
            $mailed
                ? 'Usuario creado. Se enviaron los accesos por correo.'
                : 'Usuario creado, pero no se pudo enviar el correo con los accesos. Usa "Restablecer contraseña" cuando el correo funcione.'
        );
    }

    public function update(UsuarioRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $data = $request->validated();
        $role = Role::with('permissions:id,name')->findOrFail($data['role_id']);

        $roleChanges = ! $user->roles->contains('id', $role->id) || $user->roles->count() !== 1;
        if ($roleChanges) {
            abort_unless($request->user()->can('changeRole', $user), 403, 'No tienes permiso para cambiar el rol de esta cuenta.');
            $this->assertRoleAssignable($request->user(), $role);
            $this->guard->assertCanChangeRole($user, $role);
        }
        if ((int) ($data['empleado_id'] ?? 0) !== (int) $user->empleado_id) {
            $this->assertEmpleadoPermitido($request->user(), $data['empleado_id'] ?? null);
        }
        // Cambiar el estado desde el formulario exige el mismo permiso que el botón dedicado.
        if ($user->activo && ! $data['activo']) {
            abort_unless($request->user()->can('delete', $user), 403, 'No tienes permiso para desactivar cuentas.');
            $this->assertNotSelf($request, $user, 'activo');
            $this->guard->assertCanDeactivate($user);
        }
        if (! $user->activo && $data['activo']) {
            abort_unless($request->user()->can('restore', $user), 403, 'No tienes permiso para reactivar cuentas.');
        }

        DB::transaction(function () use ($user, $data, $role) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'empleado_id' => $data['empleado_id'] ?? null,
                'activo' => $data['activo'],
            ]);
            $user->syncLegacyRol($role);
            $user->save();
            $user->syncRoles([$role]);
        });

        if ($roleChanges) {
            $this->notifySecurity($request, "El rol de {$user->name} cambió a «{$role->name}».", [$user], $user);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        if (! $user->activo) {
            return back()->with('success', 'La cuenta ya estaba desactivada.');
        }

        $this->assertNotSelf($request, $user, 'user');
        $this->guard->assertCanDeactivate($user, 'user');

        $user->forceFill(['activo' => false])->save();
        $this->notifySecurity($request, "Se desactivó la cuenta de {$user->name}.", [], $user);

        return back()->with('success', 'Cuenta desactivada. La persona ya no podrá iniciar sesión.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('restore', $user);
        $user->forceFill(['activo' => true])->save();
        $this->notifySecurity($request, "Se reactivó la cuenta de {$user->name}.", [], $user);

        return back()->with('success', 'Cuenta reactivada.');
    }

    /**
     * Genera una contraseña temporal y la envía por correo. Si el correo falla,
     * la contraseña anterior se conserva para no dejar a la persona sin acceso.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);
        $plainPassword = $this->temporaryPassword();

        if (! $this->notifications->mail($user->email, new EmpleadoAccesoCreadoMail($user, $plainPassword))) {
            return back()->with('error', 'No se pudo enviar el correo; la contraseña no se cambió. Revisa la configuración de correo e intenta de nuevo.');
        }

        $user->forceFill([
            'password' => Hash::make($plainPassword),
            'remember_token' => Str::random(60),
        ])->save();

        $this->notifySecurity($request, "Se restableció la contraseña de {$user->name}.", [$user], $user);

        return back()->with('success', 'Se envió una contraseña temporal al correo del usuario.');
    }

    private function formProps(Request $request, ?User $user): array
    {
        $user?->load(['roles:id,name', 'empleado']);

        $actor = $request->user();
        // Solo colaboradores dentro del alcance de Usuarios de quien edita.
        $colaboradores = $this->empleadosPermitidos($actor)
            ->where(fn ($q) => $q->doesntHave('user')->when($user?->empleado_id, fn ($qq, $id) => $qq->orWhere('id', $id)))
            ->orderBy('apellido_paterno')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno', 'email', 'puesto', 'activo'])
            ->map(fn (Empleado $e) => [
                'id' => $e->id,
                'nombre' => trim("{$e->nombre} {$e->apellido_paterno} ".($e->apellido_materno ?? '')),
                'email' => $e->email,
                'puesto' => $e->puesto,
                'activo' => (bool) $e->activo,
            ]);

        $effective = $user ? $user->permissionNames() : [];
        $grouped = collect(PermissionCatalog::grouped())
            ->map(fn ($m) => [
                'label' => $m['label'],
                'permissions' => collect($m['permissions'])->whereIn('name', $effective)->pluck('label')->values(),
            ])
            ->filter(fn ($m) => $m['permissions']->isNotEmpty())
            ->values();

        return [
            'user' => $user ? $this->present($user, $actor) + ['role_id' => $user->roles->first()?->id] : null,
            // Solo roles cuyos permisos también tiene quien asigna (evita escalar privilegios).
            'roles' => Role::query()->where('guard_name', 'web')->with('permissions:id,name')->orderBy('name')->get(['id', 'name', 'descripcion'])
                ->filter(fn (Role $r) => $this->canAssign($actor, $r) || $user?->roles->contains('id', $r->id))
                ->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name, 'descripcion' => $r->descripcion])
                ->values(),
            'colaboradores' => $colaboradores,
            'prefill' => $user ? null : $this->prefill($request),
            'effectivePermissions' => $grouped,
            'isSelf' => $user && $user->is($request->user()),
            'can' => [
                'editar' => $user ? $actor->can('update', $user) : $actor->can('usuarios.registrar'),
                'cambiar_rol' => $user ? $actor->can('changeRole', $user) : $actor->can('usuarios.registrar'),
                'restablecer' => $user && $actor->can('resetPassword', $user),
                'desactivar' => $user ? $actor->can('delete', $user) : $actor->can('usuarios.desactivar'),
                'reactivar' => $user ? $actor->can('restore', $user) : $actor->can('usuarios.reactivar'),
                'ver_vinculo' => $actor->can('usuarios.ver_vinculo'),
            ],
        ];
    }

    /** Prellenado al crear la cuenta desde Colaboradores ("Crear usuario"). */
    private function prefill(Request $request): ?array
    {
        $empleado = $request->integer('colaborador') ? Empleado::query()->doesntHave('user')->find($request->integer('colaborador')) : null;
        if (! $empleado) {
            return null;
        }

        return [
            'empleado_id' => $empleado->id,
            'name' => trim("{$empleado->nombre} {$empleado->apellido_paterno}"),
            'email' => $empleado->email,
            'role_id' => Role::where('name', PermissionCatalog::ROLE_COLABORADOR)->value('id'),
        ];
    }

    private function present(User $u, ?User $actor = null): array
    {
        // La relación usuario–colaborador solo se muestra con su permiso.
        $vinculo = $actor === null || $actor->can('usuarios.ver_vinculo');

        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'activo' => (bool) $u->activo,
            'roles' => $u->roles->pluck('name')->values(),
            'empleado_id' => $u->empleado_id,
            'colaborador' => $vinculo && $u->empleado ? [
                'id' => $u->empleado->id,
                'nombre' => trim("{$u->empleado->nombre} {$u->empleado->apellido_paterno} ".($u->empleado->apellido_materno ?? '')),
                'puesto' => $u->empleado->puesto,
            ] : null,
            'created_at' => optional($u->created_at)->toISOString(),
        ];
    }

    /**
     * Colaboradores que se pueden vincular a una cuenta: los del alcance de
     * Usuarios (mi sucursal, mi corporativo o todos).
     */
    private function empleadosPermitidos(User $actor)
    {
        $q = Empleado::query();

        return match (AccessScope::for($actor, 'usuarios')) {
            Scope::Global => $q,
            Scope::Corporativo => $q->whereIn('sucursal_id', AccessScope::sucursalesOf(AccessScope::corporativoId($actor) ?? 0)),
            Scope::Sucursal => $q->where('sucursal_id', AccessScope::sucursalId($actor) ?? 0),
            default => $q->whereRaw('1 = 0'),
        };
    }

    private function assertEmpleadoPermitido(User $actor, mixed $empleadoId): void
    {
        if ($empleadoId === null || $empleadoId === '') {
            // Sin colaborador la cuenta solo es visible con alcance global.
            if (AccessScope::for($actor, 'usuarios') !== Scope::Global) {
                throw ValidationException::withMessages(['empleado_id' => 'Selecciona un colaborador de tu alcance para la cuenta.']);
            }

            return;
        }

        if (! $this->empleadosPermitidos($actor)->whereKey((int) $empleadoId)->exists()) {
            throw ValidationException::withMessages(['empleado_id' => 'El colaborador está fuera de tu alcance de usuarios.']);
        }
    }

    /** Solo se asignan roles cuyos permisos también tiene quien asigna. */
    private function canAssign(User $actor, Role $role): bool
    {
        $mine = collect($actor->permissionNames());

        // Los permisos legados (ocultos) no cuentan: ya no autorizan nada.
        return $role->permissions->pluck('name')
            ->intersect(PermissionCatalog::all())
            ->diff($mine)
            ->isEmpty();
    }

    private function assertRoleAssignable(User $actor, Role $role): void
    {
        if (! $this->canAssign($actor, $role)) {
            throw ValidationException::withMessages(['role_id' => 'No puedes asignar un rol con permisos que tú no tienes.']);
        }
    }

    /** Nadie puede desactivar su propia cuenta (evita bloqueos accidentales). */
    private function assertNotSelf(Request $request, User $user, string $field): void
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([$field => 'No puedes desactivar tu propia cuenta.']);
        }
    }

    private function temporaryPassword(): string
    {
        return Str::password(12, symbols: false);
    }

    /** @param  list<User>  $direct */
    private function notifySecurity(Request $request, string $message, array $direct = [], ?User $subject = null): void
    {
        $this->notifications->notify(
            topic: NotificationTopic::Seguridad,
            event: 'seguridad.usuario_actualizado',
            title: 'Cambio en cuentas de usuario',
            message: "{$request->user()->name}: {$message}",
            severity: 'info',
            url: route('usuarios.index', [], false),
            direct: $direct,
            actor: $request->user(),
            canSee: fn (User $u) => $subject ? $u->can('view', $subject) : AccessScope::for($u, 'usuarios') === Scope::Global,
        );
    }
}
