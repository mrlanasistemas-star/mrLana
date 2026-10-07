@php
    use App\Support\Pdf\PdfAssets;
    use App\Services\Dashboard\DashboardDataService;

    $engine = $pdfEngine ?? 'dompdf';
    $brand = PdfAssets::colors();
    $logo = PdfAssets::corporativoLogo($requisicion->comprador?->logo_path) ?? PdfAssets::logo();
    $money = fn ($v) => '$'.number_format((float) ($v ?? 0), 2);
    $tz = \App\Support\BusinessDate::timezone();
    $fecha = fn ($d, $withTime = false) => $d ? \Carbon\Carbon::parse($d)->timezone($tz)->locale('es')->isoFormat($withTime ? 'D MMM YYYY, HH:mm' : 'D MMM YYYY') : null;
    $ajustes = collect($requisicion->ajustes ?? [])->sortByDesc('id')->values();
    $status = (string) $requisicion->status;
    $statusLabel = DashboardDataService::STATUS_LABELS[$status] ?? $status;
    $statusClass = match (true) {
        in_array($status, ['PAGADA', 'COMPROBACION_ACEPTADA'], true) => 'badge-ok',
        in_array($status, ['PAGO_RECHAZADO', 'COMPROBACION_RECHAZADA', 'ELIMINADA'], true) => 'badge-bad',
        in_array($status, ['CAPTURADA', 'POR_COMPROBAR'], true) => 'badge-warn',
        default => 'badge-info',
    };
    $solicitante = $requisicion->solicitante
        ? trim($requisicion->solicitante->nombre.' '.$requisicion->solicitante->apellido_paterno.' '.($requisicion->solicitante->apellido_materno ?? ''))
        : '—';
    $pagadoTotal = (float) collect($requisicion->pagos ?? [])->sum('monto');
    $tipoLabel = fn ($t) => ['INCREMENTO_AUTORIZADO' => 'Incremento autorizado', 'FALTANTE' => 'Faltante', 'DEVOLUCION' => 'Devolución'][$t] ?? $t;
    $ajusteClass = fn ($e) => ['APLICADO' => 'badge-info', 'APROBADO' => 'badge-ok', 'RECHAZADO' => 'badge-bad', 'CANCELADO' => 'badge-off'][$e] ?? 'badge-warn';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Requisición {{ $requisicion->folio }}</title>
    <style>
        @if ($engine === 'dompdf') @page { margin: 14mm 12mm 16mm 12mm; } @endif
        * { box-sizing: border-box; }
        body {
            margin: 0; color: #18181B; line-height: 1.45;
            font-family: {!! $engine === 'browsershot' ? "'Inter', 'Segoe UI', Helvetica, Arial, sans-serif" : "'DejaVu Sans', sans-serif" !!};
            font-size: {{ $engine === 'browsershot' ? '10.5px' : '9.5px' }};
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .brandbar { height: 4px; background: {{ $brand['primary_color'] }}; border-radius: 4px; margin-bottom: 14px; }
        table { border-collapse: collapse; width: 100%; }
        .head td { vertical-align: middle; }
        .logo { max-height: 40px; max-width: 140px; }
        .folio { font-size: 22px; font-weight: 700; letter-spacing: -0.02em; margin: 0; }
        .muted { color: #71717A; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 9px; font-weight: 700; border: 1px solid transparent; white-space: nowrap; }
        .badge-ok { background: #ECFDF5; color: #047857; border-color: #A7F3D0; }
        .badge-off { background: #F4F4F5; color: #52525B; border-color: #E4E4E7; }
        .badge-warn { background: #FFFBEB; color: #B45309; border-color: #FDE68A; }
        .badge-bad { background: #FEF2F2; color: #B91C1C; border-color: #FECACA; }
        .badge-info { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
        .card { border: 1px solid #E4E4E7; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; page-break-inside: avoid; }
        .k { font-size: 8.5px; letter-spacing: .07em; text-transform: uppercase; font-weight: 700; color: #71717A; }
        .v { font-size: 11px; font-weight: 600; margin-top: 2px; color: #09090B; word-wrap: break-word; overflow-wrap: anywhere; }
        .grid td { width: 33.33%; vertical-align: top; padding: 6px 8px 6px 0; }
        .dates td { width: 25%; vertical-align: top; padding: 9px 10px; border: 1px solid #E4E4E7; background: #FAFAFA; }
        .dates { border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px; }
        .dates .v { font-size: 12px; }
        .section { font-size: 10.5px; font-weight: 700; margin: 0 0 8px; text-transform: uppercase; letter-spacing: .05em; }
        .items th { background: #F4F4F5; color: #52525B; font-size: 8.5px; text-transform: uppercase; letter-spacing: .05em; text-align: left; padding: 7px 8px; }
        .items td { padding: 7px 8px; border-bottom: 1px solid #F1F1F3; vertical-align: top; word-wrap: break-word; overflow-wrap: anywhere; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .totals td { padding: 4px 8px; }
        .totals .grand td { font-size: 14px; font-weight: 700; border-top: 2px solid #18181B; padding-top: 8px; }
        .pre { white-space: pre-wrap; word-wrap: break-word; overflow-wrap: anywhere; }
        @if ($engine === 'dompdf')
            .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8px; color: #71717A; }
            .footer .page:after { content: counter(page); }
        @endif
    </style>
</head>
<body>
    <div class="brandbar"></div>

    <table class="head" style="margin-bottom: 12px;">
        <tr>
            @if ($logo)
                <td style="width: 150px;"><img class="logo" src="{{ $logo }}" alt=""></td>
            @endif
            <td>
                <div class="k">Requisición</div>
                <p class="folio">{{ $requisicion->folio }}</p>
                <div class="muted">Registrada {{ $fecha($requisicion->created_at, true) ?? '—' }}</div>
            </td>
            <td style="text-align: right;">
                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                <div style="margin-top: 8px;" class="k">Total</div>
                <div style="font-size: 20px; font-weight: 700;">{{ $money($totalFinalAuditado) }}</div>
            </td>
        </tr>
    </table>

    <table class="dates" style="margin-bottom: 12px;">
        <tr>
            <td style="border-radius: 10px;">
                <div class="k">Fecha de solicitud</div>
                <div class="v">{{ $fecha($requisicion->fecha_solicitud) ?? '—' }}</div>
            </td>
            <td style="border-radius: 10px;">
                <div class="k">Fecha esperada de pago</div>
                <div class="v">{{ $fecha($requisicion->fecha_pago_esperada) ?? 'Sin definir' }}</div>
            </td>
            <td style="border-radius: 10px;">
                <div class="k">Autorización real</div>
                <div class="v">{{ $fecha($requisicion->fecha_autorizacion, true) ?? 'Pendiente' }}</div>
            </td>
            <td style="border-radius: 10px;">
                <div class="k">{{ $pagadoTotal > 0 ? 'Fecha de pago' : 'Pago programado' }}</div>
                <div class="v">{{ $fecha($requisicion->fecha_pago) ?? 'Sin programar' }}</div>
            </td>
        </tr>
    </table>

    <div class="card">
        <table class="grid">
            <tr>
                <td><div class="k">Comprador</div><div class="v">{{ $requisicion->comprador->nombre ?? '—' }}</div></td>
                <td><div class="k">Sucursal</div><div class="v">{{ $requisicion->sucursal->nombre ?? '—' }}</div></td>
                <td><div class="k">Concepto</div><div class="v">{{ $requisicion->concepto->nombre ?? '—' }}</div></td>
            </tr>
            <tr>
                <td><div class="k">Solicitante</div><div class="v">{{ $solicitante }}</div></td>
                <td><div class="k">Capturó</div><div class="v">{{ $requisicion->creadaPor->name ?? '—' }}</div></td>
                <td>
                    <div class="k">Proveedor</div>
                    <div class="v">{{ $requisicion->proveedor->razon_social ?? '—' }}</div>
                    @if ($requisicion->proveedor?->rfc)
                        <div class="muted">RFC {{ $requisicion->proveedor->rfc }}</div>
                    @endif
                </td>
            </tr>
        </table>
        @if (filled($requisicion->observaciones))
            <div class="k" style="margin-top: 6px;">Observaciones</div>
            <div class="v pre" style="font-weight: 500;">{{ $requisicion->observaciones }}</div>
        @endif
    </div>

    <div class="card">
        <p class="section">Partidas</p>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 8%;">Cant.</th>
                    <th style="width: 16%;">Sucursal</th>
                    <th>Descripción</th>
                    <th class="num" style="width: 14%;">Importe</th>
                    <th class="num" style="width: 11%;">IVA</th>
                    <th class="num" style="width: 14%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($requisicion->detalles ?? []) as $d)
                    <tr>
                        <td>{{ rtrim(rtrim(number_format((float) $d->cantidad, 2), '0'), '.') }}</td>
                        <td>{{ $d->sucursal->nombre ?? '—' }}</td>
                        <td class="pre">{{ $d->descripcion ?? '—' }}</td>
                        <td class="num">{{ $money($d->subtotal) }}</td>
                        <td class="num">{{ $money($d->iva) }}</td>
                        <td class="num"><strong>{{ $money($d->total) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted" style="text-align:center; padding: 16px;">Sin partidas.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals" style="width: 46%; margin-left: 54%; margin-top: 10px;">
            <tr><td class="muted">Subtotal</td><td class="num">{{ $money($requisicion->monto_subtotal) }}</td></tr>
            <tr><td class="muted">Total por partidas</td><td class="num">{{ $money($totalItemsOriginal) }}</td></tr>
            <tr><td class="muted">Ajuste neto aplicado</td><td class="num">{{ $money($totalAjustesAplicados) }}</td></tr>
            @if ($pagadoTotal > 0)
                <tr><td class="muted">Pagado</td><td class="num">{{ $money($pagadoTotal) }}</td></tr>
            @endif
            <tr class="grand"><td>Total final</td><td class="num">{{ $money($totalFinalAuditado) }}</td></tr>
        </table>
    </div>

    <div class="card">
        <p class="section">Ajustes de monto</p>
        @if ($ajustes->isEmpty())
            <div class="muted">No hay ajustes registrados.</div>
        @else
            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 15%;">Tipo</th>
                        <th style="width: 11%;">Estatus</th>
                        <th class="num" style="width: 11%;">Impacto</th>
                        <th>Motivo y revisión</th>
                        <th style="width: 23%;">Auditoría</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ajustes as $a)
                        @php $impacto = ($a->sentido === 'A_FAVOR_EMPRESA' ? -1 : 1) * (float) $a->monto; @endphp
                        <tr>
                            <td>{{ $tipoLabel($a->tipo) }}</td>
                            <td><span class="badge {{ $ajusteClass($a->estatus) }}">{{ ucfirst(strtolower($a->estatus)) }}</span></td>
                            <td class="num">{{ $money($impacto) }}</td>
                            <td>
                                <div class="pre">{{ $a->motivo ?? '—' }}</div>
                                @if (filled($a->comentario_revision))
                                    <div class="muted pre" style="margin-top: 4px;"><strong>Revisión:</strong> {{ $a->comentario_revision }}</div>
                                @endif
                            </td>
                            <td class="muted">
                                <div>Solicitó: {{ $a->solicitadoPor?->name ?? '—' }} · {{ $fecha($a->fecha_registro) ?? '—' }}</div>
                                @if ($a->resueltoPor)
                                    <div>Revisó: {{ $a->resueltoPor->name }} · {{ $fecha($a->fecha_resolucion) }}</div>
                                @endif
                                @if ($a->aplicadoPor)
                                    <div>Aplicó: {{ $a->aplicadoPor->name }} · {{ $fecha($a->fecha_aplicacion) }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="muted" style="font-size: 8.5px;">
        Generado el {{ now()->timezone($tz)->format('d/m/Y H:i') }}@if (!empty($generatedBy)) por {{ $generatedBy }}@endif · ERP MR-Lana
    </div>

    @if ($engine === 'dompdf')
        <div class="footer">ERP MR-Lana · {{ $requisicion->folio }} · Página <span class="page"></span></div>
    @endif
</body>
</html>
