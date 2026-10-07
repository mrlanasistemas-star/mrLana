<?php

namespace App\Rules;

use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La fecha (Y-m-d) no puede ser anterior a "hoy" en la zona horaria de negocio.
 */
class NotBeforeBusinessToday implements ValidationRule
{
    public function __construct(
        private string $message = 'La fecha de solicitud no puede ser anterior a hoy.'
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return; // el formato lo valida date_format
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, BusinessDate::timezone());

        if ($date === false || $date->lt(BusinessDate::today())) {
            $fail($this->message);
        }
    }
}
