@php
    use App\Services\Dashboard\DashboardDataService;

    $rows = $rows ?? [];
    $money = fn ($v) => ($v === null || $v === '') ? '' : '$'.number_format((float) $v, 2);
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

    $headers = collect($rows)->filter(fn ($r) => !empty($r['folio']));
    $stats = [
        'Requisiciones' => $headers->count(),
        'Subtotal' => $money($headers->sum(fn ($r) => (float) ($r['subtotal'] ?? 0))),
        'IVA' => $money($headers->sum(fn ($r) => (float) ($r['iva'] ?? 0))),
        'Ajustes netos' => $money($headers->sum(fn ($r) => (float) ($r['ajustes_netos'] ?? 0))),
        'Total final' => $money($headers->sum(fn ($r) => (float) ($r['total_final'] ?? $r['total'] ?? 0))),
    ];
    $meta = ($meta ?? []) + ['title' => 'Reporte de requisiciones'];
@endphp
@extends('pdf.layouts.report')

@push('styles')
    table.data { table-layout: fixed; }
    .row-ajuste td { background: #EFF6FF !important; font-style: italic; }
    .dateRow { font-size: 8px; color: #71717A; margin-top: 2px; }
    .dateRow b { color: #3F3F46; }
    .final { font-weight: 700; color: #09090B; border-top: 1px solid #D4D4D8; margin-top: 3px; padding-top: 3px; }
@endpush

@section('content')
    <table class="data">
        <thead>
            <tr>
                <th style="width: 16%;">Folio y fechas</th>
                <th style="width: 18%;">Origen</th>
                <th style="width: 15%;">Proveedor</th>
                <th style="width: 12%;">Concepto</th>
                <th>Descripción</th>
                <th class="num" style="width: 13%;">Importes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr class="{{ ($r['row_kind'] ?? '') === 'AJUSTE' ? 'row-ajuste' : '' }}">
                    <td>
                        @if (!empty($r['folio']))
                            <div class="strong">{{ $r['folio'] }}</div>
                        @endif
                        @if (!empty($r['estatus']))
                            <div style="margin-top: 3px;"><span class="badge {{ $badge($r['estatus']) }}">{{ DashboardDataService::STATUS_LABELS[$r['estatus']] ?? $r['estatus'] }}</span></div>
                        @endif
                        @if ($d = $date($r['fecha_solicitud'] ?? null))<div class="dateRow"><b>Solicitud:</b> {{ $d }}</div>@endif
                        @if ($d = $date($r['fecha_pago_esperada'] ?? null))<div class="dateRow"><b>Pago esperado:</b> {{ $d }}</div>@endif
                        @if ($d = $date($r['fecha_autorizacion'] ?? null))<div class="dateRow"><b>Autorizada:</b> {{ $d }}</div>@endif
                        @if ($d = $date($r['fecha_pago'] ?? null))<div class="dateRow"><b>Pago:</b> {{ $d }}</div>@endif
                    </td>
                    <td>
                        @if (!empty($r['corporativo']))<div class="strong">{{ $r['corporativo'] }}</div>@endif
                        @if (!empty($r['sucursal']))<div>{{ $r['sucursal'] }}</div>@endif
                        @if (!empty($r['solicitante']))<div class="muted">Solicita: {{ $r['solicitante'] }}</div>@endif
                    </td>
                    <td>{{ $r['proveedor'] ?? '' }}</td>
                    <td>{{ $r['concepto'] ?? '' }}</td>
                    <td>
                        <div>{{ ($r['descripcion_item'] ?? '') !== '' ? $r['descripcion_item'] : '—' }}</div>
                        @if (!empty($r['observaciones']))
                            <div class="muted" style="margin-top: 3px;">Observaciones: {{ $r['observaciones'] }}</div>
                        @endif
                    </td>
                    <td class="num">
                        @if (($r['cantidad'] ?? '') !== '')<div class="muted">Cant. {{ $r['cantidad'] }}</div>@endif
                        @if (($r['total_item'] ?? '') !== '')<div>Partida {{ $money($r['total_item']) }}</div>@endif
                        @if (($r['subtotal'] ?? '') !== '')<div class="muted">Subtotal {{ $money($r['subtotal']) }}</div>@endif
                        @if (($r['iva'] ?? '') !== '')<div class="muted">IVA {{ $money($r['iva']) }}</div>@endif
                        @if (($r['ajustes_netos'] ?? '') !== '' && abs((float) $r['ajustes_netos']) > 0.00001)<div class="muted">Ajuste {{ $money($r['ajustes_netos']) }}</div>@endif
                        @if (($r['total_final'] ?? '') !== '')<div class="final">{{ $money($r['total_final']) }}</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Sin resultados con los filtros actuales.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
