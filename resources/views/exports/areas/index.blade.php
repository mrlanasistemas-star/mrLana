@php
    use App\Support\Pdf\PdfFormat as F;

    $rows = $rows ?? [];
    $activos = collect($rows)->where('activo', true)->count();
    $stats = [
        'Áreas' => count($rows),
        'Activas' => $activos,
        'De baja' => count($rows) - $activos,
        'Corporativos' => collect($rows)->pluck('corporativo')->filter()->unique()->count(),
    ];
    $meta = ['title' => 'Reporte de áreas'] + ($meta ?? []);
@endphp
@extends('pdf.layouts.report')

@section('content')
    <table class="data">
        <thead>
            <tr>
                <th style="width: 7%">ID</th>
                <th style="width: 30%">Corporativo</th>
                <th>Área</th>
                <th style="width: 11%">Estatus</th>
                <th style="width: 13%">Creada</th>
                <th style="width: 13%">Actualizada</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="muted">{{ $r['id'] ?? '—' }}</td>
                    <td>{{ $r['corporativo'] ?? '—' }}</td>
                    <td class="strong">{{ $r['nombre'] ?? '—' }}</td>
                    <td><span class="badge {{ ! empty($r['activo']) ? 'badge-ok' : 'badge-off' }}">{{ ! empty($r['activo']) ? 'Activa' : 'De baja' }}</span></td>
                    <td class="muted nowrap">{{ F::date($r['created_at'] ?? null) }}</td>
                    <td class="muted nowrap">{{ F::date($r['updated_at'] ?? null) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Sin resultados con los filtros actuales.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
