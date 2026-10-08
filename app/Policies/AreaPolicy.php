<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

/**
 * Áreas dentro del alcance (mi área, las de mi corporativo o todas).
 * Editar, desactivar y reactivar exigen su permiso y que el registro sea
 * visible para quien actúa.
 */
class AreaPolicy
{
    public function view(User $user, Area $area): bool
    {
        return $area->isVisibleTo($user);
    }

    public function update(User $user, Area $area): bool
    {
        return $user->can('areas.editar') && $this->view($user, $area);
    }

    public function delete(User $user, Area $area): bool
    {
        return $user->can('areas.desactivar') && $this->view($user, $area);
    }

    public function restore(User $user, Area $area): bool
    {
        return $user->can('areas.reactivar') && $this->view($user, $area);
    }
}
