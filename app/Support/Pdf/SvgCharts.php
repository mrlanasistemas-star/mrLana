<?php

namespace App\Support\Pdf;

/**
 * Gráficas SVG generadas en el servidor para reportes PDF, con el estilo de
 * los charts de shadcn: cuadrícula tenue, barras redondeadas, área con
 * degradado y dona con hueco amplio.
 *
 * Devuelven data URIs (image/svg+xml) para que funcionen igual con
 * Browsershot (Chrome) y con el respaldo DomPDF.
 */
final class SvgCharts
{
    private const FONT = "Inter, 'Segoe UI', Helvetica, Arial, sans-serif";

    private const GRID = '#E4E4E7';

    private const AXIS = '#71717A';

    /**
     * @param  list<array{name: string, value: int|float}>  $points
     */
    public static function bars(array $points, string $color, string $unit = '', int $width = 520, int $height = 220, bool $money = false): string
    {
        $max = self::niceMax(max(array_column($points, 'value') ?: [0]));
        if (! $money && $max <= 12) {
            // Conteos chicos: múltiplo de 4 para que las 4 líneas del eje sean enteras.
            $max = max(4, ceil($max / 4) * 4);
        }
        $axis = fn ($v) => ($money ? '$' : '').self::short($v);
        // El eje Y se reserva según la etiqueta más ancha (p. ej. «$2.5M»).
        $plotX = self::axisWidth($max, $axis);
        [$plotY, $plotW, $plotH] = [16, $width - $plotX - 8, $height - 40];
        $n = max(1, count($points));
        $slot = $plotW / $n;
        $barW = min(26, $slot * 0.62);
        $labelStep = self::labelStep($points, $plotW);

        $svg = self::open($width, $height);
        $svg .= self::grid($plotX, $plotY, $plotW, $plotH, $max, $axis);

        foreach ($points as $i => $p) {
            $h = $max > 0 ? ($p['value'] / $max) * $plotH : 0;
            $x = $plotX + $slot * $i + ($slot - $barW) / 2;
            $y = $plotY + $plotH - $h;
            if ($h > 0) {
                $r = min(5, $barW / 2, $h);
                $svg .= sprintf(
                    '<path d="%s" fill="%s"/>',
                    self::roundedTopRect($x, $y, $barW, $h, $r),
                    self::e($color)
                );
            }
            if ($i % $labelStep === 0) {
                $svg .= self::xLabel($x + $barW / 2, $plotY + $plotH + 15, TextMetrics::fit((string) $p['name'], max(24, $slot * $labelStep - 4), 9), $width);
            }
        }

        return self::uri($svg.'</svg>');
    }

    /**
     * @param  list<array{name: string, value: int|float}>  $points
     */
    public static function area(array $points, string $color, bool $money = false, int $width = 520, int $height = 220): string
    {
        $max = self::niceMax(max(array_column($points, 'value') ?: [0]));
        $axis = fn ($v) => ($money ? '$' : '').self::short($v);
        $plotX = max(40, self::axisWidth($max, $axis));
        [$plotY, $plotW, $plotH] = [16, $width - $plotX - 14, $height - 40];
        $n = count($points);
        $step = $n > 1 ? $plotW / ($n - 1) : 0;
        $labelStep = self::labelStep($points, $plotW);

        $coords = [];
        foreach ($points as $i => $p) {
            $coords[] = [
                $plotX + $step * $i,
                $plotY + $plotH - ($max > 0 ? ($p['value'] / $max) * $plotH : 0),
            ];
        }

        $svg = self::open($width, $height);
        $svg .= self::grid($plotX, $plotY, $plotW, $plotH, $max, $axis);

        if ($coords !== []) {
            $line = self::smoothPath($coords);
            $last = end($coords);
            $first = $coords[0];
            $base = $plotY + $plotH;
            // Relleno liso translúcido: DomPDF no soporta degradados SVG (los pinta negros).
            $svg .= sprintf('<path d="%s L %.2f %.2f L %.2f %.2f Z" fill="%s" fill-opacity="0.14"/>', $line, $last[0], $base, $first[0], $base, self::e($color));
            $svg .= sprintf('<path d="%s" fill="none" stroke="%s" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>', $line, self::e($color));
            foreach ($coords as [$x, $y]) {
                $svg .= sprintf('<circle cx="%.2f" cy="%.2f" r="2.6" fill="#FFFFFF" stroke="%s" stroke-width="1.6"/>', $x, $y, self::e($color));
            }
        }

        foreach ($points as $i => $p) {
            if ($i % $labelStep === 0) {
                $svg .= self::xLabel($plotX + $step * $i, $plotY + $plotH + 15, TextMetrics::fit((string) $p['name'], max(24, $step * $labelStep - 4), 9), $width);
            }
        }

        return self::uri($svg.'</svg>');
    }

