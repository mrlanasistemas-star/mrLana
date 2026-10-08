<?php

namespace App\Http\Controllers\Exports;

use App\Exports\Colaboradores\ColaboradoresExport;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Corporativo;
use App\Models\Empleado;
use App\Models\Sucursal;
use App\Services\Colaboradores\ColaboradorQuery;
use App\Services\Pdf\PdfService;
use App\Support\BusinessDate;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ColaboradorExportController extends Controller
{
    public function pdf(Request $request, PdfService $pdf)
    {
        $rows = $this->buildRows($request);

        return $pdf->download('exports.colaboradores.index', [
            'rows' => $rows,
            'filters' => $this->filtersLabel($request),
            'meta' => $this->meta($request),
        ], 'colaboradores.pdf', ['paper' => 'letter', 'landscape' => true]);
    }

    public function excel(Request $request)
    {
        return Excel::download(
            new ColaboradoresExport($this->buildRows($request), $this->filtersLabel($request), $this->meta($request)),
            'colaboradores.xlsx'
        );
    }

    private function meta(Request $request): array
    {
        return [
            'title' => 'Reporte de colaboradores',
            'subtitle' => 'Exportación con los filtros actuales',
            'generated_at' => now()->timezone(BusinessDate::timezone())->format('d/m/Y H:i'),
            'generated_by' => $request->user()?->name,
            'footer_left' => 'ERP MR-Lana',
        ];
    }

    private function buildRows(Request $request): array
    {
        $items = ColaboradorQuery::build(ColaboradorQuery::filters($request), $request->user())
            ->with([
                'sucursal:id,corporativo_id,nombre,activo',
                'sucursal.corporativo:id,nombre,activo',
                'area:id,nombre,activo',
                'user:id,empleado_id,email,activo',
                'user.roles:id,name',
            ])
            ->orderBy('apellido_paterno')
            ->orderBy('nombre')
            ->get();

        // La relación usuario–colaborador solo se muestra con su permiso.
        $vinculo = $request->user()->can('usuarios.ver_vinculo');

        return $items->map(function (Empleado $e) use ($vinculo) {
            if (! $vinculo) {
                $e->setRelation('user', null);
            }
            $acceso = $e->user
                ? 'Con acceso · '.($e->user->roles->pluck('name')->implode(', ') ?: 'Sin rol').($e->user->activo ? '' : ' (cuenta inactiva)')
                : 'Sin acceso';

            return [
                'empleado' => trim("{$e->nombre} {$e->apellido_paterno} ".($e->apellido_materno ?? '')) ?: '—',
                'puesto' => $e->puesto,
                'corporativo' => $e->sucursal?->corporativo?->nombre,
                'sucursal' => $e->sucursal?->nombre,
                'area' => $e->area?->nombre,
                'correo' => $e->email ?: $e->user?->email,
                'acceso' => $acceso,
                'con_acceso' => $e->user !== null,
                'activo' => (bool) $e->activo,
            ];
        })->values()->all();
    }

    private function filtersLabel(Request $request): array
    {
        $f = ColaboradorQuery::filters($request);

        return [
            'Corporativo' => $f['corporativo_id'] ? Corporativo::whereKey($f['corporativo_id'])->value('nombre') : null,
            'Sucursal' => $f['sucursal_id'] ? Sucursal::whereKey($f['sucursal_id'])->value('nombre') : null,
            'Área' => $f['area_id'] ? Area::whereKey($f['area_id'])->value('nombre') : null,
            'Búsqueda' => $f['q'],
            'Estatus' => ['all' => null, '1' => 'Activos', '0' => 'Inactivos'][$f['activo']],
            'Acceso' => ['all' => null, 'con' => 'Con usuario', 'sin' => 'Sin usuario'][$f['acceso']],
        ];
    }
}
