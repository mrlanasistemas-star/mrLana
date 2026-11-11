<?php

namespace App\Support\Pdf;

/**
 * Ancho aproximado de un texto en DejaVu Sans, la tipografía de DomPDF (y la
 * que suele usar Chrome en un servidor Linux sin otras fuentes). Es de las
 * más anchas, así que si un texto cabe con esta medida también cabe con
 * Inter, Segoe UI o Arial.
 *
 * Los anchos salen de las métricas de la fuente (unidades por 1000 em) que
 * trae DomPDF (lib/fonts/DejaVuSans*.ufm).
 */
final class TextMetrics
{
    /** Anchos de los caracteres ASCII 32-126. */
    private const REGULAR = [318, 401, 460, 838, 636, 950, 780, 275, 390, 390, 500, 838, 318, 361, 318, 337, 636, 636, 636, 636, 636, 636, 636, 636, 636, 636, 337, 337, 838, 838, 838, 531, 1000, 684, 686, 698, 770, 632, 575, 775, 752, 295, 295, 656, 557, 863, 748, 787, 603, 787, 695, 635, 611, 732, 684, 989, 685, 611, 685, 390, 337, 390, 838, 500, 500, 613, 635, 550, 635, 615, 352, 635, 634, 278, 278, 579, 278, 974, 634, 612, 635, 635, 411, 521, 392, 634, 592, 818, 592, 592, 525, 636, 337, 636, 838];

    private const BOLD = [348, 456, 521, 838, 696, 1002, 872, 306, 457, 457, 523, 838, 380, 415, 380, 365, 696, 696, 696, 696, 696, 696, 696, 696, 696, 696, 400, 400, 838, 838, 838, 580, 1000, 774, 762, 734, 830, 683, 683, 821, 837, 372, 372, 775, 637, 995, 837, 850, 733, 850, 770, 720, 682, 812, 774, 1103, 771, 724, 725, 457, 365, 457, 838, 500, 500, 675, 716, 593, 716, 678, 435, 716, 712, 343, 343, 665, 343, 1042, 712, 687, 716, 716, 493, 595, 478, 712, 652, 924, 645, 652, 582, 712, 365, 712, 838];

    /** Letras acentuadas: miden lo mismo que la letra base. */
    private const BASE = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c', 'Ç' => 'C',
        '¿' => '?', '¡' => '!', '–' => '-', '“' => '"', '”' => '"', '‘' => "'", '’' => "'",
    ];

    /** Otros caracteres frecuentes (unidades por 1000 em). */
    private const EXTRA = ['…' => 1000, '—' => 1000, '·' => 318, '°' => 500, '•' => 590];

    /** Ancho en px de $text a $size px. */
    public static function width(string $text, float $size, bool $bold = false): float
    {
        $table = $bold ? self::BOLD : self::REGULAR;
        $units = 0;
        foreach (mb_str_split(strtr($text, self::BASE)) as $ch) {
            $code = ord($ch);
            $units += match (true) {
                strlen($ch) === 1 && $code >= 32 && $code <= 126 => $table[$code - 32],
                isset(self::EXTRA[$ch]) => self::EXTRA[$ch],
                default => 700,
            };
        }

        return $units * $size / 1000;
    }

    /**
     * Recorta $text con «…» para que no pase de $maxWidth px.
     */
    public static function fit(string $text, float $maxWidth, float $size, bool $bold = false): string
    {
        $text = trim($text);
        if (self::width($text, $size, $bold) <= $maxWidth) {
            return $text;
        }

        $chars = mb_str_split($text);
        $budget = $maxWidth - self::width('…', $size, $bold);
        $out = '';
        foreach ($chars as $ch) {
            if (self::width($out.$ch, $size, $bold) > $budget) {
                break;
            }
            $out .= $ch;
        }

        return rtrim($out, " \t,.;:-·").'…';
    }

    /**
     * Cuántas líneas ocupa $text en una columna de $maxWidth px, partiendo
     * en espacios (las palabras más largas que la columna se cortan).
     */
    public static function lines(string $text, float $maxWidth, float $size, bool $bold = false): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '' || $maxWidth <= 0) {
            return 1;
        }

        $space = self::width(' ', $size, $bold);
        $lines = 1;
        $used = 0.0;
        foreach (explode(' ', $text) as $word) {
            $w = self::width($word, $size, $bold);
            if ($used > 0 && $used + $space + $w > $maxWidth) {
                $lines++;
                $used = 0.0;
            }
            if ($used === 0.0 && $w > $maxWidth) {
                $lines += (int) ceil($w / $maxWidth) - 1;
                $w = fmod($w, $maxWidth);
            }
            $used += ($used > 0 ? $space : 0) + $w;
        }

        return $lines;
    }
}
