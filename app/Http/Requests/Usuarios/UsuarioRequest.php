<?php

namespace App\Http\Requests\Usuarios;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('user') ? 'usuarios.editar' : 'usuarios.registrar');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : $this->input('email'),
            'empleado_id' => in_array($this->input('empleado_id'), ['', null], true) ? null : $this->input('empleado_id'),
            'sucursal_id' => in_array($this->input('sucursal_id'), ['', null], true) ? null : $this->input('sucursal_id'),
            'activo' => $this->has('activo') ? $this->boolean('activo') : true,
        ]);
    }

    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'role_id' => ['required', 'integer', Rule::exists(config('permission.table_names.roles'), 'id')->where('guard_name', 'web')],
            'empleado_id' => [
                'nullable', 'integer', 'exists:empleados,id',
                // Un colaborador solo puede tener una cuenta.
                Rule::unique('users', 'empleado_id')->ignore($user?->id),
            ],
            // Sin colaborador elegido, la cuenta nueva necesita la sucursal donde
            // se registrará el suyo: toda cuenta nueva queda como solicitante.
            'sucursal_id' => [
                'nullable', 'integer', 'exists:sucursals,id',
                Rule::requiredIf(fn () => $user === null && $this->input('empleado_id') === null),
            ],
            'activo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del usuario.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'role_id.required' => 'Selecciona un rol.',
            'role_id.exists' => 'El rol seleccionado no existe.',
            'empleado_id.exists' => 'El colaborador seleccionado no existe.',
            'empleado_id.unique' => 'Ese colaborador ya tiene una cuenta vinculada.',
            'sucursal_id.required' => 'Elige un colaborador o la sucursal donde se registrará el de esta cuenta.',
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
        ];
    }
}
