<?php

namespace App\Policies;

use App\Models\User;

/**
 * Cuentas de usuario: cada acción exige su permiso y que la cuenta esté dentro
 * del alcance (mi sucursal, mi corporativo o todas). El perfil propio no pasa
 * por aquí: cualquier cuenta activa puede editarlo.
 */
class UserPolicy
{
    public function view(User $actor, User $target): bool
    {
        return $target->isVisibleTo($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('usuarios.editar') && $this->view($actor, $target);
    }

    public function changeRole(User $actor, User $target): bool
    {
        return $actor->can('usuarios.cambiar_rol') && $this->view($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('usuarios.desactivar') && $this->view($actor, $target);
    }

    public function restore(User $actor, User $target): bool
    {
        return $actor->can('usuarios.reactivar') && $this->view($actor, $target);
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->can('usuarios.restablecer_contrasena') && $this->view($actor, $target);
    }
}
