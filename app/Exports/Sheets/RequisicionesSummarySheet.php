<?php

namespace App\Exports\Sheets;

use App\Support\Reports\RequisicionesSummary;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
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
 * Hoja «Resumen»: indicadores generales y una tabla por estatus,
 * solicitante, concepto, proveedor, sucursal y mes, cada una con su gráfica
 * nativa de Excel a la derecha (H:P). Las gráficas leen las celdas de la
 * propia tabla, así que la posición de cada bloque se calcula una sola vez en
 * el constructor y se usa tanto para escribir como para graficar.
 */
class RequisicionesSummarySheet implements WithTitle, WithEvents, WithCharts
{
    public const TITLE = 'Resumen';

    private const MONEY = '"$"#,##0.00;-"$"#,##0.00';

    private const MONEY_KPI = '"$"#,##0;-"$"#,##0';

    private const PCT = '0.0%';

    private const CHART_ROWS = 17;

    /** @var list<array<string, mixed>> Bloques con su fila inicial, filas de datos y fila de total. */
    private array $blocks = [];

    private string $primary;

    public function __construct(
        private RequisicionesSummary $summary,
        private array $filters,
        private array $meta,
        ?string $primaryColor = null,
    ) {
        $this->primary = strtoupper(ltrim($primaryColor ?: '#6419FF', '#'));
        $this->plan();
    }

    public function title(): string
    {
        return self::TITLE;
    }

    private function plan(): void
    {
        $s = $this->summary;
        $defs = [
            ['key' => 'estatus', 'title' => 'Por estatus', 'label' => 'Estatus', 'groups' => $s->estatus, 'chart' => 'donut', 'chartTitle' => 'Requisiciones por estatus'],
            ['key' => 'solicitantes', 'title' => 'Por solicitante', 'label' => 'Solicitante', 'groups' => $s->solicitantes, 'chart' => 'bar', 'chartTitle' => 'Solicitantes con mayor monto'],
            ['key' => 'conceptos', 'title' => 'Por concepto', 'label' => 'Concepto', 'groups' => $s->conceptos, 'chart' => 'bar', 'chartTitle' => 'Conceptos principales'],
            ['key' => 'proveedores', 'title' => 'Por proveedor', 'label' => 'Proveedor', 'groups' => $s->proveedores, 'chart' => 'bar', 'chartTitle' => 'Proveedores principales'],
            ['key' => 'sucursales', 'title' => 'Por sucursal', 'label' => 'Sucursal', 'groups' => $s->sucursales, 'chart' => 'bar', 'chartTitle' => 'Monto por sucursal'],
        ];
        if ($s->spansMonths()) {
            $defs[] = ['key' => 'meses', 'title' => 'Por mes de registro', 'label' => 'Mes', 'groups' => $s->meses, 'chart' => 'column', 'chartTitle' => 'Monto por mes de registro'];
        }

        $row = 11;
        foreach ($defs as $d) {
            $n = max(1, count($d['groups']));
            $header = $row + 1;
            $first = $header + 1;
            $last = $first + $n - 1;
            $total = $last + 1;
            $chartPoints = $d['key'] === 'meses' || $d['key'] === 'estatus' ? $n : min($n, 10);

            $this->blocks[] = $d + compact('row', 'header', 'first', 'last', 'total', 'chartPoints');
            $row = max($total, $row + self::CHART_ROWS) + 3;
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
        $t = $this->summary->totals;
        $sheet->setShowGridlines(false);
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        foreach (['A' => 34, 'B' => 18, 'C' => 19, 'D' => 19, 'E' => 19, 'F' => 19, 'G' => 3] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        foreach (range('H', 'P') as $col) {
            $sheet->getColumnDimension($col)->setWidth(11);
        }

        // Encabezado
        $sheet->setCellValue('A1', 'Resumen de requisiciones');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setARGB('FF09090B');
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->setCellValue('A2', (string) ($this->meta['subtitle'] ?? ''));
        $generado = 'Generado el '.($this->meta['generated_at'] ?? '').(! empty($this->meta['generated_by']) ? ' por '.$this->meta['generated_by'] : '');
        $sheet->setCellValue('A3', $generado);
        $sheet->setCellValue('A4', $this->filters
            ? implode('   ·   ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($this->filters), $this->filters))
            : 'Sin filtros: todas las requisiciones visibles');
        $sheet->getStyle('A2:A4')->getFont()->setSize(10)->getColor()->setARGB('FF71717A');

        // Indicadores (fila 6 etiqueta, 7 valor, 8 nota)
        $kpis = [
            ['Requisiciones', $t['count'], '#,##0', $t['solicitantes'].' solicitantes · '.$t['proveedores'].' proveedores'],
            ['Monto solicitado', $t['monto'], self::MONEY_KPI, 'Promedio $'.number_format($t['promedio'], 2).' por requisición'],
            ['Pagado', $t['pagado'], self::MONEY, number_format($t['pct_pagado'], 1).'% del monto solicitado'],
            ['Comprobado', $t['comprobado'], self::MONEY, number_format($t['pct_comprobado'], 1).'% de lo pagado'],
            ['Pendiente de pago', $t['pendiente_pago'], self::MONEY, 'Por pagar en requisiciones vigentes'],
            ['Pendiente de comprobar', $t['pendiente_comprobar'], self::MONEY, 'Pagado sin comprobante aprobado'],
        ];
        foreach ($kpis as $i => [$label, $value, $format, $hint]) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue("{$col}6", $label);
            $sheet->setCellValue("{$col}7", $value);
            $sheet->setCellValue("{$col}8", $hint);
            // Sin centavos en los indicadores para que quepan a tamaño grande.
            $sheet->getStyle("{$col}7")->getNumberFormat()->setFormatCode($format === '#,##0' ? $format : self::MONEY_KPI);
        }
        $sheet->getStyle('A6:F8')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8F7FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE4E4E7']]],
        ]);
        $sheet->getStyle('A6:F6')->getFont()->setSize(9)->setBold(true)->getColor()->setARGB('FF71717A');
        $sheet->getStyle('A7:F7')->getFont()->setSize(14)->setBold(true)->getColor()->setARGB('FF09090B');
        $sheet->getStyle('A6:F6')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A8:F8')->getFont()->setSize(8)->getColor()->setARGB('FF71717A');
        $sheet->getStyle('A8:F8')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('A7:F7')->getBorders()->getTop()->setBorderStyle(Border::BORDER_NONE);
        foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
            $next = chr(ord($col) + 1);
            $sheet->getStyle("{$next}6:{$next}8")->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE4E4E7');
        }
        $sheet->getRowDimension(6)->setRowHeight(20);
        $sheet->getRowDimension(7)->setRowHeight(26);
        $sheet->getRowDimension(8)->setRowHeight(26);

