@php
    use App\Support\Pdf\PdfFormat as F;
    use App\Support\Pdf\SvgCharts;

    $rows = $rows ?? [];
    $kpis = $kpis ?? [];
    $primary = \App\Support\Pdf\PdfAssets::colors()['primary_color'] ?? '#6419FF';
    $chrome = ($pdfEngine ?? 'dompdf') === 'browsershot';

    $stats = [
        'Pagos' => F::int($kpis['total'] ?? count($rows)),
        'Monto pagado' => F::money($kpis['monto'] ?? collect($rows)->sum('monto')),
        'Requisiciones' => F::int($kpis['requisiciones'] ?? 0),
        'Por transferencia' => F::money($kpis['transferencias'] ?? 0),
        'Otros medios' => F::money($kpis['otros'] ?? 0),
    ];
    $meta = ['title' => 'Reporte de pagos'] + ($meta ?? []);

    // Resumen gráfico (con las filas exportadas).
    $c = collect($rows);
    $top = function ($groups, int $n) {
        $groups = $groups->sortDesc();
        $out = $groups->take($n)->map(fn ($v, $k) => ['name' => (string) $k, 'value' => (float) $v])->values()->all();
        if ($groups->count() > $n) {
            $out[] = ['name' => 'Otros ('.($groups->count() - $n).')', 'value' => (float) $groups->slice($n)->sum(), 'color' => '#D4D4D8'];
        }

        return $out;
    };
    $porTipo = $top($c->groupBy(fn ($r) => $r['tipo_label'] ?? 'Sin tipo')->map->sum('monto'), 5);
    $porBenef = $top($c->groupBy(fn ($r) => $r['beneficiario'] ?: 'Sin beneficiario')->map->sum('monto'), 5);
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $porMes = $c->filter(fn ($r) => ! empty($r['fecha_pago']))
        ->groupBy(fn ($r) => substr((string) $r['fecha_pago'], 0, 7))->map->sum('monto')->sortKeys();
    $porMes = $porMes->count() > 12 ? $porMes->slice(-12) : $porMes;
    $mesPts = $porMes->map(fn ($v, $k) => ['name' => $meses[(int) substr($k, 5, 2) - 1].($porMes->keys()->map(fn ($x) => substr($x, 0, 4))->unique()->count() > 1 ? ' '.substr($k, 2, 2) : ''), 'value' => (float) $v])->values()->all();
    $showSummary = count($rows) >= 3;
    $rowH = $chrome ? 22 : 19;
    $chartH = 8 + 6 * $rowH;
@endphp
@extends('pdf.layouts.report')

@section('content')
    @if ($showSummary)
        <div class="panels-wrap">
            <table class="panels">
                <tr>
                    <td class="panel">
                        <h3>Monto por tipo de pago</h3>
                        <div class="hint">Cómo se está pagando</div>
                        <img src="{{ SvgCharts::hbars($porTipo, $primary, true, 278, 96, $rowH, 72) }}" alt="">
                    </td>
                    <td class="panel">
                        @if (count($mesPts) >= 2)
                            <h3>Monto pagado por mes</h3>
                            <div class="hint">Según la fecha de pago{{ $porMes->count() >= 12 ? ' · últimos 12 meses' : '' }}</div>
                            <img src="{{ SvgCharts::bars($mesPts, $primary, '', 278, $chartH, true) }}" alt="">
                        @else
                            <h3>Pagos por tipo</h3>
                            <div class="hint">Número de pagos</div>
                            <img src="{{ SvgCharts::hbars($c->groupBy(fn ($r) => $r['tipo_label'] ?? 'Sin tipo')->map->count()->sortDesc()->map(fn ($v, $k) => ['name' => (string) $k, 'value' => $v])->values()->all(), $primary, false, 278, 96, $rowH, 50) }}" alt="">
                        @endif
                    </td>
                    <td class="panel">
                        <h3>Principales beneficiarios</h3>
                        <div class="hint">A quién se le pagó más</div>
                        <img src="{{ SvgCharts::hbars($porBenef, $primary, true, 278, 128, $rowH, 66) }}" alt="">
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th style="width: 12%">Requisición</th>
                <th style="width: 8%">Fecha</th>
                <th style="width: 13%">Solicitante</th>
                <th style="width: 16%">Beneficiario</th>
                <th style="width: 10%">Banco / cuenta</th>
                <th style="width: 9%">Tipo</th>
                <th style="width: 10%" class="num">Monto</th>
                <th style="width: 10%">Referencia</th>
                <th>Registró / autorizó</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="strong">{{ $r['requisicion']['folio'] ?? '—' }}</td>
                    <td class="nowrap">{{ F::date($r['fecha_pago'] ?? null) }}</td>
                    <td>{{ $r['requisicion']['solicitante'] ?? '—' }}</td>
                    <td>{{ $r['beneficiario'] ?? '—' }}</td>
                    <td>{{ $r['banco'] ?? '—' }}@if (filled($r['cuenta'] ?? null))<span class="sub nowrap">{{ $r['cuenta'] }}</span>@endif</td>
                    <td>{{ $r['tipo_label'] ?? '—' }}</td>
                    <td class="num">{{ F::money($r['monto'] ?? 0) }}</td>
                    <td class="muted">{{ $r['referencia'] ?? '—' }}</td>
                    <td>{{ $r['user_carga'] ?? '—' }}<span class="sub">Autorizó: {{ $r['requisicion']['autorizo'] ?? '—' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty">No hay pagos con los filtros actuales.</td></tr>
            @endforelse
            @if (count($rows) > 1)
                <tr class="foot">
                    <td colspan="6">Total · {{ F::int(count($rows)) }} pagos</td>
                    <td class="num">{{ F::money(collect($rows)->sum('monto')) }}</td>
                    <td colspan="2"></td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
