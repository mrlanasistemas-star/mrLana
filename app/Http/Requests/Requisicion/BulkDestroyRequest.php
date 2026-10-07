<?php

namespace App\Http\Requests\Requisicion;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('requisiciones.eliminar');
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:requisicions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Debes seleccionar al menos una requisición.',
            'ids.array' => 'El formato de selección es inválido.',
            'ids.min' => 'Selecciona al menos una requisición.',
            'ids.*.exists' => 'Una o más requisiciones no existen.',
        ];
    }
}
