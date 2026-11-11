<?php

namespace App\Http\Controllers\Exports;

use App\Exports\Dashboard\DashboardExcelExport;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\Dashboard\DashboardDataService;
use App\Services\Dashboard\DashboardView;
use App\Services\Pdf\PdfService;
use App\Support\BusinessDate;
use App\Support\Pdf\SvgCharts;
use App\Support\Reports\RequisicionesSummary;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DashboardExportController extends Controller
{
    public function __construct(private DashboardDataService $service) {}

    /**
     * GET /exports/dashboard/{vista}/pdf — reporte con gráficas de la vista
     * indicada. Solo vistas que el usuario puede consultar: cambiar la URL no
     * permite exportar un alcance superior.
     */
    public function pdf(Request $request, string $vista, PdfService $pdf)
    {
        $profile = $this->resolveView($request, $vista);
        $data = $this->service->build($profile, $request->user(), $request->query());
        $palette = AppSetting::resolved()['chart_palette'];

        // Las gráficas se dibujan al ancho real de su panel en carta vertical
        // (≈312 px de media columna, 648 px a todo lo ancho) para que el texto
        // se imprima a su tamaño y no reducido.
        $half = 312;
        $status = array_values(array_filter($data['statusMix'], fn ($s) => $s['value'] > 0));
        $comprobantes = array_values(array_filter($data['comprobantesMix'], fn ($s) => $s['value'] > 0));
        $donutH = max(130, 12 + max(count($status), count($comprobantes)) * 18);
        $rank = fn (array $items) => array_map(
            fn ($i) => $i + (str_starts_with((string) $i['name'], 'Otros (') ? ['color' => '#D4D4D8'] : []),
            $items
        );

        $charts = [
            'activity' => SvgCharts::bars($data['activityDaily'], $palette[0], 'Requisiciones', $half, 160),
            'amounts' => SvgCharts::area($data['amountsDaily'], $palette[1] ?? $palette[0], true, $half, 160),
            'status' => SvgCharts::donut($status, array_map(fn ($s) => RequisicionesSummary::STATUS_COLORS[$s['key']] ?? $palette[0], $status), $half, $donutH, 'requisiciones'),
            'comprobantes' => SvgCharts::donut($comprobantes, $palette, $half, $donutH, 'comprobantes'),
            'conceptos' => SvgCharts::hbars($rank($data['byConcepto']), $palette[0], true, $half, 120, 21, 68),
            'proveedores' => SvgCharts::hbars($rank($data['byProveedor']), $palette[0], true, $half, 120, 21, 68),
            'sucursales' => $data['bySucursal'] ? SvgCharts::hbars($rank($data['bySucursal']), $palette[0], true, $half, 120, 21, 68) : null,
            'monthly' => SvgCharts::bars($data['monthly'], $palette[0], '', $data['bySucursal'] ? $half : 648, 170, true),
        ];

        return $pdf->download('exports.dashboard.report', [
            'data' => $data,
            'charts' => $charts,
            'palette' => $palette,
            'generatedAt' => now()->timezone(\App\Support\BusinessDate::timezone())->format('d/m/Y H:i'),
        ], 'dashboard_'.$profile->value.'_'.BusinessDate::now()->format('Ymd_His').'.pdf', ['paper' => 'letter']);
    }

    /**
     * GET /exports/dashboard/{role}/excel
     */
    public function excel(Request $request, string $vista)
    {
        $profile = $this->resolveView($request, $vista);
        $data = $this->service->build($profile, $request->user(), $request->query());

        return Excel::download(
            new DashboardExcelExport($data, $this->excelFilters($data, $profile), [
                'generated_at' => BusinessDate::now()->format('d/m/Y H:i'),
                'generated_by' => $request->user()->name,
            ], $profile !== DashboardView::Personal),
            'dashboard_'.$profile->value.'_'.BusinessDate::now()->format('Ymd_His').'.xlsx'
        );
    }

    /** Filtros del Excel en texto: periodo y nombres en lugar de IDs. */
    private function excelFilters(array $data, DashboardView $profile): array
    {
        $f = $data['filters'] ?? [];
        // Nombres solo de catálogos de la vista: un ID ajeno no revela el nombre de otro corporativo.
        $options = ['corporativos' => 'corporativos', 'sucursals' => 'sucursales', 'conceptos' => 'conceptos'];
        $name = function (string $table, $id) use ($data, $options, $profile) {
            if (! $id) {
                return null;
            }
            $found = collect($data['options'][$options[$table]] ?? [])->first(fn ($o) => (int) data_get($o, 'id') === (int) $id);
            if ($found) {
                return data_get($found, 'nombre');
            }

            return $profile === DashboardView::General
                ? (\Illuminate\Support\Facades\DB::table($table)->where('id', $id)->value('nombre') ?? "#{$id}")
                : 'Fuera del alcance de esta vista';
        };

        return [
            'Periodo' => ($data['period']['label'] ?? 'Periodo').': del '.($data['period']['from'] ?? '').' al '.($data['period']['to'] ?? ''),
            'Corporativo' => $name('corporativos', $f['corporativo_id'] ?? null),
            'Sucursal' => $name('sucursals', $f['sucursal_id'] ?? null),
            'Concepto' => $name('conceptos', $f['concepto_id'] ?? null),
            'Estatus' => ($f['status'] ?? null) ? (\App\Services\Dashboard\DashboardDataService::STATUS_LABELS[$f['status']] ?? $f['status']) : null,
        ];
    }

    private function resolveView(Request $request, string $segment): DashboardView
    {
        $view = DashboardView::tryFrom(strtolower(trim($segment)));
        abort_if($view === null, 404);
        abort_unless($view->allowedFor($request->user()), 403);

        return $view;
    }
}
