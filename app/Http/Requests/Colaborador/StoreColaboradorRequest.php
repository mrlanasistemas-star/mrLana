<?php

namespace App\Http\Requests\Colaborador;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de una persona (colaborador) y, opcionalmente, de su acceso al
 * sistema en el mismo registro (`acceso` = true con correo, rol y estado).
 *
 * Registrar personas exige «Registrar colaboradores»; darles acceso exige
 * además «Registrar usuarios». Quien solo tiene «Registrar usuarios» puede
 * registrar personas únicamente junto con su acceso (como antes al crear una
 * cuenta). El controlador vuelve a validar alcance y roles asignables.
 */
class StoreColaboradorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAny(['colaboradores.registrar', 'usuarios.registrar']);
    }

    public function rules(): array
    {
        return $this->personaRules() + $this->accesoRules(null);
    }

    /** Datos de la persona. */
    protected function personaRules(): array
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

    /** Acceso al sistema: solo se valida cuando se pide (`acceso` = true). */
    protected function accesoRules(?User $cuenta): array
    {
        $rules = ['acceso' => ['sometimes', 'boolean']];

        if ($this->boolean('acceso')) {
            $rules += [
                'acceso_email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($cuenta?->id)],
                'acceso_role_id' => ['required', 'integer', Rule::exists(config('permission.table_names.roles'), 'id')->where('guard_name', 'web')],
                'acceso_activo' => ['nullable', 'boolean'],
            ];
        }

        return $rules;
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
            'acceso_email.required' => 'Escribe el correo con el que iniciará sesión.',
            'acceso_email.email' => 'El correo no tiene un formato válido.',
            'acceso_email.unique' => 'Ya existe una cuenta con ese correo.',
            'acceso_role_id.required' => 'Elige el rol de la cuenta.',
            'acceso_role_id.exists' => 'El rol seleccionado no existe.',
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

        if ($this->has('acceso')) {
            $email = $trim($this->input('acceso_email'));
            $this->merge([
                'acceso' => $this->boolean('acceso'),
                'acceso_email' => is_string($email) ? mb_strtolower($email) : $email,
                'acceso_activo' => $this->has('acceso_activo') ? $this->boolean('acceso_activo') : null,
            ]);
        }
    }
}
