<?php

namespace App\Support\Reports;

use App\Services\Dashboard\DashboardDataService;
use App\Support\Pdf\PdfFormat;
use App\Support\Pdf\TextMetrics;

/**
 * Acomodo en hojas del PDF de requisiciones (carta horizontal).
 *
 * DomPDF (el respaldo que se usa cuando no hay Chrome) no sabe mantener
 * juntos bloques grandes: con «page-break-inside: avoid» deja hojas casi
 * vacías y encadena saltos. Por eso, de la segunda hoja en adelante, el
 * reporte se reparte aquí: se estima la altura de cada fila con el ancho real
 * del texto (métricas de DejaVu Sans, la fuente de DomPDF y la más ancha que
 * puede tocar en Chrome) y se decide qué va en cada hoja. La vista pone un
 * salto explícito entre hojas, así que ningún motor tiene que adivinar.
 *
 *  - Hoja 1: indicadores y gráficas (los tamaños se ajustan en la vista).
 *  - Hoja 2: tabla de solicitantes completa y, a continuación, la lista.
 *  - La lista se parte en una tabla por hoja (cada una con su encabezado).
 *  - Cada requisición del detalle va completa en una hoja; si es más larga
 *    que una hoja se parte entre filas, con el encabezado de la tabla de
 *    items repetido y la marca «(continúa)».
 *
 * Todas las medidas están en px CSS (96 por pulgada). El contenido útil de
 * una hoja carta horizontal con los márgenes de PdfService es de
 * 920 × 703 px.
 */
final class RequisicionesPdfLayout
{
    public const PAGE_W = 920;

    public const PAGE_H = 703;

    /** Margen de seguridad por hoja para errores de estimación. */
    private const SAFETY = 14;

    /**
     * Alto de línea efectivo: DomPDF usa el mayor entre line-height (1.45) y
     * el alto de la fuente × font_height_ratio (1.695 × 0.86 ≈ 1.458).
     */
    private const LH = 1.46;

    /** Proporción del font_height_ratio de DomPDF que usa este reporte. */
    public const DOMPDF_FONT_HEIGHT_RATIO = 0.86;

    /** Tamaños de letra (px) que usan los estilos de la vista. */
    public const LIST_FONT = 8.5;

    public const ITEM_FONT = 9;

    public const HEAD_FONT = 8.5;

    /** Máximo de solicitantes en la tabla (el resto se agrupa en «Otros»). */
    public const SOLICITANTES_MAX = 15;

    /** @var array<string, int> Anchos (px) de las columnas de la lista. */
    public array $listCols = [];

    /** @var array<string, int> Anchos (px) de las columnas de items. */
    public array $itemCols = [];

    /** @var array<string, int> Anchos (px) de las columnas del encabezado de cada requisición. */
    public array $headCols = [];

    /** @var list<array<string, mixed>> Filas de la tabla de solicitantes (con «Otros»). */
    public array $solicitantes = [];

    /**
     * Hojas de la 2 en adelante. Cada hoja es una lista de bloques:
     *  - ['type' => 'solicitantes']
     *  - ['type' => 'lista', 'rows' => list<int>, 'title' => bool, 'foot' => bool, 'cont' => bool]
     *  - ['type' => 'req', 'row' => int, 'items' => list<int>, 'head' => 'full'|'cont', 'totals' => bool, 'title' => bool, 'part' => int, 'parts' => int]
     * Algunos bloques traen además la posición estimada ('ys', 'y', 'h'), útil
     * para comparar la estimación con el PDF real al ajustar las medidas.
     *
     * @var list<list<array<string, mixed>>>
     */
    public array $pages = [];

    /** @param list<array<string, mixed>> $rows */
    public function __construct(private readonly array $rows, RequisicionesSummary $summary)
    {
        $this->solicitantes = RequisicionesSummary::top($summary->solicitantes, self::SOLICITANTES_MAX);
        $this->listCols = $this->computeListCols();
        $this->itemCols = $this->computeItemCols();
        $this->headCols = ['main' => 184, 'who' => 248, 'prov' => 266, 'dates' => 220];

        if ($rows !== []) {
            $this->plan();
        }
    }

