<?php

namespace App\Support\Permissions;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sincroniza el catálogo de permisos y los roles iniciales de forma idempotente.
 *
 * - Crea permisos faltantes del catálogo (nunca borra permisos existentes).
 * - Crea los roles iniciales si no existen, con sus permisos predeterminados.
 * - A un rol ya existente solo le agrega permisos si es Administrador (que
 *   siempre debe tener el catálogo completo); los demás roles conservan la
 *   personalización hecha desde la interfaz.
 * - Asigna un rol a los usuarios que aún no tienen ninguno, según users.rol.
 */
class RoleSynchronizer
{
    public const GUARD = 'web';

    public function sync(): void
    {
        DB::transaction(function () {
            $this->syncPermissions();
            $this->syncRoles();
            $this->assignLegacyUsers();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function syncPermissions(): void
    {
        $existing = Permission::query()->where('guard_name', self::GUARD)->pluck('name')->all();

        foreach (array_diff(PermissionCatalog::all(), $existing) as $name) {
            Permission::create(['name' => $name, 'guard_name' => self::GUARD]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function syncRoles(): void
    {
        $meta = PermissionCatalog::defaultRoleMeta();

        foreach (PermissionCatalog::defaultRolePermissions() as $roleName => $permissions) {
            /** @var Role|null $role */
            $role = Role::query()->where('name', $roleName)->where('guard_name', self::GUARD)->first();

            if (! $role) {
                $role = Role::create([
                    'name' => $roleName,
                    'guard_name' => self::GUARD,
                    'descripcion' => $meta[$roleName]['descripcion'] ?? null,
                ]);
                $role->syncPermissions($permissions);
            } elseif ($roleName === PermissionCatalog::ROLE_ADMIN) {
                $role->givePermissionTo(array_values(array_diff(
                    $permissions,
                    $role->permissions()->pluck('name')->all(),
                )));
            }

            if (! $role->notificationPreference()->exists()) {
                $role->notificationPreference()->create([
                    'receive_all' => $meta[$roleName]['receive_all'] ?? false,
                    'topics' => $meta[$roleName]['topics'] ?? [],
                ]);
            }
        }
    }

    public function assignLegacyUsers(): void
    {
        $rolesByName = Role::query()->where('guard_name', self::GUARD)->get()->keyBy('name');

        User::query()
            ->whereDoesntHave('roles')
            ->orderBy('id')
            ->each(function (User $user) use ($rolesByName) {
                $legacy = strtoupper((string) $user->rol);
                $roleName = PermissionCatalog::LEGACY_ROLE_MAP[$legacy] ?? PermissionCatalog::ROLE_COLABORADOR;
                $role = $rolesByName->get($roleName);

                if ($role) {
                    $user->assignRole($role);
                }
            });
    }
}
