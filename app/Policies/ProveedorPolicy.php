<?php

namespace App\Policies;

use App\Models\Proveedor;
use App\Models\User;

/**
 * Proveedores: quien no tiene "Ver proveedores de todos los usuarios" solo
 * gestiona los que registró.
 */
class ProveedorPolicy
{
    public function update(User $user, Proveedor $proveedor): bool
    {
        return $user->can('proveedores.editar') && $this->owns($user, $proveedor);
    }

    public function delete(User $user, Proveedor $proveedor): bool
    {
        return $user->can('proveedores.desactivar') && $this->owns($user, $proveedor);
    }

    public function restore(User $user, Proveedor $proveedor): bool
    {
        return $user->can('proveedores.reactivar') && $this->owns($user, $proveedor);
    }

    private function owns(User $user, Proveedor $proveedor): bool
    {
        return $user->can('proveedores.ver_todos') || (int) $proveedor->user_duenio_id === (int) $user->id;
    }
}
