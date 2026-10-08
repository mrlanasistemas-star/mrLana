<?php

namespace App\Policies;

use App\Models\Sucursal;
use App\Models\User;

/**
 * Sucursales dentro del alcance (mi sucursal, las de mi corporativo o todas).
 * Editar, desactivar y reactivar exigen su permiso y que el registro sea
 * visible para quien actúa.
 */
class SucursalPolicy
{
    public function view(User $user, Sucursal $sucursal): bool
    {
        return $sucursal->isVisibleTo($user);
    }

    public function update(User $user, Sucursal $sucursal): bool
    {
        return $user->can('sucursales.editar') && $this->view($user, $sucursal);
    }

    public function delete(User $user, Sucursal $sucursal): bool
    {
        return $user->can('sucursales.desactivar') && $this->view($user, $sucursal);
    }

    public function restore(User $user, Sucursal $sucursal): bool
    {
        return $user->can('sucursales.reactivar') && $this->view($user, $sucursal);
    }
}
