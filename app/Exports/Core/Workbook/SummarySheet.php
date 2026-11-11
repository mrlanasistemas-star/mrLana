<?php

namespace App\Exports\Core\Workbook;

use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Chart\Axis;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja «Resumen» genérica: encabezado, tarjetas de indicadores y bloques
 * (tabla agregada en A:F con su gráfica nativa en H:P).
 *
 * Las gráficas leen las celdas de su tabla, así que la posición de cada
 * bloque se calcula una sola vez (plan) y se usa al escribir y al graficar.
 * El libro debe usar WithPreCalculateFormulas para que las gráficas guarden
 * los valores de sus series.
 *
 * Indicador: ['label', 'value', 'format' => Theme::*, 'hint' => ?string]
 * Bloque: ver Block::groups() y Block::table().
 */
class SummarySheet implements WithCharts, WithEvents, WithTitle
{
    public const TITLE = 'Resumen';

    private const CHART_ROWS = 16;

    private const KPI_PER_ROW = 6;

    /** @var list<array<string, mixed>> */
    private array $blocks = [];

    private int $blocksStart;

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $blocks
     * @param  array<string, string>  $filters  Filtros legibles (ya sin vacíos).
     */
    public function __construct(
        private string $heading,
        private array $kpis,
        array $blocks,
        private array $filters = [],
        private array $meta = [],
    ) {
        $kpiRows = (int) ceil(max(1, count($kpis)) / self::KPI_PER_ROW);
        $this->blocksStart = 6 + $kpiRows * 4 + 1;
        $this->plan($blocks);
    }

    public function title(): string
    {
        return self::TITLE;
    }

    private function plan(array $blocks): void
    {
        $row = $this->blocksStart;
        foreach ($blocks as $i => $b) {
            $n = max(1, count($b['rows']));
            $hasNote = ! empty($b['note']);
            $header = $row + 1 + ($hasNote ? 1 : 0);
            $first = $header + 1;
            $last = $first + $n - 1;
            $total = ($b['total'] ?? true) && $b['rows'] ? $last + 1 : null;
            // Con una sola fila la gráfica no aporta (una dona al 100 %): solo la tabla.
            $chart = count($b['rows']) >= 2 ? ($b['chart'] ?? null) : null;
            // Rankings: 10 primeros; donas: 12 rebanadas; series de tiempo: todos los puntos.
            $defaultMax = match ($chart['type'] ?? null) {
                'donut' => 12,
                'bar' => 10,
                default => PHP_INT_MAX,
            };
            $points = $chart ? min(count($b['rows']), $chart['max'] ?? $defaultMax) : 0;

            $this->blocks[] = $b + [
                'key' => 'b'.$i, 'row' => $row, 'header' => $header, 'first' => $first, 'last' => $last,
                'totalRow' => $total, 'points' => $points, 'chartSpec' => $chart,
            ];
            $end = $total ?? $last;
            $row = ($chart ? max($end, $row + self::CHART_ROWS - 1) : $end) + 3;
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->write($event->sheet->getDelegate()),
        ];
    }

