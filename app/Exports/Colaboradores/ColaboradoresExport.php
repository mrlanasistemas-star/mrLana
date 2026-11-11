<?php

namespace App\Exports\Colaboradores;

use App\Exports\Core\Workbook\Block;
use App\Exports\Core\Workbook\Groups;
use App\Exports\Core\Workbook\ReportWorkbook;
use App\Exports\Core\Workbook\Theme;

/**
 * Libro de colaboradores: estatus, acceso al sistema, corporativo,
 * sucursal, área, puesto y rol; datos y filtros.
 */
class ColaboradoresExport extends ReportWorkbook
{
    protected function heading(): string
    {
        return 'Resumen de colaboradores';
    }

    protected function noun(): string
    {
        return 'colaboradores';
    }

    protected function kpis(): array
    {
        $total = count($this->rows);
        $activos = $this->count(fn ($r) => ! empty($r['activo']));
        $acceso = $this->count(fn ($r) => ! empty($r['con_acceso']));

        return [
            ['label' => 'Colaboradores', 'value' => $total, 'hint' => 'Con los filtros aplicados'],
            ['label' => 'Activos', 'value' => $activos, 'hint' => self::pct($activos, $total).' del total', 'color' => Theme::ACTIVE],
            ['label' => 'Inactivos', 'value' => $total - $activos, 'hint' => self::pct($total - $activos, $total).' del total', 'color' => Theme::INACTIVE],
            ['label' => 'Con acceso al sistema', 'value' => $acceso, 'hint' => self::pct($acceso, $total).' tiene usuario', 'color' => '0EA5E9'],
            ['label' => 'Sin acceso', 'value' => $total - $acceso, 'hint' => 'Sin usuario vinculado'],
            ['label' => 'Sucursales', 'value' => Groups::distinct($this->rows, fn ($r) => $r['sucursal'] ?? null), 'hint' => Groups::distinct($this->rows, fn ($r) => $r['area'] ?? null).' áreas distintas'],
        ];
    }

    protected function blocks(): array
    {
        $active = fn ($r) => ! empty($r['activo']);
        $acceso = Groups::fixed($this->rows, fn ($r) => ! empty($r['con_acceso']) ? 'con' : 'sin', ['con' => 'Con acceso', 'sin' => 'Sin acceso'], ['con' => '0EA5E9', 'sin' => Theme::INACTIVE]);

        $blocks = [
            $this->activeStatus($active, 'Colaboradores', ['Activos', 'Inactivos']),
            Block::groups('Acceso al sistema', 'Acceso', $acceso, 'Colaboradores', null, 'donut', 'Colaboradores con usuario'),
            $this->activeBreakdown('Por corporativo', 'Corporativo', fn ($r) => $r['corporativo'] ?? null, $active, 'Colaboradores', 'Colaboradores por corporativo', ['Activos', 'Inactivos']),
            $this->activeBreakdown('Por sucursal', 'Sucursal', fn ($r) => $r['sucursal'] ?? null, $active, 'Colaboradores', 'Colaboradores por sucursal', ['Activos', 'Inactivos']),
            Block::groups('Por área', 'Área', Groups::top(Groups::by($this->rows, fn ($r) => $r['area'] ?? null, null, 'count', 'Sin área')), 'Colaboradores', null, 'bar', 'Colaboradores por área'),
            Block::groups('Por puesto', 'Puesto', Groups::top(Groups::by($this->rows, fn ($r) => $r['puesto'] ?? null, null, 'count', 'Sin puesto')), 'Colaboradores', null, 'bar', 'Puestos con más colaboradores'),
        ];

        // Roles de quienes tienen usuario («Con acceso · Rol1, Rol2»).
        $conRol = [];
        foreach ($this->rows as $r) {
            if (empty($r['con_acceso'])) {
                continue;
            }
            $text = preg_replace('/\s*\(cuenta inactiva\)$/u', '', (string) ($r['acceso'] ?? ''));
            $roles = str_contains($text, '·') ? trim(explode('·', $text, 2)[1]) : '';
            foreach (array_filter(array_map('trim', explode(',', $roles))) ?: ['Sin rol'] as $role) {
                $conRol[] = ['rol' => $role];
            }
        }
        if ($conRol) {
            $blocks[] = Block::groups('Usuarios por rol', 'Rol', Groups::top(Groups::by($conRol, fn ($r) => $r['rol'], null, 'count')), 'Colaboradores', null, 'bar', 'Colaboradores con acceso por rol',
                note: 'Un colaborador con varios roles cuenta en cada uno.');
        }

        return $blocks;
    }

    protected function dataTitle(): string
    {
        return 'Colaboradores';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Colaborador', 'value' => 'empleado', 'width' => 30],
            ['label' => 'Puesto', 'value' => 'puesto', 'width' => 20],
            ['label' => 'Corporativo', 'value' => 'corporativo', 'width' => 22],
            ['label' => 'Sucursal', 'value' => 'sucursal', 'width' => 22],
            ['label' => 'Área', 'value' => 'area', 'width' => 18],
            ['label' => 'Correo', 'value' => 'correo', 'width' => 30],
            ['label' => 'Acceso al sistema', 'value' => fn ($r) => $r['acceso'] ?? 'Sin acceso', 'width' => 30, 'wrap' => true, 'colors' => ['Con acceso' => '0284C7']],
            ['label' => 'Estatus', 'value' => fn ($r) => ! empty($r['activo']) ? 'Activo' : 'Inactivo', 'width' => 11, 'colors' => ['Activo' => Theme::ACTIVE, 'Inactivo' => Theme::INACTIVE]],
        ];
    }
}