    /**
     * Dona con leyenda a la derecha. El radio se ajusta al alto disponible
     * (máximo 78) y la leyenda al número de rebanadas, para poder usarla en
     * paneles angostos sin que el texto se encime.
     *
     * @param  list<array{name: string, value: int|float}>  $slices
     * @param  list<string>  $palette
     */
    public static function donut(array $slices, array $palette, int $width = 520, int $height = 200, string $centerLabel = 'Total'): string
    {
        $total = array_sum(array_column($slices, 'value'));
        $n = count($slices);
        $rowH = (int) max(13, min(20, floor(($height - 8) / max(1, $n))));
        $font = ($width >= 400 && $rowH >= 18) ? 11 : ($rowH >= 15 ? 10 : 9);
        // Texto de valor de cada fila («123 · 45%») y ancho que pide la leyenda
        // (cuadro + nombre más largo + valor), medidos con la fuente real.
        $valueText = fn ($s) => number_format($s['value']).' · '.($total > 0 ? round(($s['value'] / $total) * 100) : 0).'%';
        $valueW = max(array_map(fn ($s) => TextMetrics::width($valueText($s), $font), $slices) ?: [0]);
        $nameW = max(array_map(fn ($s) => TextMetrics::width((string) $s['name'], $font), $slices) ?: [0]);
        $legendW = 15 + $nameW + 10 + $valueW;
        // Donut a la izquierda (cx = r + 4) y leyenda a 14 px de su borde: r es lo que deja libre la leyenda.
        $r = (int) min(78, floor($height / 2) - 4, max(40, floor(($width - $legendW - 20) / 2)));
        $inner = (int) round($r * 0.64);
        $cx = $r + 4;
        $cy = $height / 2;

        $svg = self::open($width, $height);

        if ($total <= 0) {
            $svg .= sprintf('<circle cx="%d" cy="%.1f" r="%d" fill="none" stroke="%s" stroke-width="%d"/>', $cx, $cy, ($r + $inner) / 2, self::GRID, $r - $inner);
            $svg .= self::text($cx, $cy + 4, 'Sin datos', 'middle', 11, self::AXIS);

            return self::uri($svg.'</svg>');
        }

        $angle = -M_PI / 2;
        foreach ($slices as $i => $s) {
            $color = $palette[$i % max(1, count($palette))] ?? '#2563EB';
            $sweep = ($s['value'] / $total) * 2 * M_PI;

            if ($sweep >= 2 * M_PI - 0.0001) {
                $svg .= sprintf('<circle cx="%d" cy="%.1f" r="%.1f" fill="none" stroke="%s" stroke-width="%d"/>', $cx, $cy, ($r + $inner) / 2, self::e($color), $r - $inner);
            } else {
                $svg .= sprintf('<path d="%s" fill="%s" stroke="#FFFFFF" stroke-width="2"/>', self::arc($cx, $cy, $r, $inner, $angle, $angle + $sweep), self::e($color));
            }
            $angle += $sweep;
        }

        // La etiqueta central solo se pone si cabe en el hueco de la dona.
        $labelFits = $centerLabel !== '' && TextMetrics::width($centerLabel, $r >= 60 ? 9 : 8) <= $inner * 2 - 8;
        $svg .= self::text($cx, $cy + ($labelFits ? ($r >= 60 ? 2 : 3) : 5), number_format($total), 'middle', $r >= 60 ? 18 : 15, '#18181B', 700);
        if ($labelFits) {
            $svg .= self::text($cx, $cy + ($r >= 60 ? 16 : 14), $centerLabel, 'middle', $r >= 60 ? 9 : 8, self::AXIS);
        }

        $lx = $cx + $r + 14;
        $startY = $cy - ($n * $rowH) / 2 + $rowH / 2 + 4;
        // El nombre se recorta para dejar siempre aire antes del valor.
        $nameMax = $width - 2 - $valueW - 10 - ($lx + 15);
        foreach ($slices as $i => $s) {
            $color = $palette[$i % max(1, count($palette))] ?? '#2563EB';
            $y = $startY + $i * $rowH;
            $svg .= sprintf('<rect x="%d" y="%.1f" width="9" height="9" rx="2.5" fill="%s"/>', $lx, $y - 8, self::e($color));
            $svg .= self::text($lx + 15, $y, TextMetrics::fit((string) $s['name'], $nameMax, $font), 'start', $font, '#27272A');
            $svg .= self::text($width - 2, $y, $valueText($s), 'end', $font, self::AXIS);
        }

        return self::uri($svg.'</svg>');
    }