    private function write(Worksheet $sheet): void
    {
        $sheet->setShowGridlines(false);
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        foreach (['A' => 34, 'B' => 18, 'C' => 18, 'D' => 18, 'E' => 18, 'F' => 18, 'G' => 3] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        foreach (range('H', 'P') as $col) {
            $sheet->getColumnDimension($col)->setWidth(10.5);
        }

        $this->writeHeader($sheet);
        $this->writeKpis($sheet);
        foreach ($this->blocks as $b) {
            $this->writeBlock($sheet, $b);
        }

        $sheet->setSelectedCell('A1');
        $setup = $sheet->getPageSetup();
        $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $setup->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
    }

    private function writeHeader(Worksheet $sheet): void
    {
        $sheet->setCellValue('A1', $this->heading);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setARGB('FF09090B');
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->setCellValue('A2', (string) ($this->meta['subtitle'] ?? ''));
        $by = ! empty($this->meta['generated_by']) ? ' por '.$this->meta['generated_by'] : '';
        $sheet->setCellValue('A3', 'Generado el '.($this->meta['generated_at'] ?? '').$by);
        $filters = $this->filters
            ? 'Filtros: '.implode('   ·   ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($this->filters), $this->filters))
            : 'Sin filtros: incluye todos los registros que puedes consultar';
        $sheet->setCellValueExplicit('A4', $filters, DataType::TYPE_STRING);
        $sheet->getStyle('A2:A4')->getFont()->setSize(10)->getColor()->setARGB('FF71717A');
    }

    private function writeKpis(Worksheet $sheet): void
    {
        foreach (array_chunk($this->kpis, self::KPI_PER_ROW) as $chunkIndex => $chunk) {
            $top = 6 + $chunkIndex * 4;
            [$l, $v, $h] = [$top, $top + 1, $top + 2];
            $lastCol = chr(ord('A') + count($chunk) - 1);

            foreach ($chunk as $i => $k) {
                $col = chr(ord('A') + $i);
                $sheet->setCellValueExplicit("{$col}{$l}", (string) $k['label'], DataType::TYPE_STRING);
                $sheet->setCellValue("{$col}{$v}", $k['value']);
                $sheet->setCellValueExplicit("{$col}{$h}", (string) ($k['hint'] ?? ''), DataType::TYPE_STRING);
                $format = $k['format'] ?? Theme::INT;
                $sheet->getStyle("{$col}{$v}")->getNumberFormat()->setFormatCode($format === Theme::MONEY ? Theme::MONEY_KPI : $format);
                if (! empty($k['color'])) {
                    $sheet->getStyle("{$col}{$l}:{$col}{$h}")->getBorders()->getLeft()
                        ->setBorderStyle(Border::BORDER_THICK)->getColor()->setARGB('FF'.Theme::hex($k['color']));
                }
            }

            $sheet->getStyle("A{$l}:{$lastCol}{$h}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8F7FF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE4E4E7']]],
            ]);
            $sheet->getStyle("A{$l}:{$lastCol}{$l}")->getFont()->setSize(9)->setBold(true)->getColor()->setARGB('FF71717A');
            $sheet->getStyle("A{$l}:{$lastCol}{$l}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("A{$v}:{$lastCol}{$v}")->getFont()->setSize(14)->setBold(true)->getColor()->setARGB('FF09090B');
            // Montos de 8+ cifras se reducen en vez de mostrar «####».
            $sheet->getStyle("A{$v}:{$lastCol}{$v}")->getAlignment()->setShrinkToFit(true);
            $sheet->getStyle("A{$h}:{$lastCol}{$h}")->getFont()->setSize(8)->getColor()->setARGB('FF71717A');
            $sheet->getStyle("A{$h}:{$lastCol}{$h}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            for ($i = 1; $i < count($chunk); $i++) {
                $col = chr(ord('A') + $i);
                $border = $sheet->getStyle("{$col}{$l}:{$col}{$h}")->getBorders()->getLeft();
                if ($border->getBorderStyle() !== Border::BORDER_THICK) {
                    $border->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE4E4E7');
                }
            }
            $sheet->getRowDimension($l)->setRowHeight(24);
            $sheet->getRowDimension($v)->setRowHeight(26);
            $longHint = max(array_map(fn ($k) => mb_strlen((string) ($k['hint'] ?? '')), $chunk)) > 34;
            $sheet->getRowDimension($h)->setRowHeight($longHint ? 36 : 26);
        }
    }

    private function writeBlock(Worksheet $sheet, array $b): void
    {
        ['row' => $row, 'header' => $header, 'first' => $first, 'last' => $last, 'totalRow' => $total] = $b;
        $headers = $b['headers'];
        $formats = $b['formats'];
        $share = $b['share'] ?? null;
        if ($share !== null) {
            $headers[] = $b['shareLabel'] ?? '% del total';
            $formats[] = Theme::PCT;
        }
        $cols = count($headers);
        $lastCol = Coordinate::stringFromColumnIndex($cols);

        $sheet->setCellValue("A{$row}", $b['title']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13)->getColor()->setARGB('FF'.Theme::primary());
        $sheet->getRowDimension($row)->setRowHeight(22);
        if (! empty($b['note'])) {
            $sheet->setCellValueExplicit('A'.($row + 1), (string) $b['note'], DataType::TYPE_STRING);
            $sheet->getStyle('A'.($row + 1))->getFont()->setSize(9)->setItalic(true)->getColor()->setARGB('FF71717A');
        }

        foreach ($headers as $i => $h) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).$header, (string) $h, DataType::TYPE_STRING);
        }
        $sheet->getStyle("A{$header}:{$lastCol}{$header}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF27272A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        if ($cols > 1) {
            $sheet->getStyle("B{$header}:{$lastCol}{$header}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getRowDimension($header)->setRowHeight(18);

        if (! $b['rows']) {
            $sheet->setCellValue("A{$first}", $b['empty'] ?? 'Sin datos con los filtros aplicados');
            $sheet->getStyle("A{$first}")->getFont()->setItalic(true)->getColor()->setARGB('FF71717A');

            return;
        }

        $shareCol = $share !== null ? Coordinate::stringFromColumnIndex($share + 1) : null;
        foreach (array_values($b['rows']) as $i => $values) {
            $r = $first + $i;
            foreach (array_values($values) as $c => $value) {
                $cell = Coordinate::stringFromColumnIndex($c + 1).$r;
                if (is_string($value) && str_starts_with($value, '=')) {
                    $sheet->setCellValue($cell, str_replace('{r}', (string) $r, $value));
                } elseif ($c === 0 || is_string($value)) {
                    $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($cell, $value);
                }
                $format = $b['rowFormats'][$i][$c] ?? $formats[$c] ?? null;
                if ($format) {
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode($format);
                }
            }
            if ($shareCol) {
                $ref = $total ? "\${$shareCol}\${$total}" : "SUM(\${$shareCol}\${$first}:\${$shareCol}\${$last})";
                $sheet->setCellValue("{$lastCol}{$r}", "=IF({$ref}=0,0,{$shareCol}{$r}/{$ref})");
            }
            if ($i % 2 === 1) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFAFAFA');
            }
            if (! empty($b['colors'][$i])) {
                $sheet->getStyle("A{$r}")->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THICK)
                    ->getColor()->setARGB('FF'.Theme::hex($b['colors'][$i]));
            }
        }

