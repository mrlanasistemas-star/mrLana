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
     * Márgenes de página en mm (arriba, derecha, abajo, izquierda), iguales
     * para Browsershot y DomPDF. Abajo queda espacio para el pie con el
     * número de página sin que choque con el contenido.
     */
    public const MARGINS = ['top' => 12, 'right' => 18, 'bottom' => 18, 'left' => 18];

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
     * @param  array{paper?: string, landscape?: bool, dompdf?: array<string, mixed>}  $options
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

        $dompdf = DomPdf::loadView($view, $data + ['pdfEngine' => 'dompdf'])
            ->setPaper($paper, $landscape ? 'landscape' : 'portrait');

        // Opciones de DomPDF por reporte (p. ej. font_height_ratio), sin tocar a los demás.
        foreach ((array) ($options['dompdf'] ?? []) as $key => $value) {
            $dompdf->setOption($key, $value);
        }

        return $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderWithBrowsershot(string $view, array $data, string $paper, bool $landscape): string
    {
        $html = view($view, $data + ['pdfEngine' => 'browsershot'])->render();

        $footerLabel = e((string) ($data['meta']['footer_left'] ?? config('app.name', 'ERP MR-Lana')));
        $footer = '<div style="width:100%;font-family:Segoe UI,Helvetica,Arial,sans-serif;font-size:8px;color:#71717A;'
            .'padding:0 '.self::MARGINS['left'].'mm 6mm;display:flex;justify-content:space-between;">'
            .'<span>'.$footerLabel.'</span>'
            .'<span>Página <span class="pageNumber"></span> de <span class="totalPages"></span></span></div>';

        return $this->browsershot($html, (int) config('erp.pdf.timeout', 60))
            ->format(ucfirst($paper))
            ->landscape($landscape)
            ->margins(self::MARGINS['top'], self::MARGINS['right'], self::MARGINS['bottom'], self::MARGINS['left'])
            ->showBrowserHeaderAndFooter()
            ->headerHtml('<div></div>')
            ->footerHtml($footer)
            ->showBackground()
            ->emulateMedia('print')
            ->pdf();
    }

    /**
     * Intenta generar un PDF mínimo con Chrome (Browsershot) para saber si en
     * este servidor los reportes salen con Chrome o con el respaldo DomPDF.
     * No lanza excepciones.
     *
     * @return array{ok: bool, seconds: float, bytes: int, error: ?string}
     */
    public function probeChrome(int $timeout = 20): array
    {
        $start = microtime(true);
        try {
            $pdf = $this->browsershot('<!doctype html><meta charset="utf-8"><p>Prueba de PDF</p>', $timeout)
                ->format('Letter')
                ->pdf();
            $ok = str_starts_with($pdf, '%PDF');

            return ['ok' => $ok, 'seconds' => microtime(true) - $start, 'bytes' => strlen($pdf), 'error' => $ok ? null : 'Chrome no devolvió un PDF válido.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'seconds' => microtime(true) - $start, 'bytes' => 0, 'error' => self::chromeError($e->getMessage())];
        }
    }

    /**
     * De la excepción de Browsershot (que incluye el comando completo) deja
     * lo útil: la salida de error de Node/Chrome sin la pila de llamadas y,
     * si no la hay, el código de salida.
     */
    private static function chromeError(string $message): string
    {
        if (preg_match('/Error Output:\s*=+\s*(.+)$/s', $message, $m) && trim($m[1]) !== '') {
            $lines = array_filter(array_map('trim', explode("\n", $m[1])), fn ($l) => $l !== '' && ! str_starts_with($l, 'at '));

            return implode(' ', array_slice($lines, 0, 4));
        }
        if (preg_match('/Exit Code:\s*([^\n]+)/', $message, $m)) {
            return 'Exit Code: '.trim($m[1]);
        }

        return trim($message);
    }

    /** Browsershot con las rutas y opciones del servidor (config/erp.php → pdf). */
    private function browsershot(string $html, int $timeout): Browsershot
    {
        $shot = Browsershot::html($html)->timeout($timeout);

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

        return $shot;
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
