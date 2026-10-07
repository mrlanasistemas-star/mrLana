<?php

namespace App\Http\Requests\Requisiciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Revisión de un ajuste: el comentario es opcional al aprobar y obligatorio
 * al rechazar.
 */
class AjusteReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('ajustes.revisar');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'accion' => strtoupper(trim((string) $this->input('accion'))),
            'comentario_revision' => is_string($this->input('comentario_revision'))
                ? trim($this->input('comentario_revision'))
                : $this->input('comentario_revision'),
        ]);
    }

    public function rules(): array
    {
        return [
            'accion' => ['required', Rule::in(['APROBAR', 'RECHAZAR'])],
            'comentario_revision' => ['nullable', 'required_if:accion,RECHAZAR', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'accion.required' => 'Indica si apruebas o rechazas el ajuste.',
            'accion.in' => 'La acción de revisión no es válida.',
            'comentario_revision.required_if' => 'Escribe el motivo del rechazo.',
            'comentario_revision.max' => 'El comentario no debe exceder 2,000 caracteres.',
        ];
    }
}