        foreach ($this->blocks as $b) {
            $this->writeBlock($sheet, $b);
        }

        $sheet->setSelectedCell('A1');

        $setup = $sheet->getPageSetup();
        $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $setup->setFitToWidth(1)->setFitToHeight(0);
    }

    private function writeBlock(Worksheet $sheet, array $b): void
    {
        ['row' => $row, 'header' => $header, 'first' => $first, 'last' => $last, 'total' => $total] = $b;

        $sheet->setCellValue("A{$row}", $b['title']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13)->getColor()->setARGB('FF'.$this->primary);
        $sheet->getRowDimension($row)->setRowHeight(22);

        $sheet->fromArray([[$b['label'], 'Requisiciones', 'Monto', 'Pagado', 'Comprobado', '% del monto']], null, "A{$header}");
        $sheet->getStyle("A{$header}:F{$header}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF27272A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("B{$header}:F{$header}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($header)->setRowHeight(18);

        $groups = $b['groups'] ?: [['name' => 'Sin datos', 'count' => 0, 'total' => 0, 'pagado' => 0, 'comprobado' => 0, 'label' => 'Sin datos']];
        foreach (array_values($groups) as $i => $g) {
            $r = $first + $i;
            $name = $b['key'] === 'meses' ? ($g['label'] ?? $g['name']) : $g['name'];
            $sheet->setCellValueExplicit("A{$r}", (string) $name, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("B{$r}", (int) $g['count']);
            $sheet->setCellValue("C{$r}", (float) $g['total']);
            $sheet->setCellValue("D{$r}", (float) $g['pagado']);
            $sheet->setCellValue("E{$r}", (float) $g['comprobado']);
            $sheet->setCellValue("F{$r}", "=IF(\$C\${$total}=0,0,C{$r}/\$C\${$total})");
            if ($i % 2 === 1) {
                $sheet->getStyle("A{$r}:F{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFAFAFA');
            }
            if ($b['key'] === 'estatus' && ! empty($g['color'])) {
                $sheet->getStyle("A{$r}")->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THICK)
                    ->getColor()->setARGB('FF'.strtoupper(ltrim($g['color'], '#')));
            }
        }

        $sheet->setCellValue("A{$total}", 'Total');
        foreach (['B', 'C', 'D', 'E', 'F'] as $col) {
            $sheet->setCellValue("{$col}{$total}", "=SUM({$col}{$first}:{$col}{$last})");
        }
        $sheet->getStyle("A{$total}:F{$total}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF4F4F5']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFA1A1AA']]],
        ]);

        $sheet->getStyle("A{$first}:F{$last}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB('FFE4E4E7');
        $sheet->getStyle("B{$first}:B{$total}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("C{$first}:E{$total}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("F{$first}:F{$total}")->getNumberFormat()->setFormatCode(self::PCT);
        $sheet->getStyle("A{$first}:A{$total}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    }

    /** @return list<Chart> */
    public function charts(): array
    {
        $charts = [];
        foreach ($this->blocks as $b) {
            if (! $b['groups']) {
                continue;
            }
            $charts[] = $this->chartFor($b);
        }

        return $charts;
    }

    private function chartFor(array $b): Chart
    {
        $sheetRef = "'".self::TITLE."'";
        $firstP = $b['first'];
        $lastP = $b['first'] + $b['chartPoints'] - 1;
        $n = $b['chartPoints'];
        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$sheetRef}!\$A\${$firstP}:\$A\${$lastP}", null, $n)];
        $labelCell = $b['key'] === 'estatus' ? 'B' : 'C';
        $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$sheetRef}!\${$labelCell}\${$b['header']}", null, 1)];

        $title = new Title($b['chartTitle']);

        if ($b['chart'] === 'donut') {
            $colors = array_map(fn ($g) => strtoupper(ltrim($g['color'] ?? '#A1A1AA', '#')), array_slice($b['groups'], 0, $n));
            $values = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "{$sheetRef}!\$B\${$firstP}:\$B\${$lastP}", '#,##0', $n, [], null, $colors)];
            $series = new DataSeries(DataSeries::TYPE_DONUTCHART, null, range(0, 0), $labels, $categories, $values);
            $layout = new Layout;
            $layout->setShowVal(false)->setShowPercent(true)->setShowCatName(false)->setShowLeaderLines(false);
            $chart = new Chart('chart_'.$b['key'], $title, new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea($layout, [$series]), true, DataSeries::EMPTY_AS_GAP);
        } else {
            $values = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "{$sheetRef}!\$C\${$firstP}:\$C\${$lastP}", self::MONEY, $n, [], null, $this->primary)];
            $horizontal = $b['chart'] === 'bar';
            $series = new DataSeries(
                DataSeries::TYPE_BARCHART,
                DataSeries::GROUPING_CLUSTERED,
                range(0, 0),
                $labels,
                $categories,
                $values,
                $horizontal ? DataSeries::DIRECTION_BAR : DataSeries::DIRECTION_COL,
            );
            $layout = new Layout;
            $layout->setShowVal(true)->setShowCatName(false)->setShowSerName(false)->setShowLegendKey(false)->setShowPercent(false);
            $xAxis = new Axis;
            if ($horizontal) {
                // Mayor monto arriba, igual que la tabla.
                $xAxis->setAxisOrientation('maxMin');
            }
            $yAxis = new Axis;
            $yAxis->setAxisNumberProperties('"$"#,##0');
            if ($horizontal) {
                // Las barras ya llevan su monto; el eje de valores solo estorba.
                $yAxis->setAxisOptionsProperties('none');
            }
            $chart = new Chart('chart_'.$b['key'], $title, null, new PlotArea($layout, [$series]), true, DataSeries::EMPTY_AS_GAP, null, null, $xAxis, $yAxis);
        }

        $top = $b['row'];
        $chart->setTopLeftPosition("H{$top}");
        $chart->setBottomRightPosition('P'.($top + self::CHART_ROWS - 1));

        return $chart;
    }
}
