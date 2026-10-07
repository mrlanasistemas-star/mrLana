<?php

namespace App\Http\Requests\Colaborador;

/**
 * Edición de datos laborales de un colaborador. La cuenta de acceso
 * vinculada se administra desde el módulo Usuarios.
 */
class UpdateColaboradorRequest extends StoreColaboradorRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('colaboradores.editar');
    }
}
