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
        [$plotX, $plotY, $plotW, $plotH] = [$money ? 48 : 40, 16, $width - ($money ? 60 : 52), $height - 48];
        $max = self::niceMax(max(array_column($points, 'value') ?: [0]));
        if (! $money && $max <= 12) {
            // Conteos chicos: múltiplo de 4 para que las 4 líneas del eje sean enteras.
            $max = max(4, ceil($max / 4) * 4);
        }
        $n = max(1, count($points));
        $slot = $plotW / $n;
        $barW = min(26, $slot * 0.62);
        $labelStep = self::labelStep($points, $plotW);

        $svg = self::open($width, $height);
        $svg .= self::grid($plotX, $plotY, $plotW, $plotH, $max, fn ($v) => ($money ? '$' : '').self::short($v));

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
                $svg .= self::text($x + $barW / 2, $plotY + $plotH + 16, $p['name'], 'middle', 9);
            }
        }

        return self::uri($svg.'</svg>');
    }

    /**
     * @param  list<array{name: string, value: int|float}>  $points
     */
    public static function area(array $points, string $color, bool $money = false, int $width = 520, int $height = 220): string
    {
        [$plotX, $plotY, $plotW, $plotH] = [56, 16, $width - 68, $height - 48];
        $max = self::niceMax(max(array_column($points, 'value') ?: [0]));
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
        $svg .= self::grid($plotX, $plotY, $plotW, $plotH, $max, fn ($v) => ($money ? '$' : '').self::short($v));

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
                $svg .= self::text($plotX + $step * $i, $plotY + $plotH + 16, $p['name'], 'middle', 9);
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
        $font = ($width >= 400 && ($height - 8) / max(1, $n) >= 18) ? 11 : 10;
        $charW = $font * 0.53;
        // Ancho que pide la leyenda (cuadro + nombre más largo + «123 · 45%»).
        $longest = max(array_map(fn ($s) => mb_strlen((string) $s['name']), $slices) ?: [0]);
        $legendW = 15 + $longest * $charW + 8 + 9 * $charW;
        $r = (int) min(78, floor($height / 2) - 4, max(40, floor(($width - $legendW - 24) / 2)));
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
        $labelFits = $centerLabel !== '' && mb_strlen($centerLabel) * ($r >= 60 ? 9 : 8) * 0.55 <= $inner * 2 - 10;
        $svg .= self::text($cx, $cy + ($labelFits ? ($r >= 60 ? 2 : 3) : 5), number_format($total), 'middle', $r >= 60 ? 18 : 15, '#18181B', 700);
        if ($labelFits) {
            $svg .= self::text($cx, $cy + ($r >= 60 ? 16 : 14), $centerLabel, 'middle', $r >= 60 ? 9 : 8, self::AXIS);
        }

        $lx = $cx + $r + 16;
        $rowH = (int) max(14, min(20, floor(($height - 8) / max(1, $n))));
        $startY = $cy - ($n * $rowH) / 2 + $rowH / 2 + 4;
        $maxChars = max(8, (int) floor(($width - $lx - 15 - 8 - 9 * $charW) / $charW));
        foreach ($slices as $i => $s) {
            $color = $palette[$i % max(1, count($palette))] ?? '#2563EB';
            $y = $startY + $i * $rowH;
            $pct = round(($s['value'] / $total) * 100);
            $name = (string) $s['name'];
            if (mb_strlen($name) > $maxChars) {
                $name = rtrim(mb_substr($name, 0, $maxChars - 1)).'…';
            }
            $svg .= sprintf('<rect x="%d" y="%.1f" width="9" height="9" rx="2.5" fill="%s"/>', $lx, $y - 8, self::e($color));
            $svg .= self::text($lx + 15, $y, $name, 'start', $font, '#27272A');
            $svg .= self::text($width - 2, $y, number_format($s['value'])." · {$pct}%", 'end', $font, self::AXIS);
        }

        return self::uri($svg.'</svg>');
    }

    /**
     * Ranking en barras horizontales: etiqueta a la izquierda, barra sobre un
     * riel tenue y el valor a la derecha. La altura se ajusta al número de
     * filas. Cada punto puede traer su propio `color` (p. ej. «Otros» en gris).
     *
     * @param  list<array{name: string, value: int|float, color?: string, note?: string}>  $items
     */
    public static function hbars(array $items, string $color, bool $money = true, int $width = 460, int $labelWidth = 150, int $rowH = 24, int $valueW = 92): string
    {
        $padT = 4;
        $n = count($items);
        $height = max(40, $padT * 2 + $n * $rowH);
        $svg = self::open($width, $height);

        if ($n === 0) {
            $svg .= self::text($width / 2, $height / 2 + 4, 'Sin datos', 'middle', 11, self::AXIS);

            return self::uri($svg.'</svg>');
        }

        $max = max(array_map(fn ($i) => (float) $i['value'], $items)) ?: 1;
        $trackX = $labelWidth + 8;
        $trackW = $width - $trackX - $valueW;
        $barH = min(12, $rowH - 10);
        $maxChars = max(8, (int) floor($labelWidth / 5.3));

        foreach ($items as $i => $it) {
            $y = $padT + $i * $rowH;
            $mid = $y + $rowH / 2;
            $name = (string) $it['name'];
            if (mb_strlen($name) > $maxChars) {
                $name = rtrim(mb_substr($name, 0, $maxChars - 1)).'…';
            }
            $svg .= self::text($labelWidth, $mid + 3.5, $name, 'end', 10, '#27272A');
            $svg .= sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="#F4F4F5"/>', $trackX, $mid - $barH / 2, $trackW, $barH, $barH / 2);
            $w = max(0, ((float) $it['value'] / $max) * $trackW);
            if ($w > 0) {
                $svg .= sprintf(
                    '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="%s"/>',
                    $trackX, $mid - $barH / 2, max($w, $barH), $barH, $barH / 2, self::e($it['color'] ?? $color)
                );
            }
            $value = $money ? self::money((float) $it['value']) : number_format((float) $it['value']);
            $svg .= self::text($width - 2, $mid + 3.5, $value, 'end', 10, '#18181B', 600);
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
     * (≈5.4 px por carácter a 9 px más un margen).
     *
     * @param  list<array{name: string, value: int|float}>  $points
     */
    private static function labelStep(array $points, float $plotW): int
    {
        $n = count($points);
        if ($n === 0) {
            return 1;
        }
        $longest = max(array_map(fn ($p) => mb_strlen((string) $p['name']), $points));
        $fit = max(1, (int) floor($plotW / ($longest * 5.0 + 6)));

        return max(1, (int) ceil($n / $fit));
    }

    private static function niceMax(float $value): float
    {
        if ($value <= 0) {
            return 4;
        }
        $exp = 10 ** floor(log10($value));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $exp >= $value) {
                return $m * $exp;
            }
        }

        return 10 * $exp;
    }

    private static function short(float $v): string
    {
        return match (true) {
            $v >= 1_000_000 => rtrim(rtrim(number_format($v / 1_000_000, 1), '0'), '.').'M',
            $v >= 1_000 => rtrim(rtrim(number_format($v / 1_000, 1), '0'), '.').'k',
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
