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
    public static function bars(array $points, string $color, string $unit = '', int $width = 520, int $height = 220): string
    {
        [$plotX, $plotY, $plotW, $plotH] = [40, 16, $width - 52, $height - 48];
        $max = self::niceMax(max(array_column($points, 'value') ?: [0]));
        $n = max(1, count($points));
        $slot = $plotW / $n;
        $barW = min(26, $slot * 0.62);

        $svg = self::open($width, $height);
        $svg .= self::grid($plotX, $plotY, $plotW, $plotH, $max, fn ($v) => self::short($v));

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
            if ($n <= 16 || $i % 2 === 0) {
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

        $coords = [];
        foreach ($points as $i => $p) {
            $coords[] = [
                $plotX + $step * $i,
                $plotY + $plotH - ($max > 0 ? ($p['value'] / $max) * $plotH : 0),
            ];
        }

        $svg = self::open($width, $height);
        $svg .= '<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1">'
            .'<stop offset="0%" stop-color="'.self::e($color).'" stop-opacity="0.35"/>'
            .'<stop offset="100%" stop-color="'.self::e($color).'" stop-opacity="0.02"/>'
            .'</linearGradient></defs>';
        $svg .= self::grid($plotX, $plotY, $plotW, $plotH, $max, fn ($v) => ($money ? '$' : '').self::short($v));

        if ($coords !== []) {
            $line = self::smoothPath($coords);
            $last = end($coords);
            $first = $coords[0];
            $base = $plotY + $plotH;
            $svg .= sprintf('<path d="%s L %.2f %.2f L %.2f %.2f Z" fill="url(#g)"/>', $line, $last[0], $base, $first[0], $base);
            $svg .= sprintf('<path d="%s" fill="none" stroke="%s" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>', $line, self::e($color));
            foreach ($coords as [$x, $y]) {
                $svg .= sprintf('<circle cx="%.2f" cy="%.2f" r="2.6" fill="#FFFFFF" stroke="%s" stroke-width="1.6"/>', $x, $y, self::e($color));
            }
        }

        foreach ($points as $i => $p) {
            if ($n <= 16 || $i % 2 === 0) {
                $svg .= self::text($plotX + $step * $i, $plotY + $plotH + 16, $p['name'], 'middle', 9);
            }
        }

        return self::uri($svg.'</svg>');
    }

    /**
     * Dona con leyenda a la derecha.
     *
     * @param  list<array{name: string, value: int|float}>  $slices
     * @param  list<string>  $palette
     */
    public static function donut(array $slices, array $palette, int $width = 520, int $height = 200): string
    {
        $total = array_sum(array_column($slices, 'value'));
        $cx = 100;
        $cy = $height / 2;
        $r = 78;
        $inner = 50;

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

        $svg .= self::text($cx, $cy - 2, number_format($total), 'middle', 18, '#18181B', 700);
        $svg .= self::text($cx, $cy + 14, 'Total', 'middle', 9, self::AXIS);

        $lx = 210;
        $rowH = 20;
        $startY = $cy - (count($slices) * $rowH) / 2 + 10;
        foreach ($slices as $i => $s) {
            $color = $palette[$i % max(1, count($palette))] ?? '#2563EB';
            $y = $startY + $i * $rowH;
            $pct = round(($s['value'] / $total) * 100);
            $svg .= sprintf('<rect x="%d" y="%.1f" width="10" height="10" rx="3" fill="%s"/>', $lx, $y - 9, self::e($color));
            $svg .= self::text($lx + 18, $y, $s['name'], 'start', 11, '#27272A');
            $svg .= self::text($width - 12, $y, number_format($s['value'])." · {$pct}%", 'end', 11, self::AXIS);
        }

        return self::uri($svg.'</svg>');
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
