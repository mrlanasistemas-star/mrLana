<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ajuste de monto de una requisición.
 *
 * Flujo: PENDIENTE → APROBADO → APLICADO, o PENDIENTE → RECHAZADO / CANCELADO.
 * - user_registro_id / fecha_registro: quién y cuándo lo solicitó.
 * - user_resuelve_id / fecha_resolucion / comentario_revision: revisión.
 * - user_aplica_id / fecha_aplicacion: aplicación al monto de la requisición.
 */
class Ajuste extends Model
{
    use HasFactory;

    public const ESTATUS_PENDIENTE = 'PENDIENTE';

    public const ESTATUS_APROBADO = 'APROBADO';

    public const ESTATUS_RECHAZADO = 'RECHAZADO';

    public const ESTATUS_APLICADO = 'APLICADO';

    public const ESTATUS_CANCELADO = 'CANCELADO';

    protected $table = 'ajustes';

    protected $fillable = [
        'requisicion_id',
        'tipo',
        'sentido',
        'monto',
        'monto_anterior',
        'monto_nuevo',
        'estatus',
        'metodo',
        'referencia',
        'motivo',
        'fecha_registro',
        'fecha_resolucion',
        'fecha_aplicacion',
        'user_registro_id',
        'user_resuelve_id',
        'user_aplica_id',
        'notas',
        'comentario_revision',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'monto_anterior' => 'decimal:2',
        'monto_nuevo' => 'decimal:2',
        'fecha_registro' => 'datetime',
        'fecha_resolucion' => 'datetime',
        'fecha_aplicacion' => 'datetime',
    ];

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class);
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_registro_id');
    }

    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_resuelve_id');
    }

    public function aplicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_aplica_id');
    }

    public function getDescripcionAttribute(): ?string
    {
        return $this->motivo;
    }

    public function setDescripcionAttribute($value): void
    {
        $this->attributes['motivo'] = $value;
    }

    public function getFechaAttribute(): ?string
    {
        return $this->fecha_registro
            ? Carbon::parse($this->fecha_registro)->toDateString()
            : null;
    }

    public function setFechaAttribute($value): void
    {
        if (! $value) {
            return;
        }
        $this->attributes['fecha_registro'] = Carbon::parse($value)->startOfDay();
    }
}
