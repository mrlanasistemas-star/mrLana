<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Http\Requests\Roles\RoleRequest;
use App\Models\Role;
use App\Services\Notifications\NotificationService;
use App\Services\Users\AdministratorGuard;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y permisos.
 *
 * Los tres roles iniciales son "de sistema": no se renombran ni se eliminan.
 * Administrador conserva siempre el catálogo completo de permisos.
 */
class RoleController extends Controller
{
    public function __construct(
        private AdministratorGuard $guard,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->withCount(['users'])
            ->with(['notificationPreference', 'permissions:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'descripcion' => $r->descripcion,
                'users_count' => $r->users_count,
                'active_users_count' => $r->users()->where('activo', true)->count(),
                // Solo permisos vigentes: los legados ocultos no se cuentan.
                'permissions_count' => $r->permissions->pluck('name')->intersect(PermissionCatalog::all())->count(),
                'is_system' => $this->isSystem($r),
                'is_admin' => $this->guard->roleGrantsAdministration($r->permissions->pluck('name')),
                'receive_all' => (bool) $r->notificationPreference?->receive_all,
                'topics' => $r->notificationPreference?->topics ?? [],
            ]);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'totalPermissions' => count(PermissionCatalog::all()),
            'can' => [
                'registrar' => $request->user()->can('roles.registrar'),
                'editar' => $request->user()->can('roles.editar'),
                'eliminar' => $request->user()->can('roles.eliminar'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Form', $this->formProps(null));
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Roles/Form', $this->formProps($role));
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = DB::transaction(function () use ($data, $request) {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => 'web',
                'descripcion' => $data['descripcion'] ?? null,
            ]);
            $role->syncPermissions($request->normalizedPermissions());
            $role->notificationPreference()->create([
                'receive_all' => (bool) $data['receive_all'],
                'topics' => $data['receive_all'] ? [] : ($data['topics'] ?? []),
            ]);

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->notifySecurity($request, "Se creó el rol «{$role->name}».");

        return redirect()->route('roles.index')->with('success', 'Rol creado correctamente.');
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();
        $isSystem = $this->isSystem($role);
        $isAdminRole = $role->name === PermissionCatalog::ROLE_ADMIN;

        if ($isSystem && $data['name'] !== $role->name) {
            return back()->withErrors(['name' => 'Los roles iniciales del sistema no se pueden renombrar.']);
        }

        // Administrador conserva siempre todo el catálogo (actual y futuro).
        $permissions = $isAdminRole ? PermissionCatalog::all() : $request->normalizedPermissions();

        $this->guard->assertCanChangeRolePermissions($role, $permissions);

        DB::transaction(function () use ($role, $data, $permissions) {
            $role->update([
                'name' => $data['name'],
                'descripcion' => $data['descripcion'] ?? null,
            ]);
            $role->syncPermissions($permissions);
            $role->notificationPreference()->updateOrCreate([], [
                'receive_all' => (bool) $data['receive_all'],
                'topics' => $data['receive_all'] ? [] : ($data['topics'] ?? []),
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->notifySecurity($request, "Se actualizaron los permisos del rol «{$role->name}».");

        return redirect()->route('roles.index')->with('success', $isAdminRole
            ? 'Rol actualizado. Administrador conserva siempre todos los permisos.'
            : 'Rol actualizado correctamente.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        if ($this->isSystem($role)) {
            return back()->with('error', 'Los roles iniciales del sistema no se pueden eliminar.');
        }

        $usuarios = $role->users()->count();
        if ($usuarios > 0) {
            return back()->with('error', "No se puede eliminar: {$usuarios} usuario(s) tienen este rol. Asígnales otro rol primero.");
        }

        $this->guard->assertCanDeleteRole($role);

        $name = $role->name;
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->notifySecurity($request, "Se eliminó el rol «{$name}».");

        return redirect()->route('roles.index')->with('success', 'Rol eliminado.');
    }

    private function formProps(?Role $role): array
    {
        $role?->load(['permissions:id,name', 'notificationPreference']);

        return [
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'descripcion' => $role->descripcion,
                // Solo permisos vigentes; los legados ocultos nunca llegan a la interfaz.
                'permissions' => $role->permissions->pluck('name')->intersect(PermissionCatalog::all())->values(),
                'receive_all' => (bool) $role->notificationPreference?->receive_all,
                'topics' => $role->notificationPreference?->topics ?? [],
                'users_count' => $role->users()->count(),
                'active_users_count' => $role->users()->where('activo', true)->count(),
                'is_system' => $this->isSystem($role),
                'is_admin_role' => $role->name === PermissionCatalog::ROLE_ADMIN,
            ] : null,
            'modules' => PermissionCatalog::forUi(),
            'scopeLevels' => collect(\App\Support\Permissions\Scope::cases())->map(fn ($s) => ['value' => $s->key(), 'label' => $s->label()])->values(),
            'topics' => NotificationTopic::options(),
            'adminPermissions' => PermissionCatalog::ADMIN_PERMISSIONS,
            'canEdit' => request()->user()->can($role ? 'roles.editar' : 'roles.registrar'),
        ];
    }

    private function isSystem(Role $role): bool
    {
        return array_key_exists($role->name, PermissionCatalog::defaultRolePermissions());
    }

    private function notifySecurity(Request $request, string $message): void
    {
        $this->notifications->notify(
            topic: NotificationTopic::Seguridad,
            event: 'seguridad.rol_actualizado',
            title: 'Cambio en roles y permisos',
            message: "{$request->user()->name}: {$message}",
            severity: 'warning',
            url: route('roles.index', [], false),
            actor: $request->user(),
        );
    }
}
