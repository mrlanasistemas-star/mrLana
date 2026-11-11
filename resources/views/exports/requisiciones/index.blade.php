@php
    use App\Services\Dashboard\DashboardDataService;
    use App\Support\Pdf\SvgCharts;
    use App\Support\Pdf\TextMetrics;
    use App\Support\Reports\RequisicionesPdfLayout;
    use App\Support\Reports\RequisicionesSummary;

    /** @var list<array<string, mixed>> $rows  Requisiciones con items, ajustes y totales. */
    $rows = $rows ?? [];
    $summary = ($summary ?? null) instanceof RequisicionesSummary ? $summary : RequisicionesSummary::fromRows($rows);
    $t = $summary->totals;
    $brand = \App\Support\Pdf\PdfAssets::colors();
    $primary = $brand['primary_color'] ?? '#6419FF';
    $otros = '#D4D4D8';
    $chrome = ($pdfEngine ?? 'dompdf') === 'browsershot';
    // DejaVu (DomPDF) solo tiene regular y negrita: un 600 caería en otra fuente.
    $semibold = $chrome ? 600 : 700;

    // Montos: siempre con centavos en indicadores y tablas (en las gráficas, abreviados).
    $money = fn ($v) => ($v === null || $v === '') ? '' : (((float) $v < 0) ? '-$' : '$').number_format(abs((float) $v), 2);
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

    // Indicadores: la cifra usa la letra más grande con la que cabe en la tarjeta (124 px útiles).
    $kpiSize = function (string $s) {
        foreach ([16, 14, 12.5] as $size) {
            if (TextMetrics::width($s, $size, true) <= 122) {
                return $size;
            }
        }

        return 11.5;
    };

    // Puntos para las gráficas de ranking («Otros» en gris).
    $rank = fn (array $groups, int $n) => array_map(fn ($g) => [
        'name' => $g['name'],
        'value' => $g['total'],
    ] + (! empty($g['otros']) ? ['color' => $otros] : []), RequisicionesSummary::top($groups, $n));

    $hasData = $t['count'] > 0;
    $layout = new RequisicionesPdfLayout($rows, $summary);

    // ---- Hoja 1: alto disponible para las dos filas de gráficas ----
    // Medido en DomPDF (la tipografía más alta): encabezado 67 px, filtros
    // 13 + 25 px por renglón de chips, indicadores 109 px y 130 px del marco
    // de los dos renglones de paneles, sobre 703 px útiles y 15 de margen.
    $chipLines = 0;
    $lineW = 0;
    foreach (($filters ?? []) as $k => $v) {
        $vv = trim(is_array($v) ? implode(', ', $v) : (string) $v);
        if ($vv === '') {
            continue;
        }
        $w = TextMetrics::width($k.': ', 9, true) + TextMetrics::width($vv, 9) + 24;
        if ($chipLines === 0 || $lineW + $w > RequisicionesPdfLayout::PAGE_W) {
            $chipLines++;
            $lineW = 0;
        }
        $lineW += $w;
    }
    $chartsBudget = 703 - 15 - 67 - ($chipLines ? 13 + 25 * $chipLines : 0) - 109 - 130;
    $chartsBudget = max(250, min(340, $chartsBudget));
    $chartH1 = (int) floor($chartsBudget * 0.51);
    $chartH2 = (int) floor($chartsBudget - $chartH1);
    // Ranking del segundo renglón: 6 nombres + «Otros», o 5 si el espacio es corto.
    $rankN = $chartH2 >= 132 ? 6 : 5;
    $rowB = (int) floor(($chartH2 - 8) / ($rankN + 1));
    $fontB = $rowB >= 17 ? 10 : 9;
    // Anchos interiores reales de los paneles (para que las gráficas no se escalen).
    [$w1, $w2, $w3] = [336, 281, 209];
    $wRow2 = 275;
    $sucN = max(1, min(6, count($summary->sucursales)));
    $rowSuc = (int) max(15, min(24, floor(($chartH1 - 8) / min(7, $sucN + (count($summary->sucursales) > 6 ? 1 : 0)))));
    $estatusRows = max(1, count($summary->estatus));
    $rowEst = (int) max(14, min(22, floor(($chartH1 - 8) / $estatusRows)));

    $meta = ($meta ?? []) + ['title' => 'Reporte de requisiciones'];
@endphp
@extends('pdf.layouts.report')

