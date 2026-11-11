<?php

namespace App\Services\Requisiciones;

use App\Models\Requisicion;
use App\Models\User;

/**
 * Marca una requisición como eliminada dejando registro de quién lo hizo
 * (o autorizó la solicitud), cuándo, por qué y cuánto se había pagado.
 * Los pagos y comprobantes no se borran: quedan para auditoría. La bitácora
 * registra la baja sola (LogsActivity) con estos campos.
 */
final class EliminarRequisicion
{
    public static function marcar(Requisicion $requisicion, User $por, ?string $motivo = null): void
    {
        $pagado = round($requisicion->montoPagado(), 2);
        $motivo = trim((string) $motivo) ?: null;

        $requisicion->forceFill([
            'status' => 'ELIMINADA',
            'eliminada_por_id' => $por->id,
            'eliminada_at' => now(),
            'eliminacion_motivo' => $motivo,
            'pagado_al_eliminar' => $pagado,
        ])->save();
    }
}
