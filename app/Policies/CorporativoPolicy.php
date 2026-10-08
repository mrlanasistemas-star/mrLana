<?php

namespace App\Policies;

use App\Models\Corporativo;
use App\Models\User;

/**
 * Corporativos dentro del alcance (mi corporativo o todos).
 * Editar, desactivar y reactivar exigen su permiso y que el registro sea
 * visible para quien actúa.
 */
class CorporativoPolicy
{
    public function view(User $user, Corporativo $corporativo): bool
    {
        return $corporativo->isVisibleTo($user);
    }

    public function update(User $user, Corporativo $corporativo): bool
    {
        return $user->can('corporativos.editar') && $this->view($user, $corporativo);
    }

    public function delete(User $user, Corporativo $corporativo): bool
    {
        return $user->can('corporativos.desactivar') && $this->view($user, $corporativo);
    }

    public function restore(User $user, Corporativo $corporativo): bool
    {
        return $user->can('corporativos.reactivar') && $this->view($user, $corporativo);
    }
}
