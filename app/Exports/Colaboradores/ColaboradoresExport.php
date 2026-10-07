<?php

namespace App\Exports\Colaboradores;

use App\Exports\Core\BaseReportExport;

class ColaboradoresExport extends BaseReportExport
{
    protected function headings(): array
    {
        return ['Colaborador', 'Puesto', 'Corporativo', 'Sucursal', 'Área', 'Correo', 'Acceso al sistema', 'Estatus'];
    }

    protected function mapRow(array $r): array
    {
        return [
            $r['empleado'] ?? '—',
            $r['puesto'] ?? '—',
            $r['corporativo'] ?? '—',
            $r['sucursal'] ?? '—',
            $r['area'] ?? '—',
            $r['correo'] ?? '—',
            $r['acceso'] ?? 'Sin acceso',
            (($r['activo'] ?? false) ? 'Activo' : 'Inactivo'),
        ];
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 20,
            'C' => 22,
            'D' => 22,
            'E' => 18,
            'F' => 32,
            'G' => 26,
            'H' => 12,
        ];
    }
}
