<?php

namespace App\Http\Requests\Pagos;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisicion = $this->route('requisicion');

        return $requisicion !== null && (bool) $this->user()?->can('registerPayment', $requisicion);
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:12288', 'mimes:pdf,jpg,jpeg,png,webp'],
            'fecha_pago' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'tipo_pago' => ['required', 'in:TRANSFERENCIA,EFECTIVO,TARJETA,CHEQUE,OTRO'],
            'referencia' => ['nullable', 'string', 'max:140'],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Sube el comprobante del pago.',
            'archivo.mimes' => 'El archivo debe ser PDF o imagen (JPG/PNG/WebP).',
            'monto.min' => 'El monto debe ser mayor a 0.',
        ];
    }
}