    /**
     * Ranking en barras horizontales: etiqueta a la izquierda, barra sobre un
     * riel tenue y el valor a la derecha. La altura se ajusta al número de
     * filas. Cada punto puede traer su propio `color` (p. ej. «Otros» en gris).
     *
     * Los anchos de etiqueta y valor se calculan con el texto real (medido en
     * DejaVu Sans, la fuente más ancha que puede tocar): $labelWidth es el
     * máximo para las etiquetas (se recortan con «…» si no caben) y la
     * columna de valores crece lo necesario para que el monto nunca choque con
     * la barra. Con $compact los montos se abrevian ($1.2M, $616k).
     *
     * @param  list<array{name: string, value: int|float, color?: string, note?: string}>  $items
     */
    public static function hbars(array $items, string $color, bool $money = true, int $width = 460, int $labelWidth = 150, int $rowH = 24, int $valueW = 92, bool $compact = false, int $fontSize = 10): string
    {
        $padT = 4;
        $n = count($items);
        $height = max(40, $padT * 2 + $n * $rowH);
        $svg = self::open($width, $height);

        if ($n === 0) {
            $svg .= self::text($width / 2, $height / 2 + 4, 'Sin datos', 'middle', 11, self::AXIS);

            return self::uri($svg.'</svg>');
        }

        $font = $fontSize;
        $gap = 8;
        $values = array_map(fn ($it) => match (true) {
            $money && $compact => self::compactMoney((float) $it['value']),
            $money => self::money((float) $it['value']),
            default => number_format((float) $it['value']),
        }, $items);
        // Columna de valores: el más ancho (en negrita, por si la fuente la respeta) y aire con la barra.
        $valueCol = max(array_map(fn ($v) => TextMetrics::width($v, $font, true), $values)) + 2 + $gap;
        // Columna de etiquetas: lo que pida la más larga, sin pasar del máximo ni dejar la barra sin espacio.
        $longest = max(array_map(fn ($it) => TextMetrics::width((string) $it['name'], $font), $items));
        $labelCol = min($labelWidth, $longest, $width - $valueCol - $gap - max(36, $width * 0.28));
        $labelCol = max(24, $labelCol);

        $max = max(array_map(fn ($i) => (float) $i['value'], $items)) ?: 1;
        $trackX = $labelCol + $gap;
        $trackW = max(10, $width - $trackX - $valueCol);
        $barH = max(6, min(12, $rowH - 10));

        foreach ($items as $i => $it) {
            $y = $padT + $i * $rowH;
            $mid = $y + $rowH / 2;
            $base = $mid + $font * 0.35;
            $svg .= self::text($labelCol, $base, TextMetrics::fit((string) $it['name'], $labelCol, $font), 'end', $font, '#27272A');
            $svg .= sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="#F4F4F5"/>', $trackX, $mid - $barH / 2, $trackW, $barH, $barH / 2);
            $w = max(0, ((float) $it['value'] / $max) * $trackW);
            if ($w > 0) {
                $svg .= sprintf(
                    '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="%s"/>',
                    $trackX, $mid - $barH / 2, max($w, $barH), $barH, $barH / 2, self::e($it['color'] ?? $color)
                );
            }
            $svg .= self::text($width - 2, $base, $values[$i], 'end', $font, '#18181B', 600);
            if (! empty($it['note'])) {
                $svg .= self::text($width - 2, $mid + 13, (string) $it['note'], 'end', 8, self::AXIS);
            }
        }

        return self::uri($svg.'</svg>');
    }

    /** Montos en gráficas: siempre en pesos enteros (las tablas llevan centavos). */
    private static function money(float $v): string
    {
        return ($v < 0 ? '-$' : '$').number_format(abs($v), 0);
    }

    /** Montos abreviados para gráficas: $950, $9.5k, $616k, $1.25M, $15.4M. */
    public static function compactMoney(float $v): string
    {
        $abs = abs($v);
        $sign = $v < 0 ? '-$' : '$';
        $trim = fn (string $s) => str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;

        return $sign.match (true) {
            $abs >= 999_500 => $trim(number_format($abs / 1_000_000, $abs >= 9_995_000 ? 1 : 2)).'M',
            $abs >= 9_995 => number_format($abs / 1_000, 0).'k',
            $abs >= 1_000 => $trim(number_format($abs / 1_000, 1)).'k',
            default => number_format($abs, 0),
        };
    }

    /** Etiqueta del eje X centrada en $x sin salirse del lienzo. */
    private static function xLabel(float $x, float $y, string $label, int $width): string
    {
        $half = TextMetrics::width($label, 9) / 2 + 1;
        $x = max($half, min($width - $half, $x));

        return self::text($x, $y, $label, 'middle', 9);
    }

