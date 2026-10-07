@php
    $rows = $rows ?? [];
    $activos = collect($rows)->where('activo', true)->count();
    $stats = ['Total' => count($rows), 'Activas' => $activos, 'De baja' => count($rows) - $activos];
    $meta = ($meta ?? []) + ['title' => 'Reporte de áreas'];
@endphp
@extends('pdf.layouts.report')

@section('content')
    <table class="data">
        <thead>
            <tr>
                <th style="width:8%">ID</th>
                <th style="width:32%">Corporativo</th>
                <th>Área</th>
                <th style="width:11%">Estatus</th>
                <th style="width:13%">Creado</th>
                <th style="width:13%">Actualizado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="muted">{{ $r['id'] ?? '—' }}</td>
                    <td>{{ $r['corporativo'] ?? '—' }}</td>
                    <td class="strong">{{ $r['nombre'] ?? '—' }}</td>
                    <td>
                        <span class="badge {{ !empty($r['activo']) ? 'badge-ok' : 'badge-off' }}">{{ !empty($r['activo']) ? 'Activa' : 'De baja' }}</span>
                    </td>
                    <td class="muted">{{ $r['created_at'] ?? '—' }}</td>
                    <td class="muted">{{ $r['updated_at'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Sin resultados con los filtros actuales.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
