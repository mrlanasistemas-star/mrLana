<?php

namespace App\Policies;

use App\Models\Plantilla;
use App\Models\User;

/**
 * Plantillas: las propias con "Editar/Eliminar mis plantillas"; las de otras
 * personas (visibles por alcance) con "…cualquier plantilla autorizada".
 */
class PlantillaPolicy
{
    public function view(User $user, Plantilla $plantilla): bool
    {
        return $plantilla->isVisibleTo($user);
    }

    public function update(User $user, Plantilla $plantilla): bool
    {
        return $this->manages($user, $plantilla, 'plantillas.editar', 'plantillas.editar_cualquiera');
    }

    public function delete(User $user, Plantilla $plantilla): bool
    {
        return $this->manages($user, $plantilla, 'plantillas.eliminar', 'plantillas.eliminar_cualquiera');
    }

    private function manages(User $user, Plantilla $plantilla, string $own, string $any): bool
    {
        if (! $this->view($user, $plantilla)) {
            return false;
        }

        return (int) $plantilla->user_id === (int) $user->id
            ? $user->can($own) || $user->can($any)
            : $user->can($any);
    }
}
