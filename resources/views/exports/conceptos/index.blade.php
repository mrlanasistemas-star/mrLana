@php
    use App\Support\Pdf\PdfFormat as F;

    $rows = $rows ?? [];
    $activos = collect($rows)->where('activo', true)->count();
    $stats = ['Conceptos' => count($rows), 'Activos' => $activos, 'De baja' => count($rows) - $activos];
    $meta = ['title' => 'Reporte de conceptos'] + ($meta ?? []);
@endphp
@extends('pdf.layouts.report')

@section('content')
    <table class="data">
        <thead>
            <tr>
                <th style="width: 8%">ID</th>
                <th>Concepto</th>
                <th style="width: 13%">Estatus</th>
                <th style="width: 15%">Creado</th>
                <th style="width: 15%">Actualizado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="muted">{{ $r['id'] ?? '—' }}</td>
                    <td class="strong">{{ $r['nombre'] ?? '—' }}</td>
                    <td><span class="badge {{ ! empty($r['activo']) ? 'badge-ok' : 'badge-off' }}">{{ ! empty($r['activo']) ? 'Activo' : 'De baja' }}</span></td>
                    <td class="muted nowrap">{{ F::date($r['created_at'] ?? null) }}</td>
                    <td class="muted nowrap">{{ F::date($r['updated_at'] ?? null) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Sin resultados con los filtros actuales.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
