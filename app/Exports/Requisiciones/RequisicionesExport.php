<?php

namespace App\Exports\Requisiciones;

use App\Exports\Sheets\RequisicionesDataSheet;
use App\Exports\Sheets\RequisicionesFiltersSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RequisicionesExport implements WithMultipleSheets {

    private array $rows;
    private array $filters;
    private array $meta;

    public function __construct(array $rows, array $filters, array $meta) {
        $this->rows    = $rows;
        $this->filters = $filters;
        $this->meta    = $meta;
    }

    public function sheets(): array {
        return [
            new RequisicionesDataSheet($this->rows, $this->filters, $this->meta),
            new RequisicionesFiltersSheet($this->filters, $this->meta),
        ];
    }

}
