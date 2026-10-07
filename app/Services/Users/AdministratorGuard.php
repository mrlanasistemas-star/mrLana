<?php

namespace App\Services\Users;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Impide que una operación deje al sistema sin al menos un usuario activo con
 * administración total (permisos de PermissionCatalog::ADMIN_PERMISSIONS).
 *
 * Cada método simula el estado posterior al cambio y lanza una
 * ValidationException con un mensaje humano si el resultado no es válido.
 */
class AdministratorGuard
{
    public const MESSAGE = 'Esta acción dejaría al sistema sin ningún administrador activo. Asigna antes la administración a otra cuenta.';

    /** Al desactivar un usuario. */
    public function assertCanDeactivate(User $user, string $field = 'activo'): void
    {
        $this->assertRemaining(
            $this->activeAdmins()->reject(fn (User $u) => $u->is($user)),
            $field,
        );
    }

    /**
     * Al desactivar varias cuentas a la vez (p. ej. baja de colaboradores).
     *
     * @param  iterable<User>  $users
     */
    public function assertCanDeactivateMany(iterable $users, string $field = 'activo'): void
    {
        $ids = collect($users)->map(fn (User $u) => $u->id)->all();

        if ($ids === []) {
            return;
        }

        $this->assertRemaining(
            $this->activeAdmins()->reject(fn (User $u) => in_array($u->id, $ids, true)),
            $field,
        );
    }

    /** Al cambiar el rol de un usuario. */
    public function assertCanChangeRole(User $user, ?Role $newRole, string $field = 'role_id'): void
    {
        if (! $user->activo) {
            return;
        }

        $newIsAdmin = $newRole !== null && $this->roleGrantsAdministration($newRole->permissions->pluck('name'));

        if ($newIsAdmin) {
            return;
        }

        $this->assertRemaining(
            $this->activeAdmins()->reject(fn (User $u) => $u->is($user)),
            $field,
        );
    }

    /**
     * Al cambiar los permisos de un rol.
     *
     * @param  iterable<string>  $newPermissions
     */
    public function assertCanChangeRolePermissions(Role $role, iterable $newPermissions, string $field = 'permissions'): void
    {
        if ($this->roleGrantsAdministration(collect($newPermissions))) {
            return;
        }

        $remaining = $this->activeAdmins()->filter(
            fn (User $u) => $this->userStaysAdminWithout($u, $role)
        );

        $this->assertRemaining($remaining, $field);
    }

    /** Al eliminar un rol. */
    public function assertCanDeleteRole(Role $role, string $field = 'role'): void
    {
        $remaining = $this->activeAdmins()->filter(
            fn (User $u) => $this->userStaysAdminWithout($u, $role)
        );

        $this->assertRemaining($remaining, $field);
    }

    /** @return Collection<int, User> */
    public function activeAdmins(): Collection
    {
        return User::query()
            ->active()
            ->with(['roles.permissions', 'permissions'])
            ->get()
            ->filter(fn (User $u) => $u->hasFullAdministration())
            ->values();
    }

    /** @param  Collection<int, string>  $permissions */
    public function roleGrantsAdministration(Collection $permissions): bool
    {
        return collect(PermissionCatalog::ADMIN_PERMISSIONS)->every(fn ($p) => $permissions->contains($p));
    }

    /** ¿El usuario conserva administración total sin el rol indicado? */
    private function userStaysAdminWithout(User $user, Role $excluded): bool
    {
        $permissions = $user->permissions->pluck('name');
        foreach ($user->roles as $role) {
            if (! $role->is($excluded)) {
                $permissions = $permissions->merge($role->permissions->pluck('name'));
            }
        }

        return $this->roleGrantsAdministration($permissions);
    }

    /** @param  Collection<int, User>  $remaining */
    private function assertRemaining(Collection $remaining, string $field): void
    {
        if ($remaining->isEmpty()) {
            throw ValidationException::withMessages([$field => self::MESSAGE]);
        }
    }
}
