<?php

namespace App\Http\Controllers;

use App\Exports\Comprobantes\ComprobantesExport;
use App\Models\Comprobante;
use App\Services\Pdf\PdfService;
use App\Services\Reportes\ComprobanteReport;
use App\Support\BusinessDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Módulo de comprobantes: consulta global con filtros, vista previa protegida
 * de archivos y reportes PDF/Excel. La carga y revisión siguen en la
 * pantalla de cada requisición.
 */
class ComprobanteController extends Controller
{
    public function __construct(private ComprobanteReport $report) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $f = $this->report->filters($request->query());
        $query = $this->report->query($user, $f);

        $page = (clone $query)->paginate($f['per_page'])->withQueryString();
        $page->getCollection()->transform(fn (Comprobante $c) => $this->report->present($c));

        return Inertia::render('Comprobantes/Index', [
            'comprobantes' => $page,
            'kpis' => $this->report->kpis($query),
            'filters' => $f,
            'options' => $this->report->options($user),
            'can' => [
                'exportar' => $user->can('comprobaciones.exportar'),
                'revisar' => $user->can('comprobaciones.revisar'),
            ],
        ]);
    }

    /** Archivo del comprobante (vista previa en línea o descarga), solo para quien ve la requisición. */
    public function archivo(Request $request, Comprobante $comprobante): StreamedResponse
    {
        $comprobante->loadMissing('requisicion');
        abort_unless($comprobante->requisicion && $request->user()->can('view', $comprobante->requisicion), 403);
        abort_unless($comprobante->archivo_path && Storage::disk('public')->exists($comprobante->archivo_path), 404, 'El archivo ya no está disponible.');

        $name = $comprobante->archivo_original ?: basename($comprobante->archivo_path);

        return $request->boolean('descargar')
            ? Storage::disk('public')->download($comprobante->archivo_path, $name)
            : Storage::disk('public')->response($comprobante->archivo_path, $name, ['Cache-Control' => 'private, max-age=600']);
    }

    public function pdf(Request $request, PdfService $pdf)
    {
        [$rows, $f, $kpis] = $this->rows($request);

        return $pdf->download('exports.comprobantes.index', [
            'rows' => $rows,
            'kpis' => $kpis,
            'filters' => $this->report->filterLabels($f),
            'meta' => $this->meta($request),
        ], 'comprobantes_'.BusinessDate::now()->format('Ymd_His').'.pdf', ['paper' => 'letter', 'landscape' => true]);
    }

    public function excel(Request $request)
    {
        [$rows, $f] = $this->rows($request);

        return Excel::download(
            new ComprobantesExport($rows, $this->report->filterLabels($f), $this->meta($request)),
            'comprobantes_'.BusinessDate::now()->format('Ymd_His').'.xlsx'
        );
    }

    /** @return array{0: list<array<string, mixed>>, 1: array, 2: array} */
    private function rows(Request $request): array
    {
        $f = $this->report->filters($request->query());
        $query = $this->report->query($request->user(), $f);

        return [
            $query->limit(5000)->get()->map(fn (Comprobante $c) => $this->report->present($c))->all(),
            $f,
            $this->report->kpis($this->report->query($request->user(), $f)),
        ];
    }

    private function meta(Request $request): array
    {
        return [
            'title' => 'Reporte de comprobantes',
            'subtitle' => 'Comprobantes de requisiciones con los filtros actuales',
            'generated_at' => BusinessDate::now()->format('Y-m-d H:i'),
            'generated_by' => $request->user()->name,
            'footer_left' => 'ERP MR-Lana',
        ];
    }
}
