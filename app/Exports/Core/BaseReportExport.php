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

    public function registerEvents(): array {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11);
                // Calcula dónde empieza la tabla (para estilos/freeze)
                $rowStart = 1;
                $rowStart += 4; // title/subtitle/generated/blank
                if (!empty($this->filters)) {
                    $rowStart += 1; // "Filtros"
                    $rowStart += count(array_filter($this->filters, fn($v) => $v !== null && $v !== ''));
                    $rowStart += 1; // blank
                }
                $tableHeaderRow = $rowStart + 1;
                $colCount = count($this->headings());
                $lastCol = chr(ord('A') + $colCount - 1);
                // Encabezado tabla
                $sheet->getStyle("A{$tableHeaderRow}:{$lastCol}{$tableHeaderRow}")
                    ->getFont()->setBold(true);
                $sheet->getStyle("A{$tableHeaderRow}:{$lastCol}{$tableHeaderRow}")
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF1F5F9');
                $lastRow = $tableHeaderRow + max(1, count($this->rows));
                // Bordes
                $sheet->getStyle("A{$tableHeaderRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                // Freeze + autofilter
                $sheet->freezePane("A" . ($tableHeaderRow + 1));
                $sheet->setAutoFilter("A{$tableHeaderRow}:{$lastCol}{$tableHeaderRow}");
                // Anchos
                foreach ($this->columnWidths() as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
                $sheet->getStyle("A{$tableHeaderRow}:{$lastCol}{$lastRow}")
                    ->getAlignment()->setWrapText(true);
            },
        ];
    }

}
