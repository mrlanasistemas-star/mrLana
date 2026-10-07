<?php

namespace App\Rules;

use App\Models\Proveedor;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El proveedor debe existir y estar ACTIVO.
 */
class ActiveProveedor implements ValidationRule
{
    public const MESSAGE = 'Selecciona un proveedor activo antes de guardar o enviar la requisición.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $active = is_numeric($value) && Proveedor::query()
            ->whereKey((int) $value)
            ->where('status', 'ACTIVO')
            ->exists();

        if (! $active) {
            $fail(self::MESSAGE);
        }
    }
}
