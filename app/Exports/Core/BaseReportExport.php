<?php

namespace App\Exports\Core;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

abstract class BaseReportExport implements FromArray, WithEvents {

    protected array $meta = [];
    protected array $filters = [];
    protected array $rows = [];

    public function __construct(array $rows, array $filters = [], array $meta = []) {
        $this->rows = $rows;
        $this->filters = $filters;
        $this->meta = $meta;
    }

    abstract protected function headings(): array;
    abstract protected function mapRow(array $row): array;
    abstract protected function columnWidths(): array;

    public function array(): array {
        $out[] = [$this->meta['title'] ?? 'Reporte'];
        $out[] = [$this->meta['subtitle'] ?? ''];
        $out[] = ['Generado:', $this->meta['generated_at'] ?? now()->format('Y-m-d H:i')];
        $out[] = [''];
        $out[] = [''];
        if (!empty($this->filters)) {
            $out[] = ['Filtros'];
            foreach ($this->filters as $k => $v) {
                if ($v === null || $v === '') continue;
                $out[] = [$k, is_array($v) ? implode(', ', $v) : (string) $v];
            }
            $out[] = [''];
        }
        $out[] = $this->headings();
        foreach ($this->rows as $r) {
            $out[] = $this->mapRow($r);
        }
        return $out;
    }

    protected function columnFormats(): array
    {
        return [];
    }

    public function registerEvents(): array {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Título y subtítulo
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->setColor(
                    (new \PhpOffice\PhpSpreadsheet\Style\Color())->setARGB('FF0F172A')
                );
                $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->setColor(
                    (new \PhpOffice\PhpSpreadsheet\Style\Color())->setARGB('FF64748B')
                );

                // Calcula fila de inicio de la tabla
                $rowStart = 1;
                $rowStart += 4; // title/subtitle/generated/blank/blank
                if (!empty($this->filters)) {
                    $rowStart += 1; // "Filtros"
                    $rowStart += count(array_filter($this->filters, fn($v) => $v !== null && $v !== ''));
                    $rowStart += 1; // blank
                }
                $tableHeaderRow = $rowStart + 1;
                $colCount  = count($this->headings());
                $lastCol   = chr(ord('A') + $colCount - 1);
                $lastRow   = $tableHeaderRow + max(1, count($this->rows));

                // Header de tabla: fondo oscuro, texto blanco
                $headerRange = "A{$tableHeaderRow}:{$lastCol}{$tableHeaderRow}";
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['argb' => 'FFF1F5F9'],
                        'size'  => 9,
                    ],
                    'fill' => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF1E293B'],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        'wrapText'   => false,
                    ],
                ]);
                $sheet->getRowDimension($tableHeaderRow)->setRowHeight(18);

                // Bordes para todo el rango de datos
                $dataRange = "A{$tableHeaderRow}:{$lastCol}{$lastRow}";
                $sheet->getStyle($dataRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color'       => ['argb' => 'FFE2E8F0'],
                        ],
                    ],
                    'alignment' => ['wrapText' => true, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP],
                ]);

                // Filas pares con fondo suave
                for ($row = $tableHeaderRow + 1; $row <= $lastRow; $row++) {
                    if ($row % 2 === 0) {
                        $sheet->getStyle("A{$row}:{$lastCol}{$row}")
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFF8FAFC');
                    }
                }

                // Freeze + autoFilter
                $sheet->freezePane("A" . ($tableHeaderRow + 1));
                $sheet->setAutoFilter("A{$tableHeaderRow}:{$lastCol}{$tableHeaderRow}");

                // Anchos de columna
                foreach ($this->columnWidths() as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // Formatos de celda por columna (moneda, fecha, etc.)
                foreach ($this->columnFormats() as $col => $format) {
                    $dataRows = $lastRow - $tableHeaderRow;
                    if ($dataRows <= 0) continue;
                    $sheet->getStyle("{$col}" . ($tableHeaderRow + 1) . ":{$col}{$lastRow}")
                        ->getNumberFormat()->setFormatCode($format);
                }
            },
        ];
    }

}
