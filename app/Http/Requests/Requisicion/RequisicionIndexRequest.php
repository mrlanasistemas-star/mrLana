<?php

namespace App\Http\Requests\Requisicion;

use Illuminate\Foundation\Http\FormRequest;

class RequisicionIndexRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:40'],
            'tab' => ['nullable', 'string', 'max:40'],
            'comprador_corp_id' => ['nullable', 'integer', 'min:1'],
            'sucursal_id'       => ['nullable', 'integer', 'min:1'],
            'solicitante_id'    => ['nullable', 'integer', 'min:1'],
            'concepto_id'       => ['nullable', 'integer', 'min:1'],
            'proveedor_id'      => ['nullable', 'integer', 'min:1'],
            'fecha_from' => ['nullable', 'date_format:Y-m-d'],
            'fecha_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_from'],
            'perPage' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value === 'all' || $value === 'todos' || is_numeric($value)) {
                        return;
                    }

                    $fail('El valor de por página no es válido.');
                },
            ],
            'sort'    => ['nullable', 'string', 'max:40'],
            'dir'     => ['nullable', 'in:asc,desc'],
        ];
    }

}
