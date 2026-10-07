@php
    $rows = $rows ?? [];
    $activos = collect($rows)->filter(fn ($r) => !empty($r['activo']))->count();
    $stats = [
        'Corporativos' => count($rows),
        'Activos' => $activos,
        'Sucursales' => collect($rows)->sum(fn ($r) => (int) ($r['sucursales_count'] ?? 0)),
        'Áreas' => collect($rows)->sum(fn ($r) => (int) ($r['areas_count'] ?? 0)),
    ];
    $meta = ($meta ?? []) + ['title' => 'Reporte de corporativos'];
@endphp
@extends('pdf.layouts.report')

@section('content')
    <table class="data">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th style="width:16%">Corporativo</th>
                <th style="width:10%">RFC</th>
                <th style="width:7%">Código</th>
                <th style="width:10%">Teléfono</th>
                <th style="width:15%">Correo</th>
                <th>Dirección</th>
                <th style="width:7%" class="num">Sucursales</th>
                <th style="width:6%" class="num">Áreas</th>
                <th style="width:8%">Estatus</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                @php $ok = !empty($r['activo']); @endphp
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td class="strong">{{ $r['nombre'] ?? '—' }}</td>
                    <td>{{ $r['rfc'] ?? '—' }}</td>
                    <td>{{ $r['codigo'] ?? '—' }}</td>
                    <td>{{ $r['telefono'] ?? '—' }}</td>
                    <td>{{ $r['email'] ?? '—' }}</td>
                    <td class="muted">{{ $r['direccion'] ?? '—' }}</td>
                    <td class="num">{{ $r['sucursales_count'] ?? 0 }}</td>
                    <td class="num">{{ $r['areas_count'] ?? 0 }}</td>
                    <td><span class="badge {{ $ok ? 'badge-ok' : 'badge-off' }}">{{ $r['estatus_label'] ?? ($ok ? 'Activo' : 'De baja') }}</span></td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty">No hay registros con los filtros actuales.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
