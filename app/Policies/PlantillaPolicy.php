<?php

namespace App\Policies;

use App\Models\Plantilla;
use App\Models\User;

class PlantillaPolicy
{
    public function view(User $user, Plantilla $plantilla): bool
    {
        if ($user->can('plantillas.ver_todos')) {
            return true;
        }

        return $user->can('plantillas.ver_propios') && (int) $plantilla->user_id === (int) $user->id;
    }

    public function update(User $user, Plantilla $plantilla): bool
    {
        return $user->can('plantillas.editar') && $this->view($user, $plantilla);
    }

    public function delete(User $user, Plantilla $plantilla): bool
    {
        return $user->can('plantillas.eliminar') && $this->view($user, $plantilla);
    }
}
