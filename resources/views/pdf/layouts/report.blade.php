@php
    $engine = $pdfEngine ?? 'dompdf';
    $brand = \App\Support\Pdf\PdfAssets::colors();
    $logo = \App\Support\Pdf\PdfAssets::logo();
    $landscape = $landscape ?? false;
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] ?? 'Reporte' }}</title>
    <style>
        @if ($engine === 'dompdf')
            @page { margin: 16mm 12mm 18mm 12mm; }
        @endif
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: {!! $engine === 'browsershot' ? "'Inter', 'Segoe UI', Helvetica, Arial, sans-serif" : "'DejaVu Sans', sans-serif" !!};
            font-size: {{ $engine === 'browsershot' ? '10.5px' : '9.5px' }};
            color: #18181B;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .brandbar { height: 4px; background: {{ $brand['primary_color'] }}; border-radius: 4px; margin-bottom: 14px; }
        .head { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .head td { vertical-align: middle; padding: 0; border: 0; }
        .logo { max-height: 34px; max-width: 120px; display: block; }
        .logo-cell { width: 136px; padding-right: 16px !important; }
        .title { font-size: 18px; font-weight: 700; letter-spacing: -0.02em; margin: 0; color: #09090B; }
        .subtitle { margin: 2px 0 0; color: #71717A; font-size: 10.5px; }
        .meta { text-align: right; color: #52525B; font-size: 9.5px; line-height: 1.6; }
        .meta strong { color: #18181B; }
        .stats { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 12px; }
        .stat { border: 1px solid #E4E4E7; border-radius: 10px; padding: 9px 12px; background: #FAFAFA; }
        .stat .label { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.06em; color: #71717A; font-weight: 700; }
        .stat .value { font-size: 16px; font-weight: 700; color: #09090B; margin-top: 2px; }
        .chips { margin-bottom: 10px; }
        .chip { display: inline-block; border: 1px solid #E4E4E7; background: #FFFFFF; color: #3F3F46; border-radius: 999px; padding: 3px 9px; margin: 0 4px 4px 0; font-size: 9px; }
        .chip b { color: #18181B; }
        table.data { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #E4E4E7; border-radius: 10px; overflow: hidden; }
        table.data thead th {
            background: #F4F4F5; color: #52525B; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.05em;
            font-weight: 700; text-align: left; padding: 8px 9px; border-bottom: 1px solid #E4E4E7;
        }
        table.data tbody td { padding: 7px 9px; border-bottom: 1px solid #F1F1F3; vertical-align: top; word-wrap: break-word; overflow-wrap: anywhere; }
        table.data tbody tr:nth-child(even) td { background: #FCFCFD; }
        table.data tbody tr:last-child td { border-bottom: 0; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        .num, table.data thead th.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .muted { color: #71717A; }
        .strong { font-weight: 700; color: #09090B; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 8.5px; font-weight: 700; border: 1px solid transparent; white-space: nowrap; }
        .badge-ok { background: #ECFDF5; color: #047857; border-color: #A7F3D0; }
        .badge-off { background: #F4F4F5; color: #52525B; border-color: #E4E4E7; }
        .badge-warn { background: #FFFBEB; color: #B45309; border-color: #FDE68A; }
        .badge-bad { background: #FEF2F2; color: #B91C1C; border-color: #FECACA; }
        .badge-info { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
        .empty { text-align: center; color: #71717A; padding: 22px !important; }
        .section-title { font-size: 11px; font-weight: 700; color: #09090B; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 0.05em; }
        @if ($engine === 'dompdf')
            .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8.5px; color: #71717A; border-top: 1px solid #E4E4E7; padding-top: 5px; }
            .footer .page:after { content: counter(page); }
            .footer .pages:after { content: counter(pages); }
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
                <div><strong>Generado:</strong> {{ $meta['generated_at'] ?? now()->format('d/m/Y H:i') }}</div>
                @if (!empty($meta['generated_by']))
                    <div><strong>Por:</strong> {{ $meta['generated_by'] }}</div>
                @endif
                <div>{{ $meta['footer_left'] ?? 'ERP MR-Lana' }}</div>
            </td>
        </tr>
    </table>

    @if (!empty($stats))
        <table class="stats">
            <tr>
                @foreach ($stats as $label => $value)
                    <td class="stat">
                        <div class="label">{{ $label }}</div>
                        <div class="value">{{ $value }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

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

    @yield('content')

    @if ($engine === 'dompdf')
        <div class="footer">
            {{ $meta['footer_left'] ?? 'ERP MR-Lana' }} · Página <span class="page"></span> de <span class="pages"></span>
        </div>
    @endif
</body>
</html>
