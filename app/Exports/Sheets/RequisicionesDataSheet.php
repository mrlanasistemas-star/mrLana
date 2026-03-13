<?php

namespace App\Exports\Sheets;

use App\Exports\Core\BaseReportExport;

class RequisicionesDataSheet extends BaseReportExport
{
    protected array $rows;
    protected array $filters;

    public function __construct(array $rows, array $filters, array $meta)
    {
        parent::__construct($rows, $filters, $meta);
        $this->rows    = $rows;
        $this->filters = $filters;
    }

    public function headings(): array
    {
        return [
            'Folio',
            'Proveedor',
            'Concepto',
            'Descripción',
            'Cantidad',
            'P. unitario',
            'IVA ítem',
            'Total ítem',
            'Subtotal requisición',
            'IVA requisición',
            'Total items requisición',
            'Ajuste neto',
            'Total final requisición',
            'Fecha captura',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, // Folio
            'B' => 28, // Proveedor
            'C' => 22, // Concepto
            'D' => 40, // Descripción
            'E' => 12, // Cantidad
            'F' => 16, // P. unitario
            'G' => 14, // IVA ítem
            'H' => 16, // Total ítem
            'I' => 18, // Subtotal requisición
            'J' => 18, // IVA requisición
            'K' => 20, // Total items requisición
            'L' => 16, // Ajuste neto
            'M' => 20, // Total final requisición
            'N' => 18, // Fecha captura
        ];
    }

    protected function mapRow(array $r): array
    {
        return [
            $r['folio']             ?? '',
            $r['proveedor']         ?? '',
            $r['concepto']          ?? '',
            $r['descripcion_item']  ?? '',
            $r['cantidad']          ?? '',
            $r['precio_unitario']   ?? '',
            $r['iva_item']          ?? '',
            $r['total_item']        ?? '',
            $r['subtotal']          ?? '',
            $r['iva']               ?? '',
            $r['total']             ?? '',
            $r['ajustes_netos']     ?? '',
            $r['total_final']       ?? '',
            $r['fecha_captura']     ?? '',
        ];
    }
}
