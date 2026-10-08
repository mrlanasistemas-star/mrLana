<?php

namespace App\Policies;

use App\Models\Empleado;
use App\Models\User;

/**
 * Colaboradores dentro del alcance (mi registro, mi sucursal, mi corporativo o todos).
 * Editar, desactivar y reactivar exigen su permiso y que el registro sea
 * visible para quien actúa.
 */
class EmpleadoPolicy
{
    public function view(User $user, Empleado $empleado): bool
    {
        return $empleado->isVisibleTo($user);
    }

    public function update(User $user, Empleado $empleado): bool
    {
        return $user->can('colaboradores.editar') && $this->view($user, $empleado);
    }

    public function delete(User $user, Empleado $empleado): bool
    {
        return $user->can('colaboradores.desactivar') && $this->view($user, $empleado);
    }

    public function restore(User $user, Empleado $empleado): bool
    {
        return $user->can('colaboradores.reactivar') && $this->view($user, $empleado);
    }
}
