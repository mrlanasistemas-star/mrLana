<?php

namespace App\Exports\Proveedores;

use App\Exports\Core\Workbook\Block;
use App\Exports\Core\Workbook\Groups;
use App\Exports\Core\Workbook\ReportWorkbook;
use App\Exports\Core\Workbook\Theme;

/** Libro de proveedores: estatus, bancos y altas por mes, datos y filtros. */
class ProveedoresExport extends ReportWorkbook
{
    private const STATUS = ['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'];

    protected function heading(): string
    {
        return 'Resumen de proveedores';
    }

    protected function noun(): string
    {
        return 'proveedores';
    }

    private static function active(array $r): bool
    {
        return strtoupper((string) ($r['status'] ?? '')) === 'ACTIVO';
    }

    protected function kpis(): array
    {
        $total = count($this->rows);
        $activos = $this->count(fn ($r) => self::active($r));
        $conClabe = $this->count(fn ($r) => trim((string) ($r['clabe'] ?? '')) !== '');

        return [
            ['label' => 'Proveedores', 'value' => $total, 'hint' => 'Con los filtros aplicados'],
            ['label' => 'Activos', 'value' => $activos, 'hint' => self::pct($activos, $total).' del total', 'color' => Theme::ACTIVE],
            ['label' => 'Inactivos', 'value' => $total - $activos, 'hint' => self::pct($total - $activos, $total).' del total', 'color' => Theme::INACTIVE],
            ['label' => 'Con CLABE', 'value' => $conClabe, 'hint' => ($total - $conClabe).' sin cuenta para transferencia'],
            ['label' => 'Bancos', 'value' => Groups::distinct($this->rows, fn ($r) => $r['banco'] ?? null), 'hint' => 'Bancos distintos registrados'],
        ];
    }

    protected function blocks(): array
    {
        $status = Groups::fixed($this->rows, fn ($r) => strtoupper((string) ($r['status'] ?? '')), self::STATUS, ['ACTIVO' => Theme::ACTIVE, 'INACTIVO' => Theme::INACTIVE]);
        $blocks = [
            Block::groups('Por estatus', 'Estatus', $status, 'Proveedores', null, 'donut', 'Proveedores por estatus'),
            Block::groups('Por banco', 'Banco', Groups::top(Groups::by($this->rows, fn ($r) => $r['banco'] ?? null, null, 'count', 'Sin banco')), 'Proveedores', null, 'bar', 'Proveedores por banco'),
        ];

        $months = Groups::months($this->rows, fn ($r) => $r['created_at'] ?? null);
        if (count($months) > 1) {
            $blocks[] = Block::groups('Altas por mes', 'Mes', array_slice($months, -24), 'Proveedores dados de alta', null, 'column', 'Altas de proveedores por mes',
                note: count($months) > 24 ? 'Últimos 24 meses con altas.' : null);
        }

        return $blocks;
    }

    protected function dataTitle(): string
    {
        return 'Proveedores';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Razón social', 'value' => 'razon_social', 'width' => 34],
            ['label' => 'RFC', 'value' => 'rfc', 'width' => 16],
            ['label' => 'CLABE', 'value' => 'clabe', 'width' => 21],
            ['label' => 'Banco', 'value' => 'banco', 'width' => 20],
            ['label' => 'Estatus', 'value' => fn ($r) => self::STATUS[strtoupper((string) ($r['status'] ?? ''))] ?? ($r['status'] ?? ''), 'width' => 11, 'colors' => ['Activo' => Theme::ACTIVE, 'Inactivo' => Theme::INACTIVE]],
            ['label' => 'Fecha de alta', 'value' => 'created_at', 'type' => 'datetime', 'width' => 17],
        ];
    }
}
