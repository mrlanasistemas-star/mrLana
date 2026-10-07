@php
    $rows = $rows ?? [];
    $activos = collect($rows)->filter(fn ($r) => !empty($r['activo']))->count();
    $stats = ['Total' => count($rows), 'Activas' => $activos, 'De baja' => count($rows) - $activos];
    $meta = ($meta ?? []) + ['title' => 'Reporte de sucursales'];
@endphp
@extends('pdf.layouts.report')

@section('content')
    <table class="data">
        <thead>
            <tr>
                <th style="width:17%">Corporativo</th>
                <th style="width:17%">Sucursal</th>
                <th style="width:9%">Código</th>
                <th style="width:12%">Ciudad</th>
                <th style="width:11%">Estado</th>
                <th>Dirección</th>
                <th style="width:9%">Estatus</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['corporativo'] ?? '—' }}</td>
                    <td class="strong">{{ $r['sucursal'] ?? '—' }}</td>
                    <td>{{ $r['codigo'] ?? '—' }}</td>
                    <td>{{ $r['ciudad'] ?? '—' }}</td>
                    <td>{{ $r['estado'] ?? '—' }}</td>
                    <td class="muted">{{ $r['direccion'] ?? '—' }}</td>
                    <td><span class="badge {{ !empty($r['activo']) ? 'badge-ok' : 'badge-off' }}">{{ !empty($r['activo']) ? 'Activa' : 'De baja' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Sin resultados con los filtros actuales.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
