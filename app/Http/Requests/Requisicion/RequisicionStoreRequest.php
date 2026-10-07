<?php

namespace App\Http\Requests\Requisicion;

use App\Rules\ActiveProveedor;
use App\Rules\NotBeforeBusinessToday;
use Illuminate\Foundation\Http\FormRequest;

class RequisicionStoreRequest extends FormRequest {

    public function authorize(): bool {
        return (bool) $this->user()?->can('requisiciones.registrar');
    }

    public function rules(): array {
        return RequisicionRules::header() + [
            // 'accion' es opcional (las rutas storeDraft/storeCaptured la fijan)
            'accion' => ['nullable', 'string', 'in:BORRADOR,ENVIAR'],
        ];
    }

    public function messages(): array {
        return RequisicionRules::messages() + [
            'accion.in' => 'Acción inválida.',
        ];
    }

}
