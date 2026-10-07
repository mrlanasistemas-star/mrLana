<?php

namespace App\Http\Requests\Requisicion;

use App\Rules\ActiveProveedor;
use App\Rules\NotBeforeBusinessToday;

/**
 * Reglas compartidas por alta y edición de requisiciones, para que ambos
 * flujos validen exactamente lo mismo (proveedor obligatorio y activo,
 * fecha de solicitud desde hoy, fecha esperada de pago coherente).
 */
final class RequisicionRules
{
    /** @return array<string, mixed> */
    public static function header(): array
    {
        return [
            'solicitante_id'      => ['required', 'integer', 'exists:empleados,id'],
            'comprador_corp_id'   => ['required', 'integer', 'exists:corporativos,id'],
            'sucursal_id'         => ['required', 'integer', 'exists:sucursals,id'],
            'concepto_id'         => ['required', 'integer', 'exists:conceptos,id'],
            'proveedor_id'        => ['required', 'integer', new ActiveProveedor()],
            'fecha_solicitud'     => ['required', 'date_format:Y-m-d', new NotBeforeBusinessToday()],
            'fecha_pago_esperada' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_solicitud'],
            'observaciones'       => ['nullable', 'string', 'max:5000'],
            'detalles'                   => ['required', 'array', 'min:1'],
            'detalles.*.sucursal_id'     => ['nullable', 'integer', 'exists:sucursals,id'],
            'detalles.*.cantidad'        => ['required', 'numeric', 'gt:0'],
            'detalles.*.descripcion'     => ['required', 'string', 'min:2', 'max:255'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'gte:0'],
            'detalles.*.genera_iva'      => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'solicitante_id.required' => 'El solicitante es obligatorio.',
            'solicitante_id.exists'   => 'El solicitante seleccionado no existe.',
            'comprador_corp_id.required' => 'El comprador (corporativo) es obligatorio.',
            'comprador_corp_id.exists'   => 'El comprador seleccionado no existe.',
            'sucursal_id.required' => 'La sucursal es obligatoria.',
            'sucursal_id.exists'   => 'La sucursal seleccionada no existe.',
            'concepto_id.required' => 'El concepto es obligatorio.',
            'concepto_id.exists'   => 'El concepto seleccionado no existe.',
            'proveedor_id.required' => ActiveProveedor::MESSAGE,
            'proveedor_id.integer'  => ActiveProveedor::MESSAGE,
            'fecha_solicitud.required'    => 'La fecha de solicitud es obligatoria.',
            'fecha_solicitud.date_format' => 'La fecha de solicitud debe tener formato AAAA-MM-DD.',
            'fecha_pago_esperada.date_format'    => 'La fecha esperada de pago debe tener formato AAAA-MM-DD.',
            'fecha_pago_esperada.after_or_equal' => 'La fecha esperada de pago no puede ser anterior a la fecha de solicitud.',
            'observaciones.max' => 'Las observaciones no deben exceder 5,000 caracteres.',
            'detalles.required' => 'Agrega al menos un item.',
            'detalles.min'      => 'Agrega al menos un item.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria en cada item.',
            'detalles.*.cantidad.gt'       => 'La cantidad debe ser mayor a 0.',
            'detalles.*.descripcion.required' => 'La descripción es obligatoria en cada item.',
            'detalles.*.descripcion.min'      => 'La descripción debe tener al menos 2 caracteres.',
            'detalles.*.precio_unitario.required' => 'El precio unitario es obligatorio en cada item.',
            'detalles.*.precio_unitario.gte'      => 'El precio unitario no puede ser negativo.',
            'detalles.*.genera_iva.required'      => 'Define si el item genera IVA.',
            'detalles.*.genera_iva.boolean'       => 'El campo "genera IVA" es inválido.',
        ];
    }
}
