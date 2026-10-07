<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Exports\Areas\AreasExport;
use App\Models\Area;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Pdf\PdfService;

class AreaExportController extends Controller
{
    public function pdf(Request $request)
    {
        $rows = $this->buildRows($request);

        $meta = [
            'title' => 'Reporte de Áreas',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
            'footer_left' => 'ERP MR-Lana',
        ];

        $filters = $this->filtersLabel($request);

        return app(PdfService::class)->download('exports.areas.index', [
            'rows' => $rows,
            'filters' => $filters,
            'meta' => $meta,
            'totals' => ['total' => count($rows)],
        ], 'areas.pdf', ['paper' => 'letter', 'landscape' => true]);
    }

    public function excel(Request $request)
    {
        $rows = $this->buildRows($request);

        $meta = [
            'title' => 'Reporte de Áreas',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
        ];

        $filters = $this->filtersLabel($request);

        return Excel::download(new AreasExport($rows, $filters, $meta), 'areas.xlsx');
    }

    private function buildRows(Request $request): array
    {
        // Parámetros esperados desde Vue:
        // corporativo_id, q, activo(all|1|0), sort, dir(asc|desc)
        $corporativoId = $request->integer('corporativo_id') ?: null;
        $q             = trim((string) $request->get('q', ''));
        $activo        = (string) $request->get('activo', 'all');
        $sort          = (string) $request->get('sort', 'nombre');
        $dir           = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = Area::query()
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
                    ->orWhereHas('corporativo', fn ($c) => $c->where('nombre', 'like', "%{$q}%"));
            });
        }

        // Orden alineado a tu UI
        $allowedSort = ['nombre', 'activo', 'corporativo_id'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'nombre';

        $query->orderBy($sort, $dir)->orderBy('id', 'desc');

        $items = $query->get();

        return $items->map(function (Area $a) {
            return [
                'id' => $a->id,
                'corporativo' => optional($a->corporativo)->nombre,
                'nombre' => $a->nombre,
                'activo' => (bool) $a->activo,
                'created_at' => optional($a->created_at)?->format('Y-m-d H:i'),
                'updated_at' => optional($a->updated_at)?->format('Y-m-d H:i'),
            ];
        })->values()->all();
    }

    private function filtersLabel(Request $request): array
    {
        return [
            'Corporativo' => $request->get('corporativo_id'),
            'Búsqueda'    => $request->get('q'),
            'Estatus'     => $request->get('activo'),
            'Orden'       => $request->get('sort'),
            'Dirección'   => $request->get('dir'),
        ];
    }
}
