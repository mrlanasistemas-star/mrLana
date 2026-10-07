<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class RequisicionResource extends JsonResource {

    public function toArray($request): array {
        $logoRaw = $this->comprador?->logo
            ?? $this->comprador?->logo_url
            ?? $this->comprador?->ruta_logo
            ?? $this->comprador?->logo_path
            ?? null;
        $logoUrl = null;
        if (is_string($logoRaw) && trim($logoRaw) !== '') {
            $val = trim($logoRaw);
            $logoUrl = Str::startsWith($val, ['http://', 'https://'])
                ? $val
                : asset($val);
        }
        $proveedorRazon = $this->proveedor?->razon_social;
        return [
            'id'       => $this->id,
            'folio'    => $this->folio,
            'tipo'     => $this->tipo,
            'status'   => $this->status,
            'monto_subtotal' => $this->monto_subtotal,
            'monto_total'    => $this->monto_total,
            'fecha_solicitud'    => optional($this->fecha_solicitud)->toISOString(),
            // Fecha opcional capturada por el solicitante (no es la autorización real).
            'fecha_pago_esperada' => optional($this->fecha_pago_esperada)->format('Y-m-d'),
            // Momento real en que se autorizó el pago (auditoría).
            'fecha_autorizacion' => optional($this->fecha_autorizacion)->toISOString(),
            // Fecha programada/real de pago.
            'fecha_pago'         => optional($this->fecha_pago)->format('Y-m-d'),
            'created_at'         => optional($this->created_at)->toISOString(),
            'updated_at'         => optional($this->updated_at)->toISOString(),
            // Campos seguros YYYY-MM-DD para mostrar fechas sin desfase de timezone
            'fecha_registro_ymd'  => optional($this->created_at)->format('Y-m-d'),
            'fecha_solicitud_ymd' => optional($this->fecha_solicitud)->format('Y-m-d'),
            'fecha_pago_ymd'      => optional($this->fecha_pago)->format('Y-m-d'),
            'observaciones' => $this->observaciones,
            'eliminacion_pendiente' => (bool) ($this->eliminacion_pendiente ?? false),
            'can' => $this->when($this->resource->getAttribute('can') !== null, fn () => $this->resource->getAttribute('can')),
            'comprador' => $this->whenLoaded('comprador', fn() => [
                'id'       => $this->comprador?->id,
                'nombre'   => $this->comprador?->nombre,
                'logo_url' => $logoUrl,
            ]),
            'sucursal' => $this->whenLoaded('sucursal', fn() => [
                'id'             => $this->sucursal?->id,
                'nombre'         => $this->sucursal?->nombre,
                'codigo'         => $this->sucursal?->codigo,
                'corporativo_id' => $this->sucursal?->corporativo_id,
                'activo'         => $this->sucursal?->activo,
            ]),
            'solicitante' => $this->whenLoaded('solicitante', fn() => [
                'id'     => $this->solicitante?->id,
                'nombre' => trim(
                    ($this->solicitante?->nombre ?? '') . ' ' .
                    ($this->solicitante?->apellido_paterno ?? '') . ' ' .
                    ($this->solicitante?->apellido_materno ?? '')
                ),
                'puesto' => $this->solicitante?->puesto,
                'activo' => $this->solicitante?->activo,
            ]),
            'proveedor' => $this->whenLoaded('proveedor', fn() => [
                'id'           => $this->proveedor?->id,
                'razon_social' => $proveedorRazon,
                'rfc'       => $this->proveedor?->rfc ?? null,
                'banco'       => $this->proveedor?->banco ?? null,
                'clabe'       => $this->proveedor?->clabe ?? null,
            ]),
            'concepto' => $this->whenLoaded('concepto', fn() => [
                'id'     => $this->concepto?->id,
                'nombre' => $this->concepto?->nombre,
                'activo' => $this->concepto?->activo,
            ]),
        ];
    }
}
