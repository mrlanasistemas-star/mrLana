<?php

namespace App\Exports\Conceptos;

use App\Exports\Core\Workbook\ReportWorkbook;
use App\Exports\Core\Workbook\Theme;

/** Libro de conceptos: resumen breve por estatus, datos y filtros. */
class ConceptosExport extends ReportWorkbook
{
    protected function heading(): string
    {
        return 'Resumen de conceptos';
    }

    protected function noun(): string
    {
        return 'conceptos';
    }

    protected function kpis(): array
    {
        $total = count($this->rows);
        $activos = $this->count(fn ($r) => ! empty($r['activo']));

        return [
            ['label' => 'Conceptos', 'value' => $total, 'hint' => 'Con los filtros aplicados'],
            ['label' => 'Activos', 'value' => $activos, 'hint' => self::pct($activos, $total).' del total', 'color' => Theme::ACTIVE],
            ['label' => 'De baja', 'value' => $total - $activos, 'hint' => self::pct($total - $activos, $total).' del total', 'color' => Theme::INACTIVE],
        ];
    }

    protected function blocks(): array
    {
        return [$this->activeStatus(fn ($r) => ! empty($r['activo']), 'Conceptos')];
    }

    protected function dataTitle(): string
    {
        return 'Conceptos';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'ID', 'value' => 'id', 'type' => 'int', 'width' => 8],
            ['label' => 'Concepto', 'value' => 'nombre', 'width' => 44],
            ['label' => 'Estatus', 'value' => fn ($r) => ! empty($r['activo']) ? 'Activo' : 'De baja', 'width' => 12, 'colors' => ['Activo' => Theme::ACTIVE, 'De baja' => Theme::INACTIVE]],
            ['label' => 'Creado', 'value' => 'created_at', 'type' => 'datetime', 'width' => 17],
            ['label' => 'Actualizado', 'value' => 'updated_at', 'type' => 'datetime', 'width' => 17],
        ];
    }
}
