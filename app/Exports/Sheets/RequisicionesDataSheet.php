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
            'Estatus',
            'Tipo',
            'Corporativo',
            'Sucursal',
            'Código sucursal',
            'Solicitante',
            'Proveedor',
            'RFC proveedor',
            'Concepto',
            'Observaciones',
            'Fecha de registro',
            'Fecha de solicitud',
            'Fecha de pago',
            'Descripción',
            'Cantidad',
            'Precio unitario',
            'Genera IVA',
            'Subtotal ítem',
            'IVA ítem',
            'Total ítem',
            'Subtotal requisición',
            'IVA requisición',
            'Total items req.',
            'Ajuste neto',
            'Total final req.',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 22,
            'C' => 14,
            'D' => 28,
            'E' => 28,
            'F' => 16,
            'G' => 28,
            'H' => 32,
            'I' => 18,
            'J' => 24,
            'K' => 44,
            'L' => 18,
            'M' => 18,
            'N' => 18,
            'O' => 44,
            'P' => 12,
            'Q' => 16,
            'R' => 12,
            'S' => 16,
            'T' => 16,
            'U' => 16,
            'V' => 20,
            'W' => 18,
            'X' => 22,
            'Y' => 16,
            'Z' => 22,
        ];
    }

    protected function columnFormats(): array
    {
        // S=subtotal_item, T=iva_item, U=total_item, V=subtotal_req, W=iva_req, X=total_items_req, Y=ajuste, Z=total_final
        $currency = '"$"#,##0.00';
        return [
            'Q' => '#,##0.00',  // P. unitario
            'S' => $currency,   // Subtotal ítem
            'T' => $currency,   // IVA ítem
            'U' => $currency,   // Total ítem
            'V' => $currency,   // Subtotal req
            'W' => $currency,   // IVA req
            'X' => $currency,   // Total items req
            'Y' => $currency,   // Ajuste neto
            'Z' => $currency,   // Total final
        ];
    }

    protected function mapRow(array $r): array
    {
        return [
            $r['folio']             ?? '',
            $r['estatus']           ?? '',
            $r['tipo']              ?? '',
            $r['corporativo']       ?? '',
            $r['sucursal']          ?? '',
            $r['sucursal_codigo']   ?? '',
            $r['solicitante']       ?? '',
            $r['proveedor']         ?? '',
            $r['proveedor_rfc']     ?? '',
            $r['concepto']          ?? '',
            $r['observaciones']     ?? '',
            $r['fecha_captura']     ?? '',
            $r['fecha_solicitud']   ?? '',
            $r['fecha_pago']        ?? '',
            $r['descripcion_item']  ?? '',
            $r['cantidad']          ?? '',
            $r['precio_unitario']   ?? '',
            $r['genera_iva']        ?? '',
            $r['subtotal_item']     ?? '',
            $r['iva_item']          ?? '',
            $r['total_item']        ?? '',
            $r['subtotal']          ?? '',
            $r['iva']               ?? '',
            $r['total']             ?? '',
            $r['ajustes_netos']     ?? '',
            $r['total_final']       ?? '',
        ];
    }
}