@push('styles')
    /* Cada hoja planeada por RequisicionesPdfLayout empieza en una hoja nueva. */
    .pg { page-break-before: always; break-before: page; }
    .gap, .sec.gap { margin-top: 18px; }

    /* Encabezados de sección */
    .sec { margin: 0 0 8px; page-break-after: avoid; break-after: avoid; }
    .sec h2 { font-size: 13px; line-height: 1.45; font-weight: 700; color: #09090B; margin: 0; letter-spacing: -0.01em; }
    .sec p { margin: 2px 0 0; font-size: 9px; line-height: 1.45; color: #71717A; }

    /* Indicadores */
    .kpis-wrap { margin: 0 -7px 8px; }
    table.kpis { width: 100%; border-collapse: separate; border-spacing: 8px 0; table-layout: fixed; }
    table.kpis td { border: 1px solid #E4E4E7; border-radius: 10px; padding: 9px 10px 9px; vertical-align: top; background: #FFFFFF; }
    table.kpis td.lead { background: #FAFAFA; }
    .kpi-label { font-size: 8.5px; color: #71717A; font-weight: {{ $semibold }}; white-space: nowrap; }
    .kpi-value { font-weight: 700; color: #09090B; margin-top: 2px; letter-spacing: -0.02em; white-space: nowrap; font-variant-numeric: tabular-nums; line-height: 1.45; }
    .kpi-hint { font-size: 8.5px; color: #71717A; margin-top: 2px; line-height: 1.4; }
    .meter { height: 4px; background: #F4F4F5; border-radius: 99px; margin-top: 5px; overflow: hidden; }
    .meter div { height: 4px; border-radius: 99px; }

    /* Paneles de gráficas: dos renglones de tres (estilos base en el layout). */
    table.panels { border-spacing: 8px 8px; }
    .panel .hint { margin: 1px 0 6px; }

    /* Tablas: anchos fijos (los calcula RequisicionesPdfLayout) para que el
       acomodo en hojas sea predecible; las filas nunca se parten. */
    table.t { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.t thead { display: table-header-group; }
    table.t th { text-align: left; font-size: 8.5px; line-height: 1.45; font-weight: {{ $semibold }}; color: #52525B; padding: 6px 8px; border-bottom: 1.5px solid #D4D4D8; background: #FAFAFA; }
    table.t td { padding: 5px 8px; font-size: 9px; line-height: 1.45; border-bottom: 1px solid #F1F1F3; vertical-align: top; }
    table.t tr { page-break-inside: avoid; break-inside: avoid; }
    table.t .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    table.t tr.foot td { font-weight: 700; color: #09090B; border-top: 1.5px solid #D4D4D8; border-bottom: 0; background: #FAFAFA; }
    table.t.lista th { padding: 5px 6px; font-size: 8px; }
    table.t.lista td { padding: 4px 6px; font-size: {{ RequisicionesPdfLayout::LIST_FONT }}px; }
    table.t .folio { font-weight: 700; color: #09090B; white-space: nowrap; }
    .share { width: 100%; border-collapse: collapse; }
    .share td { padding: 0 !important; border: 0 !important; vertical-align: middle !important; }
    .share .track { height: 5px; background: #F4F4F5; border-radius: 99px; overflow: hidden; }
    .share .track div { height: 5px; border-radius: 99px; }
    .share .val { width: 40px; text-align: right; font-size: 8.5px; color: #3F3F46; padding-left: 6px !important; }
    .dot { display: inline-block; width: 7px; height: 7px; border-radius: 99px; margin-right: 4px; vertical-align: 0; }
    .muted { color: #71717A; }

    /* Detalle por requisición */
    .req { margin-bottom: 10px; border: 1px solid #E4E4E7; border-radius: 8px; overflow: hidden; @if ($chrome) break-inside: avoid; @endif }
    .req-head { width: 100%; border-collapse: collapse; table-layout: fixed; background: #FAFAFA; }
    .req-head td { padding: 7px 10px; vertical-align: top; font-size: {{ RequisicionesPdfLayout::HEAD_FONT }}px; border-bottom: 1px solid #E4E4E7; line-height: 1.45; }
    .req-head .folio { font-size: 11.5px; font-weight: 700; color: #09090B; white-space: nowrap; }
    .req-head .status { margin-top: 2px; white-space: nowrap; }
    .req-head .paid { margin-top: 2px; }
    .req-cont td { padding: 6px 10px; font-size: 9px; line-height: 1.45; background: #FAFAFA; border-bottom: 1px solid #E4E4E7; }
    .req-cont .folio { font-weight: 700; color: #09090B; }
    .lbl { color: #71717A; }
    table.items { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.items thead { display: table-header-group; }
    table.items tr { page-break-inside: avoid; break-inside: avoid; }
    table.items th { text-align: left; font-size: 8px; line-height: 1.45; font-weight: {{ $semibold }}; color: #52525B; padding: 5px 10px; border-bottom: 1px solid #E4E4E7; background: #FFFFFF; }
    table.items td { padding: 4px 10px; font-size: {{ RequisicionesPdfLayout::ITEM_FONT }}px; line-height: 1.45; border-bottom: 1px solid #F4F4F5; vertical-align: top; }
    table.items .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .row-ajuste td { background: #F5F3FF; }
    .row-ajuste.pendiente td { background: #FFFBEB; color: #71717A; }
    .row-total td { font-weight: 700; color: #09090B; background: #F4F4F5; border-top: 1px solid #D4D4D8; }
    .row-sub td { color: #52525B; }
    .tag { font-size: 7.5px; line-height: 1.45; color: #71717A; }
@endpush

@section('content')
    {{-- ===================== Hoja 1: resumen ===================== --}}
    <div class="kpis-wrap">
    <table class="kpis">
        <tr>
            <td class="lead">
                <div class="kpi-label">Requisiciones</div>
                <div class="kpi-value" style="font-size: 16px;">{{ number_format($t['count']) }}</div>
                <div class="kpi-hint">{{ $plural($t['solicitantes'], 'solicitante', 'solicitantes') }} · {{ $plural($t['proveedores'], 'proveedor', 'proveedores') }}</div>
            </td>
            @foreach ([
                ['Monto solicitado', $t['monto'], 'Promedio de '.$money($t['promedio']), null, true],
                ['Pagado', $t['pagado'], $pct($t['pct_pagado']).' del monto solicitado', ['#10B981', $t['pct_pagado']], false],
                ['Comprobado', $t['comprobado'], $pct($t['pct_comprobado']).' de lo pagado', ['#14B8A6', $t['pct_comprobado']], false],
                ['Pendiente de pago', $t['pendiente_pago'], 'Por pagar en requisiciones vigentes', null, false],
                ['Pendiente de comprobar', $t['pendiente_comprobar'], 'Pagado sin comprobante aprobado', null, false],
            ] as [$label, $value, $hint, $meter, $lead])
                <td @class(['lead' => $lead])>
                    <div class="kpi-label">{{ $label }}</div>
                    <div class="kpi-value" style="font-size: {{ $kpiSize($money($value)) }}px;">{{ $money($value) }}</div>
                    <div class="kpi-hint">{{ $hint }}</div>
                    @if ($meter)
                        <div class="meter"><div style="width: {{ min(100, max(0, $meter[1])) }}%; background: {{ $meter[0] }};"></div></div>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>
    </div>

    @if (! $hasData)
        <table class="t"><tr><td class="empty">No hay requisiciones con los filtros actuales.</td></tr></table>
    @else
        {{-- Seis gráficas en dos renglones dimensionados para caber juntos en la
             primera hoja. Cada SVG se dibuja al ancho real de su panel para que
             el texto no se escale; las etiquetas se miden y se recortan con «…». --}}
        <div class="panels-wrap">
            <table class="panels">
                <tr>
                    <td class="panel" style="width: 40%;">
                        <h3>Requisiciones por estatus</h3>
                        <div class="hint">Cantidad y porcentaje del total</div>
                        <img src="{{ SvgCharts::donut(
                            array_map(fn ($g) => ['name' => $g['name'], 'value' => $g['count']], $summary->estatus),
                            array_column($summary->estatus, 'color'),
                            $w1, $chartH1, 'requisiciones'
                        ) }}" alt="">
                    </td>
                    @if ($summary->spansMonths())
                        <td class="panel" style="width: 34%;">
                            <h3>Monto por mes de registro</h3>
                            <div class="hint">{{ $summary->meses[0]['label'] }} a {{ end($summary->meses)['label'] }}</div>
                            <img src="{{ SvgCharts::bars(array_map(fn ($m) => ['name' => $m['name'], 'value' => $m['total']], $summary->meses), $primary, '', $w2, $chartH1, true) }}" alt="">
                        </td>
                    @else
                        <td class="panel" style="width: 34%;">
                            <h3>Monto por estatus</h3>
                            <div class="hint">Cuánto dinero hay en cada etapa</div>
                            <img src="{{ SvgCharts::hbars(array_map(fn ($g) => ['name' => $g['name'], 'value' => $g['total'], 'color' => $g['color']], $summary->estatus), $primary, true, $w2, 130, $rowEst, 0, true, $rowEst >= 17 ? 10 : 9) }}" alt="">
                        </td>
                    @endif
                    <td class="panel" style="width: 26%;">
                        <h3>Sucursales</h3>
                        <div class="hint">Monto solicitado por sucursal</div>
                        <img src="{{ SvgCharts::hbars($rank($summary->sucursales, 6), $primary, true, $w3, 92, $rowSuc, 0, true, $rowSuc >= 17 ? 10 : 9) }}" alt="">
                    </td>
                </tr>
            </table>
        </div>
        <div class="panels-wrap">
            <table class="panels" style="border-spacing: 8px 0;">
                <tr>
                    <td class="panel">
                        <h3>Solicitantes con mayor monto</h3>
                        <div class="hint">Monto solicitado por persona</div>
                        <img src="{{ SvgCharts::hbars($rank($summary->solicitantes, $rankN), $primary, true, $wRow2, 136, $rowB, 0, true, $fontB) }}" alt="">
                    </td>
                    <td class="panel">
                        <h3>Conceptos principales</h3>
                        <div class="hint">En qué se está pidiendo dinero</div>
                        <img src="{{ SvgCharts::hbars($rank($summary->conceptos, $rankN), $primary, true, $wRow2, 136, $rowB, 0, true, $fontB) }}" alt="">
                    </td>
                    <td class="panel">
                        <h3>Proveedores principales</h3>
                        <div class="hint">A quién se le está pagando</div>
                        <img src="{{ SvgCharts::hbars($rank($summary->proveedores, $rankN), $primary, true, $wRow2, 136, $rowB, 0, true, $fontB) }}" alt="">
                    </td>
                </tr>
            </table>
        </div>

        {{-- ===================== Hojas 2 en adelante =====================
             El reparto lo decide RequisicionesPdfLayout (ver la clase). --}}
        @php
            $cols = $layout->listCols;
            $ic = $layout->itemCols;
            $hc = $layout->headCols;
            // Ancho de columna en % de su tabla: DomPDF, con table-layout fixed, solo respeta porcentajes.
            $pc = fn (int $w, array $all) => round($w / array_sum($all) * 100, 3);
        @endphp
        @foreach ($layout->pages as $blocks)
            <div class="pg">
                @foreach ($blocks as $bi => $b)
                    @if ($b['type'] === 'solicitantes')
                        <div class="sec">
                            <h2>Solicitantes</h2>
                            <p>Requisiciones y montos por persona, de mayor a menor monto{{ count($summary->solicitantes) > RequisicionesPdfLayout::SOLICITANTES_MAX ? '; se muestran los '.RequisicionesPdfLayout::SOLICITANTES_MAX.' principales y el resto en «Otros»' : '' }}.</p>
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
                                @foreach ($layout->solicitantes as $g)
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

                    @elseif ($b['type'] === 'lista')
                        <div @class(['gap' => $bi > 0])>
                            @if ($b['title'])
                                <div class="sec">
                                    <h2>Lista de requisiciones</h2>
                                    <p>{{ $plural($t['count'], 'requisición', 'requisiciones') }} en el orden del listado. El detalle de items y ajustes está al final.</p>
                                </div>
                            @endif
                            <table class="t lista">
                                <thead>
                                    <tr>
                                        <th style="width: {{ $pc($cols['folio'], $cols) }}%;">Folio</th>
                                        <th style="width: {{ $pc($cols['registro'], $cols) }}%;">Registro</th>
                                        <th style="width: {{ $pc($cols['estatus'], $cols) }}%;">Estatus</th>
                                        <th style="width: {{ $pc($cols['solicitante'], $cols) }}%;">Solicitante</th>
                                        <th style="width: {{ $pc($cols['sucursal'], $cols) }}%;">Sucursal</th>
                                        <th style="width: {{ $pc($cols['concepto'], $cols) }}%;">Concepto</th>
                                        <th style="width: {{ $pc($cols['proveedor'], $cols) }}%;">Proveedor</th>
                                        <th class="num" style="width: {{ $pc($cols['monto'], $cols) }}%;">Monto</th>
                                        <th class="num" style="width: {{ $pc($cols['pagado'], $cols) }}%;">Pagado</th>
                                        <th class="num" style="width: {{ $pc($cols['comprobado'], $cols) }}%;">Comprobado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($b['rows'] as $i)
                                        @php $r = $rows[$i]; @endphp
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
                                    @if ($b['foot'])
                                        <tr class="foot">
                                            <td colspan="7">Total · {{ $plural($t['count'], 'requisición', 'requisiciones') }}</td>
                                            <td class="num">{{ $money($t['monto']) }}</td>
                                            <td class="num">{{ $money($t['pagado']) }}</td>
                                            <td class="num">{{ $money($t['comprobado']) }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                    @elseif ($b['type'] === 'req')
                        @php $r = $rows[$b['row']]; @endphp
                        @if ($b['title'])
                            <div class="sec @if ($bi > 0) gap @endif">
                                <h2>Detalle de items y ajustes</h2>
                                <p>Lo que se pidió en cada requisición, los ajustes que cambiaron su monto y el total efectuado.</p>
                            </div>
                        @endif
                        <div class="req">
                            @if ($b['head'] === 'full')
                                <table class="req-head">
                                    <tr>
                                        <td style="width: {{ $pc($hc['main'], $hc) }}%;">
                                            <div class="folio">{{ $r['folio'] }}</div>
                                            <div class="status"><span class="dot" style="background: {{ $statusColor($r['estatus']) }};"></span>{{ $statusLabel($r['estatus']) }}</div>
                                            <div class="paid"><span class="lbl">Pagado:</span> {{ $money($r['pagado']) }}</div>
                                            <div><span class="lbl">Comprobado:</span> {{ $money($r['comprobado']) }}</div>
                                        </td>
                                        <td style="width: {{ $pc($hc['who'], $hc) }}%;">
                                            @if (!empty($r['corporativo']))<div><b>{{ $r['corporativo'] }}</b></div>@endif
                                            @if (!empty($r['sucursal']))<div>{{ $r['sucursal'] }}</div>@endif
                                            @if (!empty($r['solicitante']))<div><span class="lbl">Solicita:</span> {{ $r['solicitante'] }}</div>@endif
                                        </td>
                                        <td style="width: {{ $pc($hc['prov'], $hc) }}%;">
                                            @if (!empty($r['proveedor']))<div><span class="lbl">Proveedor:</span> {{ $r['proveedor'] }}</div>@endif
                                            @if (!empty($r['proveedor_rfc']))<div><span class="lbl">RFC:</span> {{ $r['proveedor_rfc'] }}</div>@endif
                                            @if (!empty($r['concepto']))<div><span class="lbl">Concepto:</span> {{ $r['concepto'] }}</div>@endif
                                        </td>
                                        <td style="width: {{ $pc($hc['dates'], $hc) }}%;">
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
                            @else
                                <table class="req-cont" style="width: 100%; border-collapse: collapse;">
                                    <tr><td><span class="folio">{{ $r['folio'] }}</span> <span class="lbl">(continúa · hoja {{ $b['part'] }} de {{ $b['parts'] }} de esta requisición)</span></td></tr>
                                </table>
                            @endif

                            <table class="items">
                                <thead>
                                    <tr>
                                        <th style="width: {{ $pc($ic['n'], $ic) }}%;">#</th>
                                        <th style="width: {{ $pc($ic['item'], $ic) }}%;">Item</th>
                                        <th class="num" style="width: {{ $pc($ic['cantidad'], $ic) }}%;">Cantidad</th>
                                        <th class="num" style="width: {{ $pc($ic['precio'], $ic) }}%;">Precio unitario</th>
                                        <th class="num" style="width: {{ $pc($ic['subtotal'], $ic) }}%;">Subtotal</th>
                                        <th class="num" style="width: {{ $pc($ic['iva'], $ic) }}%;">IVA</th>
                                        <th class="num" style="width: {{ $pc($ic['importe'], $ic) }}%;">Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($b['items'] as $k)
                                        @php $it = $r['items'][$k]; @endphp
                                        <tr>
                                            <td class="muted">{{ $it['n'] }}</td>
                                            <td>{{ $it['item'] !== '' ? $it['item'] : '—' }}</td>
                                            <td class="num">{{ $qty($it['cantidad']) }}</td>
                                            <td class="num">{{ $money($it['precio_unitario']) }}</td>
                                            <td class="num">{{ $money($it['subtotal']) }}</td>
                                            <td class="num">{{ $it['genera_iva'] ? $money($it['iva']) : 'Sin IVA' }}</td>
                                            <td class="num">{{ $money($it['total']) }}</td>
                                        </tr>
                                    @endforeach

                                    @if ($b['totals'])
                                        @if (empty($r['items']))
                                            <tr><td colspan="7" class="lbl" style="text-align: center;">Sin items registrados.</td></tr>
                                        @endif
                                        @if (RequisicionesPdfLayout::hasAdjustments($r))
                                            <tr class="row-sub">
                                                <td></td>
                                                <td colspan="5">Total de items</td>
                                                <td class="num">{{ $money($r['total_items']) }}</td>
                                            </tr>
                                        @endif

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
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach
    @endif
@endsection
