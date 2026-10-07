<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Exports\Conceptos\ConceptosExport;
use App\Models\Concepto;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Pdf\PdfService;

class ConceptoExportController extends Controller
{
    public function pdf(Request $request)
    {
        $rows = $this->buildRows($request);

        $meta = [
            'title' => 'Reporte de Conceptos',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
            'footer_left' => 'ERP MR-Lana',
        ];

        $filters = $this->filtersLabel($request);

        return app(PdfService::class)->download('exports.conceptos.index', [
            'rows' => $rows,
            'filters' => $filters,
            'meta' => $meta,
            'totals' => ['total' => count($rows)],
        ], 'conceptos.pdf', ['paper' => 'letter', 'landscape' => true]);
    }

    public function excel(Request $request)
    {
        $rows = $this->buildRows($request);

        $meta = [
            'title' => 'Reporte de Conceptos',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
        ];

        $filters = $this->filtersLabel($request);

        return Excel::download(new ConceptosExport($rows, $filters, $meta), 'conceptos.xlsx');
    }

    private function buildRows(Request $request): array
    {
        // Parámetros esperados desde Vue:
        // q, activo(all|1|0), sort, dir(asc|desc)
        $q      = trim((string) $request->get('q', ''));
        $activo = (string) $request->get('activo', 'all');
        $sort   = (string) $request->get('sort', 'nombre');
        $dir    = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = Concepto::query();

        if ($activo !== 'all') {
            $query->where('activo', $activo === '1' || $activo === 'true');
        }

        if ($q !== '') {
            $query->where('nombre', 'like', "%{$q}%");
        }

        // Orden permitido
        $allowedSort = ['nombre', 'activo', 'id'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'nombre';

        $query->orderBy($sort, $dir)->orderBy('id', 'desc');

        $items = $query->get();

        return $items->map(function (Concepto $c) {
            return [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'activo' => (bool) $c->activo,
                'created_at' => optional($c->created_at)?->format('Y-m-d H:i'),
                'updated_at' => optional($c->updated_at)?->format('Y-m-d H:i'),
            ];
        })->values()->all();
    }

    private function filtersLabel(Request $request): array
    {
        return [
            'Búsqueda'  => $request->get('q'),
            'Estatus'   => $request->get('activo'),
            'Orden'     => $request->get('sort'),
            'Dirección' => $request->get('dir'),
        ];
    }
}