    /** Alto de una línea de texto a $size px. */
    private static function line(float $size): float
    {
        return self::LH * $size;
    }

    // ------------------------------------------------------------------
    // Columnas
    // ------------------------------------------------------------------

    /** @return array<string, int> */
    private function computeListCols(): array
    {
        $f = self::LIST_FONT;
        $pad = 13; // 6 + 6 de padding y 1 de holgura
        $max = fn (callable $get, bool $bold = false) => max(array_map(fn ($r) => TextMetrics::width((string) $get($r), $f, $bold), $this->rows) ?: [0]);
        $th = fn (string $t) => TextMetrics::width($t, 8, true) + $pad;

        $cols = [
            'folio' => max($th('Folio'), $max(fn ($r) => $r['folio'] ?? '', true) + $pad),
            'registro' => max($th('Registro'), TextMetrics::width('00/00/0000', $f) + $pad + 4),
            'monto' => max($th('Monto'), $max(fn ($r) => PdfFormat::money($r['total_efectuado'] ?? 0)) + $pad),
            'pagado' => max($th('Pagado'), $max(fn ($r) => PdfFormat::money($r['pagado'] ?? 0)) + $pad),
            'comprobado' => max($th('Comprobado'), $max(fn ($r) => PdfFormat::money($r['comprobado'] ?? 0)) + $pad),
        ];
        // La fila de totales usa las mismas columnas de montos.
        $totals = ['monto' => 'total_efectuado', 'pagado' => 'pagado', 'comprobado' => 'comprobado'];
        foreach ($totals as $col => $key) {
            $sum = array_sum(array_map(fn ($r) => (float) ($r[$key] ?? 0), $this->rows));
            $cols[$col] = max($cols[$col], TextMetrics::width(PdfFormat::money($sum), $f, true) + $pad);
        }
        $cols = array_map(fn ($w) => (int) ceil($w), $cols);

        $out = $this->allocateTextCols(self::PAGE_W - array_sum($cols), $pad);

        $order = ['folio', 'registro', 'estatus', 'solicitante', 'sucursal', 'concepto', 'proveedor', 'monto', 'pagado', 'comprobado'];
        $all = $cols + $out;
        $all['proveedor'] += self::PAGE_W - array_sum($all);

        return array_combine($order, array_map(fn ($k) => $all[$k], $order));
    }

    /** Textos de las columnas que pueden partirse en varias líneas, por fila de la lista. */
    private static function listTexts(array $r): array
    {
        return [
            'estatus' => self::statusLabel($r['estatus'] ?? ''),
            'solicitante' => (string) ($r['solicitante'] ?: '—'),
            'sucursal' => (string) ($r['sucursal'] ?: '—'),
            'concepto' => (string) ($r['concepto'] ?: '—'),
            'proveedor' => (string) ($r['proveedor'] ?: '—'),
        ];
    }

