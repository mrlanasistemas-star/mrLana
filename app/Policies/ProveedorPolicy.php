<?php

namespace App\Policies;

use App\Models\Proveedor;
use App\Models\User;

/**
 * Proveedores: cada acción exige su permiso y que el proveedor esté dentro
 * del alcance ("mis proveedores" o "todos").
 */
class ProveedorPolicy
{
    public function view(User $user, Proveedor $proveedor): bool
    {
        return $proveedor->isVisibleTo($user);
    }

    public function update(User $user, Proveedor $proveedor): bool
    {
        return $user->can('proveedores.editar') && $this->view($user, $proveedor);
    }

    public function delete(User $user, Proveedor $proveedor): bool
    {
        return $user->can('proveedores.desactivar') && $this->view($user, $proveedor);
    }

    public function restore(User $user, Proveedor $proveedor): bool
    {
        return $user->can('proveedores.reactivar') && $this->view($user, $proveedor);
    }
}
