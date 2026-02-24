<?php

namespace App\Http\Requests\Requisiciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AjusteReviewRequest extends FormRequest {

    public function authorize(): bool {
        return auth()->check();
    }

    public function rules(): array {
        return [
            'accion' => ['required', Rule::in(['APROBAR','RECHAZAR'])],
        ];
    }

}
