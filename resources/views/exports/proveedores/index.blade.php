@php
    $rows = $rows ?? [];
    $activos = collect($rows)->filter(fn ($r) => strtoupper((string) ($r['status'] ?? '')) === 'ACTIVO')->count();
    $stats = ['Total' => count($rows), 'Activos' => $activos, 'Inactivos' => count($rows) - $activos];
    $meta = ($meta ?? []) + ['title' => 'Reporte de proveedores'];
@endphp
@extends('pdf.layouts.report')

@section('content')
    <table class="data">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th>Razón social</th>
                <th style="width:15%">RFC</th>
                <th style="width:22%">CLABE</th>
                <th style="width:15%">Banco</th>
                <th style="width:10%">Estatus</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                @php $ok = strtoupper((string) ($r['status'] ?? '')) === 'ACTIVO'; @endphp
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td class="strong">{{ $r['razon_social'] ?? '—' }}</td>
                    <td>{{ $r['rfc'] ?? '—' }}</td>
                    <td style="font-family: monospace;">{{ $r['clabe'] ?? '—' }}</td>
                    <td>{{ $r['banco'] ?? '—' }}</td>
                    <td><span class="badge {{ $ok ? 'badge-ok' : 'badge-off' }}">{{ $ok ? 'Activo' : 'Inactivo' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No hay registros con los filtros actuales.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
