<?php

namespace App\Http\Controllers\Exports;

use App\Exports\Sucursales\SucursalesExport;
use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Services\Pdf\PdfService;
use App\Support\BusinessDate;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SucursalExportController extends Controller
{
    public function pdf(Request $request)
    {
        $rows = $this->buildRows($request);

        $meta = [
            'title' => 'Reporte de Sucursales',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => BusinessDate::now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
            'footer_left' => 'ERP MR-Lana',
        ];

        $filters = $this->filtersLabel($request);

        return app(PdfService::class)->download('exports.sucursales.index', [
            'rows' => $rows,
            'filters' => $filters,
            'meta' => $meta,
            'totals' => ['total' => count($rows)],
        ], 'sucursales.pdf', ['paper' => 'letter', 'landscape' => true]);
    }

    public function excel(Request $request)
    {
        $rows = $this->buildRows($request);

        $meta = [
            'title' => 'Reporte de Sucursales',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => BusinessDate::now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
        ];

        $filters = $this->filtersLabel($request);

        return Excel::download(new SucursalesExport($rows, $filters, $meta), 'sucursales.xlsx');
    }

    private function buildRows(Request $request): array
    {
        // Parámetros esperados desde Vue:
        // corporativo_id, q, activo(all|1|0), sort, dir(asc|desc)
        $corporativoId = $request->integer('corporativo_id') ?: null;
        $q = trim((string) $request->get('q', ''));
        $activo = (string) $request->get('activo', 'all');
        $sort = (string) $request->get('sort', 'nombre');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Mismo alcance que el listado: nunca exporta más de lo que se puede ver.
        $query = Sucursal::query()
            ->visibleTo($request->user())
            ->with(['corporativo:id,nombre,activo']);

        if ($corporativoId) {
            $query->where('corporativo_id', $corporativoId);
        }

        if ($activo !== 'all') {
            $query->where('activo', $activo === '1' || $activo === 'true');
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%")
                    ->orWhere('ciudad', 'like', "%{$q}%")
                    ->orWhere('estado', 'like', "%{$q}%")
                    ->orWhere('direccion', 'like', "%{$q}%")
                    ->orWhereHas('corporativo', fn ($c) => $c->where('nombre', 'like', "%{$q}%"));
            });
        }

        // Orden (alineado a tu UI)
        $allowedSort = ['nombre', 'codigo', 'ciudad', 'estado', 'activo'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'nombre';
        }

        $query->orderBy($sort, $dir)->orderBy('id', 'desc');

        $items = $query->get();

        return $items->map(function (Sucursal $s) {
            return [
                'id' => $s->id,
                'corporativo' => optional($s->corporativo)->nombre,
                'sucursal' => $s->nombre,
                'codigo' => $s->codigo,
                'ciudad' => $s->ciudad,
                'estado' => $s->estado,
                'direccion' => $s->direccion,
                'activo' => (bool) $s->activo,
                'created_at' => optional($s->created_at)?->format('Y-m-d H:i'),
                'updated_at' => optional($s->updated_at)?->format('Y-m-d H:i'),
            ];
        })->values()->all();
    }

    private function filtersLabel(Request $request): array
    {
        return [
            'Corporativo' => $request->get('corporativo_id'),
            'Búsqueda' => $request->get('q'),
            'Estatus' => $request->get('activo'),
            'Orden' => $request->get('sort'),
            'Dirección' => $request->get('dir'),
        ];
    }
}
