<?php

namespace App\Models;

use App\Support\Permissions\AccessScope;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Plantilla
 *
 * Representa una “plantilla” o requisición guardada por un usuario para reutilizarla posteriormente.
 * Cada plantilla pertenece a un usuario y almacena la cabecera y los detalles de una requisición.
 * Las plantillas tienen estados simples (BORRADOR o ELIMINADA).
 */
class Plantilla extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'monto_subtotal' => 'decimal:2',
        'monto_total' => 'decimal:2',
        'fecha_solicitud' => 'datetime',
        'fecha_pago_esperada' => 'date',
        'fecha_autorizacion' => 'datetime',
    ];

    /**
     * Limita la consulta a las plantillas que el usuario puede ver.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return AccessScope::apply($query, $user, 'plantillas', [
            'own' => fn ($q) => $q->where('plantillas.user_id', $user->id),
        ]);
    }

    public function isVisibleTo(User $user): bool
    {
        return AccessScope::contains($user, 'plantillas', (int) $this->user_id === (int) $user->id);
    }

    /* ============================
     * Relaciones
     * ============================ */

    // Usuario dueño de la plantilla
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Sucursal asociada a la plantilla
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    // Empleado solicitante (puede estar vacío si el usuario aún no lo configuró)
    public function solicitante()
    {
        return $this->belongsTo(Empleado::class, 'solicitante_id');
    }

    // Corporativo comprador derivado de la sucursal
    public function comprador()
    {
        return $this->belongsTo(Corporativo::class, 'comprador_corp_id');
    }

    // Proveedor sugerido en la plantilla
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    // Concepto sugerido en la plantilla
    public function concepto()
    {
        return $this->belongsTo(Concepto::class);
    }

    // Detalles de la plantilla (líneas de compra)
    public function detalles()
    {
        return $this->hasMany(PlantillaDetalle::class, 'plantilla_id');
    }
}
