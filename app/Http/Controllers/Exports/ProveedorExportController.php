<?php

namespace App\Http\Controllers\Exports;

use App\Exports\Proveedores\ProveedoresExport;
use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use App\Services\Pdf\PdfService;
use App\Support\BusinessDate;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ProveedorExportController extends Controller
{
    public function pdf(Request $request)
    {
        $rows = $this->buildRows($request);
        $meta = [
            'title' => 'Reporte de Proveedores',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => BusinessDate::now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
            'footer_left' => 'ERP MR-Lana',
        ];
        $filters = $this->filtersLabel($request);

        return app(PdfService::class)->download('exports.proveedores.index', [
            'rows' => $rows,
            'filters' => $filters,
            'meta' => $meta,
            'totals' => ['total' => count($rows)],
        ], 'proveedores.pdf', ['paper' => 'letter', 'landscape' => true]);
    }

    public function excel(Request $request)
    {
        $rows = $this->buildRows($request);
        $meta = [
            'title' => 'Reporte de Proveedores',
            'subtitle' => 'Exportación con filtros actuales',
            'generated_at' => BusinessDate::now()->format('Y-m-d H:i'),
            'generated_by' => optional($request->user())->name,
        ];
        $filters = $this->filtersLabel($request);

        return Excel::download(new ProveedoresExport($rows, $filters, $meta), 'proveedores.xlsx');
    }

    private function buildRows(Request $request): array
    {
        // Filtros (basados en el index de proveedores)
        $q = trim((string) $request->get('q', ''));
        $status = strtoupper((string) $request->get('status', ''));
        $owner = $request->integer('user_duenio_id');
        $sort = (string) $request->get('sort', 'created_at');
        $dir = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $isAdminLike = \App\Support\Permissions\AccessScope::for($request->user(), 'proveedores') === \App\Support\Permissions\Scope::Global;
        $query = Proveedor::query()
            ->visibleTo($request->user())
            ->select(['id', 'user_duenio_id', 'razon_social', 'rfc', 'clabe', 'banco', 'status', 'created_at']);
        if (! $isAdminLike) {
            $query->where('status', 'ACTIVO');
        }
        if ($isAdminLike && ! empty($owner)) {
            $query->where('user_duenio_id', (int) $owner);
        }
        if ($isAdminLike && in_array($status, ['ACTIVO', 'INACTIVO'], true)) {
            $query->where('status', $status);
        }
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('razon_social', 'like', "%{$q}%")
                    ->orWhere('rfc', 'like', "%{$q}%")
                    ->orWhere('clabe', 'like', "%{$q}%")
                    ->orWhere('banco', 'like', "%{$q}%");
            });
        }
        // Orden seguro
        if (! in_array($sort, ['created_at', 'razon_social', 'status'], true)) {
            $sort = 'created_at';
        }
        $items = $query->orderBy($sort, $dir)->orderBy('id', 'desc')->get();

        // Transformar filas para exportar
        return $items->map(function (Proveedor $p) {
            return [
                'razon_social' => $p->razon_social,
                'rfc' => $p->rfc,
                'clabe' => $p->clabe,
                'banco' => $p->banco,
                'status' => $p->status,
                'created_at' => optional($p->created_at)->format('Y-m-d H:i'),
            ];
        })->values()->all();
    }

    private function filtersLabel(Request $request): array
    {
        return [
            'Búsqueda' => $request->get('q'),
            'Estatus' => $request->get('status'),
            'Dueño' => $request->get('user_duenio_id'),
            'Orden' => $request->get('sort'),
            'Dirección' => $request->get('dir'),
        ];
    }
}
