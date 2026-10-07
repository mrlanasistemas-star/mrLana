<?php

namespace App\Policies;

use App\Models\Requisicion;
use App\Models\RequisicionEliminacionSolicitud;
use App\Models\User;

/**
 * Autorización por requisición: combina el permiso del módulo con el alcance
 * ("ver todos" o "ver propios"). Cambiar un ID en la URL no da acceso a
 * registros ajenos.
 */
class RequisicionPolicy
{
    /** Estatus en los que se puede solicitar un ajuste de monto. */
    public const AJUSTE_STATUSES = [
        'CAPTURADA', 'PAGO_AUTORIZADO', 'PAGADA', 'POR_COMPROBAR', 'COMPROBACION_ACEPTADA', 'COMPROBACION_RECHAZADA',
    ];

    public function viewAny(User $user): bool
    {
        return $user->canAny(['requisiciones.ver_todos', 'requisiciones.ver_propios']);
    }

    public function view(User $user, Requisicion $requisicion): bool
    {
        if ($user->can('requisiciones.ver_todos')) {
            return true;
        }

        return $user->can('requisiciones.ver_propios') && $requisicion->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can('requisiciones.registrar');
    }

    /** Editar o capturar un borrador. */
    public function update(User $user, Requisicion $requisicion): bool
    {
        return $requisicion->status === 'BORRADOR'
            && $user->can('requisiciones.editar')
            && $this->manages($user, $requisicion);
    }

    public function capture(User $user, Requisicion $requisicion): bool
    {
        return $requisicion->status === 'BORRADOR'
            && $user->can('requisiciones.registrar')
            && $this->manages($user, $requisicion);
    }

    /**
     * Eliminar: con permiso "Eliminar requisiciones" cualquiera visible que no
     * esté ya eliminada; sin él, solo borradores propios.
     */
    public function delete(User $user, Requisicion $requisicion): bool
    {
        if ($requisicion->status === 'ELIMINADA' || ! $this->view($user, $requisicion)) {
            return false;
        }

        if ($user->can('requisiciones.eliminar')) {
            return true;
        }

        return $requisicion->status === 'BORRADOR'
            && $user->can('requisiciones.registrar')
            && $requisicion->isOwnedBy($user);
    }

    /** Solicitar eliminación: solo CAPTURADA y sin otra solicitud pendiente. */
    public function requestDeletion(User $user, Requisicion $requisicion): bool
    {
        return $requisicion->status === 'CAPTURADA'
            && $user->can('requisiciones.solicitar_eliminacion')
            && $this->view($user, $requisicion)
            && ! $requisicion->eliminacionSolicitudes()
                ->where('estatus', RequisicionEliminacionSolicitud::PENDIENTE)
                ->exists();
    }

    public function viewAjustes(User $user, Requisicion $requisicion): bool
    {
        return $user->can('ajustes.ver') && $this->view($user, $requisicion);
    }

    public function requestAdjustment(User $user, Requisicion $requisicion): bool
    {
        return in_array($requisicion->status, self::AJUSTE_STATUSES, true)
            && $user->can('ajustes.solicitar')
            && $this->view($user, $requisicion);
    }

    public function viewPayments(User $user, Requisicion $requisicion): bool
    {
        return $user->can('pagos.ver') && $this->view($user, $requisicion);
    }

    public function authorizePayment(User $user, Requisicion $requisicion): bool
    {
        return $user->can('pagos.autorizar') && $this->view($user, $requisicion);
    }

    public function registerPayment(User $user, Requisicion $requisicion): bool
    {
        return $user->can('pagos.registrar') && $this->view($user, $requisicion);
    }

    public function viewComprobaciones(User $user, Requisicion $requisicion): bool
    {
        return $user->can('comprobaciones.ver') && $this->view($user, $requisicion);
    }

    public function uploadComprobante(User $user, Requisicion $requisicion): bool
    {
        return $requisicion->status !== 'ELIMINADA'
            && $user->can('comprobaciones.subir')
            && $this->view($user, $requisicion);
    }

    /** Puede gestionar el registro: lo ve todo o es propio. */
    private function manages(User $user, Requisicion $requisicion): bool
    {
        return $user->can('requisiciones.ver_todos') || $requisicion->isOwnedBy($user);
    }
}
