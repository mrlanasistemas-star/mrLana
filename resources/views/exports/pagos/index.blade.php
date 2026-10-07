@php
    $rows = $rows ?? [];
    $kpis = $kpis ?? [];
    $stats = [
        'Pagos' => number_format($kpis['total'] ?? count($rows)),
        'Monto pagado' => '$'.number_format($kpis['monto'] ?? 0, 2),
        'Requisiciones' => number_format($kpis['requisiciones'] ?? 0),
        'Transferencias' => '$'.number_format($kpis['transferencias'] ?? 0, 2),
    ];
    $meta = ($meta ?? []) + ['title' => 'Reporte de pagos'];
@endphp
@extends('pdf.layouts.report')

@section('content')
    <table class="data">
        <thead>
            <tr>
                <th style="width:9%">Requisición</th>
                <th style="width:12%">Solicitante</th>
                <th style="width:15%">Beneficiario</th>
                <th style="width:10%">Banco / cuenta</th>
                <th style="width:8%">Tipo</th>
                <th style="width:8%">Fecha</th>
                <th style="width:9%" class="num">Monto</th>
                <th style="width:10%">Referencia</th>
                <th style="width:10%">Registró</th>
                <th>Autorizó</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="strong">{{ $r['requisicion']['folio'] ?? '—' }}</td>
                    <td>{{ $r['requisicion']['solicitante'] ?? '—' }}</td>
                    <td>{{ $r['beneficiario'] ?? '—' }}</td>
                    <td class="muted">{{ $r['banco'] ?? '—' }}<br>{{ $r['cuenta'] ?? '' }}</td>
                    <td>{{ $r['tipo_label'] }}</td>
                    <td>{{ $r['fecha_pago'] ?? '—' }}</td>
                    <td class="num">${{ number_format($r['monto'], 2) }}</td>
                    <td class="muted" style="word-break:break-all">{{ $r['referencia'] ?? '—' }}</td>
                    <td>{{ $r['user_carga'] ?? '—' }}</td>
                    <td>{{ $r['requisicion']['autorizo'] ?? 'No registrado' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty">No hay pagos con los filtros actuales.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