    /** Ancho del eje Y según su etiqueta más ancha más el espacio a la cuadrícula. */
    private static function axisWidth(float $max, callable $format): int
    {
        $w = 0;
        for ($i = 0; $i <= 4; $i++) {
            $w = max($w, TextMetrics::width($format($max / 4 * $i), 9));
        }

        return (int) ceil($w + 12);
    }

    private static function open(int $w, int $h): string
    {
        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" font-family="%s">', $w, $h, $w, $h, self::e(self::FONT));
    }

    private static function grid(float $x, float $y, float $w, float $h, float $max, callable $format): string
    {
        $out = '';
        for ($i = 0; $i <= 4; $i++) {
            $gy = $y + $h - ($h / 4) * $i;
            $out .= sprintf(
                '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="%s" stroke-width="1" %s/>',
                $x, $gy, $x + $w, $gy, self::GRID, $i === 0 ? '' : 'stroke-dasharray="3 4"'
            );
            $out .= self::text($x - 8, $gy + 3, $format($max / 4 * $i), 'end', 9);
        }

        return $out;
    }

    private static function text(float $x, float $y, string $value, string $anchor = 'start', int $size = 10, string $color = self::AXIS, int $weight = 500): string
    {
        return sprintf(
            '<text x="%.2f" y="%.2f" text-anchor="%s" font-size="%d" font-weight="%d" fill="%s">%s</text>',
            $x, $y, $anchor, $size, $weight, $color, self::e($value)
        );
    }

    private static function roundedTopRect(float $x, float $y, float $w, float $h, float $r): string
    {
        return sprintf(
            'M %.2f %.2f L %.2f %.2f Q %.2f %.2f %.2f %.2f L %.2f %.2f Q %.2f %.2f %.2f %.2f L %.2f %.2f Z',
            $x, $y + $h,
            $x, $y + $r,
            $x, $y, $x + $r, $y,
            $x + $w - $r, $y,
            $x + $w, $y, $x + $w, $y + $r,
            $x + $w, $y + $h
        );
    }

    /** @param list<array{0: float, 1: float}> $pts */
    private static function smoothPath(array $pts): string
    {
        $d = sprintf('M %.2f %.2f', $pts[0][0], $pts[0][1]);
        $count = count($pts);
        for ($i = 1; $i < $count; $i++) {
            [$x0, $y0] = $pts[$i - 1];
            [$x1, $y1] = $pts[$i];
            $mx = ($x0 + $x1) / 2;
            $d .= sprintf(' C %.2f %.2f, %.2f %.2f, %.2f %.2f', $mx, $y0, $mx, $y1, $x1, $y1);
        }

        return $d;
    }

    private static function arc(float $cx, float $cy, float $r, float $ri, float $a0, float $a1): string
    {
        $large = ($a1 - $a0) > M_PI ? 1 : 0;
        $p = fn ($rad, $a) => [$cx + $rad * cos($a), $cy + $rad * sin($a)];
        [$x0, $y0] = $p($r, $a0);
        [$x1, $y1] = $p($r, $a1);
        [$x2, $y2] = $p($ri, $a1);
        [$x3, $y3] = $p($ri, $a0);

        return sprintf(
            'M %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 0 %.2f %.2f Z',
            $x0, $y0, $r, $r, $large, $x1, $y1, $x2, $y2, $ri, $ri, $large, $x3, $y3
        );
    }

    /**
     * Cada cuántas etiquetas del eje X se dibuja una para que no se encimen
     * (ancho real de la más larga a 9 px más un margen).
     *
     * @param  list<array{name: string, value: int|float}>  $points
     */
    private static function labelStep(array $points, float $plotW): int
    {
        $n = count($points);
        if ($n === 0) {
            return 1;
        }
        $longest = max(array_map(fn ($p) => TextMetrics::width((string) $p['name'], 9), $points));
        $fit = max(1, (int) floor($plotW / ($longest + 3)));

        return max(1, (int) ceil($n / $fit));
    }

    private static function niceMax(float $value): float
    {
        if ($value <= 0) {
            return 4;
        }
        $exp = 10 ** floor(log10($value));
        // Máximos cuya cuarta parte es redonda: las 4 líneas del eje quedan en cifras limpias.
        foreach ([1, 2, 4, 6, 8, 10] as $m) {
            if ($m * $exp >= $value) {
                return $m * $exp;
            }
        }

        return 10 * $exp;
    }

    private static function short(float $v): string
    {
        return match (true) {
            $v >= 1_000_000 => rtrim(rtrim(number_format($v / 1_000_000, 2), '0'), '.').'M',
            $v >= 1_000 => rtrim(rtrim(number_format($v / 1_000, 2), '0'), '.').'k',
            default => (string) round($v, 1),
        };
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function uri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
