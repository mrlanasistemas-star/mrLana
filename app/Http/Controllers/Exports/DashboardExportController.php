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

        $charts = [
            'activity' => SvgCharts::bars($data['activityDaily'], $palette[0], 'Requisiciones'),
            'amounts' => SvgCharts::area($data['amountsDaily'], $palette[1] ?? $palette[0], money: true),
            'status' => SvgCharts::donut(array_values(array_filter($data['statusMix'], fn ($s) => $s['value'] > 0)), $palette),
            'comprobantes' => SvgCharts::donut(array_values(array_filter($data['comprobantesMix'], fn ($s) => $s['value'] > 0)), $palette),
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

        $data['activityDaily'] = array_map(fn ($p) => ['date' => $p['name'], 'value' => $p['value']], $data['activityDaily']);
        $data['amountsDaily'] = array_map(fn ($p) => ['date' => $p['name'], 'value' => $p['value']], $data['amountsDaily']);
        $data['statusMix'] = array_column($data['statusMix'], 'value', 'name');
        $data['comprobantesMix'] = array_column($data['comprobantesMix'], 'value', 'name');

        return Excel::download(
            new DashboardExcelExport($profile->headline(), BusinessDate::now()->format('Y-m-d H:i'), $data, $profile !== DashboardView::Personal),
            'dashboard_'.$profile->value.'_'.BusinessDate::now()->format('Ymd_His').'.xlsx'
        );
    }

    private function resolveView(Request $request, string $segment): DashboardView
    {
        $view = DashboardView::tryFrom(strtolower(trim($segment)));
        abort_if($view === null, 404);
        abort_unless($view->allowedFor($request->user()), 403);

        return $view;
    }
}
