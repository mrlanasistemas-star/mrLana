<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] ?? 'Reporte' }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; }
        .header { border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 14px; }
        .title { font-size: 16px; font-weight: 700; margin: 0; }
        .subtitle { margin: 2px 0 0 0; color: #555; }
        .meta { margin-top: 8px; color: #666; font-size: 10px; }
        .chips { margin-top: 10px; }
        .chip { display:inline-block; background:#f3f4f6; border:1px solid #e5e7eb; padding:3px 8px; border-radius: 999px; margin: 0 6px 6px 0; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        thead th { background: #f8fafc; border: 1px solid #e5e7eb; padding: 8px; text-align: left; font-size: 10px; }
        tbody td { border: 1px solid #e5e7eb; padding: 7px; vertical-align: top; }
        .muted { color:#6b7280; }
        .badge { display:inline-block; padding:2px 8px; border-radius: 999px; font-size: 10px; border:1px solid #e5e7eb; }
        .badge-ok { background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
        .badge-off { background:#f3f4f6; border-color:#e5e7eb; color:#374151; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #ddd; padding: 8px 0; font-size: 10px; color: #666; }
        .footer .wrap { display:flex; justify-content: space-between; }
        .page:after { content: counter(page); }
        .pages:after { content: counter(pages); }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">{{ $meta['title'] ?? 'Reporte' }}</p>
        <p class="subtitle">{{ $meta['subtitle'] ?? '' }}</p>
        <div class="meta">
            Generado: {{ $meta['generated_at'] ?? \App\Support\BusinessDate::now()->format('Y-m-d H:i') }}
            @if(!empty($meta['generated_by'])) | Por: {{ $meta['generated_by'] }} @endif
        </div>

        @if(!empty($filters))
            <div class="chips">
                @foreach($filters as $k => $v)
                    @if($v !== null && $v !== '')
                        <span class="chip"><strong>{{ $k }}:</strong> {{ is_array($v) ? implode(', ', $v) : $v }}</span>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    @yield('content')

    <div class="footer">
        <div class="wrap">
            <div class="muted">{{ $meta['footer_left'] ?? 'ERP MR-Lana' }}</div>
            <div class="muted">Página <span class="page"></span> de <span class="pages"></span></div>
        </div>
    </div>
</body>
</html>
