<?php

namespace App\Exports\Sheets;

use App\Exports\Core\BaseReportExport;
use App\Services\Dashboard\DashboardDataService;

/**
 * Hoja de requisiciones: una fila por item, una por ajuste y una de
 * «Total efectuado» por requisición. El folio se repite en cada fila para
 * poder filtrar y hacer tablas dinámicas; sumar «Importe» de las filas
 * «Item» y «Ajuste aplicado» da el total efectuado.
 */
class RequisicionesDataSheet extends BaseReportExport
{
    public function __construct(array $requisiciones, array $filters, array $meta)
    {
        parent::__construct(self::flatten($requisiciones), $filters, $meta);
    }

    /** @param  list<array<string, mixed>>  $requisiciones */
    private static function flatten(array $requisiciones): array
    {
        $rows = [];
        foreach ($requisiciones as $r) {
            $base = [
                'folio' => $r['folio'],
                'estatus' => DashboardDataService::STATUS_LABELS[$r['estatus']] ?? $r['estatus'],
                'corporativo' => $r['corporativo'],
                'sucursal' => $r['sucursal'],
                'solicitante' => $r['solicitante'],
                'proveedor' => $r['proveedor'],
                'proveedor_rfc' => $r['proveedor_rfc'],
                'concepto' => $r['concepto'],
                'fecha_solicitud' => $r['fecha_solicitud'],
                'fecha_pago' => $r['fecha_pago'],
            ];

            foreach ($r['items'] as $it) {
                $rows[] = $base + [
                    'fila' => 'Item',
                    'n' => $it['n'],
                    'descripcion' => $it['item'],
                    'cantidad' => $it['cantidad'],
                    'precio_unitario' => $it['precio_unitario'],
                    'genera_iva' => $it['genera_iva'] ? 'Sí' : 'No',
                    'subtotal' => $it['subtotal'],
                    'iva' => $it['iva'],
                    'importe' => $it['total'],
                ];
            }

            foreach ($r['ajustes'] as $a) {
                $rows[] = $base + [
                    'fila' => $a['aplicado'] ? 'Ajuste aplicado' : 'Ajuste no aplicado',
                    'descripcion' => $a['tipo'].($a['motivo'] !== '' ? ': '.$a['motivo'] : ''),
                    'importe' => $a['monto'],
                    'detalle' => $a['estatus'].($a['fecha'] ? ' ('.$a['fecha'].')' : ''),
                ];
            }

            if (abs($r['otras_diferencias']) > 0.004) {
                $rows[] = $base + [
                    'fila' => 'Ajuste aplicado',
                    'descripcion' => 'Diferencia sin ajuste registrado (registros anteriores)',
                    'importe' => $r['otras_diferencias'],
                ];
            }

            $rows[] = $base + [
                'fila' => 'Total efectuado',
                'descripcion' => 'Total de items '.number_format($r['total_items'], 2).' · ajustes aplicados '.number_format($r['ajustes_aplicados'] + $r['otras_diferencias'], 2),
                'importe' => $r['total_efectuado'],
                'pagado' => $r['pagado'],
                'comprobado' => $r['comprobado'],
                'observaciones' => $r['observaciones'],
                'fecha_captura' => $r['fecha_captura'],
                'fecha_pago_esperada' => $r['fecha_pago_esperada'],
                'fecha_autorizacion' => $r['fecha_autorizacion'],
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Folio', 'Estatus', 'Tipo de fila', 'Corporativo', 'Sucursal', 'Solicitante', 'Proveedor', 'RFC proveedor', 'Concepto',
            'Fecha de solicitud', 'Fecha de pago', '#', 'Item / descripción', 'Cantidad', 'Precio unitario', 'Genera IVA',
            'Subtotal', 'IVA', 'Importe', 'Detalle del ajuste', 'Pagado', 'Comprobado (aprobado)', 'Observaciones',
            'Fecha de registro', 'Fecha esperada de pago', 'Autorización',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, 'B' => 20, 'C' => 18, 'D' => 26, 'E' => 24, 'F' => 26, 'G' => 30, 'H' => 16, 'I' => 22,
            'J' => 14, 'K' => 14, 'L' => 6, 'M' => 46, 'N' => 11, 'O' => 15, 'P' => 11,
            'Q' => 15, 'R' => 14, 'S' => 16, 'T' => 28, 'U' => 16, 'V' => 18, 'W' => 40,
            'X' => 18, 'Y' => 18, 'Z' => 18,
        ];
    }

    protected function columnFormats(): array
    {
        $currency = '"$"#,##0.00;-"$"#,##0.00';

        return [
            'N' => '#,##0.##',
            'O' => $currency,
            'Q' => $currency,
            'R' => $currency,
            'S' => $currency,
            'U' => $currency,
            'V' => $currency,
        ];
    }

    protected function mapRow(array $r): array
    {
        $keys = [
            'folio', 'estatus', 'fila', 'corporativo', 'sucursal', 'solicitante', 'proveedor', 'proveedor_rfc', 'concepto',
            'fecha_solicitud', 'fecha_pago', 'n', 'descripcion', 'cantidad', 'precio_unitario', 'genera_iva',
            'subtotal', 'iva', 'importe', 'detalle', 'pagado', 'comprobado', 'observaciones',
            'fecha_captura', 'fecha_pago_esperada', 'fecha_autorizacion',
        ];

        return array_map(fn ($k) => $r[$k] ?? '', $keys);
    }
}
