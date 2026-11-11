@php
    $rows = $rows ?? [];
    $activos = collect($rows)->filter(fn ($r) => strtoupper((string) ($r['status'] ?? '')) === 'ACTIVO')->count();
    $stats = [
        'Proveedores' => count($rows),
        'Activos' => $activos,
        'Inactivos' => count($rows) - $activos,
        'Con CLABE' => collect($rows)->filter(fn ($r) => filled($r['clabe'] ?? null))->count(),
    ];
    $meta = ['title' => 'Reporte de proveedores'] + ($meta ?? []);
@endphp
@extends('pdf.layouts.report')

@section('content')
    <table class="data">
        <thead>
            <tr>
                <th style="width: 5%">#</th>
                <th>Razón social</th>
                <th style="width: 15%">RFC</th>
                <th style="width: 21%">CLABE</th>
                <th style="width: 15%">Banco</th>
                <th style="width: 10%">Estatus</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                @php $ok = strtoupper((string) ($r['status'] ?? '')) === 'ACTIVO'; @endphp
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td class="strong">{{ $r['razon_social'] ?? '—' }}</td>
                    <td class="mono">{{ $r['rfc'] ?? '—' }}</td>
                    <td class="mono">{{ $r['clabe'] ?? '—' }}</td>
                    <td>{{ $r['banco'] ?? '—' }}</td>
                    <td><span class="badge {{ $ok ? 'badge-ok' : 'badge-off' }}">{{ $ok ? 'Activo' : 'Inactivo' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No hay registros con los filtros actuales.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
