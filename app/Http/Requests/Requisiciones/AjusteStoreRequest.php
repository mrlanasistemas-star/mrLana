<?php

namespace App\Http\Requests\Requisiciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AjusteStoreRequest extends FormRequest {

    public function authorize(): bool {
        return auth()->check();
    }

    public function rules(): array {
        return [
            'tipo' => ['required', Rule::in(['DEVOLUCION','FALTANTE','INCREMENTO_AUTORIZADO'])],
            'sentido' => ['nullable','string','max:30'],
            'monto' => ['required','numeric','gt:0'],
            'descripcion' => ['required','string','max:500'],
            'fecha' => ['required','date'],
        ];
    }

}
