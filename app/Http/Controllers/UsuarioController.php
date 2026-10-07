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
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

        $users = User::query()
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

        $users->getCollection()->transform(fn (User $u) => $this->present($u));

        return Inertia::render('Usuarios/Index', [
            'users' => $users,
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
            'filters' => ['q' => $q, 'estado' => $estado, 'role_id' => $roleId],
            'counts' => [
                'total' => User::count(),
                'activos' => User::where('activo', true)->count(),
                'inactivos' => User::where('activo', false)->count(),
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
        return Inertia::render('Usuarios/Form', $this->formProps($request, $user));
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $role = Role::findOrFail($data['role_id']);
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

        $this->notifySecurity($request, "Se creó la cuenta de {$user->name} con el rol «{$role->name}».");

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
        $data = $request->validated();
        $role = Role::with('permissions:id,name')->findOrFail($data['role_id']);

        $roleChanges = ! $user->roles->contains('id', $role->id) || $user->roles->count() !== 1;
        if ($roleChanges) {
            $this->guard->assertCanChangeRole($user, $role);
        }
        if ($user->activo && ! $data['activo']) {
            $this->guard->assertCanDeactivate($user);
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
            $this->notifySecurity($request, "El rol de {$user->name} cambió a «{$role->name}».", [$user]);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        if (! $user->activo) {
            return back()->with('success', 'La cuenta ya estaba desactivada.');
        }

        $this->guard->assertCanDeactivate($user, 'user');

        $user->forceFill(['activo' => false])->save();
        $this->notifySecurity($request, "Se desactivó la cuenta de {$user->name}.");

        return back()->with('success', 'Cuenta desactivada. La persona ya no podrá iniciar sesión.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $user->forceFill(['activo' => true])->save();
        $this->notifySecurity($request, "Se reactivó la cuenta de {$user->name}.");

        return back()->with('success', 'Cuenta reactivada.');
    }

    /**
     * Genera una contraseña temporal y la envía por correo. Si el correo falla,
     * la contraseña anterior se conserva para no dejar a la persona sin acceso.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $plainPassword = $this->temporaryPassword();

        if (! $this->notifications->mail($user->email, new EmpleadoAccesoCreadoMail($user, $plainPassword))) {
            return back()->with('error', 'No se pudo enviar el correo; la contraseña no se cambió. Revisa la configuración de correo e intenta de nuevo.');
        }

        $user->forceFill([
            'password' => Hash::make($plainPassword),
            'remember_token' => Str::random(60),
        ])->save();

        $this->notifySecurity($request, "Se restableció la contraseña de {$user->name}.", [$user]);

        return back()->with('success', 'Se envió una contraseña temporal al correo del usuario.');
    }

    private function formProps(Request $request, ?User $user): array
    {
        $user?->load(['roles:id,name', 'empleado']);

        $colaboradores = Empleado::query()
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
            'user' => $user ? $this->present($user) + ['role_id' => $user->roles->first()?->id] : null,
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name', 'descripcion']),
            'colaboradores' => $colaboradores,
            'prefill' => $user ? null : $this->prefill($request),
            'effectivePermissions' => $grouped,
            'isSelf' => $user && $user->is($request->user()),
            'can' => [
                'editar' => $request->user()->can($user ? 'usuarios.editar' : 'usuarios.registrar'),
                'restablecer' => $user && $request->user()->can('usuarios.restablecer_contrasena'),
                'desactivar' => $request->user()->can('usuarios.desactivar'),
                'reactivar' => $request->user()->can('usuarios.reactivar'),
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

    private function present(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'activo' => (bool) $u->activo,
            'roles' => $u->roles->pluck('name')->values(),
            'empleado_id' => $u->empleado_id,
            'colaborador' => $u->empleado ? [
                'id' => $u->empleado->id,
                'nombre' => trim("{$u->empleado->nombre} {$u->empleado->apellido_paterno} ".($u->empleado->apellido_materno ?? '')),
                'puesto' => $u->empleado->puesto,
            ] : null,
            'created_at' => optional($u->created_at)->toISOString(),
        ];
    }

    private function temporaryPassword(): string
    {
        return Str::password(12, symbols: false);
    }

    /** @param  list<User>  $direct */
    private function notifySecurity(Request $request, string $message, array $direct = []): void
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
        );
    }
}