        if ($shareCol) {
            $sheet->getStyle("{$lastCol}{$first}:{$lastCol}{$last}")->getNumberFormat()->setFormatCode(Theme::PCT);
        }
        $sheet->getStyle("A{$first}:{$lastCol}{$last}")->applyFromArray([
            'borders' => ['horizontal' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFF1F1F3']]],
        ]);
        $sheet->getStyle("A{$first}:A".($total ?? $last))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        if ($total) {
            $sheet->setCellValue("A{$total}", 'Total');
            for ($c = 2; $c <= $cols; $c++) {
                $col = Coordinate::stringFromColumnIndex($c);
                $summable = in_array($formats[$c - 1] ?? null, [Theme::INT, Theme::MONEY, Theme::PCT], true) && ! in_array($c - 1, $b['noTotal'] ?? [], true);
                if ($summable || ($share !== null && $c === $cols)) {
                    $sheet->setCellValue("{$col}{$total}", "=SUM({$col}{$first}:{$col}{$last})");
                    $sheet->getStyle("{$col}{$total}")->getNumberFormat()->setFormatCode($formats[$c - 1] ?? Theme::INT);
                }
            }
            $sheet->getStyle("A{$total}:{$lastCol}{$total}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF4F4F5']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFA1A1AA']]],
            ]);
        }
    }

    /** @return list<Chart> */
    public function charts(): array
    {
        $charts = [];
        foreach ($this->blocks as $b) {
            if ($b['chartSpec'] && $b['points'] > 0) {
                $charts[] = $this->chartFor($b);
            }
        }

        return $charts;
    }

    private function chartFor(array $b): Chart
    {
        $spec = $b['chartSpec'];
        $ref = "'".self::TITLE."'";
        $n = $b['points'];
        $firstP = $b['first'];
        $lastP = $firstP + $n - 1;
        $valueCols = (array) ($spec['values'] ?? $spec['value'] ?? 1);
        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$ref}!\$A\${$firstP}:\$A\${$lastP}", null, $n)];
        $palette = Theme::palette();

        $labels = [];
        $values = [];
        foreach ($valueCols as $s => $c) {
            $col = Coordinate::stringFromColumnIndex($c + 1);
            // Etiquetas de datos sin centavos para que quepan junto a la barra.
            $format = ($b['formats'][$c] ?? Theme::INT) === Theme::MONEY ? Theme::MONEY_KPI : ($b['formats'][$c] ?? Theme::INT);
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$ref}!\${$col}\${$b['header']}", null, 1);
            $color = $spec['type'] === 'donut'
                ? array_map(fn ($i) => Theme::hex($b['colors'][$i] ?? null, $palette[$i % count($palette)]), range(0, $n - 1))
                : (count($valueCols) > 1
                    ? Theme::hex($spec['colors'][$s] ?? null, $palette[$s % count($palette)])
                    : Theme::hex($spec['color'] ?? null, Theme::primary()));
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "{$ref}!\${$col}\${$firstP}:\${$col}\${$lastP}", $format, $n, [], null, $color);
        }

        $title = new Title($spec['title'] ?? $b['title']);
        $multi = count($values) > 1;

        if ($spec['type'] === 'donut') {
            $series = new DataSeries(DataSeries::TYPE_DONUTCHART, null, range(0, count($values) - 1), $labels, $categories, $values);
            $layout = new Layout;
            $layout->setShowVal(false)->setShowPercent(true)->setShowCatName(false)->setShowSerName(false)->setShowLeaderLines(false);
            $chart = new Chart($b['key'], $title, new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea($layout, [$series]), true, DataSeries::EMPTY_AS_GAP);
        } elseif ($spec['type'] === 'line') {
            $series = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, range(0, count($values) - 1), $labels, $categories, $values);
            $layout = new Layout;
            $layout->setShowVal($n <= 6)->setShowCatName(false)->setShowSerName(false)->setShowLegendKey(false)->setShowPercent(false);
            $layout->setNumFmtCode(($b['formats'][$valueCols[0]] ?? null) === Theme::MONEY ? Theme::MONEY_KPI : Theme::INT)->setNumFmtLinked(false);
            $yAxis = new Axis;
            $yAxis->setAxisNumberProperties($b['formats'][$valueCols[0]] === Theme::MONEY ? Theme::MONEY_KPI : Theme::INT);
            $chart = new Chart($b['key'], $title, $multi ? new Legend(Legend::POSITION_BOTTOM, null, false) : null, new PlotArea($layout, [$series]), true, DataSeries::EMPTY_AS_GAP, null, null, null, $yAxis);
        } else {
            $horizontal = $spec['type'] === 'bar';
            $series = new DataSeries(
                DataSeries::TYPE_BARCHART,
                ! empty($spec['stacked']) ? DataSeries::GROUPING_STACKED : DataSeries::GROUPING_CLUSTERED,
                range(0, count($values) - 1),
                $labels,
                $categories,
                $values,
                $horizontal ? DataSeries::DIRECTION_BAR : DataSeries::DIRECTION_COL,
            );
            $layout = new Layout;
            $layout->setShowVal($horizontal || $n <= 6)->setShowCatName(false)->setShowSerName(false)->setShowLegendKey(false)->setShowPercent(false);
            // Etiquetas sin centavos para que quepan junto a la barra.
            $layout->setNumFmtCode(($b['formats'][$valueCols[0]] ?? null) === Theme::MONEY ? Theme::MONEY_KPI : Theme::INT)->setNumFmtLinked(false);
            if (! empty($spec['stacked'])) {
                // En barras apiladas un segmento en cero no lleva etiqueta.
                $layout->setNumFmtCode('#,##0;-#,##0;;');
            }
            $xAxis = new Axis;
            if ($horizontal) {
                // Mayor valor arriba, igual que la tabla.
                $xAxis->setAxisOrientation('maxMin');
            }
            $yAxis = new Axis;
            $yAxis->setAxisNumberProperties(($b['formats'][$valueCols[0]] ?? null) === Theme::MONEY ? Theme::MONEY_KPI : Theme::INT);
            if ($horizontal) {
                // Las barras ya llevan su valor; el eje de valores solo estorba.
                $yAxis->setAxisOptionsProperties('none');
            }
            $chart = new Chart($b['key'], $title, $multi ? new Legend(Legend::POSITION_BOTTOM, null, false) : null, new PlotArea($layout, [$series]), true, DataSeries::EMPTY_AS_GAP, null, null, $xAxis, $yAxis);
        }

        $chart->setTopLeftPosition("H{$b['row']}");
        $chart->setBottomRightPosition('Q'.($b['row'] + self::CHART_ROWS - 1));

        return $chart;
    }
}
