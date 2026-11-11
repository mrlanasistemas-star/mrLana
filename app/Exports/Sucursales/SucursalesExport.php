<?php

namespace App\Exports\Sucursales;

use App\Exports\Core\Workbook\Block;
use App\Exports\Core\Workbook\Groups;
use App\Exports\Core\Workbook\ReportWorkbook;
use App\Exports\Core\Workbook\Theme;

/** Libro de sucursales: estatus, corporativo y ubicación, datos y filtros. */
class SucursalesExport extends ReportWorkbook
{
    protected function heading(): string
    {
        return 'Resumen de sucursales';
    }

    protected function noun(): string
    {
        return 'sucursales';
    }

    protected function kpis(): array
    {
        $total = count($this->rows);
        $activas = $this->count(fn ($r) => ! empty($r['activo']));

        return [
            ['label' => 'Sucursales', 'value' => $total, 'hint' => 'Con los filtros aplicados'],
            ['label' => 'Activas', 'value' => $activas, 'hint' => self::pct($activas, $total).' del total', 'color' => Theme::ACTIVE],
            ['label' => 'De baja', 'value' => $total - $activas, 'hint' => self::pct($total - $activas, $total).' del total', 'color' => Theme::INACTIVE],
            ['label' => 'Corporativos', 'value' => Groups::distinct($this->rows, fn ($r) => $r['corporativo'] ?? null), 'hint' => 'Con al menos una sucursal'],
            ['label' => 'Estados', 'value' => Groups::distinct($this->rows, fn ($r) => $r['estado'] ?? null), 'hint' => 'Entidades con presencia'],
            ['label' => 'Ciudades', 'value' => Groups::distinct($this->rows, fn ($r) => $r['ciudad'] ?? null), 'hint' => 'Ciudades distintas'],
        ];
    }

    protected function blocks(): array
    {
        $blocks = [
            $this->activeStatus(fn ($r) => ! empty($r['activo']), 'Sucursales', ['Activas', 'De baja']),
            $this->activeBreakdown('Por corporativo', 'Corporativo', fn ($r) => $r['corporativo'] ?? null, fn ($r) => ! empty($r['activo']), 'Sucursales', 'Sucursales por corporativo', ['Activas', 'De baja']),
        ];

        $estados = Groups::top(Groups::by($this->rows, fn ($r) => $r['estado'] ?? null, null, 'count', 'Sin estado'));
        if (count($estados) > 1) {
            $blocks[] = Block::groups('Por estado', 'Estado', $estados, 'Sucursales', null, 'bar', 'Sucursales por estado');
        }
        $ciudades = Groups::top(Groups::by($this->rows, fn ($r) => $r['ciudad'] ?? null, null, 'count', 'Sin ciudad'));
        if (count($ciudades) > 1) {
            $blocks[] = Block::groups('Por ciudad', 'Ciudad', $ciudades, 'Sucursales', null, 'bar', 'Sucursales por ciudad');
        }

        return $blocks;
    }

    protected function dataTitle(): string
    {
        return 'Sucursales';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'ID', 'value' => 'id', 'type' => 'int', 'width' => 8],
            ['label' => 'Corporativo', 'value' => 'corporativo', 'width' => 26],
            ['label' => 'Sucursal', 'value' => 'sucursal', 'width' => 26],
            ['label' => 'Código', 'value' => 'codigo', 'width' => 12],
            ['label' => 'Ciudad', 'value' => 'ciudad', 'width' => 16],
            ['label' => 'Estado', 'value' => 'estado', 'width' => 16],
            ['label' => 'Dirección', 'value' => 'direccion', 'width' => 38, 'wrap' => true],
            ['label' => 'Estatus', 'value' => fn ($r) => ! empty($r['activo']) ? 'Activa' : 'De baja', 'width' => 11, 'colors' => ['Activa' => Theme::ACTIVE, 'De baja' => Theme::INACTIVE]],
            ['label' => 'Creada', 'value' => 'created_at', 'type' => 'datetime', 'width' => 17],
            ['label' => 'Actualizada', 'value' => 'updated_at', 'type' => 'datetime', 'width' => 17],
        ];
    }
}
