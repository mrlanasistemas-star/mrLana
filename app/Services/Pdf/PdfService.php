<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\Response;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Genera PDF a partir de vistas Blade.
 *
 * Motor principal: Browsershot (Chrome headless) → tipografía, CSS moderno y
 * gráficas SVG con calidad de impresión. Si Chrome/Node no están disponibles
 * o fallan, se usa DomPDF como respaldo para que la descarga nunca se rompa.
 *
 * Las vistas reciben `$pdfEngine` ('browsershot' | 'dompdf') por si necesitan
 * ajustar algún estilo no soportado por DomPDF.
 */
class PdfService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array{paper?: string, landscape?: bool}  $options
     */
    public function download(string $view, array $data, string $filename, array $options = []): Response
    {
        return $this->respond($view, $data, $filename, $options, 'attachment');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{paper?: string, landscape?: bool}  $options
     */
    public function inline(string $view, array $data, string $filename, array $options = []): Response
    {
        return $this->respond($view, $data, $filename, $options, 'inline');
    }

    /**
     * Devuelve el binario del PDF.
     *
     * @param  array<string, mixed>  $data
     * @param  array{paper?: string, landscape?: bool}  $options
     */
    public function render(string $view, array $data, array $options = []): string
    {
        $paper = strtolower($options['paper'] ?? 'letter');
        $landscape = (bool) ($options['landscape'] ?? false);

        if (config('erp.pdf.driver') === 'browsershot') {
            try {
                return $this->renderWithBrowsershot($view, $data, $paper, $landscape);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return DomPdf::loadView($view, $data + ['pdfEngine' => 'dompdf'])
            ->setPaper($paper, $landscape ? 'landscape' : 'portrait')
            ->output();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderWithBrowsershot(string $view, array $data, string $paper, bool $landscape): string
    {
        $html = view($view, $data + ['pdfEngine' => 'browsershot'])->render();

        $footerLabel = e((string) ($data['meta']['footer_left'] ?? config('app.name', 'ERP MR-Lana')));
        $footer = '<div style="width:100%;font-family:Segoe UI,Helvetica,Arial,sans-serif;font-size:8px;color:#71717A;'
            .'padding:0 12mm;display:flex;justify-content:space-between;">'
            .'<span>'.$footerLabel.'</span>'
            .'<span>Página <span class="pageNumber"></span> de <span class="totalPages"></span></span></div>';

        $shot = Browsershot::html($html)
            ->format(ucfirst($paper))
            ->landscape($landscape)
            ->margins(14, 12, 16, 12)
            ->showBrowserHeaderAndFooter()
            ->headerHtml('<div></div>')
            ->footerHtml($footer)
            ->showBackground()
            ->emulateMedia('print')
            ->timeout((int) config('erp.pdf.timeout', 60));

        if ($chrome = config('erp.pdf.chrome_path')) {
            $shot->setChromePath($chrome);
        }
        if ($node = config('erp.pdf.node_binary')) {
            $shot->setNodeBinary($node);
        }
        if ($npm = config('erp.pdf.npm_binary')) {
            $shot->setNpmBinary($npm);
        }
        if (config('erp.pdf.no_sandbox')) {
            $shot->noSandbox();
        }

        return $shot->pdf();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{paper?: string, landscape?: bool}  $options
     */
    private function respond(string $view, array $data, string $filename, array $options, string $disposition): Response
    {
        $safeName = str_replace(['"', "\r", "\n"], '', $filename);

        return response($this->render($view, $data, $options), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$safeName.'"',
        ]);
    }
}
