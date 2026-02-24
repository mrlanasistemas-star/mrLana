<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Ajuste extends Model {

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
        'user_registro_id',
        'user_resuelve_id',
        'notas',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'monto_anterior' => 'decimal:2',
        'monto_nuevo' => 'decimal:2',
        'fecha_registro' => 'datetime',
        'fecha_resolucion' => 'datetime',
    ];

    public function requisicion(): BelongsTo {
        return $this->belongsTo(Requisicion::class);
    }

    public function getDescripcionAttribute(): ?string {
        return $this->motivo;
    }

    public function setDescripcionAttribute($value): void {
        $this->attributes['motivo'] = $value;
    }

    public function getFechaAttribute(): ?string {
        return $this->fecha_registro
            ? Carbon::parse($this->fecha_registro)->toDateString()
            : null;
    }

    public function setFechaAttribute($value): void {
        if (!$value) return;
        $this->attributes['fecha_registro'] = Carbon::parse($value)->startOfDay();
    }

}
