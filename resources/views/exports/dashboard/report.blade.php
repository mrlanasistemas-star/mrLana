@php
    $meta = [
        'title' => $data['headline'] ?? 'Dashboard',
        'subtitle' => ($data['subheadline'] ?? '').' Periodo: '.($data['period']['from'] ?? '').' – '.($data['period']['to'] ?? ''),
        'generated_at' => $generatedAt ?? \App\Support\BusinessDate::now()->format('d/m/Y H:i'),
        'generated_by' => trim(($data['userName'] ?? '').(!empty($data['userRole']) ? ' · '.$data['userRole'] : '')),
        'footer_left' => 'ERP MR-Lana · Reporte de dashboard',
    ];
    $stats = collect($data['kpis'] ?? [])->mapWithKeys(fn ($k) => [$k['label'] => $k['value']])->all();
    $activityTotal = collect($data['activityDaily'] ?? [])->sum('value');
    $amountTotal = collect($data['amountsDaily'] ?? [])->sum('value');
@endphp
@extends('pdf.layouts.report')

@push('styles')
    .charts { width: 100%; border-collapse: separate; border-spacing: 10px; margin: 0 -10px; }
    .chart-card { border: 1px solid #E4E4E7; border-radius: 12px; padding: 12px 14px; vertical-align: top; background: #FFFFFF; page-break-inside: avoid; }
    .chart-title { font-size: 11px; font-weight: 700; color: #09090B; margin: 0; }
    .chart-desc { font-size: 9px; color: #71717A; margin: 2px 0 8px; }
    .chart-img { width: 100%; height: auto; display: block; }
    .kpi-hint { font-size: 8.5px; color: #71717A; margin-top: 2px; }
@endpush

@section('content')
    <table class="charts">
        <tr>
            <td class="chart-card" style="width: 50%;">
                <p class="chart-title">Requisiciones por día</p>
                <p class="chart-desc">Últimos 14 días · {{ number_format($activityTotal) }} en total</p>
                <img class="chart-img" src="{{ $charts['activity'] }}" alt="Requisiciones por día">
            </td>
            <td class="chart-card" style="width: 50%;">
                <p class="chart-title">Monto solicitado por día</p>
                <p class="chart-desc">Últimos 14 días · ${{ number_format($amountTotal, 2) }} en total</p>
                <img class="chart-img" src="{{ $charts['amounts'] }}" alt="Monto solicitado por día">
            </td>
        </tr>
        @if (($data['profile'] ?? '') !== 'personal' || collect($data['statusMix'] ?? [])->sum('value') > 0)
            <tr>
                <td class="chart-card">
                    <p class="chart-title">Estatus de requisiciones</p>
                    <p class="chart-desc">Últimos 30 días por fecha de solicitud</p>
                    <img class="chart-img" src="{{ $charts['status'] }}" alt="Estatus de requisiciones">
                </td>
                <td class="chart-card">
                    <p class="chart-title">Comprobantes del mes</p>
                    <p class="chart-desc">{{ ucfirst($data['period']['month'] ?? '') }} por tipo de documento</p>
                    <img class="chart-img" src="{{ $charts['comprobantes'] }}" alt="Comprobantes del mes">
                </td>
            </tr>
        @endif
    </table>

    <p class="section-title">Indicadores</p>
    <table class="data">
        <thead>
            <tr><th>Indicador</th><th class="num" style="width: 22%;">Valor</th><th style="width: 40%;">Referencia</th></tr>
        </thead>
        <tbody>
            @foreach (($data['kpis'] ?? []) as $k)
                <tr>
                    <td class="strong">{{ $k['label'] }}</td>
                    <td class="num strong">{{ $k['value'] }}</td>
                    <td class="muted">{{ $k['hint'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="section-title">Detalle diario</p>
    <table class="data">
        <thead>
            <tr><th>Día</th><th class="num">Requisiciones</th><th class="num">Monto</th></tr>
        </thead>
        <tbody>
            @foreach (($data['activityDaily'] ?? []) as $i => $p)
                <tr>
                    <td>{{ $p['name'] }}</td>
                    <td class="num">{{ number_format($p['value']) }}</td>
                    <td class="num">${{ number_format($data['amountsDaily'][$i]['value'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
