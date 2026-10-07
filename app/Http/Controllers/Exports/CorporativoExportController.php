<?php

namespace App\Http\Controllers\Exports;

use App\Exports\Corporativos\CorporativosExport;
use App\Http\Controllers\Controller;
use App\Models\Corporativo;
use App\Services\Pdf\PdfService;
use App\Support\BusinessDate;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CorporativoExportController extends Controller
{
    public function pdf(Request $request)
    {
        $rows = $this->buildRows($request);

        $meta = [
            'title' => 'Reporte de Corporativos',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => BusinessDate::now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
            'footer_left' => 'ERP MR-Lana',
        ];

        $filters = $this->filtersLabel($request);

        return app(PdfService::class)->download('exports.corporativos.index', [
            'rows' => $rows,
            'filters' => $filters,
            'meta' => $meta,
            'totals' => ['total' => count($rows)],
        ], 'corporativos.pdf', ['paper' => 'letter', 'landscape' => true]);
    }

    public function excel(Request $request)
    {
        $rows = $this->buildRows($request);

        $meta = [
            'title' => 'Reporte de Corporativos',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => BusinessDate::now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
        ];

        $filters = $this->filtersLabel($request);

        return Excel::download(new CorporativosExport($rows, $filters, $meta), 'corporativos.xlsx');
    }

    private function buildRows(Request $request): array
    {

        // Parámetros esperados desde Vue:
        // q, activo(all|1|0), sort, perPage (perPage no afecta export, pero puede venir)
        $q = trim((string) $request->get('q', ''));
        $activo = (string) $request->get('activo', 'all'); // all|1|0|true|false
        $sort = (string) $request->get('sort', 'nombre_asc');

        $query = Corporativo::query()
            ->withCount(['sucursales', 'areas']);

        // Estatus
        if ($activo !== 'all') {
            $isActive = $activo === '1' || $activo === 'true';
            $query->where('activo', $isActive);
        }

        // Búsqueda
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('nombre', 'like', "%{$q}%")
                    ->orWhere('rfc', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%")
                    ->orWhere('direccion', 'like', "%{$q}%");
            });
        }

        // Orden
        switch ($sort) {
            case 'nombre_desc':
                $query->orderBy('nombre', 'desc');
                break;
            case 'id_desc':
                $query->orderBy('id', 'desc');
                break;
            case 'id_asc':
                $query->orderBy('id', 'asc');
                break;
            default: // nombre_asc
                $query->orderBy('nombre', 'asc');
                break;
        }

        $items = $query->get();

        // Flatten para PDF/Excel
        return $items->map(function (Corporativo $c) {
            return [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'rfc' => $c->rfc,
                'codigo' => $c->codigo,
                'telefono' => $c->telefono,
                'email' => $c->email,
                'direccion' => $c->direccion,
                'sucursales_count' => (int) ($c->sucursales_count ?? 0),
                'areas_count' => (int) ($c->areas_count ?? 0),
                'activo' => (bool) $c->activo,
                'estatus_label' => $c->activo ? 'Activo' : 'Baja',
                'created_at' => optional($c->created_at)->format('Y-m-d') ?? '—',
                'updated_at' => optional($c->updated_at)->format('Y-m-d') ?? '—',
            ];
        })->values()->all();
    }

    private function filtersLabel(Request $request): array
    {
        return [
            'Búsqueda' => $request->get('q'),
            'Estatus' => $request->get('activo', 'all'),
            'Orden' => $request->get('sort', 'nombre_asc'),
            'Por página' => $request->get('perPage'),
        ];
    }
}