    /**
     * Reparte $free px entre las columnas de texto de la lista para que las
     * filas ocupen la menor altura posible: empieza con lo mínimo (la palabra
     * más larga) y va dando espacio a la columna que más líneas ahorra por
     * px, hasta agotar el ancho.
     *
     * @return array<string, int>
     */
    private function allocateTextCols(float $free, int $pad): array
    {
        $f = self::LIST_FONT;
        $extra = ['estatus' => 11]; // punto de color antes del texto
        $keys = ['estatus', 'solicitante', 'sucursal', 'concepto', 'proveedor'];

        // Filas distintas (con cuántas veces aparecen) para no medir de más.
        $sigs = [];
        foreach ($this->rows as $r) {
            $t = self::listTexts($r);
            $k = implode("\0", $t);
            $sigs[$k] ??= ['t' => $t, 'n' => 0];
            $sigs[$k]['n']++;
        }
        $sigs = array_values($sigs);

        $min = [];
        $maxW = [];
        foreach ($keys as $c) {
            $words = 0;
            $full = 0;
            foreach ($sigs as $s) {
                foreach (explode(' ', $s['t'][$c]) as $w) {
                    $words = max($words, TextMetrics::width($w, $f));
                }
                $full = max($full, TextMetrics::width($s['t'][$c], $f));
            }
            $head = TextMetrics::width(ucfirst($c), 8, true);
            $min[$c] = (int) ceil(max($words, $head, 40) + $pad + ($extra[$c] ?? 0));
            $maxW[$c] = (int) ceil(max($full + $pad + ($extra[$c] ?? 0), $min[$c]));
        }

        $width = $min;
        $free -= array_sum($min);
        if ($free <= 0) {
            return $width;
        }

        $memo = [];
        $lines = function (string $c, string $text, int $w) use (&$memo, $f, $pad, $extra) {
            return $memo[$c][$text][$w] ??= TextMetrics::lines($text, $w - $pad - ($extra[$c] ?? 0) + 1, $f);
        };
        $rowLines = fn (array $s, array $w) => array_combine($keys, array_map(fn ($c) => $lines($c, $s['t'][$c], $w[$c]), $keys));

        $step = 6;
        while ($free >= $step) {
            $cur = array_map(fn ($s) => $rowLines($s, $width), $sigs);
            $best = null;
            foreach ($keys as $c) {
                // Lo que pesan las otras columnas en cada fila. El costo es la
                // altura de la fila y, en menor medida, cuántos textos se parten
                // (a igual altura, mejor un nombre en una línea).
                $others = array_map(function ($l) use ($c) {
                    unset($l[$c]);

                    return $l ? max($l) : 1;
                }, $cur);
                $sum = array_map(fn ($l) => array_sum($l) - $l[$c], $cur);
                $rowCost = fn (int $i, int $lc) => max($others[$i], $lc) + 0.2 * ($sum[$i] + $lc);
                $base = 0;
                foreach ($sigs as $i => $s) {
                    $base += $s['n'] * $rowCost($i, $cur[$i][$c]);
                }
                for ($inc = $step; $inc <= min($free, $step * 6) && $width[$c] + $inc <= $maxW[$c] + $step; $inc += $step) {
                    $cost = 0;
                    foreach ($sigs as $i => $s) {
                        $cost += $s['n'] * $rowCost($i, $lines($c, $s['t'][$c], $width[$c] + $inc));
                    }
                    $gain = ($base - $cost) / $inc;
                    if ($gain > 0 && ($best === null || $gain > $best[0])) {
                        $best = [$gain, $c, $inc];
                    }
                }
            }
            if ($best === null) {
                break;
            }
            $width[$best[1]] += $best[2];
            $free -= $best[2];
        }

        // Lo que sobre va a las columnas de texto más largas.
        if ($free > 0) {
            $width['proveedor'] += (int) floor($free / 2);
            $width['concepto'] += (int) floor($free / 2);
        }

        return $width;
    }

    /** @return array<string, int> */
    private function computeItemCols(): array
    {
        $pad = 21; // 10 + 10 de padding y 1 de holgura
        $amounts = [];
        foreach ($this->rows as $r) {
            foreach ($r['items'] ?? [] as $it) {
                $amounts[] = $it['precio_unitario'] ?? 0;
                $amounts[] = $it['subtotal'] ?? 0;
                $amounts[] = $it['total'] ?? 0;
            }
            foreach (['total_items', 'total_efectuado', 'pagado', 'comprobado', 'otras_diferencias'] as $k) {
                $amounts[] = $r[$k] ?? 0;
            }
            foreach ($r['ajustes'] ?? [] as $a) {
                $amounts[] = $a['monto'] ?? 0;
            }
        }
        $moneyW = max(array_map(fn ($v) => TextMetrics::width(PdfFormat::money($v), self::ITEM_FONT, true), $amounts) ?: [0]) + $pad;
        $th = fn (string $t) => TextMetrics::width($t, 8, true) + $pad;

        $cols = [
            'n' => 32,
            'cantidad' => (int) ceil(max($th('Cantidad'), 56)),
            'precio' => (int) ceil(max($th('Precio unitario'), $moneyW)),
            'subtotal' => (int) ceil(max($th('Subtotal'), $moneyW)),
            'iva' => (int) ceil(max($th('IVA'), $moneyW, TextMetrics::width('Sin IVA', self::ITEM_FONT) + $pad)),
            'importe' => (int) ceil(max($th('Importe'), $moneyW)),
        ];
        // El ancho interior de la caja de cada requisición (borde de 1 px por lado).
        $cols['item'] = self::PAGE_W - 2 - array_sum($cols);

        return $cols;
    }

