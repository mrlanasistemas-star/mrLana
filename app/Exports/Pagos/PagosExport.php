<?php

namespace App\Exports\Pagos;

use App\Exports\Core\BaseReportExport;

class PagosExport extends BaseReportExport
{
    protected function headings(): array
    {
        return ['ID', 'Requisición', 'Solicitante', 'Concepto', 'Corporativo', 'Beneficiario', 'Banco', 'Cuenta', 'Tipo de pago', 'Fecha de pago', 'Monto', 'Referencia', 'Registró', 'Autorizó', 'Archivo'];
    }

    protected function mapRow(array $r): array
    {
        $req = $r['requisicion'] ?? [];

        return [
            $r['id'], $req['folio'] ?? '', $req['solicitante'] ?? '', $req['concepto'] ?? '', $req['corporativo'] ?? '',
            $r['beneficiario'] ?? '', $r['banco'] ?? '', $r['cuenta'] ?? '', $r['tipo_label'], $r['fecha_pago'] ?? '', $r['monto'],
            $r['referencia'] ?? '', $r['user_carga'] ?? '', $req['autorizo'] ?? 'No registrado', $r['archivo_original'] ?? '',
        ];
    }

    protected function columnWidths(): array
    {
        return ['A' => 8, 'B' => 16, 'C' => 26, 'D' => 20, 'E' => 22, 'F' => 30, 'G' => 18, 'H' => 14, 'I' => 14, 'J' => 13, 'K' => 14, 'L' => 22, 'M' => 22, 'N' => 22, 'O' => 32];
    }

    protected function columnFormats(): array
    {
        return ['K' => '"$"#,##0.00'];
    }
}
