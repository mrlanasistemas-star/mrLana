<?php

namespace App\Http\Controllers;

use App\Http\Requests\Usuarios\UsuarioRequest;
use App\Models\Empleado;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Users\AdministratorGuard;
use App\Services\Users\CuentasDeAcceso;
use App\Support\Permissions\AccessScope;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Usuarios: cuentas autenticables, vinculadas (uno a uno) a un colaborador.
 * Forma parte del módulo Colaboradores: el alta de personas con acceso y la
 * edición de cuentas con colaborador se hacen en Colaboradores; aquí quedan la
 * pestaña «Cuentas de acceso» y la edición de cuentas sin colaborador (o de
 * personas que quien edita no ve en Colaboradores). Las contraseñas
 * temporales solo se envían por correo y nunca se muestran.
 */
class UsuarioController extends Controller
{
    public function __construct(
        private AdministratorGuard $guard,
        private CuentasDeAcceso $cuentas,
    ) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $estado = in_array($request->query('estado'), ['activos', 'inactivos'], true) ? $request->query('estado') : 'todos';
        $roleId = $request->integer('role_id') ?: null;

        $actor = $request->user();
        // Filtro por colaborador vinculado: solo con permiso para ver esa relación.
        $verVinculo = $actor->can('usuarios.ver_vinculo');
        $vinculo = $verVinculo && in_array($request->query('vinculo'), ['con', 'sin'], true) ? $request->query('vinculo') : 'todos';
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
            ->when($vinculo === 'con', fn ($qq) => $qq->whereNotNull('empleado_id'))
            ->when($vinculo === 'sin', fn ($qq) => $qq->whereNull('empleado_id'))
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
            'filters' => ['q' => $q, 'estado' => $estado, 'role_id' => $roleId, 'vinculo' => $vinculo],
            'counts' => [
                'total' => $base()->count(),
                'activos' => $base()->where('activo', true)->count(),
                'inactivos' => $base()->where('activo', false)->count(),
                'sin_colaborador' => $verVinculo ? $base()->whereNull('empleado_id')->count() : null,
            ],
            'can' => [
                'registrar' => $request->user()->can('usuarios.registrar'),
                'ver_vinculo' => $verVinculo,
                'editar' => $request->user()->can('usuarios.editar'),
                'desactivar' => $request->user()->can('usuarios.desactivar'),
                'reactivar' => $request->user()->can('usuarios.reactivar'),
            ],
        ]);
    }

    /**
     * El alta vive en Colaboradores: se registra a la persona y, en la misma
     * pantalla, se le da acceso. «Crear usuario» de un colaborador existente
     * abre su edición con el acceso activado.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $empleado = $request->integer('colaborador') ? Empleado::query()->doesntHave('user')->find($request->integer('colaborador')) : null;

        if (! $empleado) {
            return redirect()->route('colaboradores.create', ['acceso' => 1]);
        }
        if ($request->user()->can('view', $empleado)) {
            return redirect()->route('colaboradores.edit', ['empleado' => $empleado, 'acceso' => 1]);
        }

        // Colaborador fuera del alcance de Colaboradores pero dentro del de Usuarios.
        return Inertia::render('Usuarios/Form', $this->formProps($request, null));
    }

    /**
     * Las cuentas con colaborador se editan junto con la persona en
     * Colaboradores. Aquí quedan las cuentas sin colaborador (para ligarlas o
     * completar sus datos) y las de personas que quien edita no ve en
     * Colaboradores.
     */
    public function edit(Request $request, User $user): Response|RedirectResponse
    {
        $this->authorize('view', $user);

        if ($user->empleado && $request->user()->can('view', $user->empleado)) {
            return redirect()->route('colaboradores.edit', $user->empleado);
        }

        return Inertia::render('Usuarios/Form', $this->formProps($request, $user));
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $role = Role::with('permissions:id,name')->findOrFail($data['role_id']);
        $this->cuentas->assertRoleAssignable($request->user(), $role);
        $sucursal = $this->sucursalParaColaborador($request->user(), $data);
        if (! $sucursal) {
            $this->assertEmpleadoPermitido($request->user(), $data['empleado_id'] ?? null);
        }
        $plainPassword = $this->cuentas->temporaryPassword();

        $user = DB::transaction(fn () => $this->cuentas->crear([
            'name' => $data['name'],
            'email' => $data['email'],
            'empleado_id' => $sucursal ? $this->crearColaborador($data, $sucursal)->id : ($data['empleado_id'] ?? null),
            'activo' => $data['activo'],
        ], $role, $plainPassword));

        $this->notifySecurity($request, "Se creó la cuenta de {$user->name} con el rol «{$role->name}».", [], $user);

        // Correo fuera de la transacción: si falla, la cuenta queda creada sin datos inconsistentes.
        $mailed = $this->cuentas->enviarAcceso($user, $plainPassword);

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

        // Rol y estado exigen los mismos permisos que los botones dedicados.
        $roleChanges = $this->cuentas->validarCambios($request->user(), $user, $role, (bool) $data['activo'], null);
        $sucursal = $this->sucursalParaColaborador($request->user(), $data);
        if (! $sucursal && (int) ($data['empleado_id'] ?? 0) !== (int) $user->empleado_id) {
            $this->assertEmpleadoPermitido($request->user(), $data['empleado_id'] ?? null);
        }

        DB::transaction(function () use ($user, $data, $role, $sucursal) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'empleado_id' => $sucursal ? $this->crearColaborador($data, $sucursal)->id : ($data['empleado_id'] ?? null),
                'activo' => $data['activo'],
            ]);
            $this->cuentas->asignarRol($user, $role);
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
        $plainPassword = $this->cuentas->temporaryPassword();

        if (! $this->cuentas->enviarAcceso($user, $plainPassword)) {
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

        $grouped = $this->cuentas->permisosEfectivos($user);

        return [
            'user' => $user ? $this->present($user, $actor) + ['role_id' => $user->roles->first()?->id] : null,
            // Solo roles cuyos permisos también tiene quien asigna (evita escalar privilegios).
            'roles' => $this->cuentas->rolesPara($actor, $user),
            'colaboradores' => $colaboradores,
            // Sucursales donde se puede registrar el colaborador de una cuenta que no elige uno.
            'sucursales' => $this->cuentas->sucursalesPermitidas($actor)->with('corporativo:id,nombre')->orderBy('nombre')
                ->get(['id', 'corporativo_id', 'nombre'])
                ->map(fn (Sucursal $s) => ['id' => $s->id, 'nombre' => $s->nombre, 'corporativo' => $s->corporativo?->nombre])
                ->values(),
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

    /**
     * Sucursal donde se registrará el colaborador de la cuenta cuando no se
     * eligió uno existente (null si se eligió colaborador o no se indicó sucursal).
     */
    private function sucursalParaColaborador(User $actor, array $data): ?Sucursal
    {
        if (($data['empleado_id'] ?? null) !== null || ($data['sucursal_id'] ?? null) === null) {
            return null;
        }

        return $this->cuentas->sucursalesPermitidas($actor)->find((int) $data['sucursal_id'])
            ?? throw ValidationException::withMessages(['sucursal_id' => 'La sucursal no está activa o está fuera de tu alcance de usuarios.']);
    }

    /**
     * Registra el colaborador de una cuenta que no eligió uno, para que la
     * persona quede como solicitante y pueda crear requisiciones de inmediato.
     * El nombre de la cuenta se reparte en nombre y apellidos; se puede
     * corregir después en Colaboradores.
     */
    private function crearColaborador(array $data, Sucursal $sucursal): Empleado
    {
        return Empleado::create(Empleado::nombreDesde($data['name']) + [
            'sucursal_id' => $sucursal->id,
            'email' => $data['email'],
            'activo' => true,
        ]);
    }

    /** Nadie puede desactivar su propia cuenta (evita bloqueos accidentales). */
    private function assertNotSelf(Request $request, User $user, string $field): void
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([$field => 'No puedes desactivar tu propia cuenta.']);
        }
    }

    /** @param  list<User>  $direct */
    private function notifySecurity(Request $request, string $message, array $direct = [], ?User $subject = null): void
    {
        $this->cuentas->notificar($request->user(), $message, $direct, $subject);
    }
}
