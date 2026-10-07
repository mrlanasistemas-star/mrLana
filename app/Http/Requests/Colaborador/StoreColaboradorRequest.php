<?php

namespace App\Http\Requests\Colaborador;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de un colaborador (persona de la organización). No crea cuenta de
 * acceso: eso se hace desde el módulo Usuarios.
 */
class StoreColaboradorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('colaboradores.registrar');
    }

    public function rules(): array
    {
        return [
            'sucursal_id' => ['required', 'integer', 'exists:sucursals,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'nombre' => ['required', 'string', 'max:120'],
            'apellido_paterno' => ['required', 'string', 'max:120'],
            'apellido_materno' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'puesto' => ['nullable', 'string', 'max:120'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sucursal_id.required' => 'La sucursal es obligatoria.',
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
            'area_id.exists' => 'El área seleccionada no existe.',
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido_paterno.required' => 'El apellido paterno es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'telefono.max' => 'El teléfono no debe exceder 30 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Compatibilidad con el formulario anterior, que enviaba "user_email".
        if (! $this->filled('email') && $this->filled('user_email')) {
            $this->merge(['email' => $this->input('user_email')]);
        }

        $trim = fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v;

        $this->merge([
            'email' => $trim($this->input('email')),
            'nombre' => $trim($this->input('nombre')),
            'apellido_paterno' => $trim($this->input('apellido_paterno')),
            'apellido_materno' => $trim($this->input('apellido_materno')),
            'telefono' => $trim($this->input('telefono')),
            'puesto' => $trim($this->input('puesto')),
            'area_id' => $this->input('area_id') === '' ? null : $this->input('area_id'),
        ]);
    }
}
