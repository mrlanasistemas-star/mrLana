<?php

namespace App\Exports\Pagos;

use App\Exports\Core\Workbook\Block;
use App\Exports\Core\Workbook\Groups;
use App\Exports\Core\Workbook\ReportWorkbook;
use App\Exports\Core\Workbook\Theme;
use App\Services\Reportes\PagoReport;

/**
 * Libro de pagos: indicadores, tablas por tipo de pago, corporativo,
 * concepto, beneficiario, solicitante, quién registró, quién autorizó y mes;
 * datos y filtros.
 */
class PagosExport extends ReportWorkbook
{
    protected function heading(): string
    {
        return 'Resumen de pagos';
    }

    protected function noun(): string
    {
        return 'pagos';
    }

    protected function kpis(): array
    {
        $total = count($this->rows);
        $monto = $this->sum(fn ($r) => $r['monto'] ?? 0);
        $transfer = $this->sum(fn ($r) => ($r['tipo_pago'] ?? '') === 'TRANSFERENCIA' ? ($r['monto'] ?? 0) : 0);
        $reqs = Groups::distinct($this->rows, fn ($r) => $r['requisicion']['folio'] ?? null);
        $benef = Groups::distinct($this->rows, fn ($r) => $r['beneficiario'] ?? null);

        return [
            ['label' => 'Pagos', 'value' => $total, 'hint' => 'De '.$reqs.' '.($reqs === 1 ? 'requisición' : 'requisiciones')],
            ['label' => 'Monto pagado', 'value' => $monto, 'format' => Theme::MONEY, 'hint' => 'Suma de los pagos exportados'],
            ['label' => 'Pago promedio', 'value' => $total ? round($monto / $total, 2) : 0, 'format' => Theme::MONEY, 'hint' => 'Monto promedio por pago'],
            ['label' => 'Por transferencia', 'value' => $transfer, 'format' => Theme::MONEY, 'hint' => self::pct($transfer, $monto).' del monto pagado', 'color' => '0EA5E9'],
            ['label' => 'Otros medios', 'value' => round($monto - $transfer, 2), 'format' => Theme::MONEY, 'hint' => 'Efectivo, tarjeta, cheque y otros'],
            ['label' => 'Beneficiarios', 'value' => $benef, 'hint' => 'Beneficiarios distintos'],
        ];
    }

    protected function blocks(): array
    {
        $amount = fn ($r) => $r['monto'] ?? 0;
        $req = fn (string $k) => fn ($r) => $r['requisicion'][$k] ?? null;

        $blocks = [
            Block::groups('Por tipo de pago', 'Tipo de pago', Groups::fixed($this->rows, fn ($r) => $r['tipo_pago'] ?? '', PagoReport::TIPOS, [], $amount),
                'Pagos', 'Monto', 'donut', 'Monto por tipo de pago'),
            Block::groups('Por corporativo', 'Corporativo', Groups::top(Groups::by($this->rows, $req('corporativo'), $amount)), 'Pagos', 'Monto', 'bar', 'Monto pagado por corporativo'),
            Block::groups('Por concepto', 'Concepto', Groups::top(Groups::by($this->rows, $req('concepto'), $amount)), 'Pagos', 'Monto', 'bar', 'Conceptos con mayor monto pagado'),
            Block::groups('Por beneficiario', 'Beneficiario', Groups::top(Groups::by($this->rows, fn ($r) => $r['beneficiario'] ?? null, $amount, 'amount', 'Sin beneficiario')), 'Pagos', 'Monto', 'bar', 'Beneficiarios con mayor monto'),
            Block::groups('Por solicitante', 'Solicitante', Groups::top(Groups::by($this->rows, $req('solicitante'), $amount)), 'Pagos', 'Monto', 'bar', 'Monto pagado por solicitante'),
            Block::groups('Por quién registró', 'Usuario', Groups::top(Groups::by($this->rows, fn ($r) => $r['user_carga'] ?? null, $amount, 'count', 'Sin registro')), 'Pagos', 'Monto', 'bar', 'Pagos registrados por usuario', 'count'),
            Block::groups('Por quién autorizó', 'Usuario', Groups::top(Groups::by($this->rows, $req('autorizo'), $amount, 'amount', 'No registrado')), 'Pagos', 'Monto', 'bar', 'Monto pagado por quien autorizó'),
        ];

        $months = Groups::months($this->rows, fn ($r) => $r['fecha_pago'] ?? null, $amount);
        if (count($months) > 1) {
            $blocks[] = Block::groups('Por mes de pago', 'Mes', array_slice($months, -24), 'Pagos', 'Monto', 'column', 'Monto pagado por mes',
                note: count($months) > 24 ? 'Últimos 24 meses con pagos.' : null);
        }

        return $blocks;
    }

    protected function dataTitle(): string
    {
        return 'Pagos';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'ID', 'value' => 'id', 'type' => 'int', 'width' => 8],
            ['label' => 'Requisición', 'value' => 'requisicion.folio', 'width' => 15],
            ['label' => 'Solicitante', 'value' => 'requisicion.solicitante', 'width' => 24],
            ['label' => 'Concepto', 'value' => 'requisicion.concepto', 'width' => 20],
            ['label' => 'Corporativo', 'value' => 'requisicion.corporativo', 'width' => 22],
            ['label' => 'Beneficiario', 'value' => 'beneficiario', 'width' => 28],
            ['label' => 'Banco', 'value' => 'banco', 'width' => 16],
            ['label' => 'Cuenta', 'value' => 'cuenta', 'width' => 12],
            ['label' => 'Tipo de pago', 'value' => 'tipo_label', 'width' => 14],
            ['label' => 'Fecha de pago', 'value' => 'fecha_pago', 'type' => 'date', 'width' => 13],
            ['label' => 'Monto', 'value' => 'monto', 'type' => 'money', 'width' => 15, 'total' => true],
            ['label' => 'Referencia', 'value' => 'referencia', 'width' => 20],
            ['label' => 'Registró', 'value' => 'user_carga', 'width' => 22],
            ['label' => 'Autorizó', 'value' => fn ($r) => $r['requisicion']['autorizo'] ?? 'No registrado', 'width' => 22],
            ['label' => 'Archivo', 'value' => 'archivo_original', 'width' => 30],
        ];
    }
}
