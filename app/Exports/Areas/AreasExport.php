<?php

namespace App\Exports\Areas;

use App\Exports\Core\Workbook\Groups;
use App\Exports\Core\Workbook\ReportWorkbook;
use App\Exports\Core\Workbook\Theme;

/** Libro de áreas: resumen breve (estatus y corporativo), datos y filtros. */
class AreasExport extends ReportWorkbook
{
    protected function heading(): string
    {
        return 'Resumen de áreas';
    }

    protected function noun(): string
    {
        return 'áreas';
    }

    protected function kpis(): array
    {
        $total = count($this->rows);
        $activas = $this->count(fn ($r) => ! empty($r['activo']));
        $corps = Groups::distinct($this->rows, fn ($r) => $r['corporativo'] ?? null);

        return [
            ['label' => 'Áreas', 'value' => $total, 'hint' => 'En '.$corps.' '.($corps === 1 ? 'corporativo' : 'corporativos')],
            ['label' => 'Activas', 'value' => $activas, 'hint' => self::pct($activas, $total).' del total', 'color' => Theme::ACTIVE],
            ['label' => 'De baja', 'value' => $total - $activas, 'hint' => self::pct($total - $activas, $total).' del total', 'color' => Theme::INACTIVE],
            ['label' => 'Promedio por corporativo', 'value' => $corps ? round($total / $corps, 1) : 0, 'format' => '#,##0.0', 'hint' => 'Áreas por corporativo'],
        ];
    }

    protected function blocks(): array
    {
        return [
            $this->activeStatus(fn ($r) => ! empty($r['activo']), 'Áreas', ['Activas', 'De baja']),
            $this->activeBreakdown('Por corporativo', 'Corporativo', fn ($r) => $r['corporativo'] ?? null, fn ($r) => ! empty($r['activo']), 'Áreas', 'Áreas por corporativo', ['Activas', 'De baja']),
        ];
    }

    protected function dataTitle(): string
    {
        return 'Áreas';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'ID', 'value' => 'id', 'type' => 'int', 'width' => 8],
            ['label' => 'Corporativo', 'value' => 'corporativo', 'width' => 30],
            ['label' => 'Área', 'value' => 'nombre', 'width' => 34],
            ['label' => 'Estatus', 'value' => fn ($r) => ! empty($r['activo']) ? 'Activa' : 'De baja', 'width' => 12, 'colors' => ['Activa' => Theme::ACTIVE, 'De baja' => Theme::INACTIVE]],
            ['label' => 'Creada', 'value' => 'created_at', 'type' => 'datetime', 'width' => 17],
            ['label' => 'Actualizada', 'value' => 'updated_at', 'type' => 'datetime', 'width' => 17],
        ];
    }
}
