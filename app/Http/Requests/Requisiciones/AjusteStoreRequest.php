<?php

namespace App\Http\Requests\Requisiciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AjusteStoreRequest extends FormRequest {

    public function authorize(): bool {
        $requisicion = $this->route('requisicion');

        return $requisicion !== null && (bool) $this->user()?->can('requestAdjustment', $requisicion);
    }

    protected function prepareForValidation(): void {
        // Compatibilidad: la interfaz anterior enviaba "descripcion" en lugar de "motivo".
        if (! $this->filled('motivo') && $this->filled('descripcion')) {
            $this->merge(['motivo' => $this->input('descripcion')]);
        }
    }

    public function rules(): array {
        return [
            'tipo' => ['required', Rule::in(['DEVOLUCION', 'FALTANTE', 'INCREMENTO_AUTORIZADO'])],
            'sentido' => ['nullable', Rule::in(['A_FAVOR_EMPRESA', 'A_FAVOR_SOLICITANTE'])],
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'motivo' => ['required', 'string', 'min:3', 'max:2000'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array {
        return [
            'tipo.required' => 'Selecciona el tipo de ajuste.',
            'tipo.in' => 'El tipo de ajuste no es válido.',
            'sentido.in' => 'El sentido del ajuste no es válido.',
            'monto.required' => 'Captura el monto del ajuste.',
            'monto.gt' => 'El monto debe ser mayor a 0.',
            'motivo.required' => 'Describe el motivo del ajuste.',
            'motivo.min' => 'El motivo debe tener al menos 3 caracteres.',
            'motivo.max' => 'El motivo no debe exceder 2,000 caracteres.',
            'fecha.date_format' => 'La fecha debe tener formato AAAA-MM-DD.',
        ];
    }

}
