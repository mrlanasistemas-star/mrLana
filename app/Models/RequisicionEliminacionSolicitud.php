<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud para eliminar una requisición CAPTURADA (aún no pagada).
 * La solicita el colaborador y la autoriza o rechaza Contabilidad.
 */
class RequisicionEliminacionSolicitud extends Model
{
    public const PENDIENTE = 'PENDIENTE';
    public const APROBADA = 'APROBADA';
    public const RECHAZADA = 'RECHAZADA';
    public const CANCELADA = 'CANCELADA';

    protected $table = 'requisicion_eliminacion_solicitudes';

    protected $fillable = [
        'requisicion_id',
        'solicitado_por_id',
        'motivo',
        'estatus',
        'revisado_por_id',
        'comentario_revision',
        'fecha_resolucion',
    ];

    protected $casts = [
        'fecha_resolucion' => 'datetime',
    ];

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class);
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }
}
