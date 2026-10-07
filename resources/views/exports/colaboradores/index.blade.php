@php
    $rows = $rows ?? [];
    $con = collect($rows)->where('con_acceso', true)->count();
    $stats = [
        'Colaboradores' => count($rows),
        'Con acceso' => $con,
        'Sin acceso' => count($rows) - $con,
        'Activos' => collect($rows)->where('activo', true)->count(),
    ];
    $meta = ($meta ?? []) + ['title' => 'Reporte de colaboradores'];
@endphp
@extends('pdf.layouts.report')

@section('content')
    <table class="data">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th style="width:20%">Colaborador</th>
                <th style="width:14%">Corporativo</th>
                <th style="width:13%">Sucursal</th>
                <th style="width:11%">Área</th>
                <th style="width:17%">Correo</th>
                <th>Acceso al sistema</th>
                <th style="width:8%">Estatus</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td>
                        <div class="strong">{{ $r['empleado'] ?? '—' }}</div>
                        <div class="muted">{{ $r['puesto'] ?? '—' }}</div>
                    </td>
                    <td>{{ $r['corporativo'] ?? '—' }}</td>
                    <td>{{ $r['sucursal'] ?? '—' }}</td>
                    <td>{{ $r['area'] ?? '—' }}</td>
                    <td>{{ $r['correo'] ?? '—' }}</td>
                    <td>
                        <span class="badge {{ !empty($r['con_acceso']) ? 'badge-info' : 'badge-off' }}">{{ !empty($r['con_acceso']) ? 'Con acceso' : 'Sin acceso' }}</span>
                        @if (!empty($r['con_acceso']))
                            <div class="muted" style="margin-top:2px;">{{ \Illuminate\Support\Str::after($r['acceso'], 'Con acceso · ') }}</div>
                        @endif
                    </td>
                    <td><span class="badge {{ !empty($r['activo']) ? 'badge-ok' : 'badge-off' }}">{{ !empty($r['activo']) ? 'Activo' : 'Inactivo' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No hay registros con los filtros actuales.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
