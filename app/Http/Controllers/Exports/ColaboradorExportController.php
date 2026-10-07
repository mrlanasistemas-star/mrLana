<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Exports\Empleados\EmpleadosExport;
use App\Models\Empleado;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class EmpleadoExportController extends Controller {

    public function pdf(Request $request) {
        $rows = $this->buildRows($request);

        $meta = [
            'title' => 'Reporte de Empleados',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
            'footer_left' => 'ERP MR-Lana',
        ];

        $filters = $this->filtersLabel($request);

        $pdf = Pdf::loadView('exports.empleados.index', [
            'rows' => $rows,
            'filters' => $filters,
            'meta' => $meta,
            'totals' => ['total' => count($rows)],
        ])->setPaper('a4', 'landscape');

        return $pdf->download('empleados.pdf');
    }

    public function excel(Request $request)
    {
        $rows = $this->buildRows($request);

        $meta = [
            'title' => 'Reporte de Empleados',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
        ];

        $filters = $this->filtersLabel($request);

        return Excel::download(new EmpleadosExport($rows, $filters, $meta), 'empleados.xlsx');
    }

    private function buildRows(Request $request): array
    {
        // Parametros esperados desde Vue:
        // corporativo_id, sucursal_id, area_id, q, activo(all|1|0), sort, perPage
        $corporativoId = $request->integer('corporativo_id') ?: null;
        $sucursalId    = $request->integer('sucursal_id') ?: null;
        $areaId        = $request->integer('area_id') ?: null;
        $q             = trim((string) $request->get('q', ''));
        $activo        = (string) $request->get('activo', 'all');
        $sort          = (string) $request->get('sort', 'nombre_asc');

        $query = Empleado::query()
            ->with([
                'sucursal.corporativo:id,nombre,activo',
                'sucursal:id,corporativo_id,nombre,activo',
                'area:id,nombre,activo',
                'user:id,empleado_id,email',
            ]);

        // Reglas de negocio alineadas a tu UI:
        // - Si no hay corporativo_id, normalmente tu UI deshabilita todo; aquí igual no filtramos.
        if ($corporativoId) {
            $query->whereHas('sucursal', fn($sq) => $sq->where('corporativo_id', $corporativoId));
        }

        if ($sucursalId) {
            $query->where('sucursal_id', $sucursalId);
        }

        if ($areaId) {
            $query->where('area_id', $areaId);
        }

        if ($activo !== 'all') {
            $query->where('activo', $activo === '1' || $activo === 'true');
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('nombre', 'like', "%{$q}%")
                  ->orWhere('apellido_paterno', 'like', "%{$q}%")
                  ->orWhere('apellido_materno', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('telefono', 'like', "%{$q}%")
                  ->orWhere('puesto', 'like', "%{$q}%")
                  ->orWhereHas('sucursal', fn($s) => $s->where('nombre', 'like', "%{$q}%"))
                  ->orWhereHas('area', fn($a) => $a->where('nombre', 'like', "%{$q}%"))
                  ->orWhereHas('sucursal.corporativo', fn($c) => $c->where('nombre', 'like', "%{$q}%"));
            });
        }

        // Orden estándar (adáptalo a lo que ya manejes en useEmpleadosIndex)
        switch ($sort) {
            case 'nombre_desc':
                $query->orderBy('nombre', 'desc')->orderBy('apellido_paterno', 'desc');
                break;
            case 'apellido_asc':
                $query->orderBy('apellido_paterno', 'asc')->orderBy('nombre', 'asc');
                break;
            case 'apellido_desc':
                $query->orderBy('apellido_paterno', 'desc')->orderBy('nombre', 'desc');
                break;
            default: // nombre_asc
                $query->orderBy('nombre', 'asc')->orderBy('apellido_paterno', 'asc');
                break;
        }

        $items = $query->get();

        // Flatten para PDF/Excel
        return $items->map(function (Empleado $e) {
            $empleado = trim(($e->nombre ?? '') . ' ' . ($e->apellido_paterno ?? '') . ' ' . ($e->apellido_materno ?? ''));
            $corp = optional(optional($e->sucursal)->corporativo)->nombre;
            $suc  = optional($e->sucursal)->nombre;
            $area = optional($e->area)->nombre;

            $correo = $e->email;
            if (!$correo) {
                $correo = optional($e->user)->email; // fallback si usas email en User
            }

            return [
                'empleado'   => $empleado ?: '—',
                'puesto'     => $e->puesto,
                'corporativo'=> $corp,
                'sucursal'   => $suc,
                'area'       => $area,
                'correo'     => $correo,
                'activo'     => (bool) $e->activo,
            ];
        })->values()->all();
    }

    private function filtersLabel(Request $request): array
    {
        return [
            'Corporativo' => $request->get('corporativo_id'),
            'Sucursal'    => $request->get('sucursal_id'),
            'Área'        => $request->get('area_id'),
            'Búsqueda'    => $request->get('q'),
            'Estatus'     => $request->get('activo'),
            'Orden'       => $request->get('sort'),
            'Por página'  => $request->get('perPage'),
        ];
    }

}
