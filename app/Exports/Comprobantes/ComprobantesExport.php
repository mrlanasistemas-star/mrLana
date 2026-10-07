<?php

namespace App\Exports\Comprobantes;

use App\Exports\Core\BaseReportExport;

class ComprobantesExport extends BaseReportExport
{
    protected function headings(): array
    {
        return ['ID', 'Requisición', 'Solicitante', 'Proveedor', 'Concepto', 'Corporativo', 'Tipo', 'Fecha', 'Monto', 'Estatus', 'Cargó', 'Revisó', 'Comentario de revisión', 'Archivo'];
    }

    protected function mapRow(array $r): array
    {
        $req = $r['requisicion'] ?? [];

        return [
            $r['id'], $req['folio'] ?? '', $req['solicitante'] ?? '', $req['proveedor'] ?? '', $req['concepto'] ?? '', $req['corporativo'] ?? '',
            $r['tipo_label'], $r['fecha_emision'] ?? substr((string) $r['created_at'], 0, 10), $r['monto'], $r['estatus_label'],
            $r['user_carga'] ?? '', $r['user_revision'] ?? '', $r['comentario_revision'] ?? '', $r['archivo_original'] ?? '',
        ];
    }

    protected function columnWidths(): array
    {
        return ['A' => 8, 'B' => 16, 'C' => 26, 'D' => 28, 'E' => 20, 'F' => 22, 'G' => 12, 'H' => 12, 'I' => 14, 'J' => 12, 'K' => 22, 'L' => 22, 'M' => 40, 'N' => 32];
    }

    protected function columnFormats(): array
    {
        return ['I' => '"$"#,##0.00'];
    }
}
