@php
    $rows = $rows ?? [];
    $kpis = $kpis ?? [];
    $stats = [
        'Comprobantes' => number_format($kpis['total'] ?? count($rows)),
        'Monto' => '$'.number_format($kpis['monto'] ?? 0, 2),
        'Pendientes' => number_format($kpis['pendientes'] ?? 0),
        'Aprobados' => number_format($kpis['aprobados'] ?? 0),
        'Rechazados' => number_format($kpis['rechazados'] ?? 0),
    ];
    $meta = ($meta ?? []) + ['title' => 'Reporte de comprobantes'];
    $badge = ['APROBADO' => 'badge-ok', 'RECHAZADO' => 'badge-off', 'PENDIENTE' => 'badge-off'];
@endphp
@extends('pdf.layouts.report')

@section('content')
    <table class="data">
        <thead>
            <tr>
                <th style="width:9%">Requisición</th>
                <th style="width:13%">Solicitante</th>
                <th style="width:14%">Proveedor</th>
                <th style="width:10%">Concepto</th>
                <th style="width:7%">Tipo</th>
                <th style="width:8%">Fecha</th>
                <th style="width:9%" class="num">Monto</th>
                <th style="width:8%">Estatus</th>
                <th style="width:11%">Cargó / Revisó</th>
                <th>Archivo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="strong">{{ $r['requisicion']['folio'] ?? '—' }}</td>
                    <td>{{ $r['requisicion']['solicitante'] ?? '—' }}</td>
                    <td>{{ $r['requisicion']['proveedor'] ?? '—' }}</td>
                    <td class="muted">{{ $r['requisicion']['concepto'] ?? '—' }}</td>
                    <td>{{ $r['tipo_label'] }}</td>
                    <td>{{ $r['fecha_emision'] ?? substr((string) $r['created_at'], 0, 10) }}</td>
                    <td class="num">${{ number_format($r['monto'], 2) }}</td>
                    <td><span class="badge {{ $badge[$r['estatus']] ?? 'badge-off' }}">{{ $r['estatus_label'] }}</span></td>
                    <td>{{ $r['user_carga'] ?? '—' }}<br><span class="muted">{{ $r['user_revision'] ?? 'Sin revisar' }}</span></td>
                    <td class="muted" style="word-break:break-all">{{ $r['archivo_original'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty">No hay comprobantes con los filtros actuales.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
