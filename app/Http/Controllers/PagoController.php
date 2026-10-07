<?php

namespace App\Http\Controllers;

use App\Exports\Pagos\PagosExport;
use App\Models\Pago;
use App\Services\Pdf\PdfService;
use App\Services\Reportes\PagoReport;
use App\Support\BusinessDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Módulo de pagos: consulta global con filtros, vista previa protegida
 * del comprobante de pago y reportes PDF/Excel. El registro sigue en la
 * pantalla de cada requisición.
 */
class PagoController extends Controller
{
    public function __construct(private PagoReport $report) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $f = $this->report->filters($request->query());
        $query = $this->report->query($user, $f);

        $page = (clone $query)->paginate($f['per_page'])->withQueryString();
        $page->getCollection()->transform(fn (Pago $p) => $this->report->present($p));

        return Inertia::render('Pagos/Index', [
            'pagos' => $page,
            'kpis' => $this->report->kpis($query),
            'filters' => $f,
            'options' => $this->report->options($user),
            'can' => [
                'exportar' => $user->can('pagos.exportar'),
                'registrar' => $user->can('pagos.registrar'),
            ],
        ]);
    }

    /** Comprobante del pago (vista previa en línea o descarga), solo para quien ve la requisición. */
    public function archivo(Request $request, Pago $pago): StreamedResponse
    {
        $pago->loadMissing('requisicion');
        abort_unless($pago->requisicion && $request->user()->can('view', $pago->requisicion), 403);
        abort_unless($pago->archivo_path && Storage::disk('public')->exists($pago->archivo_path), 404, 'El archivo ya no está disponible.');

        $name = $pago->archivo_original ?: basename($pago->archivo_path);

        return $request->boolean('descargar')
            ? Storage::disk('public')->download($pago->archivo_path, $name)
            : Storage::disk('public')->response($pago->archivo_path, $name, ['Cache-Control' => 'private, max-age=600']);
    }

    public function pdf(Request $request, PdfService $pdf)
    {
        [$rows, $f, $kpis] = $this->rows($request);

        return $pdf->download('exports.pagos.index', [
            'rows' => $rows,
            'kpis' => $kpis,
            'filters' => $this->report->filterLabels($f),
            'meta' => $this->meta($request),
        ], 'pagos_'.BusinessDate::now()->format('Ymd_His').'.pdf', ['paper' => 'letter', 'landscape' => true]);
    }

    public function excel(Request $request)
    {
        [$rows, $f] = $this->rows($request);

        return Excel::download(
            new PagosExport($rows, $this->report->filterLabels($f), $this->meta($request)),
            'pagos_'.BusinessDate::now()->format('Ymd_His').'.xlsx'
        );
    }

    /** @return array{0: list<array<string, mixed>>, 1: array, 2: array} */
    private function rows(Request $request): array
    {
        $f = $this->report->filters($request->query());
        $query = $this->report->query($request->user(), $f);

        return [
            $query->limit(5000)->get()->map(fn (Pago $p) => $this->report->present($p))->all(),
            $f,
            $this->report->kpis($this->report->query($request->user(), $f)),
        ];
    }

    private function meta(Request $request): array
    {
        return [
            'title' => 'Reporte de pagos',
            'subtitle' => 'Pagos registrados con los filtros actuales',
            'generated_at' => BusinessDate::now()->format('Y-m-d H:i'),
            'generated_by' => $request->user()->name,
            'footer_left' => 'ERP MR-Lana',
        ];
    }
}
