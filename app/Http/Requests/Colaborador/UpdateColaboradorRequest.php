<?php

namespace App\Http\Requests\Empleado;

use App\Models\Empleado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpleadoRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        /** @var Empleado|null $empleado */
        $empleado = $this->route('empleado');
        $userId = optional($empleado?->user)->id;
        return [
            'sucursal_id' => ['required', 'integer', 'exists:sucursals,id'],
            'area_id'     => ['nullable', 'integer', 'exists:areas,id'],
            'nombre'           => ['required', 'string', 'max:120'],
            'apellido_paterno' => ['required', 'string', 'max:120'],
            'apellido_materno' => ['nullable', 'string', 'max:120'],
            'telefono'         => ['nullable', 'string', 'max:40'],
            'puesto'           => ['nullable', 'string', 'max:150'],
            'activo'           => ['sometimes', 'boolean'],
            // Usuario (mismo contrato que Store)
            'user_name'   => ['required', 'string', 'max:150'],
            'user_email'  => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'user_rol'    => ['required', Rule::in(['ADMIN', 'CONTADOR', 'COLABORADOR'])],
            'user_activo' => ['sometimes', 'boolean'],
            // opcional si en el futuro lo mandas
            'user_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array {
        return [
            'sucursal_id.required' => 'La sucursal es obligatoria.',
            'sucursal_id.exists'   => 'La sucursal seleccionada no existe.',
            'area_id.exists'       => 'El área seleccionada no existe.',
            'nombre.required'           => 'El nombre es obligatorio.',
            'apellido_paterno.required' => 'El apellido paterno es obligatorio.',
            'user_name.required'        => 'El nombre de usuario es obligatorio.',
            'user_email.required' => 'El correo es obligatorio.',
            'user_email.email'    => 'El correo no tiene un formato válido.',
            'user_email.unique'   => 'Ese correo ya existe en el sistema.',
            'user_rol.required' => 'El rol es obligatorio.',
            'user_rol.in'       => 'El rol seleccionado no es válido.',
            'user_password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }

    protected function prepareForValidation(): void {
        $this->merge([
            'user_email' => is_string($this->user_email) ? trim($this->user_email) : $this->user_email,
            'nombre' => is_string($this->nombre) ? trim($this->nombre) : $this->nombre,
            'apellido_paterno' => is_string($this->apellido_paterno) ? trim($this->apellido_paterno) : $this->apellido_paterno,
            'apellido_materno' => is_string($this->apellido_materno) ? trim($this->apellido_materno) : $this->apellido_materno,
            'telefono' => is_string($this->telefono) ? trim($this->telefono) : $this->telefono,
            'puesto' => is_string($this->puesto) ? trim($this->puesto) : $this->puesto,
            'user_name' => is_string($this->user_name) ? trim($this->user_name) : $this->user_name,
        ]);
    }

}
