<?php

namespace App\Exports\Core\Workbook;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Hoja «Filtros»: los filtros aplicados en texto y los datos del reporte. */
class FiltersSheet implements WithEvents, WithTitle
{
    /**
     * @param  array<string, string>  $filters  Filtros legibles (sin vacíos).
     * @param  array<string, string>  $details  Datos adicionales (Generado, Registros…).
     */
    public function __construct(private array $filters, private array $details = []) {}

    public function title(): string
    {
        return 'Filtros';
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
        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(70);

        $sheet->setCellValue('A1', 'Filtros aplicados');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);

        $row = 3;
        $row = $this->table($sheet, $row, ['Filtro', 'Valor'], $this->filters
            ?: ['Sin filtros' => 'Se exportaron todos los registros que puedes consultar.']);

        if ($this->details) {
            $row += 2;
            $sheet->setCellValue("A{$row}", 'Datos del reporte');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
            $this->table($sheet, $row + 1, ['Dato', 'Valor'], $this->details);
        }
    }

    private function table(Worksheet $sheet, int $row, array $headers, array $pairs): int
    {
        $sheet->fromArray([$headers], null, "A{$row}");
        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF27272A']],
        ]);
        $first = $row + 1;
        foreach ($pairs as $k => $v) {
            $row++;
            $sheet->setCellValueExplicit("A{$row}", (string) $k, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$row}", is_array($v) ? implode(', ', $v) : (string) $v, DataType::TYPE_STRING);
        }
        $sheet->getStyle("A{$first}:A{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$first}:B{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $hair = ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE4E4E7']];
        $sheet->getStyle("A{$first}:B{$row}")->applyFromArray(['borders' => ['inside' => $hair, 'bottom' => $hair]]);

        return $row;
    }
}
