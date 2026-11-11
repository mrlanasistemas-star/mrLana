<?php

namespace App\Exports\Comprobantes;

use App\Exports\Core\Workbook\Block;
use App\Exports\Core\Workbook\Groups;
use App\Exports\Core\Workbook\ReportWorkbook;
use App\Exports\Core\Workbook\Theme;
use App\Services\Reportes\ComprobanteReport;

/**
 * Libro de comprobantes: indicadores de revisión, tablas por estatus, tipo,
 * proveedor, corporativo, solicitante, quién cargó y mes; datos y filtros.
 */
class ComprobantesExport extends ReportWorkbook
{
    private const STATUS_COLORS = ['PENDIENTE' => Theme::PENDING, 'APROBADO' => Theme::ACTIVE, 'RECHAZADO' => Theme::REJECTED];

    protected function heading(): string
    {
        return 'Resumen de comprobantes';
    }

    protected function noun(): string
    {
        return 'comprobantes';
    }

    private static function fecha(array $r): ?string
    {
        return $r['fecha_emision'] ?? $r['created_at'] ?? null;
    }

    protected function kpis(): array
    {
        $total = count($this->rows);
        $monto = $this->sum(fn ($r) => $r['monto'] ?? 0);
        $by = fn (string $s) => [
            $this->count(fn ($r) => ($r['estatus'] ?? '') === $s),
            $this->sum(fn ($r) => ($r['estatus'] ?? '') === $s ? ($r['monto'] ?? 0) : 0),
        ];
        [$apr, $aprM] = $by('APROBADO');
        [$pen, $penM] = $by('PENDIENTE');
        [$rec, $recM] = $by('RECHAZADO');
        $reqs = Groups::distinct($this->rows, fn ($r) => $r['requisicion']['folio'] ?? null);

        return [
            ['label' => 'Comprobantes', 'value' => $total, 'hint' => 'De '.$reqs.' '.($reqs === 1 ? 'requisición' : 'requisiciones')],
            ['label' => 'Monto comprobado', 'value' => $monto, 'format' => Theme::MONEY, 'hint' => 'Promedio $'.number_format($total ? $monto / $total : 0, 2).' por comprobante'],
            ['label' => 'Aprobado', 'value' => $aprM, 'format' => Theme::MONEY, 'hint' => $apr.' comprobantes · '.self::pct($aprM, $monto).' del monto', 'color' => Theme::ACTIVE],
            ['label' => 'Pendiente de revisión', 'value' => $penM, 'format' => Theme::MONEY, 'hint' => $pen.' comprobantes por revisar', 'color' => Theme::PENDING],
            ['label' => 'Rechazado', 'value' => $recM, 'format' => Theme::MONEY, 'hint' => $rec.' comprobantes · '.self::pct($recM, $monto).' del monto', 'color' => Theme::REJECTED],
            ['label' => 'Tasa de aprobación', 'value' => ($apr + $rec) ? $apr / ($apr + $rec) : 0, 'format' => Theme::PCT, 'hint' => 'Aprobados entre revisados'],
        ];
    }

    protected function blocks(): array
    {
        $amount = fn ($r) => $r['monto'] ?? 0;
        $req = fn (string $k) => fn ($r) => $r['requisicion'][$k] ?? null;

        $blocks = [
            Block::groups('Por estatus', 'Estatus', Groups::fixed($this->rows, fn ($r) => $r['estatus'] ?? '', ComprobanteReport::ESTATUS, self::STATUS_COLORS, $amount),
                'Comprobantes', 'Monto', 'donut', 'Comprobantes por estatus', 'count'),
            Block::groups('Por tipo de documento', 'Tipo', Groups::fixed($this->rows, fn ($r) => $r['tipo_doc'] ?? '', ComprobanteReport::TIPOS, [], $amount),
                'Comprobantes', 'Monto', 'donut', 'Monto por tipo de documento'),
            Block::groups('Por proveedor', 'Proveedor', Groups::top(Groups::by($this->rows, $req('proveedor'), $amount, 'amount', 'Sin proveedor')), 'Comprobantes', 'Monto', 'bar', 'Proveedores con mayor monto comprobado'),
            Block::groups('Por corporativo', 'Corporativo', Groups::top(Groups::by($this->rows, $req('corporativo'), $amount)), 'Comprobantes', 'Monto', 'bar', 'Monto comprobado por corporativo'),
            Block::groups('Por solicitante', 'Solicitante', Groups::top(Groups::by($this->rows, $req('solicitante'), $amount)), 'Comprobantes', 'Monto', 'bar', 'Solicitantes con mayor monto comprobado'),
            Block::groups('Por quién cargó', 'Usuario', Groups::top(Groups::by($this->rows, fn ($r) => $r['user_carga'] ?? null, $amount, 'count', 'Sin registro')), 'Comprobantes', 'Monto', 'bar', 'Comprobantes cargados por usuario', 'count'),
        ];

        $months = Groups::months($this->rows, fn ($r) => self::fecha($r), $amount);
        if (count($months) > 1) {
            $blocks[] = Block::groups('Por mes', 'Mes', array_slice($months, -24), 'Comprobantes', 'Monto', 'column', 'Monto comprobado por mes',
                note: 'Fecha de emisión del comprobante (o de carga si no se capturó).'.(count($months) > 24 ? ' Últimos 24 meses.' : ''));
        }

        return $blocks;
    }

    protected function dataTitle(): string
    {
        return 'Comprobantes';
    }

    protected function columns(): array
    {
        $colors = [];
        foreach (ComprobanteReport::ESTATUS as $k => $label) {
            $colors[$label] = self::STATUS_COLORS[$k];
        }

        return [
            ['label' => 'ID', 'value' => 'id', 'type' => 'int', 'width' => 8],
            ['label' => 'Requisición', 'value' => 'requisicion.folio', 'width' => 15],
            ['label' => 'Solicitante', 'value' => 'requisicion.solicitante', 'width' => 24],
            ['label' => 'Proveedor', 'value' => 'requisicion.proveedor', 'width' => 28],
            ['label' => 'Concepto', 'value' => 'requisicion.concepto', 'width' => 20],
            ['label' => 'Corporativo', 'value' => 'requisicion.corporativo', 'width' => 22],
            ['label' => 'Tipo', 'value' => 'tipo_label', 'width' => 10],
            ['label' => 'Fecha', 'value' => fn ($r) => self::fecha($r), 'type' => 'date', 'width' => 12],
            ['label' => 'Monto', 'value' => 'monto', 'type' => 'money', 'width' => 15, 'total' => true],
            ['label' => 'Estatus', 'value' => 'estatus_label', 'width' => 12, 'colors' => $colors],
            ['label' => 'Cargó', 'value' => 'user_carga', 'width' => 22],
            ['label' => 'Revisó', 'value' => 'user_revision', 'width' => 22],
            ['label' => 'Fecha de revisión', 'value' => 'revisado_at', 'type' => 'datetime', 'width' => 17],
            ['label' => 'Comentario de revisión', 'value' => 'comentario_revision', 'width' => 40, 'wrap' => true],
            ['label' => 'Archivo', 'value' => 'archivo_original', 'width' => 32],
        ];
    }
}
