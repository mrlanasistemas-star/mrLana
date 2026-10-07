<?php

namespace App\Exports\Empleados;

use App\Exports\Core\BaseReportExport;

class EmpleadosExport extends BaseReportExport
{
    protected function headings(): array
    {
        return ['Empleado', 'Puesto', 'Corporativo', 'Sucursal', 'Área', 'Correo', 'Estatus'];
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
            'G' => 12,
        ];
    }
    
}
