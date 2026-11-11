<?php

namespace App\Support\Pdf;

use Carbon\Carbon;
use Throwable;

/**
 * Formatos comunes de los reportes PDF para que todos muestren las cifras y
 * fechas igual:
 *  - Montos en indicadores y tablas: siempre con centavos ($1,234.50).
 *    En las gráficas (SvgCharts) se redondean a pesos enteros.
 *  - Cantidades con separador de miles.
 *  - Fechas dd/mm/aaaa y, con hora, dd/mm/aaaa hh:mm.
 */
final class PdfFormat
{
    public static function money(int|float|string|null $v): string
    {
        $v = (float) ($v ?? 0);

        return ($v < 0 ? '-$' : '$').number_format(abs($v), 2);
    }

    public static function int(int|float|string|null $v): string
    {
        return number_format((float) ($v ?? 0));
    }

    public static function date(mixed $v, string $empty = '—'): string
    {
        return self::parse($v)?->format('d/m/Y') ?? ($v ? (string) $v : $empty);
    }

    public static function dateTime(mixed $v, string $empty = '—'): string
    {
        return self::parse($v)?->format('d/m/Y H:i') ?? ($v ? (string) $v : $empty);
    }

    private static function parse(mixed $v): ?Carbon
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            if ($v instanceof \DateTimeInterface) {
                return Carbon::instance($v)->timezone(\App\Support\BusinessDate::timezone());
            }
            // Ya viene como dd/mm/aaaa (Carbon::parse lo leería como mm/dd).
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})(?: (\d{2}):(\d{2}))?$#', (string) $v, $p)) {
                return Carbon::create((int) $p[3], (int) $p[2], (int) $p[1], (int) ($p[4] ?? 0), (int) ($p[5] ?? 0));
            }
            $d = Carbon::parse((string) $v);

            // Las marcas ISO con zona (p. ej. «…T18:00:00.000000Z») se muestran en la hora del negocio.
            return preg_match('/T\d{2}:\d{2}.*(Z|[+-]\d{2}:?\d{2})$/', (string) $v)
                ? $d->timezone(\App\Support\BusinessDate::timezone())
                : $d;
        } catch (Throwable) {
            return null;
        }
    }
}