    public static function statusLabel(?string $s): string
    {
        return DashboardDataService::STATUS_LABELS[$s] ?? (string) $s;
    }

    // ------------------------------------------------------------------
    // Alturas
    // ------------------------------------------------------------------

    /** Encabezado de sección (h2 + descripción). */
    private function sectionH(): float
    {
        return self::line(13) + 2 + self::line(9) + 8;
    }

    private function solicitantesH(): float
    {
        $nameW = self::PAGE_W * 0.21 - 16;
        $h = $this->sectionH() + 12 + self::line(8.5) + 1.5; // encabezado de la tabla
        foreach ($this->solicitantes as $g) {
            $h += 10 + self::line(9) * TextMetrics::lines((string) $g['name'], $nameW, 9) + 1;
        }

        return $h + 10 + self::line(9) + 1.5; // fila de total
    }

    private function listHeadH(): float
    {
        return 10 + self::line(8) + 1.5;
    }

    private function listRowH(array $r): float
    {
        $f = self::LIST_FONT;
        $c = $this->listCols;
        $lines = max(
            TextMetrics::lines(self::statusLabel($r['estatus'] ?? ''), $c['estatus'] - 12 - 11, $f),
            TextMetrics::lines((string) ($r['solicitante'] ?: '—'), $c['solicitante'] - 12, $f),
            TextMetrics::lines((string) ($r['sucursal'] ?: '—'), $c['sucursal'] - 12, $f),
            TextMetrics::lines((string) ($r['concepto'] ?: '—'), $c['concepto'] - 12, $f),
            TextMetrics::lines((string) ($r['proveedor'] ?: '—'), $c['proveedor'] - 12, $f),
        );

        return 8 + self::line($f) * $lines + 1;
    }

    private function listFootH(): float
    {
        return 8 + self::line(self::LIST_FONT) + 1.5;
    }

    /** Encabezado completo de una requisición (datos generales y observaciones). */
    private function reqHeadH(array $r): float
    {
        $f = self::HEAD_FONT;
        $c = $this->headCols;
        $l = fn (string $text, int $col) => TextMetrics::lines($text, $col - 20, $f);

        // Folio, estatus, pagado y comprobado.
        $main = self::line(11.5) + 2 + self::line($f) + 2 + self::line($f) * 2;
        $who = 0;
        foreach ([$r['corporativo'] ?? '', $r['sucursal'] ?? '', ! empty($r['solicitante']) ? 'Solicita: '.$r['solicitante'] : ''] as $i => $t) {
            if ($t !== '') {
                $who += $l($t, $c['who']);
            }
        }
        $prov = 0;
        foreach ([! empty($r['proveedor']) ? 'Proveedor: '.$r['proveedor'] : '', ! empty($r['proveedor_rfc']) ? 'RFC: '.$r['proveedor_rfc'] : '', ! empty($r['concepto']) ? 'Concepto: '.$r['concepto'] : ''] as $t) {
            if ($t !== '') {
                $prov += $l($t, $c['prov']);
            }
        }
        $dates = count(array_filter([$r['fecha_solicitud'] ?? null, $r['fecha_pago_esperada'] ?? null, $r['fecha_autorizacion'] ?? null, $r['fecha_pago'] ?? null]));

        $h = 14 + max($main, self::line($f) * max($who, $prov, $dates)) + 1;
        if (! empty($r['observaciones'])) {
            $h += 14 + self::line($f) * TextMetrics::lines('Observaciones: '.$r['observaciones'], self::PAGE_W - 2 - 20, $f) + 1;
        }

        return $h;
    }

