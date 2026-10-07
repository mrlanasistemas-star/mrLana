<?php

namespace App\Http\Requests\Roles;

use App\Enums\NotificationTopic;
use App\Models\Role;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('role') ? 'roles.editar' : 'roles.registrar');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim(preg_replace('/\s+/', ' ', $this->input('name'))) : $this->input('name'),
            'descripcion' => is_string($this->input('descripcion')) ? (trim($this->input('descripcion')) ?: null) : $this->input('descripcion'),
            'permissions' => array_values(array_unique((array) $this->input('permissions', []))),
            'topics' => array_values(array_unique((array) $this->input('topics', []))),
            'receive_all' => $this->boolean('receive_all'),
        ]);
    }

    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'min:3', 'max:60',
                Rule::unique(config('permission.table_names.roles'), 'name')
                    ->where('guard_name', 'web')
                    ->ignore($role?->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::all())],
            'receive_all' => ['boolean'],
            'topics' => ['array'],
            'topics.*' => ['string', Rule::in(NotificationTopic::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $permissions = (array) $this->input('permissions', []);
            $receives = $this->boolean('receive_all') || count((array) $this->input('topics', [])) > 0;

            if ($receives && ! in_array('notificaciones.ver', $permissions, true)) {
                $v->errors()->add(
                    'topics',
                    'Para recibir notificaciones, el rol necesita el permiso "Ver notificaciones" (módulo Notificaciones).'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del rol.',
            'name.min' => 'El nombre debe tener al menos 3 caracteres.',
            'name.max' => 'El nombre no debe exceder 60 caracteres.',
            'name.unique' => 'Ya existe un rol con ese nombre.',
            'descripcion.max' => 'La descripción no debe exceder 500 caracteres.',
            'permissions.*.in' => 'Uno de los permisos seleccionados no es válido.',
            'topics.*.in' => 'Uno de los temas de notificación no es válido.',
        ];
    }
}
