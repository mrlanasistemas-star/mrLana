@php
    use App\Services\Dashboard\DashboardDataService;

    /** @var list<array<string, mixed>> $rows  Requisiciones con items, ajustes y totales. */
    $rows = $rows ?? [];
    $money = fn ($v) => ($v === null || $v === '') ? '' : (((float) $v < 0) ? '-$' : '$').number_format(abs((float) $v), 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
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
    $badge = fn ($s) => match (true) {
        in_array($s, ['PAGADA', 'COMPROBACION_ACEPTADA'], true) => 'badge-ok',
        in_array($s, ['PAGO_RECHAZADO', 'COMPROBACION_RECHAZADA', 'ELIMINADA'], true) => 'badge-bad',
        in_array($s, ['CAPTURADA', 'POR_COMPROBAR'], true) => 'badge-warn',
        $s === 'BORRADOR' => 'badge-off',
        default => 'badge-info',
    };

    $all = collect($rows);
    $stats = [
        'Requisiciones' => $all->count(),
        'Total de items' => $money($all->sum('total_items')),
        'Ajustes aplicados' => $money($all->sum('ajustes_aplicados')),
        'Total efectuado' => $money($all->sum('total_efectuado')),
        'Pagado' => $money($all->sum('pagado')),
    ];
    $meta = ($meta ?? []) + ['title' => 'Reporte de requisiciones'];
@endphp
@extends('pdf.layouts.report')

@push('styles')
    .req { margin-bottom: 12px; page-break-inside: avoid; border: 1px solid #E4E4E7; border-radius: 6px; }
    .req-head { width: 100%; border-collapse: collapse; background: #FAFAFA; }
    .req-head td { padding: 6px 8px; vertical-align: top; font-size: 8.5px; border-bottom: 1px solid #E4E4E7; }
    .req-head .folio { font-size: 11px; font-weight: 700; color: #09090B; }
    .lbl { color: #71717A; }
    table.items { width: 100%; border-collapse: collapse; }
    table.items th { text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: .03em; color: #52525B; padding: 4px 8px; border-bottom: 1px solid #E4E4E7; background: #fff; }
    table.items td { padding: 4px 8px; font-size: 9px; border-bottom: 1px solid #F4F4F5; vertical-align: top; }
    table.items .num { text-align: right; white-space: nowrap; }
    .row-ajuste td { background: #EFF6FF; }
    .row-ajuste.pendiente td { background: #FFFBEB; color: #71717A; }
    .row-total td { font-weight: 700; color: #09090B; background: #F4F4F5; border-top: 1px solid #D4D4D8; }
    .row-sub td { color: #3F3F46; }
    .tag { font-size: 7.5px; color: #71717A; }
@endpush

@section('content')
    @forelse ($rows as $r)
        <div class="req">
            <table class="req-head">
                <tr>
                    <td style="width: 24%;">
                        <div class="folio">{{ $r['folio'] }}</div>
                        <div style="margin-top: 3px;"><span class="badge {{ $badge($r['estatus']) }}">{{ DashboardDataService::STATUS_LABELS[$r['estatus']] ?? $r['estatus'] }}</span></div>
                    </td>
                    <td style="width: 26%;">
                        @if (!empty($r['corporativo']))<div><b>{{ $r['corporativo'] }}</b></div>@endif
                        @if (!empty($r['sucursal']))<div>{{ $r['sucursal'] }}</div>@endif
                        @if (!empty($r['solicitante']))<div><span class="lbl">Solicita:</span> {{ $r['solicitante'] }}</div>@endif
                    </td>
                    <td style="width: 26%;">
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
                            <td>{{ $it['n'] }}</td>
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
                        <td colspan="5">Pagado · Comprobado (aprobado)</td>
                        <td class="num">{{ $money($r['pagado']) }} · {{ $money($r['comprobado']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <table class="data"><tr><td class="empty">Sin resultados con los filtros actuales.</td></tr></table>
    @endforelse
@endsection