    /** Encabezado corto de la parte que continúa en otra hoja. */
    private function reqContH(): float
    {
        return 12 + self::line(9) + 1;
    }

    private function itemsHeadH(): float
    {
        return 10 + self::line(8) + 1;
    }

    private function itemH(array $it): float
    {
        $text = ($it['item'] ?? '') !== '' ? (string) $it['item'] : '—';

        return 8 + self::line(self::ITEM_FONT) * TextMetrics::lines($text, $this->itemCols['item'] - 20, self::ITEM_FONT) + 1;
    }

    /**
     * ¿La requisición tiene ajustes o diferencias? Si no, el total de items
     * es el total efectuado y basta una fila de cierre.
     */
    public static function hasAdjustments(array $r): bool
    {
        return ! empty($r['ajustes']) || abs((float) ($r['otras_diferencias'] ?? 0)) > 0.004;
    }

    /** Filas de cierre: (total de items, ajustes y diferencias) y total efectuado. */
    private function totalsH(array $r): float
    {
        $row = 8 + self::line(self::ITEM_FONT) + 1;
        $labelW = $this->itemCols['item'] + $this->itemCols['cantidad'] + $this->itemCols['precio'] + $this->itemCols['subtotal'] + $this->itemCols['iva'] - 20;
        $h = $row * (self::hasAdjustments($r) ? 2 : 1);
        if (empty($r['items'])) {
            $h += $row;
        }
        foreach ($r['ajustes'] ?? [] as $a) {
            $text = $a['tipo'].(($a['motivo'] ?? '') !== '' ? ': '.$a['motivo'] : '');
            $h += 8 + self::line(self::ITEM_FONT) * TextMetrics::lines($text, $labelW, self::ITEM_FONT) + self::line(7.5) + 1;
        }
        if (abs((float) ($r['otras_diferencias'] ?? 0)) > 0.004) {
            $h += $row;
        }

        return $h;
    }

    /** Caja de la requisición: borde y separación con la siguiente. */
    private const REQ_FRAME = 2 + 10;

    // ------------------------------------------------------------------
    // Reparto en hojas
    // ------------------------------------------------------------------

