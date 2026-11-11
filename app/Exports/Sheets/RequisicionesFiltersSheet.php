<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RequisicionesFiltersSheet implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    /** @var array<string, string> */
    private array $filters;

    /**
     * @param array<string, string> $filters
     */
    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'Filtros';
    }

    public function headings(): array
    {
        return ['Filtro', 'Valor'];
    }

    public function array(): array
    {
        $out = [];
        foreach ($this->filters as $k => $v) {
            $out[] = [$k, $v];
        }
        return $out;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
