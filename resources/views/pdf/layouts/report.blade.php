@php
    $engine = $pdfEngine ?? 'dompdf';
    $brand = \App\Support\Pdf\PdfAssets::colors();
    $logo = \App\Support\Pdf\PdfAssets::logo();
    $landscape = $landscape ?? false;
    $m = \App\Services\Pdf\PdfService::MARGINS;
    // Los indicadores numéricos se muestran con separador de miles.
    $statValue = fn ($v) => is_int($v) ? number_format($v) : $v;
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] ?? 'Reporte' }}</title>
    <style>
        @if ($engine === 'dompdf')
            @page { margin: {{ $m['top'] }}mm {{ $m['right'] }}mm {{ $m['bottom'] }}mm {{ $m['left'] }}mm; }
        @endif
        * { box-sizing: border-box; }
        {{-- DomPDF aplica los márgenes de @page al elemento html: no se anulan aquí. --}}
        body { margin: 0; padding: 0; }
        body {
            font-family: {!! $engine === 'browsershot' ? "'Inter', 'Segoe UI', Helvetica, Arial, sans-serif" : "'DejaVu Sans', sans-serif" !!};
            font-size: {{ $engine === 'browsershot' ? '10.5px' : '9.5px' }};
            color: #18181B;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .brandbar { height: 4px; background: {{ $brand['primary_color'] }}; border-radius: 4px; margin-bottom: 12px; }
        .head { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .head td { vertical-align: middle; padding: 0; border: 0; }
        .logo { max-height: 34px; max-width: 120px; display: block; }
        .logo-cell { width: 136px; padding-right: 16px !important; }
        .title { font-size: 18px; font-weight: 700; letter-spacing: -0.02em; margin: 0; color: #09090B; }
        .subtitle { margin: 2px 0 0; color: #71717A; font-size: 10.5px; }
        .meta { text-align: right; color: #52525B; font-size: 9.5px; line-height: 1.6; }
        .meta strong { color: #18181B; }
        .stats-wrap { margin: 0 -8px 12px; }
        .stats { width: 100%; border-collapse: separate; border-spacing: 8px 0; table-layout: fixed; }
        .stat { border: 1px solid #E4E4E7; border-radius: 10px; padding: 9px 12px; background: #FAFAFA; vertical-align: top; }
        .stat .label { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.06em; color: #71717A; font-weight: 700; }
        .stat .value { font-size: 16px; font-weight: 700; color: #09090B; margin-top: 2px; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .chips { margin-bottom: 8px; }
        .chip { display: inline-block; border: 1px solid #E4E4E7; background: #FFFFFF; color: #3F3F46; border-radius: 999px; padding: 3px 9px; margin: 0 4px 4px 0; font-size: 9px; }
        .chip b { color: #18181B; }
        /* Tablas: sin marco exterior para que se vean bien al continuar en otra hoja;
           el encabezado se repite en cada hoja y las filas nunca se parten. */
        table.data { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.data thead th {
            background: #FAFAFA; color: #52525B; font-size: 8px; text-transform: uppercase; letter-spacing: 0.05em;
            font-weight: 700; text-align: left; padding: 6px 8px; border-bottom: 1.5px solid #D4D4D8; vertical-align: bottom;
        }
        table.data tbody td { padding: 5px 8px; font-size: 9.5px; border-bottom: 1px solid #F1F1F3; vertical-align: top; word-wrap: break-word; overflow-wrap: anywhere; }
        table.data tbody tr:last-child td { border-bottom: 1.5px solid #D4D4D8; }
        table.data tr.foot td { font-weight: 700; color: #09090B; background: #FAFAFA; border-top: 1.5px solid #D4D4D8; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; break-inside: avoid; }
        .nowrap { white-space: nowrap; }
        .sub { display: block; font-size: 8.5px; color: #71717A; }
        .mono { font-family: Consolas, 'DejaVu Sans Mono', monospace; font-size: 9px; letter-spacing: 0.02em; }
        .num, table.data thead th.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .muted { color: #71717A; }
        .strong { font-weight: 700; color: #09090B; }
        .badge { display: inline-block; padding: 1px 7px; border-radius: 999px; font-size: 8px; font-weight: 700; border: 1px solid transparent; white-space: nowrap; }
        .badge-ok { background: #ECFDF5; color: #047857; border-color: #A7F3D0; }
        .badge-off { background: #F4F4F5; color: #52525B; border-color: #E4E4E7; }
        .badge-warn { background: #FFFBEB; color: #B45309; border-color: #FDE68A; }
        .badge-bad { background: #FEF2F2; color: #B91C1C; border-color: #FECACA; }
        .badge-info { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
        .empty { text-align: center; color: #71717A; padding: 22px !important; }
        .section-title { font-size: 11px; font-weight: 700; color: #09090B; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 0.05em; page-break-after: avoid; break-after: avoid; }

        /* Paneles de gráficas reutilizables (resúmenes de listados) */
        .panels-wrap { margin: 0 -8px 10px; page-break-inside: avoid; break-inside: avoid; }
        table.panels { width: 100%; border-collapse: separate; border-spacing: 8px 0; table-layout: fixed; }
        table.panels td.panel { border: 1px solid #E4E4E7; border-radius: 10px; padding: 10px 12px 9px; vertical-align: top; background: #FFFFFF; }
        .panel h3 { font-size: 10.5px; font-weight: 700; color: #18181B; margin: 0; }
        .panel .hint { font-size: 8.5px; color: #71717A; margin: 1px 0 6px; }
        .panel img { width: 100%; height: auto; display: block; }
        @if ($engine === 'dompdf')
            .footer { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 8px; color: #71717A; border-top: 1px solid #E4E4E7; padding-top: 4px; }
            .footer .page:after { content: counter(page); }
        @endif
        @stack('styles')
    </style>
</head>
<body>
    <div class="brandbar"></div>

    <table class="head">
        <tr>
            @if ($logo)
                <td class="logo-cell"><img class="logo" src="{{ $logo }}" alt=""></td>
            @endif
            <td>
                <p class="title">{{ $meta['title'] ?? 'Reporte' }}</p>
                <p class="subtitle">{{ $meta['subtitle'] ?? 'Exportación con los filtros actuales' }}</p>
            </td>
            <td class="meta" style="width: 34%;">
                <div><strong>Generado:</strong> {{ \App\Support\Pdf\PdfFormat::dateTime($meta['generated_at'] ?? \App\Support\BusinessDate::now()) }}</div>
                @if (!empty($meta['generated_by']))
                    <div><strong>Por:</strong> {{ $meta['generated_by'] }}</div>
                @endif
                <div>{{ $meta['footer_left'] ?? 'ERP MR-Lana' }}</div>
            </td>
        </tr>
    </table>

    @if (!empty($filters))
        <div class="chips">
            @foreach ($filters as $k => $v)
                @php $vv = is_array($v) ? implode(', ', $v) : (string) $v; @endphp
                @if (trim($vv) !== '')
                    <span class="chip"><b>{{ $k }}:</b> {{ $vv }}</span>
                @endif
            @endforeach
        </div>
    @endif

    @if (!empty($stats))
        <div class="stats-wrap">
            <table class="stats">
                <tr>
                    @foreach ($stats as $label => $value)
                        <td class="stat">
                            <div class="label">{{ $label }}</div>
                            <div class="value">{{ $statValue($value) }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

    @yield('content')

    @if ($engine === 'dompdf')
        <div class="footer">
            {{ $meta['footer_left'] ?? 'ERP MR-Lana' }} · Página <span class="page"></span>
        </div>
    @endif
</body>
</html>
