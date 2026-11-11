<?php

namespace App\Http\Requests\Colaborador;

use App\Models\Empleado;

/**
 * Edición de una persona y de su acceso en un solo lugar. Los datos de la
 * persona solo se validan (y se guardan) si quien edita puede editar
 * colaboradores; el bloque de acceso, si lo envía, se valida con los
 * permisos de Usuarios en el controlador.
 */
class UpdateColaboradorRequest extends StoreColaboradorRequest
{
    public const PERMISOS = [
        'colaboradores.editar',
        'usuarios.registrar',
        'usuarios.editar',
        'usuarios.cambiar_rol',
        'usuarios.desactivar',
        'usuarios.reactivar',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->canAny(self::PERMISOS);
    }

    public function rules(): array
    {
        /** @var Empleado $empleado */
        $empleado = $this->route('empleado');
        $persona = $this->user()->can('update', $empleado) ? $this->personaRules() : [];

        return $persona + $this->accesoRules($empleado->user);
    }
}
