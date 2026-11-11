<?php

namespace App\Exports\Corporativos;

use App\Exports\Core\Workbook\Block;
use App\Exports\Core\Workbook\ReportWorkbook;
use App\Exports\Core\Workbook\Theme;

/** Libro de corporativos: estatus, sucursales y áreas por corporativo, datos y filtros. */
class CorporativosExport extends ReportWorkbook
{
    protected function heading(): string
    {
        return 'Resumen de corporativos';
    }

    protected function noun(): string
    {
        return 'corporativos';
    }

    protected function kpis(): array
    {
        $total = count($this->rows);
        $activos = $this->count(fn ($r) => ! empty($r['activo']));
        $sucursales = (int) $this->sum(fn ($r) => $r['sucursales_count'] ?? 0);
        $areas = (int) $this->sum(fn ($r) => $r['areas_count'] ?? 0);
        $sinSucursal = $this->count(fn ($r) => (int) ($r['sucursales_count'] ?? 0) === 0);

        return [
            ['label' => 'Corporativos', 'value' => $total, 'hint' => 'Con los filtros aplicados'],
            ['label' => 'Activos', 'value' => $activos, 'hint' => self::pct($activos, $total).' del total', 'color' => Theme::ACTIVE],
            ['label' => 'De baja', 'value' => $total - $activos, 'hint' => self::pct($total - $activos, $total).' del total', 'color' => Theme::INACTIVE],
            ['label' => 'Sucursales', 'value' => $sucursales, 'hint' => 'Promedio '.($total ? number_format($sucursales / $total, 1) : '0').' por corporativo'],
            ['label' => 'Áreas', 'value' => $areas, 'hint' => 'Promedio '.($total ? number_format($areas / $total, 1) : '0').' por corporativo'],
            ['label' => 'Sin sucursales', 'value' => $sinSucursal, 'hint' => 'Corporativos sin sucursales registradas'],
        ];
    }

    protected function blocks(): array
    {
        $rows = array_map(fn ($r) => [
            (string) ($r['nombre'] ?? '—'),
            (int) ($r['sucursales_count'] ?? 0),
            (int) ($r['areas_count'] ?? 0),
        ], $this->rows);
        usort($rows, fn ($a, $b) => [$b[1], $b[2], $a[0]] <=> [$a[1], $a[2], $b[0]]);
        if (count($rows) > 15) {
            $rest = array_slice($rows, 14);
            $rows = array_slice($rows, 0, 14);
            $rows[] = ['Otros ('.count($rest).')', array_sum(array_column($rest, 1)), array_sum(array_column($rest, 2))];
        }

        return [
            $this->activeStatus(fn ($r) => ! empty($r['activo']), 'Corporativos'),
            Block::table('Sucursales y áreas por corporativo', ['Corporativo', 'Sucursales', 'Áreas'], [null, Theme::INT, Theme::INT], $rows, [
                'share' => 1,
                'shareLabel' => '% de sucursales',
                'chart' => $rows ? ['type' => 'bar', 'values' => [1, 2], 'title' => 'Sucursales y áreas por corporativo'] : null,
            ]),
        ];
    }

    protected function dataTitle(): string
    {
        return 'Corporativos';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'ID', 'value' => 'id', 'type' => 'int', 'width' => 8],
            ['label' => 'Corporativo', 'value' => 'nombre', 'width' => 30],
            ['label' => 'RFC', 'value' => 'rfc', 'width' => 16],
            ['label' => 'Código', 'value' => 'codigo', 'width' => 12],
            ['label' => 'Teléfono', 'value' => 'telefono', 'width' => 15],
            ['label' => 'Correo', 'value' => 'email', 'width' => 28],
            ['label' => 'Dirección', 'value' => 'direccion', 'width' => 40, 'wrap' => true],
            ['label' => 'Sucursales', 'value' => 'sucursales_count', 'type' => 'int', 'width' => 11, 'total' => true],
            ['label' => 'Áreas', 'value' => 'areas_count', 'type' => 'int', 'width' => 9, 'total' => true],
            ['label' => 'Estatus', 'value' => fn ($r) => ! empty($r['activo']) ? 'Activo' : 'De baja', 'width' => 11, 'colors' => ['Activo' => Theme::ACTIVE, 'De baja' => Theme::INACTIVE]],
            ['label' => 'Alta', 'value' => 'created_at', 'type' => 'date', 'width' => 12],
        ];
    }
}
