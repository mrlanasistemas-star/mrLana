<?php

namespace App\Exports\Requisiciones;

use App\Exports\Sheets\RequisicionesDataSheet;
use App\Exports\Sheets\RequisicionesFiltersSheet;
use App\Exports\Sheets\RequisicionesListSheet;
use App\Exports\Sheets\RequisicionesSummarySheet;
use App\Support\Pdf\PdfAssets;
use App\Support\Reports\RequisicionesSummary;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;

/**
 * Libro de requisiciones: Resumen (indicadores, tablas y gráficas),
 * Requisiciones (una fila por requisición), Detalle (una fila por item y
 * ajuste) y Filtros.
 *
 * WithPreCalculateFormulas es necesario para las gráficas: PhpSpreadsheet solo
 * guarda los valores que muestran (caché de la serie) cuando precalcula; sin
 * eso las gráficas abren vacías y sin los colores por estatus.
 */
class RequisicionesExport implements WithMultipleSheets, WithPreCalculateFormulas
{
    private RequisicionesSummary $summary;

    private ?string $primaryColor = null;

    public function __construct(private array $rows, private array $filters, private array $meta, ?RequisicionesSummary $summary = null)
    {
        $this->summary = $summary ?? RequisicionesSummary::fromRows($rows);
        try {
            $this->primaryColor = PdfAssets::colors()['primary_color'] ?? null;
        } catch (\Throwable) {
            $this->primaryColor = null;
        }
    }

    public function sheets(): array
    {
        return [
            new RequisicionesSummarySheet($this->summary, $this->filters, $this->meta, $this->primaryColor),
            new RequisicionesListSheet($this->rows, $this->meta),
            new RequisicionesDataSheet($this->rows, [], ['title' => 'Detalle de items y ajustes', 'subtitle' => 'Una fila por item, por ajuste y por total efectuado de cada requisición'] + $this->meta),
            new RequisicionesFiltersSheet($this->filters, $this->meta),
        ];
    }
}
