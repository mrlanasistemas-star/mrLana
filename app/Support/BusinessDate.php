<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Fechas de negocio calculadas en el servidor con la zona horaria configurada
 * (ERP_BUSINESS_TIMEZONE, por defecto America/Mexico_City). Nunca se confía
 * en la fecha del navegador para decidir qué es "hoy".
 */
final class BusinessDate
{
    public static function timezone(): string
    {
        return (string) config('erp.business_timezone', 'America/Mexico_City');
    }

    /** Fecha y hora actuales en la zona de negocio (p. ej. "Generado:" en reportes). */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone())->startOfDay();
    }

    public static function todayString(): string
    {
        return self::today()->toDateString();
    }
}