    private function plan(): void
    {
        $cap = self::PAGE_H - self::SAFETY;
        $page = [];
        $y = 0.0;
        $newPage = function () use (&$page, &$y) {
            if ($page !== []) {
                $this->pages[] = $page;
            }
            $page = [];
            $y = 0.0;
        };

        // Hoja 2: solicitantes.
        $page[] = ['type' => 'solicitantes'];
        $y = $this->solicitantesH();

        // Lista: se agrega fila por fila; al llenarse la hoja se cierra la tabla y sigue en otra.
        $gap = 18;
        $first = true;
        $headH = $this->listHeadH();
        $current = null;
        $n = count($this->rows);
        foreach ($this->rows as $i => $r) {
            $rh = $this->listRowH($r);
            $isLast = $i === $n - 1;
            $need = $rh + ($isLast ? $this->listFootH() : 0);
            if ($current === null) {
                $start = ($y > 0 ? $gap : 0) + ($first ? $this->sectionH() : 0) + $headH;
                // Sección nueva: el título y al menos dos filas deben caber juntos.
                $min = $start + $need + ($isLast ? 0 : $this->listRowH($this->rows[$i + 1]));
                if ($y + $min > $cap) {
                    $newPage();
                    $start = ($first ? $this->sectionH() : 0) + $headH;
                }
                $current = ['type' => 'lista', 'rows' => [], 'title' => $first, 'foot' => false, 'cont' => ! $first];
                $y += $start;
                $first = false;
            } elseif ($y + $need > $cap) {
                $page[] = $current;
                $newPage();
                $current = ['type' => 'lista', 'rows' => [], 'title' => false, 'foot' => false, 'cont' => true];
                $y = $headH;
            }
            $current['rows'][] = $i;
            $current['ys'][] = $y; // posición estimada (solo para revisar la calibración)
            $y += $rh;
        }
        $current['foot'] = true;
        $y += $this->listFootH();
        $page[] = $current;

        // Detalle.
        $title = true;
        foreach ($this->rows as $i => $r) {
            $titleH = $title ? $gap + $this->sectionH() : 0;
            $head = $this->reqHeadH($r);
            $itemsHead = $this->itemsHeadH();
            $items = array_map(fn ($it) => $this->itemH($it), $r['items'] ?? []);
            $totals = $this->totalsH($r);
            $whole = $head + $itemsHead + array_sum($items) + $totals + self::REQ_FRAME;

            // Cabe completa en lo que queda de la hoja.
            if ($y + $titleH + $whole <= $cap) {
                $page[] = ['type' => 'req', 'row' => $i, 'items' => array_keys($items), 'head' => 'full', 'totals' => true, 'title' => $title, 'part' => 1, 'parts' => 1, 'y' => $y + $titleH, 'h' => $whole];
                $y += $titleH + $whole;
                $title = false;

                continue;
            }

            // Cabe completa en una hoja nueva: se pasa entera.
            if ($whole <= $cap) {
                $newPage();
                $titleH = $title ? $this->sectionH() : 0;
                $page[] = ['type' => 'req', 'row' => $i, 'items' => array_keys($items), 'head' => 'full', 'totals' => true, 'title' => $title, 'part' => 1, 'parts' => 1, 'y' => $titleH, 'h' => $whole];
                $y = $titleH + $whole;
                $title = false;

                continue;
            }

            // Más larga que una hoja: se parte entre filas. Empieza aquí si
            // queda espacio razonable (encabezado y unas filas); si no, en hoja nueva.
            $firstChunk = $head + $itemsHead + array_sum(array_slice($items, 0, 3)) + self::REQ_FRAME;
            if ($y > 0 && ($y + $titleH + $firstChunk > $cap || $cap - $y < $cap * 0.3)) {
                $newPage();
                $titleH = $title ? $this->sectionH() : 0;
            }

            $parts = [];
            $k = 0;
            $count = count($items);
            $partHead = $head;
            $partY = $y + $titleH;
            while (true) {
                $avail = $cap - $partY - $partHead - $itemsHead - self::REQ_FRAME;
                $chunk = [];
                $used = 0.0;
                while ($k < $count && ($used + $items[$k] <= $avail || $chunk === [])) {
                    $used += $items[$k];
                    $chunk[] = $k++;
                }
                if ($k >= $count) {
                    if ($used + $totals <= $avail) {
                        $parts[] = [$chunk, true, $partY + $partHead + $itemsHead + $used + $totals + self::REQ_FRAME];
                        break;
                    }
                    // Los totales no caben: las últimas filas pasan con ellos a la hoja siguiente.
                    $back = min(2, count($chunk) - 1);
                    for ($b = 0; $b < $back; $b++) {
                        array_pop($chunk);
                        $k--;
                    }
                }
                $parts[] = [$chunk, false, null];
                $partHead = $this->reqContH();
                $partY = 0.0;
            }

            foreach ($parts as $p => [$chunk, $withTotals, $endY]) {
                if ($p > 0) {
                    $newPage();
                }
                $page[] = [
                    'type' => 'req', 'row' => $i, 'items' => $chunk, 'head' => $p === 0 ? 'full' : 'cont',
                    'totals' => $withTotals, 'title' => $title && $p === 0, 'part' => $p + 1, 'parts' => count($parts),
                ];
                $title = false;
                $y = $endY ?? $cap;
            }
        }

        $newPage();
    }
}
