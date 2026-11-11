<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila ligera del listado de requisiciones (Requisiciones/Index).
 *
 * Solo lleva lo que pinta la tarjeta: con "Todas" se envían cientos de filas,
 * así que se omiten datos del detalle (CLABE/RFC/banco del proveedor, logo del
 * corporativo, puesto, updated_at, fecha_autorizacion…). El detalle completo
 * sigue en RequisicionResource (Show).
 *
 * @mixin \App\Models\Requisicion
 */
class RequisicionListResource extends JsonResource
{
    public function toArray($request): array
    {
        $r = $this->resource;
        $solicitante = $r->relationLoaded('solicitante') ? $r->solicitante : null;
        $tienePagos = $r->getAttribute('tiene_pagos');
        $can = $r->getAttribute('can');

        $row = [
            'id' => $r->id,
            'folio' => $r->folio,
            'tipo' => $r->getAttribute('tipo'),
            'status' => $r->status,
            'monto_subtotal' => $r->monto_subtotal,
            'monto_total' => $r->monto_total,
            'fecha_solicitud' => $r->fecha_solicitud?->toISOString(),
            'fecha_pago_esperada' => $r->fecha_pago_esperada?->format('Y-m-d'),
            'fecha_pago' => $r->fecha_pago?->format('Y-m-d'),
            'created_at' => $r->created_at?->toISOString(),
            // YYYY-MM-DD para mostrar fechas sin desfase de zona horaria.
            'fecha_registro_ymd' => $r->created_at?->format('Y-m-d'),
            'fecha_solicitud_ymd' => $r->fecha_solicitud?->format('Y-m-d'),
            'fecha_pago_ymd' => $r->fecha_pago?->format('Y-m-d'),
            'observaciones' => $r->observaciones,
            'eliminacion_pendiente' => (bool) ($r->getAttribute('eliminacion_pendiente') ?? false),
            'comprador' => $r->relationLoaded('comprador') && $r->comprador
                ? ['id' => $r->comprador->id, 'nombre' => $r->comprador->nombre]
                : null,
            'sucursal' => $r->relationLoaded('sucursal') && $r->sucursal
                ? [
                    'id' => $r->sucursal->id,
                    'nombre' => $r->sucursal->nombre,
                    'codigo' => $r->sucursal->codigo,
                    'corporativo_id' => $r->sucursal->corporativo_id,
                ]
                : null,
            'solicitante' => $solicitante
                ? [
                    'id' => $solicitante->id,
                    'nombre' => trim($solicitante->nombre.' '.$solicitante->apellido_paterno.' '.($solicitante->apellido_materno ?? '')),
                ]
                : null,
            'proveedor' => $r->relationLoaded('proveedor') && $r->proveedor
                ? ['id' => $r->proveedor->id, 'razon_social' => $r->proveedor->razon_social]
                : null,
            'concepto' => $r->relationLoaded('concepto') && $r->concepto
                ? ['id' => $r->concepto->id, 'nombre' => $r->concepto->nombre]
                : null,
        ];

        if ($tienePagos !== null) {
            $row['tiene_pagos'] = (bool) $tienePagos;
        }

        // Registro de la eliminación (mismo formato que RequisicionResource).
        if ($r->status === 'ELIMINADA') {
            $row['eliminacion'] = [
                'por' => $r->relationLoaded('eliminadaPor') ? $r->eliminadaPor?->name : null,
                'fecha' => $r->eliminada_at?->toISOString(),
                'motivo' => $r->eliminacion_motivo,
                'pagado' => $r->pagado_al_eliminar !== null ? (float) $r->pagado_al_eliminar : null,
            ];
        }

        if ($can !== null) {
            $row['can'] = $can;
        }

        return $row;
    }
}
