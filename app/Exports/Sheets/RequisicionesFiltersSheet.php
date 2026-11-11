<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Hoja «Filtros»: con qué filtros, orden y alcance se generó el archivo.
 */
class RequisicionesFiltersSheet implements FromArray, WithTitle, WithEvents
{
    /**
     * @param  array<string, string>  $filters
     */
    public function __construct(private array $filters, private array $meta = []) {}

    public function title(): string
    {
        return 'Filtros';
    }

    public function array(): array
    {
        $out = [
            ['Filtros aplicados'],
            ['Generado el '.($this->meta['generated_at'] ?? '').(! empty($this->meta['generated_by']) ? ' por '.$this->meta['generated_by'] : '')],
            [''],
            ['Filtro', 'Valor'],
        ];
        foreach ($this->filters as $k => $v) {
            $out[] = [$k, is_array($v) ? implode(', ', $v) : (string) $v];
        }
        if ($this->filters === []) {
            $out[] = ['Sin filtros', 'Todas las requisiciones visibles'];
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = 4 + max(1, count($this->filters));
                $sheet->setShowGridlines(false);
                $sheet->getColumnDimension('A')->setWidth(24);
                $sheet->getColumnDimension('B')->setWidth(60);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->getColor()->setARGB('FF71717A');
                $sheet->getStyle('A4:B4')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF27272A']],
                ]);
                $sheet->getStyle("A5:A{$last}")->getFont()->setBold(true);
                $sheet->getStyle("B5:B{$last}")->getAlignment()->setWrapText(true);
            },
        ];
    }
}
