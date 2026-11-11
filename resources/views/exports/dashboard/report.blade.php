@php
    use App\Support\Pdf\PdfFormat as F;

    $period = $data['period'] ?? [];
    $byMonth = ($data['trend']['granularity'] ?? 'day') === 'month';
    $unit = $byMonth ? 'mes' : 'día';
    $meta = [
        'title' => $data['headline'] ?? 'Dashboard',
        'subtitle' => $data['subheadline'] ?? '',
        'generated_at' => $generatedAt ?? \App\Support\BusinessDate::now()->format('d/m/Y H:i'),
        'generated_by' => trim(($data['userName'] ?? '').(! empty($data['userRole']) ? ' · '.$data['userRole'] : '')),
        'footer_left' => 'ERP MR-Lana · Reporte de dashboard',
    ];
    $filters = array_filter([
        'Periodo' => trim(($period['label'] ?? '').': '.($period['from'] ?? '').' – '.($period['to'] ?? ''), ': '),
        'Comparado con' => $period['compare'] ?? null,
    ]);
    $cards = $data['cards'] ?? [];
    $activityTotal = collect($data['activityDaily'] ?? [])->sum('value');
    $amountTotal = collect($data['amountsDaily'] ?? [])->sum('value');
    $showStatus = ($data['profile'] ?? '') !== 'personal' || collect($data['statusMix'] ?? [])->sum('value') > 0;
    $points = collect($data['activityDaily'] ?? [])->map(fn ($p, $i) => [
        'name' => $p['name'],
        'count' => $p['value'],
        'monto' => $data['amountsDaily'][$i]['value'] ?? 0,
    ])->values();
    // Detalle en dos columnas para no gastar una hoja en una tabla angosta.
    $half = (int) ceil($points->count() / 2);
    $detailCols = $points->count() > 12 ? [$points->slice(0, $half)->values(), $points->slice($half)->values()] : [$points];
@endphp
@extends('pdf.layouts.report')

@push('styles')
    .kpis-wrap { margin: 0 -8px 10px; }
    table.kpis { width: 100%; border-collapse: separate; border-spacing: 8px 8px; table-layout: fixed; margin-top: -8px; }
    table.kpis td { border: 1px solid #E4E4E7; border-radius: 10px; padding: 9px 12px; vertical-align: top; background: #FFFFFF; }
    .kpi-label { font-size: 9px; color: #71717A; font-weight: 700; }
    .kpi-value { font-size: 16px; font-weight: 700; color: #09090B; margin-top: 2px; letter-spacing: -0.02em; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .kpi-hint { font-size: 8.5px; color: #71717A; margin-top: 2px; }
    .delta { font-weight: 700; }
    .delta.up { color: #047857; }
    .delta.down { color: #B91C1C; }
    table.panels { border-spacing: 8px 8px; }
    .panels-wrap { margin-bottom: 2px; }
    table.cols { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.cols > tbody > tr > td { vertical-align: top; padding: 0; }
@endpush

@section('content')
    @if ($cards)
        <div class="kpis-wrap">
            <table class="kpis">
                @foreach (array_chunk($cards, 3) as $chunk)
                    <tr>
                        @foreach ($chunk as $k)
                            <td>
                                <div class="kpi-label">{{ $k['label'] }}</div>
                                <div class="kpi-value">{{ ($k['format'] ?? '') === 'money' ? F::money($k['value']) : F::int($k['value']) }}</div>
                                <div class="kpi-hint">
                                    @if (($k['delta'] ?? null) !== null)
                                        <span class="delta {{ $k['delta'] > 0 ? 'up' : ($k['delta'] < 0 ? 'down' : '') }}">{{ $k['delta'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($k['delta'], 1), '0'), '.') }}%</span> vs. periodo anterior ·
                                    @endif
                                    {{ $k['hint'] ?? '' }}
                                </div>
                            </td>
                        @endforeach
                        @for ($i = count($chunk); $i < 3; $i++)
                            <td style="border: 0; background: transparent;"></td>
                        @endfor
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <div class="panels-wrap">
        <table class="panels">
            <tr>
                <td class="panel">
                    <h3>Requisiciones por {{ $unit }}</h3>
                    <div class="hint">{{ F::int($activityTotal) }} en el periodo</div>
                    <img src="{{ $charts['activity'] }}" alt="">
                </td>
                <td class="panel">
                    <h3>Monto solicitado por {{ $unit }}</h3>
                    <div class="hint">{{ F::money($amountTotal) }} en el periodo</div>
                    <img src="{{ $charts['amounts'] }}" alt="">
                </td>
            </tr>
            @if ($showStatus)
                <tr>
                    <td class="panel">
                        <h3>Estatus de requisiciones</h3>
                        <div class="hint">Por fecha de solicitud dentro del periodo</div>
                        <img src="{{ $charts['status'] }}" alt="">
                    </td>
                    <td class="panel">
                        <h3>Comprobantes por tipo</h3>
                        <div class="hint">Emitidos dentro del periodo</div>
                        <img src="{{ $charts['comprobantes'] }}" alt="">
                    </td>
                </tr>
            @endif
        </table>
    </div>
    <div class="panels-wrap">
        <table class="panels">
            <tr>
                <td class="panel">
                    <h3>Conceptos principales</h3>
                    <div class="hint">Monto solicitado en el periodo</div>
                    <img src="{{ $charts['conceptos'] }}" alt="">
                </td>
                <td class="panel">
                    <h3>Proveedores principales</h3>
                    <div class="hint">Monto solicitado en el periodo</div>
                    <img src="{{ $charts['proveedores'] }}" alt="">
                </td>
            </tr>
        </table>
    </div>
    <div class="panels-wrap">
        <table class="panels">
            <tr>
                <td class="panel" @if (! $charts['sucursales']) colspan="2" @endif>
                    <h3>Últimos 12 meses</h3>
                    <div class="hint">Monto solicitado por mes (respeta los filtros, no el periodo)</div>
                    <img src="{{ $charts['monthly'] }}" alt="">
                </td>
                @if ($charts['sucursales'])
                    <td class="panel">
                        <h3>Sucursales</h3>
                        <div class="hint">Monto solicitado en el periodo</div>
                        <img src="{{ $charts['sucursales'] }}" alt="">
                    </td>
                @endif
            </tr>
        </table>
    </div>

    <p class="section-title">Detalle por {{ $unit }}</p>
    <table class="cols">
        <tr>
            @foreach ($detailCols as $ci => $col)
                @if ($ci > 0)<td style="width: 16px;"></td>@endif
                <td>
                    <table class="data">
                        <thead>
                            <tr><th>{{ $byMonth ? 'Mes' : 'Día' }}</th><th class="num" style="width: 30%;">Requisiciones</th><th class="num" style="width: 36%;">Monto</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($col as $p)
                                <tr>
                                    <td>{{ $p['name'] }}</td>
                                    <td class="num {{ $p['count'] ? '' : 'muted' }}">{{ F::int($p['count']) }}</td>
                                    <td class="num {{ $p['monto'] ? '' : 'muted' }}">{{ F::money($p['monto']) }}</td>
                                </tr>
                            @endforeach
                            @if ($ci === count($detailCols) - 1)
                                <tr class="foot">
                                    <td>Total</td>
                                    <td class="num">{{ F::int($activityTotal) }}</td>
                                    <td class="num">{{ F::money($amountTotal) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>
@endsection
