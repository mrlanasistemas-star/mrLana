<?php

namespace App\Http\Requests\Requisicion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la edición de una requisición en BORRADOR.
 * Usa las mismas reglas de cabecera que el alta: el proveedor es obligatorio
 * y debe estar activo, y la fecha de solicitud no puede ser anterior a hoy.
 * Folio, estatus y montos los controla el servidor, no el cliente.
 */
class RequisicionUpdateRequest extends FormRequest {

    public function authorize(): bool {
        $requisicion = $this->route('requisicion');

        return $requisicion !== null && (bool) $this->user()?->can('update', $requisicion);
    }

    public function rules(): array {
        return RequisicionRules::header();
    }

    public function messages(): array {
        return RequisicionRules::messages();
    }

}
