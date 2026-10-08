<?php

namespace App\Policies;

use App\Models\Comprobante;
use App\Models\Requisicion;
use App\Models\RequisicionEliminacionSolicitud;
use App\Models\User;
use App\Support\Permissions\AccessScope;

/**
 * Autorización por requisición: combina el permiso de la acción con el
 * alcance del módulo correspondiente (requisiciones, pagos, comprobaciones o
 * ajustes), resuelto por AccessScope. Cambiar un ID en la URL no da acceso a
 * registros fuera del alcance, y un permiso de acción nunca amplía el alcance.
 */
class RequisicionPolicy
{
    /** Estatus en los que se puede solicitar un ajuste de monto. */
    public const AJUSTE_STATUSES = [
        'CAPTURADA', 'PAGO_AUTORIZADO', 'PAGADA', 'POR_COMPROBAR', 'COMPROBACION_ACEPTADA', 'COMPROBACION_RECHAZADA',
    ];

    public function viewAny(User $user): bool
    {
        return AccessScope::for($user, 'requisiciones')->allows();
    }

    public function view(User $user, Requisicion $requisicion): bool
    {
        return $requisicion->isVisibleTo($user);
    }

    /** PDF individual. */
    public function print(User $user, Requisicion $requisicion): bool
    {
        return $user->can('requisiciones.imprimir') && $this->view($user, $requisicion);
    }

    public function create(User $user): bool
    {
        return $user->can('requisiciones.registrar');
    }

    /** Editar un borrador: los propios con "Editar mis…", los demás visibles con "Editar cualquier…". */
    public function update(User $user, Requisicion $requisicion): bool
    {
        return $requisicion->status === 'BORRADOR' && $this->managesDraft($user, $requisicion, 'requisiciones.editar');
    }

    /** Enviar un borrador a autorización. */
    public function capture(User $user, Requisicion $requisicion): bool
    {
        return $requisicion->status === 'BORRADOR'
            && $user->can('requisiciones.enviar')
            && $this->managesDraft($user, $requisicion, 'requisiciones.enviar');
    }

    /**
     * Eliminar: con "Eliminar requisiciones autorizadas" cualquiera visible que
     * no esté ya eliminada; con "Eliminar mis borradores", solo borradores propios.
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
            && $user->can('requisiciones.eliminar_borrador')
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

    public function reviewDeletion(User $user, Requisicion $requisicion): bool
    {
        return $user->can('requisiciones.autorizar_eliminacion') && $this->view($user, $requisicion);
    }

    /* ------------------------------------------------------------ Ajustes */

    public function viewAjustes(User $user, Requisicion $requisicion): bool
    {
        return $requisicion->isVisibleTo($user, 'ajustes');
    }

    public function requestAdjustment(User $user, Requisicion $requisicion): bool
    {
        return in_array($requisicion->status, self::AJUSTE_STATUSES, true)
            && $this->viewAjustes($user, $requisicion)
            && ($user->can('ajustes.solicitar_cualquiera')
                || ($user->can('ajustes.solicitar') && $requisicion->isOwnedBy($user)));
    }

    public function approveAdjustment(User $user, Requisicion $requisicion): bool
    {
        return $user->can('ajustes.autorizar') && $this->viewAjustes($user, $requisicion);
    }

    public function rejectAdjustment(User $user, Requisicion $requisicion): bool
    {
        return $user->can('ajustes.rechazar') && $this->viewAjustes($user, $requisicion);
    }

    public function applyAdjustment(User $user, Requisicion $requisicion): bool
    {
        return $user->can('ajustes.aplicar') && $this->viewAjustes($user, $requisicion);
    }

    /* -------------------------------------------------------------- Pagos */

    public function viewPayments(User $user, Requisicion $requisicion): bool
    {
        return $requisicion->isVisibleTo($user, 'pagos');
    }

    public function authorizePayment(User $user, Requisicion $requisicion): bool
    {
        return $user->can('pagos.autorizar') && $this->viewPayments($user, $requisicion);
    }

    public function rejectPayment(User $user, Requisicion $requisicion): bool
    {
        return $user->can('pagos.rechazar') && $this->viewPayments($user, $requisicion);
    }

    public function registerPayment(User $user, Requisicion $requisicion): bool
    {
        return $user->can('pagos.registrar') && $this->viewPayments($user, $requisicion);
    }

    public function editPayment(User $user, Requisicion $requisicion): bool
    {
        return $user->can('pagos.editar') && $this->viewPayments($user, $requisicion);
    }

    public function downloadPayment(User $user, Requisicion $requisicion): bool
    {
        return $user->can('pagos.descargar') && $this->viewPayments($user, $requisicion);
    }

    /* ----------------------------------------------------- Comprobaciones */

    public function viewComprobaciones(User $user, Requisicion $requisicion): bool
    {
        return $requisicion->isVisibleTo($user, 'comprobaciones');
    }

    public function uploadComprobante(User $user, Requisicion $requisicion): bool
    {
        return $requisicion->status !== 'ELIMINADA'
            && $this->viewComprobaciones($user, $requisicion)
            && ($user->can('comprobaciones.subir_cualquiera')
                || ($user->can('comprobaciones.subir') && $requisicion->isOwnedBy($user)));
    }

    /** Aprobar (APROBADO) o rechazar (RECHAZADO) un comprobante. */
    public function reviewComprobante(User $user, Requisicion $requisicion, ?string $decision = null): bool
    {
        if (! $user->can('comprobaciones.revisar') || ! $this->viewComprobaciones($user, $requisicion)) {
            return false;
        }

        return match ($decision) {
            'APROBADO' => $user->can('comprobaciones.aceptar'),
            'RECHAZADO' => $user->can('comprobaciones.rechazar'),
            default => $user->canAny(['comprobaciones.aceptar', 'comprobaciones.rechazar']),
        };
    }

    /** Eliminar un comprobante: cualquiera visible, o los propios aún no aprobados. */
    public function deleteComprobante(User $user, Requisicion $requisicion, ?Comprobante $comprobante = null): bool
    {
        if (! $this->viewComprobaciones($user, $requisicion)) {
            return false;
        }

        if ($user->can('comprobaciones.eliminar')) {
            return true;
        }

        return $comprobante !== null
            && $user->can('comprobaciones.eliminar_propios')
            && (int) $comprobante->user_carga_id === (int) $user->id
            && $comprobante->estatus !== 'APROBADO';
    }

    /* -------------------------------------------------------------- Ayudas */

    /** Borrador propio con el permiso indicado, o visible con "Editar cualquier borrador". */
    private function managesDraft(User $user, Requisicion $requisicion, string $ownPermission): bool
    {
        if ($requisicion->isOwnedBy($user) && $user->can($ownPermission) && $this->view($user, $requisicion)) {
            return true;
        }

        return $user->can('requisiciones.editar_cualquiera') && $this->view($user, $requisicion);
    }
}
