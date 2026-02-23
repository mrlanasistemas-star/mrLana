<?php

namespace App\Http\Requests\Plantilla;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlantillaStoreRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    protected function prepareForValidation(): void {
        if ($this->has('corporativo_id') && !$this->has('comprador_corp_id')) {
            $this->merge([
                'comprador_corp_id' => $this->input('corporativo_id'),
            ]);
        }
    }

    public function rules(): array {
        $rol = strtoupper((string)($this->user()?->rol ?? 'COLABORADOR'));
        return [
            'nombre' => ['required', 'string', 'max:100'],
            // Para COLABORADOR lo asignas automático en backend/UI, para el resto sí es requerido
            'solicitante_id' => [
                Rule::requiredIf($rol !== 'COLABORADOR'),
                'nullable',
                'integer',
                'exists:empleados,id',
            ],
            // En la UI lo tratas como requerido -> aquí también
            'sucursal_id'       => ['required', 'integer', 'exists:sucursals,id'],
            'comprador_corp_id' => ['nullable', 'integer', 'exists:corporativos,id'],
            'proveedor_id' => ['required', 'integer', 'exists:proveedors,id'],
            'concepto_id'  => ['required', 'integer', 'exists:conceptos,id'],
            // Si estos montos se calculan sí o sí en frontend/composable, déjalos requeridos
            'monto_subtotal' => ['required', 'numeric', 'min:0'],
            'monto_total'    => ['required', 'numeric', 'min:0'],
            'fecha_solicitud'    => ['required', 'date'],
            'fecha_autorizacion' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            // Items: mínimo 1
            'detalles' => ['required', 'array', 'min:1'],
            // Detalle: si no lo usas, lo puedes quitar; si lo usas, suele ser el mismo sucursal_id principal
            'detalles.*.sucursal_id' => ['nullable', 'integer', 'exists:sucursals,id'],

            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            // Si tu frontend ya calcula estos, los validas; si quieres que el server los calcule, ponlos nullable
            'detalles.*.subtotal' => ['required', 'numeric', 'min:0'],
            'detalles.*.iva'      => ['required', 'numeric', 'min:0'],
            'detalles.*.total'    => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Nombres “bonitos” para que los mensajes salgan pro y no con keys técnicas.
     */
    public function attributes(): array {
        return [
            'nombre' => 'nombre de la plantilla',
            'solicitante_id' => 'solicitante',
            'sucursal_id' => 'sucursal',
            'comprador_corp_id' => 'corporativo',
            'proveedor_id' => 'proveedor',
            'concepto_id' => 'concepto',
            'monto_subtotal' => 'subtotal',
            'monto_total' => 'total',
            'fecha_solicitud' => 'fecha esperada de entrega',
            'fecha_autorizacion' => 'fecha de autorización',
            'observaciones' => 'observaciones',
            'detalles' => 'items de la plantilla',
            'detalles.*.sucursal_id' => 'sucursal del item',
            'detalles.*.cantidad' => 'cantidad',
            'detalles.*.descripcion' => 'descripción',
            'detalles.*.precio_unitario' => 'precio unitario',
            'detalles.*.subtotal' => 'subtotal del item',
            'detalles.*.iva' => 'IVA del item',
            'detalles.*.total' => 'total del item',
        ];
    }

    /**
     * Mensajes personalizados
     */
    public function messages(): array {
        return [
            // Generales
            'nombre.required' => 'Escribe el :attribute.',
            'nombre.max' => 'El :attribute no debe exceder 100 caracteres.',
            'solicitante_id.required' => 'Selecciona el :attribute.',
            'solicitante_id.exists' => 'El :attribute seleccionado no es válido.',
            'sucursal_id.required' => 'Selecciona la :attribute.',
            'sucursal_id.exists' => 'La :attribute seleccionada no es válida.',
            'comprador_corp_id.exists' => 'El :attribute seleccionado no es válido.',
            'proveedor_id.required' => 'Selecciona el :attribute.',
            'proveedor_id.exists' => 'El :attribute seleccionado no es válido.',
            'concepto_id.required' => 'Selecciona el :attribute.',
            'concepto_id.exists' => 'El :attribute seleccionado no es válido.',
            'fecha_solicitud.required' => 'Selecciona la :attribute.',
            'fecha_solicitud.date' => 'La :attribute no es una fecha válida.',
            'fecha_autorizacion.date' => 'La :attribute no es una fecha válida.',
            'observaciones.max' => 'Las :attribute no deben exceder 2000 caracteres.',
            // Montos
            'monto_subtotal.required' => 'El :attribute es obligatorio.',
            'monto_subtotal.numeric' => 'El :attribute debe ser numérico.',
            'monto_subtotal.min' => 'El :attribute no puede ser negativo.',
            'monto_total.required' => 'El :attribute es obligatorio.',
            'monto_total.numeric' => 'El :attribute debe ser numérico.',
            'monto_total.min' => 'El :attribute no puede ser negativo.',
            // Detalles (items)
            'detalles.required' => 'Agrega al menos 1 item.',
            'detalles.array' => 'Los :attribute deben enviarse como lista.',
            'detalles.min' => 'Agrega al menos 1 item.',
            'detalles.*.sucursal_id.exists' => 'La :attribute seleccionada no es válida.',
            'detalles.*.cantidad.required' => 'La :attribute es obligatoria.',
            'detalles.*.cantidad.numeric' => 'La :attribute debe ser numérica.',
            'detalles.*.cantidad.min' => 'La :attribute debe ser mayor a 0.',
            'detalles.*.descripcion.required' => 'La :attribute es obligatoria.',
            'detalles.*.descripcion.max' => 'La :attribute no debe exceder 255 caracteres.',
            'detalles.*.precio_unitario.required' => 'El :attribute es obligatorio.',
            'detalles.*.precio_unitario.numeric' => 'El :attribute debe ser numérico.',
            'detalles.*.precio_unitario.min' => 'El :attribute no puede ser negativo.',
            'detalles.*.subtotal.required' => 'El :attribute es obligatorio.',
            'detalles.*.subtotal.numeric' => 'El :attribute debe ser numérico.',
            'detalles.*.subtotal.min' => 'El :attribute no puede ser negativo.',
            'detalles.*.iva.required' => 'El :attribute es obligatorio.',
            'detalles.*.iva.numeric' => 'El :attribute debe ser numérico.',
            'detalles.*.iva.min' => 'El :attribute no puede ser negativo.',
            'detalles.*.total.required' => 'El :attribute es obligatorio.',
            'detalles.*.total.numeric' => 'El :attribute debe ser numérico.',
            'detalles.*.total.min' => 'El :attribute no puede ser negativo.',
        ];
    }

}
