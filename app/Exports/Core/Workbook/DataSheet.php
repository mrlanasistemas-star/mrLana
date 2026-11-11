<?php

namespace App\Exports\Core\Workbook;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja de datos: una fila por registro con encabezado congelado, autofiltro,
 * fechas y montos reales de Excel y una fila de totales con SUBTOTAL, que
 * se recalcula con lo que quede visible al filtrar.
 *
 * Columna: ['label', 'value' => clave|callable, 'type' => text|int|money|date|datetime,
 *           'width' => float, 'total' => bool, 'wrap' => bool, 'colors' => [valor => HEX]]
 */
class DataSheet implements WithEvents, WithTitle
{
    private const HEADER_ROW = 4;

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        private string $sheetTitle,
        private string $heading,
        private array $columns,
        private array $rows,
        private string $noun = 'registros',
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
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
        $h = self::HEADER_ROW;
        $first = $h + 1;
        $count = count($this->rows);
        $last = $h + max(1, $count);
        $lastCol = Coordinate::stringFromColumnIndex(count($this->columns));

        $sheet->setCellValue('A1', $this->heading);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setARGB('FF09090B');
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->setCellValue('A2', $count
            ? number_format($count).' '.$this->noun.'. La fila de totales se recalcula al filtrar con las flechas del encabezado.'
            : 'Sin '.$this->noun.' con los filtros aplicados.');
        $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setARGB('FF71717A');

        foreach ($this->columns as $i => $col) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValueExplicit("{$letter}{$h}", (string) $col['label'], DataType::TYPE_STRING);
            $sheet->getColumnDimension($letter)->setWidth($col['width'] ?? 16);
        }
        $sheet->getStyle("A{$h}:{$lastCol}{$h}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF27272A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($h)->setRowHeight(22);

        foreach (array_values($this->rows) as $i => $row) {
            $r = $first + $i;
            foreach ($this->columns as $c => $col) {
                $cell = Coordinate::stringFromColumnIndex($c + 1).$r;
                $this->setValue($sheet, $cell, $col, $this->valueOf($col, $row));
            }
        }

        foreach ($this->columns as $c => $col) {
            $letter = Coordinate::stringFromColumnIndex($c + 1);
            $range = "{$letter}{$first}:{$letter}{$last}";
            $type = $col['type'] ?? 'text';
            $format = match ($type) {
                'money' => Theme::MONEY,
                'int' => Theme::INT,
                'date' => Theme::DATE,
                'datetime' => Theme::DATETIME,
                default => null,
            };
            if ($format) {
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode($format);
            }
            if (in_array($type, ['date', 'datetime'], true)) {
                $sheet->getStyle("{$letter}{$h}:{$letter}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            if (in_array($type, ['money', 'int'], true)) {
                $sheet->getStyle("{$letter}{$h}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            if (! empty($col['wrap'])) {
                $sheet->getStyle($range)->getAlignment()->setWrapText(true);
            }
            if (! empty($col['colors']) && $count) {
                $this->statusColors($sheet, $range, $col['colors']);
            }
        }

        if ($count === 0) {
            $sheet->setCellValue("A{$first}", 'Sin '.$this->noun.' con los filtros aplicados');
            $sheet->getStyle("A{$first}")->getFont()->setItalic(true)->getColor()->setARGB('FF71717A');
        }

        $sheet->getStyle("A{$h}:{$lastCol}{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $hair = ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE4E4E7']];
        $sheet->getStyle("A{$first}:{$lastCol}{$last}")->applyFromArray(['borders' => ['inside' => $hair, 'bottom' => $hair]]);

        // Filas alternas con formato condicional: se mantiene al ordenar o filtrar.
        if ($count) {
            $zebra = new Conditional;
            $zebra->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('MOD(ROW(),2)=0');
            $zebra->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
            $zebra->getStyle()->getFill()->getEndColor()->setARGB('FFF8FAFC');
            $sheet->getStyle("A{$first}:{$lastCol}{$last}")->setConditionalStyles([$zebra]);
            $this->writeTotals($sheet, $first, $last, $lastCol);
        }

        $sheet->freezePane('B'.$first);
        $sheet->setAutoFilter("A{$h}:{$lastCol}{$last}");
        $sheet->setSelectedCell('A'.$first);

        $setup = $sheet->getPageSetup();
        $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $setup->setFitToWidth(1)->setFitToHeight(0);
        $setup->setRowsToRepeatAtTopByStartAndEnd($h, $h);
    }

    private function writeTotals(Worksheet $sheet, int $first, int $last, string $lastCol): void
    {
        $t = $last + 1;
        // «Total: n» con n = filas visibles (cambia al filtrar).
        $sheet->setCellValue("A{$t}", "=\"Total: \"&TEXT(SUBTOTAL(103,A{$first}:A{$last}),\"#,##0\")");
        foreach ($this->columns as $c => $col) {
            if (empty($col['total']) || $c === 0) {
                continue;
            }
            $letter = Coordinate::stringFromColumnIndex($c + 1);
            $sheet->setCellValue("{$letter}{$t}", "=SUBTOTAL(109,{$letter}{$first}:{$letter}{$last})");
            $sheet->getStyle("{$letter}{$t}")->getNumberFormat()->setFormatCode(($col['type'] ?? '') === 'money' ? Theme::MONEY : Theme::INT);
        }
        $sheet->getStyle("A{$t}:{$lastCol}{$t}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF4F4F5']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF71717A']]],
        ]);
        $sheet->getRowDimension($t)->setRowHeight(18);
    }

    private function valueOf(array $col, array $row): mixed
    {
        $v = $col['value'] ?? null;
        if (is_callable($v) && ! is_string($v)) {
            return $v($row);
        }

        return data_get($row, (string) $v);
    }

    private function setValue(Worksheet $sheet, string $cell, array $col, mixed $value): void
    {
        $type = $col['type'] ?? 'text';
        if ($value === null || $value === '' || $value === '—') {
            $sheet->setCellValueExplicit($cell, '', DataType::TYPE_STRING);

            return;
        }

        switch ($type) {
            case 'money':
            case 'int':
                is_numeric($value)
                    ? $sheet->setCellValueExplicit($cell, $type === 'int' ? (int) $value : round((float) $value, 2), DataType::TYPE_NUMERIC)
                    : $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                break;
            case 'date':
            case 'datetime':
                $d = Groups::date($value);
                $d
                    ? $sheet->setCellValueExplicit($cell, ExcelDate::dateTimeToExcel($type === 'date' ? $d->startOfDay() : $d), DataType::TYPE_NUMERIC)
                    : $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                break;
            default:
                // Texto explícito: RFC, CLABE o folios no se convierten en número.
                $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
        }
    }

    /** Colorea el texto según su valor (estatus) con formato condicional. */
    private function statusColors(Worksheet $sheet, string $range, array $colors): void
    {
        $style = $sheet->getStyle($range);
        $conditionals = $style->getConditionalStyles();
        foreach ($colors as $text => $hex) {
            $cond = new Conditional;
            $cond->setConditionType(Conditional::CONDITION_CONTAINSTEXT)
                ->setOperatorType(Conditional::OPERATOR_BEGINSWITH)
                ->setText((string) $text);
            $cond->getStyle()->getFont()->setBold(true)->getColor()->setARGB('FF'.Theme::hex($hex));
            $conditionals[] = $cond;
        }
        $style->setConditionalStyles($conditionals);
    }
}
