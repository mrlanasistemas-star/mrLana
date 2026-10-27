<?php

namespace App\Policies;

use App\Models\Folio;
use App\Models\User;

/**
 * Folios de factura: se ven según el alcance de Comprobaciones (calculado
 * sobre quien registró el folio). Editar exige además "Editar folios de
 * factura"; el permiso nunca amplía el alcance.
 */
class FolioPolicy
{
    public function view(User $user, Folio $folio): bool
    {
        return $folio->isVisibleTo($user);
    }

    /** Solo datos complementarios (monto): el número de folio es fijo. */
    public function update(User $user, Folio $folio): bool
    {
        return $user->can('comprobaciones.administrar_folios') && $this->view($user, $folio);
    }
}
