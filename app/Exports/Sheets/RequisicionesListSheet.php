<?php

namespace App\Exports\Sheets;

use App\Services\Dashboard\DashboardDataService;
use App\Support\Reports\RequisicionesSummary;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja «Requisiciones»: una fila por requisición, en el orden del listado,
 * con fechas reales de Excel, moneda, autofiltro y encabezado congelado. La
 * fila de totales usa SUBTOTAL para respetar lo que se filtre en Excel.
 */
class RequisicionesListSheet implements WithTitle, WithEvents
{
    private const HEADER_ROW = 4;

    private const MONEY = '"$"#,##0.00;-"$"#,##0.00';

    /** [encabezado, ancho, tipo] */
    private const COLUMNS = [
        ['Folio', 18, 'text'],
        ['Estatus', 22, 'text'],
        ['Fecha de registro', 17, 'datetime'],
        ['Fecha de solicitud', 14, 'date'],
        ['Fecha de pago', 14, 'date'],
        ['Corporativo', 24, 'text'],
        ['Sucursal', 22, 'text'],
        ['Solicitante', 28, 'text'],
        ['Concepto', 22, 'text'],
        ['Proveedor', 30, 'text'],
        ['RFC del proveedor', 16, 'text'],
        ['Items', 8, 'int'],
        ['Total de items', 15, 'money'],
        ['Ajustes', 13, 'money'],
        ['Monto', 15, 'money'],
        ['Pagado', 15, 'money'],
        ['Comprobado', 15, 'money'],
        ['Por pagar', 15, 'money'],
        ['Por comprobar', 15, 'money'],
        ['Observaciones', 44, 'wrap'],
    ];

    /** @param list<array<string, mixed>> $rows */
    public function __construct(private array $rows, private array $meta) {}

    public function title(): string
    {
        return 'Requisiciones';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->write($event->sheet->getDelegate()),
        ];
    }

    private function write(Worksheet $sheet): void
    {
        $h = self::HEADER_ROW;
        $cols = count(self::COLUMNS);
        $lastCol = self::col($cols);
        $first = $h + 1;
        $n = count($this->rows);
        $last = $h + max(1, $n);
        $totalRow = $last + 1;

        $sheet->setShowGridlines(false);
        $sheet->setCellValue('A1', 'Requisiciones');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF09090B');
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->setCellValue('A2', number_format($n).' '.($n === 1 ? 'requisición' : 'requisiciones').' en el orden del listado · '.($this->meta['subtitle'] ?? ''));
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setARGB('FF71717A');

        foreach (self::COLUMNS as $i => [$label, $width]) {
            $c = self::col($i + 1);
            $sheet->setCellValue("{$c}{$h}", $label);
            $sheet->getColumnDimension($c)->setWidth($width);
        }
        $sheet->getStyle("A{$h}:{$lastCol}{$h}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF27272A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($h)->setRowHeight(22);

        foreach (array_values($this->rows) as $i => $r) {
            $row = $first + $i;
            $values = [
                $r['folio'],
                DashboardDataService::STATUS_LABELS[$r['estatus']] ?? $r['estatus'],
                self::excelDate($r['fecha_captura'] ?? null),
                self::excelDate($r['fecha_solicitud'] ?? null),
                self::excelDate($r['fecha_pago'] ?? null),
                $r['corporativo'],
                $r['sucursal'],
                $r['solicitante'],
                $r['concepto'],
                $r['proveedor'],
                $r['proveedor_rfc'],
                count($r['items'] ?? []),
                (float) $r['total_items'],
                round((float) $r['ajustes_aplicados'] + (float) $r['otras_diferencias'], 2),
                (float) $r['total_efectuado'],
                (float) $r['pagado'],
                (float) $r['comprobado'],
                RequisicionesSummary::pendientePago($r),
                RequisicionesSummary::pendienteComprobar($r),
                $r['observaciones'],
            ];
            foreach ($values as $j => $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $cell = self::col($j + 1).$row;
                if (self::COLUMNS[$j][2] === 'text' || self::COLUMNS[$j][2] === 'wrap') {
                    $sheet->setCellValueExplicit($cell, (string) $v, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($cell, $v);
                }
            }
            if ($i % 2 === 1) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFAFAFA');
            }
        }

        // Formatos por tipo de columna.
        foreach (self::COLUMNS as $i => [, , $type]) {
            $c = self::col($i + 1);
            $range = "{$c}{$first}:{$c}{$totalRow}";
            match ($type) {
                'money' => $sheet->getStyle($range)->getNumberFormat()->setFormatCode(self::MONEY),
                'date' => $sheet->getStyle($range)->getNumberFormat()->setFormatCode('dd/mm/yyyy'),
                'datetime' => $sheet->getStyle($range)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm'),
                'int' => $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0'),
                'wrap' => $sheet->getStyle($range)->getAlignment()->setWrapText(true),
                default => null,
            };
            if (in_array($type, ['money', 'int'], true)) {
                $sheet->getStyle("{$c}{$h}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }
        $sheet->getStyle("A{$first}:A{$last}")->getFont()->setBold(true);
        $sheet->getStyle("A{$first}:{$lastCol}{$totalRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("A{$first}:{$lastCol}{$last}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB('FFE4E4E7');

        // Totales (SUBTOTAL 109 ignora filas ocultas por el autofiltro).
        $sheet->setCellValue("A{$totalRow}", 'Total');
        $sheet->setCellValue("B{$totalRow}", "=SUBTOTAL(103,A{$first}:A{$last})&\" requisiciones\"");
        foreach (self::COLUMNS as $i => [, , $type]) {
            if ($type === 'money' || $type === 'int') {
                $c = self::col($i + 1);
                $sheet->setCellValue("{$c}{$totalRow}", "=SUBTOTAL(109,{$c}{$first}:{$c}{$last})");
            }
        }
        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF4F4F5']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFA1A1AA']]],
        ]);

        $sheet->setAutoFilter("A{$h}:{$lastCol}{$last}");
        $sheet->freezePane('B'.($h + 1));
        $sheet->setSelectedCell('A1');

        $setup = $sheet->getPageSetup();
        $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $setup->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd($h, $h);
    }

    private static function excelDate(?string $value): ?float
    {
        if (empty($value)) {
            return null;
        }
        try {
            return ExcelDate::PHPToExcel(Carbon::parse($value));
        } catch (\Throwable) {
            return null;
        }
    }

    private static function col(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }
}
