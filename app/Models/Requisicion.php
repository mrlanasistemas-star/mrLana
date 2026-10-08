<?php

namespace App\Models;

use App\Support\Permissions\AccessScope;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Requisicion extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected $fillable = [
        'folio',
        'status',
        'solicitante_id',
        'sucursal_id',
        'comprador_corp_id',
        'proveedor_id',
        'concepto_id',
        'monto_subtotal',
        'monto_total',
        'fecha_solicitud',
        'fecha_pago_esperada',
        'fecha_autorizacion',
        'fecha_pago',
        'observaciones',
        'creada_por_user_id',
    ];

    protected $casts = [
        'monto_subtotal' => 'decimal:2',
        'monto_total' => 'decimal:2',
        'fecha_solicitud' => 'datetime',
        'fecha_pago_esperada' => 'date',
        'fecha_autorizacion' => 'datetime',
        'fecha_pago' => 'date',
    ];

    /* ============================
     * Relaciones
     * ============================ */

    //  Relación al corporativo comprador (entidad que aprueba la compra)
    public function comprador()
    {
        return $this->belongsTo(Corporativo::class, 'comprador_corp_id');
    }

    public function corporativo()
    {
        return $this->belongsTo(Corporativo::class, 'comprador_corp_id');
    }

    //  Relación a la sucursal en la que se levantó la requisición
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    // Empleado que solicita la requisición
    public function solicitante()
    {
        return $this->belongsTo(Empleado::class, 'solicitante_id');
    }

    public function pagos()
    {
        return $this->hasMany(\App\Models\Pago::class);
    }

    //  Proveedor elegido
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    //  Concepto asociado
    public function concepto()
    {
        return $this->belongsTo(Concepto::class, 'concepto_id');
    }

    //  Usuario que creó la requisición
    public function creadaPor()
    {
        return $this->belongsTo(User::class, 'creada_por_user_id');
    }

    //  Usuario que autorizó el pago (desde octubre 2026; antes no se registraba)
    public function pagoAutorizadoPor()
    {
        return $this->belongsTo(User::class, 'pago_autorizado_por_id');
    }

    //  Detalles (líneas de la requisición)
    public function detalles()
    {
        return $this->hasMany(Detalle::class, 'requisicion_id');
    }

    //  Comprobantes cargados para la requisición
    public function comprobantes()
    {
        return $this->hasMany(Comprobante::class, 'requisicion_id');
    }

    // Ajustes asociados (devoluciones o faltantes)
    public function ajustes()
    {
        return $this->hasMany(Ajuste::class, 'requisicion_id');
    }

    // Solicitudes de eliminación (colaborador solicita, Contabilidad autoriza)
    public function eliminacionSolicitudes()
    {
        return $this->hasMany(RequisicionEliminacionSolicitud::class, 'requisicion_id');
    }

    /* ============================
     * Alcance por usuario (AccessScope)
     * ============================ */

    /**
     * Una requisición es "propia" si el usuario la creó o si es el
     * colaborador solicitante vinculado a su cuenta.
     */
    public function isOwnedBy(User $user): bool
    {
        if ((int) $this->creada_por_user_id === (int) $user->id) {
            return true;
        }

        return $user->empleado_id !== null
            && (int) $this->solicitante_id === (int) $user->empleado_id;
    }

    /**
     * Cómo se filtra cada nivel de alcance. Pagos, comprobaciones y ajustes
     * heredan este mismo criterio con su propio permiso de alcance.
     *
     * @return array<string, \Closure|string>
     */
    public static function scopeMap(User $user, string $table = 'requisicions'): array
    {
        return [
            'own' => function ($q) use ($user, $table) {
                $q->where("{$table}.creada_por_user_id", $user->id);
                if ($user->empleado_id) {
                    $q->orWhere("{$table}.solicitante_id", $user->empleado_id);
                }
            },
            'sucursal' => "{$table}.sucursal_id",
        ];
    }

    /**
     * Limita la consulta a lo que el usuario puede ver en el módulo indicado
     * (requisiciones, pagos, comprobaciones o ajustes). Sin alcance no
     * devuelve registros.
     */
    public function scopeVisibleTo($query, User $user, string $module = 'requisiciones')
    {
        return AccessScope::apply($query, $user, $module, self::scopeMap($user, $this->getTable()));
    }

    /** ¿El registro concreto está dentro del alcance del usuario en el módulo? */
    public function isVisibleTo(User $user, string $module = 'requisiciones'): bool
    {
        return AccessScope::contains(
            $user,
            $module,
            $this->isOwnedBy($user),
            $this->sucursal_id ? (int) $this->sucursal_id : null,
            fn () => $this->relationLoaded('sucursal')
                ? $this->sucursal?->corporativo_id
                : Sucursal::query()->whereKey($this->sucursal_id)->value('corporativo_id'),
        );
    }

    /* ============================
     * Scopes (filtros reutilizables)
     * ============================ */

    // Filtra por coincidencia en folio u observaciones.
    public function scopeSearch($query, ?string $q)
    {
        $q = trim((string) $q);
        if ($q === '') {
            return $query;
        }

        return $query->where(function ($sub) use ($q) {
            $sub->where('folio', 'like', "%{$q}%")
                ->orWhere('observaciones', 'like', "%{$q}%");
        });
    }

    /**
     * Permite agrupar por pestañas. Se redefinieron las pestañas conforme a los nuevos estados.
     * - PENDIENTES: requisiciones en BORRADOR, CAPTURADA o POR_COMPROBAR.
     * - AUTORIZADAS: requisiciones pagadas o con pagos/comprobaciones aceptadas.
     * - RECHAZADAS: requisiciones eliminadas o con pagos/comprobaciones rechazadas.
     */
    public function scopeStatusTab($query, ?string $tab)
    {
        $tab = strtoupper(trim((string) $tab));
        if ($tab === '' || $tab === 'TODAS') {
            return $query;
        }
        if ($tab === 'PENDIENTES') {
            return $query->whereIn('status', ['BORRADOR', 'CAPTURADA', 'POR_COMPROBAR']);
        }
        if ($tab === 'AUTORIZADAS') {
            return $query->whereIn('status', ['PAGO_AUTORIZADO', 'PAGADA', 'COMPROBACION_ACEPTADA']);
        }
        if ($tab === 'RECHAZADAS') {
            return $query->whereIn('status', ['ELIMINADA', 'PAGO_RECHAZADO', 'COMPROBACION_RECHAZADA']);
        }

        // Filtro específico por status
        return $query->where('status', $tab);
    }

    /**
     * Filtra requisiciones cuya fecha de solicitud se encuentre entre el rango dado (from/to).
     * Útil para reportes o búsquedas en el listado.
     */
    public function scopeDateRangeSolicitud($query, ?string $from, ?string $to)
    {
        $from = trim((string) $from);
        $to = trim((string) $to);
        if ($from !== '') {
            $query->whereDate('fecha_solicitud', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('fecha_solicitud', '<=', $to);
        }

        return $query;
    }
}
