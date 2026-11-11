@php
    use App\Support\Pdf\PdfFormat as F;
    use App\Support\Pdf\SvgCharts;

    $rows = $rows ?? [];
    $kpis = $kpis ?? [];
    $primary = \App\Support\Pdf\PdfAssets::colors()['primary_color'] ?? '#6419FF';
    $chrome = ($pdfEngine ?? 'dompdf') === 'browsershot';

    $stats = [
        'Comprobantes' => F::int($kpis['total'] ?? count($rows)),
        'Monto' => F::money($kpis['monto'] ?? collect($rows)->sum('monto')),
        'Pendientes' => F::int($kpis['pendientes'] ?? 0),
        'Aprobados' => F::int($kpis['aprobados'] ?? 0),
        'Rechazados' => F::int($kpis['rechazados'] ?? 0),
    ];
    $meta = ['title' => 'Reporte de comprobantes'] + ($meta ?? []);
    $badge = ['APROBADO' => 'badge-ok', 'RECHAZADO' => 'badge-bad', 'PENDIENTE' => 'badge-warn'];
    $estColor = ['PENDIENTE' => '#F59E0B', 'APROBADO' => '#10B981', 'RECHAZADO' => '#F43F5E'];

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
    $porEstatus = $c->groupBy('estatus')->map(fn ($g, $k) => [
        'name' => $g->first()['estatus_label'] ?? $k,
        'value' => $g->sum('monto'),
        'note' => F::int($g->count()).' '.($g->count() === 1 ? 'comprobante' : 'comprobantes'),
        'color' => $estColor[$k] ?? '#A1A1AA',
    ])->sortByDesc('value')->values()->all();
    $porTipo = $top($c->groupBy(fn ($r) => $r['tipo_label'] ?? 'Sin tipo')->map->sum('monto'), 5);
    $porProv = $top($c->groupBy(fn ($r) => $r['requisicion']['proveedor'] ?? 'Sin proveedor')->map->sum('monto'), 5);
    $showSummary = count($rows) >= 3;
    $rowH = $chrome ? 22 : 19;
@endphp
@extends('pdf.layouts.report')

@section('content')
    @if ($showSummary)
        <div class="panels-wrap">
            <table class="panels">
                <tr>
                    <td class="panel">
                        <h3>Monto por estatus de revisión</h3>
                        <div class="hint">Cuánto está aprobado, pendiente o rechazado</div>
                        <img src="{{ SvgCharts::hbars($porEstatus, $primary, true, 278, 80, $rowH + 6, 72) }}" alt="">
                    </td>
                    <td class="panel">
                        <h3>Monto por tipo de documento</h3>
                        <div class="hint">Facturas, tickets, notas y otros</div>
                        <img src="{{ SvgCharts::hbars($porTipo, $primary, true, 278, 80, $rowH, 72) }}" alt="">
                    </td>
                    <td class="panel">
                        <h3>Principales proveedores</h3>
                        <div class="hint">Monto comprobado por proveedor</div>
                        <img src="{{ SvgCharts::hbars($porProv, $primary, true, 278, 128, $rowH, 66) }}" alt="">
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
                <th style="width: 12%">Solicitante</th>
                <th style="width: 16%">Proveedor / concepto</th>
                <th style="width: 7%">Tipo</th>
                <th style="width: 10%" class="num">Monto</th>
                <th style="width: 9%">Estatus</th>
                <th style="width: 11%">Cargó / revisó</th>
                <th>Archivo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="strong">{{ $r['requisicion']['folio'] ?? '—' }}</td>
                    <td class="nowrap">{{ F::date($r['fecha_emision'] ?? ($r['created_at'] ?? null)) }}</td>
                    <td>{{ $r['requisicion']['solicitante'] ?? '—' }}</td>
                    <td>{{ $r['requisicion']['proveedor'] ?? '—' }}@if (filled($r['requisicion']['concepto'] ?? null))<span class="sub">{{ $r['requisicion']['concepto'] }}</span>@endif</td>
                    <td>{{ $r['tipo_label'] ?? '—' }}</td>
                    <td class="num">{{ F::money($r['monto'] ?? 0) }}</td>
                    <td><span class="badge {{ $badge[$r['estatus'] ?? ''] ?? 'badge-off' }}">{{ $r['estatus_label'] ?? '—' }}</span></td>
                    <td>{{ $r['user_carga'] ?? '—' }}<span class="sub">{{ $r['user_revision'] ?? 'Sin revisar' }}</span></td>
                    <td class="muted" style="font-size: 8.5px;">{{ $r['archivo_original'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty">No hay comprobantes con los filtros actuales.</td></tr>
            @endforelse
            @if (count($rows) > 1)
                <tr class="foot">
                    <td colspan="5">Total · {{ F::int(count($rows)) }} comprobantes</td>
                    <td class="num">{{ F::money(collect($rows)->sum('monto')) }}</td>
                    <td colspan="3"></td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
