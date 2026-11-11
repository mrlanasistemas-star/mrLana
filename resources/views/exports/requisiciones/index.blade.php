@php
    use App\Services\Dashboard\DashboardDataService;
    use App\Support\Pdf\SvgCharts;
    use App\Support\Reports\RequisicionesSummary;

    /** @var list<array<string, mixed>> $rows  Requisiciones con items, ajustes y totales. */
    $rows = $rows ?? [];
    $summary = ($summary ?? null) instanceof RequisicionesSummary ? $summary : RequisicionesSummary::fromRows($rows);
    $t = $summary->totals;
    $brand = \App\Support\Pdf\PdfAssets::colors();
    $primary = $brand['primary_color'] ?? '#6419FF';
    $otros = '#D4D4D8';
    // DejaVu (DomPDF) solo tiene regular y negrita: un 600 caería en otra fuente.
    $semibold = (($pdfEngine ?? 'dompdf') === 'browsershot') ? 600 : 700;

    $money = fn ($v) => ($v === null || $v === '') ? '' : (((float) $v < 0) ? '-$' : '$').number_format(abs((float) $v), 2);
    $bigMoney = fn ($v) => (((float) $v < 0) ? '-$' : '$').number_format(abs((float) $v), abs((float) $v) >= 1_000_000 ? 0 : 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.').'%';
    $date = function ($v, $format = 'd/m/Y') {
        if (empty($v)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($v)->format($format);
        } catch (\Throwable) {
            return (string) $v;
        }
    };
    $statusLabel = fn ($s) => DashboardDataService::STATUS_LABELS[$s] ?? $s;
    $statusColor = fn ($s) => RequisicionesSummary::STATUS_COLORS[$s] ?? '#A1A1AA';
    $plural = fn (int $n, string $one, string $many) => number_format($n).' '.($n === 1 ? $one : $many);

    // Puntos para las gráficas de ranking («Otros» en gris).
    $rank = fn (array $groups, int $n) => array_map(fn ($g) => [
        'name' => $g['name'],
        'value' => $g['total'],
    ] + (! empty($g['otros']) ? ['color' => $otros] : []), RequisicionesSummary::top($groups, $n));

    $hasData = $t['count'] > 0;
    $solicitantesTabla = RequisicionesSummary::top($summary->solicitantes, 15);
    $meta = ($meta ?? []) + ['title' => 'Reporte de requisiciones'];
@endphp
@extends('pdf.layouts.report')

@push('styles')
    .chips { margin-bottom: 14px; }

    /* Encabezados de sección */
    .sec { margin: 0 0 10px; }
    .sec h2 { font-size: 14px; font-weight: 700; color: #09090B; margin: 0; letter-spacing: -0.01em; }
    .sec p { margin: 2px 0 0; font-size: 9.5px; color: #71717A; }
    .new-page { page-break-before: always; break-before: page; }

    /* Indicadores */
    .kpis-wrap { margin: 0 -8px 8px; }
    table.kpis { width: 100%; border-collapse: separate; border-spacing: 8px 0; table-layout: fixed; }
    table.kpis td { border: 1px solid #E4E4E7; border-radius: 10px; padding: 10px 12px 11px; vertical-align: top; background: #FFFFFF; }
    table.kpis td.lead { background: #FAFAFA; }
    .kpi-label { font-size: 9px; color: #71717A; font-weight: {{ $semibold }}; }
    .kpi-value { font-size: 17px; font-weight: 700; color: #09090B; margin-top: 3px; letter-spacing: -0.02em; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .kpi-hint { font-size: 8.5px; color: #71717A; margin-top: 3px; }
    .meter { height: 5px; background: #F4F4F5; border-radius: 99px; margin-top: 7px; overflow: hidden; }
    .meter div { height: 5px; border-radius: 99px; }

    /* Paneles de gráficas */
    .panels-wrap { page-break-inside: avoid; break-inside: avoid; margin: 0 -8px 6px; }
    table.panels { width: 100%; border-collapse: separate; border-spacing: 8px 8px; table-layout: fixed; }
    table.panels td.panel { border: 1px solid #E4E4E7; border-radius: 10px; padding: 12px 14px 10px; vertical-align: top; background: #FFFFFF; }
    .panel h3 { font-size: 11px; font-weight: 700; color: #18181B; margin: 0; }
    .panel .hint { font-size: 8.5px; color: #71717A; margin: 1px 0 8px; }
    .panel img { width: 100%; height: auto; display: block; }
    .panel .none { color: #A1A1AA; font-size: 9px; padding: 18px 0; text-align: center; }

    /* Tablas */
    table.t { width: 100%; border-collapse: collapse; }
    table.t thead { display: table-header-group; }
    table.t th { text-align: left; font-size: 8.5px; font-weight: {{ $semibold }}; color: #52525B; padding: 6px 8px; border-bottom: 1.5px solid #D4D4D8; background: #FAFAFA; }
    table.t td { padding: 5px 8px; font-size: 9px; border-bottom: 1px solid #F1F1F3; vertical-align: top; }
    table.t tr { page-break-inside: avoid; break-inside: avoid; }
    table.t .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    table.t tr.foot td { font-weight: 700; color: #09090B; border-top: 1.5px solid #D4D4D8; border-bottom: 0; background: #FAFAFA; }
    table.t.lista th, table.t.lista td { padding-left: 6px; padding-right: 6px; }
    table.t.lista td { font-size: 8.5px; }
    table.t .folio { font-weight: 700; color: #09090B; white-space: nowrap; }
    table.t .sub { display: block; font-size: 8px; color: #A1A1AA; }
    .share { width: 100%; border-collapse: collapse; }
    .share td { padding: 0 !important; border: 0 !important; vertical-align: middle !important; }
    .share .track { height: 5px; background: #F4F4F5; border-radius: 99px; overflow: hidden; }
    .share .track div { height: 5px; border-radius: 99px; }
    .share .val { width: 40px; text-align: right; font-size: 8.5px; color: #3F3F46; padding-left: 6px !important; }
    .status { white-space: nowrap; }
    .dot { display: inline-block; width: 7px; height: 7px; border-radius: 99px; margin-right: 4px; vertical-align: 0; }
    .muted { color: #71717A; }

    /* Detalle por requisición */
    .req { margin-bottom: 10px; page-break-inside: avoid; break-inside: avoid; border: 1px solid #E4E4E7; border-radius: 8px; overflow: hidden; }
    .req-head { width: 100%; border-collapse: collapse; background: #FAFAFA; }
    .req-head td { padding: 7px 10px; vertical-align: top; font-size: 8.5px; border-bottom: 1px solid #E4E4E7; line-height: 1.5; }
    .req-head .folio { font-size: 11.5px; font-weight: 700; color: #09090B; }
    .lbl { color: #71717A; }
    table.items { width: 100%; border-collapse: collapse; }
    table.items th { text-align: left; font-size: 8px; font-weight: {{ $semibold }}; color: #52525B; padding: 5px 10px; border-bottom: 1px solid #E4E4E7; background: #FFFFFF; }
    table.items td { padding: 4px 10px; font-size: 9px; border-bottom: 1px solid #F4F4F5; vertical-align: top; }
    table.items .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .row-ajuste td { background: #F5F3FF; }
    .row-ajuste.pendiente td { background: #FFFBEB; color: #71717A; }
    .row-total td { font-weight: 700; color: #09090B; background: #F4F4F5; border-top: 1px solid #D4D4D8; }
    .row-sub td { color: #52525B; }
    .tag { font-size: 7.5px; color: #71717A; }
@endpush

@section('content')
    {{-- ===================== Resumen ===================== --}}
    <div class="kpis-wrap">
    <table class="kpis">
        <tr>
            <td class="lead">
                <div class="kpi-label">Requisiciones</div>
                <div class="kpi-value">{{ number_format($t['count']) }}</div>
                <div class="kpi-hint">{{ $plural($t['solicitantes'], 'solicitante', 'solicitantes') }} · {{ $plural($t['proveedores'], 'proveedor', 'proveedores') }}</div>
            </td>
            <td class="lead">
                <div class="kpi-label">Monto solicitado</div>
                <div class="kpi-value">{{ $bigMoney($t['monto']) }}</div>
                <div class="kpi-hint">Promedio de {{ $money($t['promedio']) }} por requisición</div>
            </td>
            <td>
                <div class="kpi-label">Pagado</div>
                <div class="kpi-value">{{ $bigMoney($t['pagado']) }}</div>
                <div class="kpi-hint">{{ $pct($t['pct_pagado']) }} del monto solicitado</div>
                <div class="meter"><div style="width: {{ min(100, $t['pct_pagado']) }}%; background: #10B981;"></div></div>
            </td>
            <td>
                <div class="kpi-label">Comprobado</div>
                <div class="kpi-value">{{ $bigMoney($t['comprobado']) }}</div>
                <div class="kpi-hint">{{ $pct($t['pct_comprobado']) }} de lo pagado</div>
                <div class="meter"><div style="width: {{ min(100, $t['pct_comprobado']) }}%; background: #14B8A6;"></div></div>
            </td>
            <td>
                <div class="kpi-label">Pendiente de pago</div>
                <div class="kpi-value">{{ $bigMoney($t['pendiente_pago']) }}</div>
                <div class="kpi-hint">Por pagar en requisiciones vigentes</div>
            </td>
            <td>
                <div class="kpi-label">Pendiente de comprobar</div>
                <div class="kpi-value">{{ $bigMoney($t['pendiente_comprobar']) }}</div>
                <div class="kpi-hint">Pagado sin comprobante aprobado</div>
            </td>
        </tr>
    </table>
    </div>

    @if (! $hasData)
        <table class="t"><tr><td class="empty">No hay requisiciones con los filtros actuales.</td></tr></table>
    @else
        <div class="panels-wrap">
            <table class="panels">
                <tr>
                    <td class="panel" style="width: 50%;">
                        <h3>Requisiciones por estatus</h3>
                        <div class="hint">Cantidad y porcentaje del total de requisiciones</div>
                        <img src="{{ SvgCharts::donut(
                            array_map(fn ($g) => ['name' => $g['name'], 'value' => $g['count']], $summary->estatus),
                            array_column($summary->estatus, 'color'),
                            440, max(180, 24 + count($summary->estatus) * 20), 'requisiciones'
                        ) }}" alt="">
                    </td>
                    <td class="panel" style="width: 50%;">
                        <h3>Solicitantes con mayor monto</h3>
                        <div class="hint">Monto solicitado por persona</div>
                        <img src="{{ SvgCharts::hbars($rank($summary->solicitantes, 7), $primary, true, 440, 150) }}" alt="">
                    </td>
                </tr>
            </table>
        </div>

        <div class="panels-wrap">
            <table class="panels">
                <tr>
                    <td class="panel" style="width: 50%;">
                        <h3>Conceptos principales</h3>
                        <div class="hint">En qué se está pidiendo dinero</div>
                        <img src="{{ SvgCharts::hbars($rank($summary->conceptos, 6), $primary, true, 440, 150) }}" alt="">
                    </td>
                    <td class="panel" style="width: 50%;">
                        <h3>Proveedores principales</h3>
                        <div class="hint">A quién se le está pagando</div>
                        <img src="{{ SvgCharts::hbars($rank($summary->proveedores, 6), $primary, true, 440, 150) }}" alt="">
                    </td>
                </tr>
            </table>
        </div>

        <div class="panels-wrap">
            <table class="panels">
                <tr>
                    @if ($summary->spansMonths())
                        <td class="panel" style="width: 50%;">
                            <h3>Monto por mes de registro</h3>
                            <div class="hint">{{ $summary->meses[0]['label'] }} a {{ end($summary->meses)['label'] }}</div>
                            <img src="{{ SvgCharts::bars(array_map(fn ($m) => ['name' => $m['name'], 'value' => $m['total']], $summary->meses), $primary, '', 440, 190, true) }}" alt="">
                        </td>
                    @else
                        <td class="panel" style="width: 50%;">
                            <h3>Monto por estatus</h3>
                            <div class="hint">Cuánto dinero hay en cada etapa</div>
                            <img src="{{ SvgCharts::hbars(array_map(fn ($g) => ['name' => $g['name'], 'value' => $g['total'], 'color' => $g['color']], $summary->estatus), $primary, true, 440, 150) }}" alt="">
                        </td>
                    @endif
                    <td class="panel" style="width: 50%;">
                        <h3>Sucursales</h3>
                        <div class="hint">Monto solicitado por sucursal</div>
                        <img src="{{ SvgCharts::hbars($rank($summary->sucursales, 6), $primary, true, 440, 150) }}" alt="">
                    </td>
                </tr>
            </table>
        </div>

        {{-- Tabla de solicitantes --}}
        <div style="page-break-inside: avoid; break-inside: avoid; margin-top: 8px;">
            <div class="sec">
                <h2>Solicitantes</h2>
                <p>Requisiciones y montos por persona, de mayor a menor monto{{ count($summary->solicitantes) > 15 ? '; se muestran los 15 principales' : '' }}.</p>
            </div>
            <table class="t">
                <thead>
                    <tr>
                        <th>Solicitante</th>
                        <th class="num" style="width: 10%;">Requisiciones</th>
                        <th class="num" style="width: 13%;">Monto</th>
                        <th class="num" style="width: 13%;">Pagado</th>
                        <th class="num" style="width: 13%;">Comprobado</th>
                        <th class="num" style="width: 13%;">Por comprobar</th>
                        <th style="width: 17%;">% del monto total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($solicitantesTabla as $g)
                        <tr>
                            <td class="{{ ! empty($g['otros']) || $g['vacio'] ? 'muted' : '' }}">{{ $g['name'] }}</td>
                            <td class="num">{{ number_format($g['count']) }}</td>
                            <td class="num">{{ $money($g['total']) }}</td>
                            <td class="num">{{ $money($g['pagado']) }}</td>
                            <td class="num">{{ $money($g['comprobado']) }}</td>
                            <td class="num">{{ $money(max(0, $g['pagado'] - $g['comprobado'])) }}</td>
                            <td>
                                <table class="share"><tr>
                                    <td><div class="track"><div style="width: {{ min(100, max(0, $g['pct'])) }}%; background: {{ ! empty($g['otros']) ? '#A1A1AA' : $primary }};"></div></div></td>
                                    <td class="val">{{ $pct($g['pct']) }}</td>
                                </tr></table>
                            </td>
                        </tr>
                    @endforeach
                    <tr class="foot">
                        <td>Total</td>
                        <td class="num">{{ number_format($t['count']) }}</td>
                        <td class="num">{{ $money($t['monto']) }}</td>
                        <td class="num">{{ $money($t['pagado']) }}</td>
                        <td class="num">{{ $money($t['comprobado']) }}</td>
                        <td class="num">{{ $money(max(0, $t['pagado'] - $t['comprobado'])) }}</td>
                        <td class="num">100%</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ===================== Lista ===================== --}}
        <div class="new-page">
            <div class="sec">
                <h2>Lista de requisiciones</h2>
                <p>{{ $plural($t['count'], 'requisición', 'requisiciones') }} en el orden del listado. El detalle de items y ajustes está al final.</p>
            </div>
            <table class="t lista">
                <thead>
                    <tr>
                        <th style="width: 10%;">Folio</th>
                        <th style="width: 6.5%;">Registro</th>
                        <th style="width: 12.5%;">Estatus</th>
                        <th style="width: 12%;">Solicitante</th>
                        <th style="width: 8%;">Sucursal</th>
                        <th style="width: 10%;">Concepto</th>
                        <th>Proveedor</th>
                        <th class="num" style="width: 8%;">Monto</th>
                        <th class="num" style="width: 8%;">Pagado</th>
                        <th class="num" style="width: 8%;">Comprobado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td class="folio">{{ $r['folio'] }}</td>
                            <td>{{ $date($r['fecha_captura']) ?? '—' }}</td>
                            <td class="status"><span class="dot" style="background: {{ $statusColor($r['estatus']) }};"></span>{{ $statusLabel($r['estatus']) }}</td>
                            <td>{{ $r['solicitante'] ?: '—' }}</td>
                            <td>{{ $r['sucursal'] ?: '—' }}</td>
                            <td>{{ $r['concepto'] ?: '—' }}</td>
                            <td>{{ $r['proveedor'] ?: '—' }}</td>
                            <td class="num">{{ $money($r['total_efectuado']) }}</td>
                            <td class="num">{{ $money($r['pagado']) }}</td>
                            <td class="num">{{ $money($r['comprobado']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="foot">
                        <td colspan="7">Total · {{ $plural($t['count'], 'requisición', 'requisiciones') }}</td>
                        <td class="num">{{ $money($t['monto']) }}</td>
                        <td class="num">{{ $money($t['pagado']) }}</td>
                        <td class="num">{{ $money($t['comprobado']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ===================== Detalle ===================== --}}
        <div class="new-page">
            <div class="sec">
                <h2>Detalle de items y ajustes</h2>
                <p>Lo que se pidió en cada requisición, los ajustes que cambiaron su monto y el total efectuado.</p>
            </div>

            @foreach ($rows as $r)
                <div class="req">
                    <table class="req-head">
                        <tr>
                            <td style="width: 22%;">
                                <div class="folio">{{ $r['folio'] }}</div>
                                <div class="status" style="margin-top: 2px;"><span class="dot" style="background: {{ $statusColor($r['estatus']) }};"></span>{{ $statusLabel($r['estatus']) }}</div>
                            </td>
                            <td style="width: 26%;">
                                @if (!empty($r['corporativo']))<div><b>{{ $r['corporativo'] }}</b></div>@endif
                                @if (!empty($r['sucursal']))<div>{{ $r['sucursal'] }}</div>@endif
                                @if (!empty($r['solicitante']))<div><span class="lbl">Solicita:</span> {{ $r['solicitante'] }}</div>@endif
                            </td>
                            <td style="width: 28%;">
                                @if (!empty($r['proveedor']))<div><span class="lbl">Proveedor:</span> {{ $r['proveedor'] }}</div>@endif
                                @if (!empty($r['proveedor_rfc']))<div><span class="lbl">RFC:</span> {{ $r['proveedor_rfc'] }}</div>@endif
                                @if (!empty($r['concepto']))<div><span class="lbl">Concepto:</span> {{ $r['concepto'] }}</div>@endif
                            </td>
                            <td>
                                @if ($d = $date($r['fecha_solicitud']))<div><span class="lbl">Solicitud:</span> {{ $d }}</div>@endif
                                @if ($d = $date($r['fecha_pago_esperada']))<div><span class="lbl">Pago esperado:</span> {{ $d }}</div>@endif
                                @if ($d = $date($r['fecha_autorizacion']))<div><span class="lbl">Autorizada:</span> {{ $d }}</div>@endif
                                @if ($d = $date($r['fecha_pago']))<div><span class="lbl">Pago:</span> {{ $d }}</div>@endif
                            </td>
                        </tr>
                        @if (!empty($r['observaciones']))
                            <tr><td colspan="4"><span class="lbl">Observaciones:</span> {{ $r['observaciones'] }}</td></tr>
                        @endif
                    </table>

                    <table class="items">
                        <thead>
                            <tr>
                                <th style="width: 4%;">#</th>
                                <th>Item</th>
                                <th class="num" style="width: 9%;">Cantidad</th>
                                <th class="num" style="width: 13%;">Precio unitario</th>
                                <th class="num" style="width: 12%;">Subtotal</th>
                                <th class="num" style="width: 11%;">IVA</th>
                                <th class="num" style="width: 13%;">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($r['items'] as $it)
                                <tr>
                                    <td class="muted">{{ $it['n'] }}</td>
                                    <td>{{ $it['item'] !== '' ? $it['item'] : '—' }}</td>
                                    <td class="num">{{ $qty($it['cantidad']) }}</td>
                                    <td class="num">{{ $money($it['precio_unitario']) }}</td>
                                    <td class="num">{{ $money($it['subtotal']) }}</td>
                                    <td class="num">{{ $it['genera_iva'] ? $money($it['iva']) : 'Sin IVA' }}</td>
                                    <td class="num">{{ $money($it['total']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="lbl" style="text-align: center;">Sin items registrados.</td></tr>
                            @endforelse

                            <tr class="row-sub">
                                <td></td>
                                <td colspan="5">Total de items</td>
                                <td class="num">{{ $money($r['total_items']) }}</td>
                            </tr>

                            @foreach ($r['ajustes'] as $a)
                                <tr class="row-ajuste {{ $a['aplicado'] ? '' : 'pendiente' }}">
                                    <td></td>
                                    <td colspan="5">
                                        <b>{{ $a['tipo'] }}</b>@if ($a['motivo'] !== ''): {{ $a['motivo'] }}@endif
                                        <div class="tag">{{ $a['estatus'] }}@if ($d = $date($a['fecha'])) · {{ $d }}@endif{{ $a['aplicado'] ? '' : ' · no afecta el total' }}</div>
                                    </td>
                                    <td class="num">{{ $money($a['monto']) }}</td>
                                </tr>
                            @endforeach

                            @if (abs($r['otras_diferencias']) > 0.004)
                                <tr class="row-ajuste">
                                    <td></td>
                                    <td colspan="5">Diferencia sin ajuste registrado <span class="tag">(registros anteriores)</span></td>
                                    <td class="num">{{ $money($r['otras_diferencias']) }}</td>
                                </tr>
                            @endif

                            <tr class="row-total">
                                <td></td>
                                <td colspan="5">Total efectuado</td>
                                <td class="num">{{ $money($r['total_efectuado']) }}</td>
                            </tr>
                            <tr class="row-sub">
                                <td></td>
                                <td colspan="5">Pagado · comprobado (aprobado)</td>
                                <td class="num">{{ $money($r['pagado']) }} · {{ $money($r['comprobado']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endif
@endsection
