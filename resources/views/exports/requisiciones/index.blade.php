<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>{{ $meta['title'] ?? 'Reporte de Requisiciones' }}</title>

  <style>
    @page {
      margin: 118px 20px 44px 20px;
    }

    * {
      font-family: DejaVu Sans, sans-serif;
      box-sizing: border-box;
    }

    body {
      font-size: 8.5px;
      color: #1e293b;
      line-height: 1.3;
      margin: 0;
      padding: 0;
    }

    .muted { color: #64748b; }
    .strong { font-weight: 800; color: #0f172a; }
    .small  { font-size: 7.8px; line-height: 1.25; }
    .tiny   { font-size: 7.2px; line-height: 1.2; }
    .right  { text-align: right; }
    .center { text-align: center; }
    .nowrap { white-space: nowrap; }
    .break  { word-break: break-word; overflow-wrap: break-word; }

    /* ── Header fijo ──────────────────────────────────── */
    .header {
      position: fixed;
      top: -100px;
      left: 0;
      right: 0;
      height: 96px;
    }

    .header-bar {
      background: #0f172a;
      height: 4px;
      width: 100%;
      margin-bottom: 6px;
    }

    .hwrap { width: 100%; border-collapse: collapse; }
    .hwrap td { vertical-align: top; padding: 0; border: 0; }

    .title {
      font-size: 16px;
      font-weight: 900;
      margin: 0;
      color: #0f172a;
      letter-spacing: -0.01em;
    }

    .subtitle {
      margin: 3px 0 0 0;
      font-size: 8.5px;
      color: #64748b;
    }

    .metaBox {
      text-align: right;
      font-size: 7.8px;
      line-height: 1.45;
      white-space: nowrap;
      color: #475569;
    }

    .metaBox b { color: #0f172a; }

    .filters { margin-top: 5px; }

    .pill {
      display: inline-block;
      border: 1px solid #cbd5e1;
      background: #f1f5f9;
      padding: 1.5px 5px;
      border-radius: 999px;
      margin: 1px 3px 2px 0;
      font-size: 7.4px;
      white-space: nowrap;
      color: #475569;
    }

    /* ── Footer fijo ──────────────────────────────────── */
    .footer {
      position: fixed;
      bottom: -30px;
      left: 0;
      right: 0;
      height: 26px;
      border-top: 1px solid #e2e8f0;
      padding-top: 5px;
      font-size: 7.6px;
      color: #94a3b8;
      display: flex;
      justify-content: space-between;
    }

    /* ── Summary cards ────────────────────────────────── */
    .summary {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 8px;
    }

    .summary td {
      border: 1px solid #e2e8f0;
      padding: 5px 8px;
      background: #f8fafc;
      vertical-align: top;
    }

    .summary td:first-child { border-left: 3px solid #0f172a; }

    .summaryLabel {
      font-size: 7px;
      color: #94a3b8;
      text-transform: uppercase;
      font-weight: 800;
      letter-spacing: .06em;
    }

    .summaryValue {
      margin-top: 2px;
      font-size: 11px;
      font-weight: 900;
      color: #0f172a;
    }

    /* ── Tabla principal ──────────────────────────────── */
    .content { width: 100%; }

    table.report {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      page-break-inside: auto;
    }

    table.report thead { display: table-header-group; }
    table.report tbody { display: table-row-group; }

    table.report tr {
      page-break-inside: avoid;
      page-break-after: auto;
    }

    table.report thead th {
      background: #1e293b;
      color: #f1f5f9;
      font-weight: 900;
      text-transform: uppercase;
      font-size: 7px;
      letter-spacing: .06em;
      padding: 6px 5px;
      border: 1px solid #1e293b;
      vertical-align: middle;
    }

    table.report tbody td {
      border: 1px solid #e2e8f0;
      padding: 5px 5px;
      vertical-align: top;
      overflow: visible;
      word-break: break-word;
      overflow-wrap: break-word;
    }

    table.report tbody tr:nth-child(odd) td {
      background: #ffffff;
    }

    table.report tbody tr:nth-child(even) td {
      background: #f8fafc;
    }

    .row-ajuste td {
      background: #eff6ff !important;
      font-weight: 700;
      font-style: italic;
    }

    .cellTitle { font-weight: 800; color: #0f172a; margin-bottom: 1px; }
    .cellLine  { margin-top: 1.5px; }

    .dateRow {
      font-size: 7.5px;
      color: #64748b;
      margin-top: 2px;
    }

    .dateLabel { font-weight: 800; color: #334155; }

    .moneyLine  { margin-bottom: 2px; white-space: nowrap; }

    .finalMoney {
      font-weight: 900;
      color: #0f172a;
      font-size: 9.5px;
      border-top: 1px solid #cbd5e1;
      margin-top: 3px;
      padding-top: 3px;
    }

    .badge {
      display: inline-block;
      padding: 1px 5px;
      border-radius: 4px;
      font-size: 7px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .04em;
    }

    .badge-pagada          { background: #d1fae5; color: #065f46; }
    .badge-pago_autorizado { background: #fef3c7; color: #92400e; }
    .badge-capturada       { background: #e0f2fe; color: #0c4a6e; }
    .badge-borrador        { background: #f1f5f9; color: #475569; }
    .badge-eliminada       { background: #fee2e2; color: #991b1b; }
    .badge-por_comprobar   { background: #ede9fe; color: #4c1d95; }
    .badge-default         { background: #f1f5f9; color: #64748b; }

    .empty {
      padding: 20px;
      text-align: center;
      color: #94a3b8;
      font-weight: 700;
      font-size: 9px;
    }

    .c-folio    { width: 15%; }
    .c-origen   { width: 19%; }
    .c-proveedor{ width: 17%; }
    .c-concepto { width: 13%; }
    .c-desc     { width: 23%; }
    .c-importes { width: 13%; }
  </style>
</head>

<body>
  @php
    $money = function($v) {
      if ($v === null || $v === '') return '';
      return '$' . number_format((float)$v, 2, '.', ',');
    };

    $dateSafe = function($v, $format = 'd/m/Y') {
      if (empty($v)) return '—';
      try {
        return \Carbon\Carbon::parse($v)->format($format);
      } catch (\Throwable $e) {
        return (string) $v;
      }
    };

    $badgeClass = function($status) {
      $s = strtolower((string)$status);
      $map = [
        'pagada'               => 'badge badge-pagada',
        'pago_autorizado'      => 'badge badge-pago_autorizado',
        'capturada'            => 'badge badge-capturada',
        'borrador'             => 'badge badge-borrador',
        'eliminada'            => 'badge badge-eliminada',
        'por_comprobar'        => 'badge badge-por_comprobar',
      ];
      return $map[$s] ?? 'badge badge-default';
    };

    $sumSubtotal = 0.0;
    $sumIva = 0.0;
    $sumTotalItems = 0.0;
    $sumAjustesNetos = 0.0;
    $sumTotalFinal = 0.0;
    $reqCount = 0;

    foreach (($rows ?? []) as $rr) {
      if (!empty($rr['folio'])) {
        $reqCount++;
        $sumSubtotal     += (float)($rr['subtotal'] ?? 0);
        $sumIva          += (float)($rr['iva'] ?? 0);
        $sumTotalItems   += (float)($rr['total'] ?? 0);
        $sumAjustesNetos += (float)($rr['ajustes_netos'] ?? 0);
        $sumTotalFinal   += (float)($rr['total_final'] ?? ($rr['total'] ?? 0));
      }
    }
  @endphp

  <div class="header">
    <div class="header-bar"></div>
    <table class="hwrap">
      <tr>
        <td style="width: 66%;">
          <div class="title">{{ $meta['title'] ?? 'Reporte de Requisiciones' }}</div>
          <div class="subtitle">{{ $meta['subtitle'] ?? 'Exportación con filtros actuales' }}</div>

          @if(!empty($filters) && is_array($filters))
            <div class="filters">
              @foreach($filters as $k => $v)
                @php $vv = is_array($v) ? implode(', ', $v) : (string) $v; @endphp
                @if(trim($vv) !== '')
                  <span class="pill"><span class="strong">{{ $k }}:</span> {{ $vv }}</span>
                @endif
              @endforeach
            </div>
          @endif
        </td>

        <td style="width: 34%;" class="metaBox">
          <div><b>Generado:</b> {{ $meta['generated_at'] ?? now()->format('d/m/Y H:i') }}</div>
          @if(!empty($meta['generated_by']))
            <div><b>Por:</b> {{ $meta['generated_by'] }}</div>
          @endif
          <div><b>Registros:</b> {{ $reqCount }}</div>
          <div><b>Total final:</b> ${{ $money($sumTotalFinal) }}</div>
        </td>
      </tr>
    </table>
  </div>

  <div class="footer">
    <span>{{ $meta['footer_left'] ?? 'ERP MR-Lana' }} &mdash; Reporte generado automáticamente</span>
    <span>Página <script type="text/php">if (isset($pdf)) { echo $PAGE_NUM . ' de ' . $PAGE_COUNT; }</script></span>
  </div>

  <div class="content">
    <table class="summary">
      <tr>
        <td style="width: 20%;">
          <div class="summaryLabel">Requisiciones</div>
          <div class="summaryValue">{{ $reqCount }}</div>
        </td>
        <td style="width: 20%;">
          <div class="summaryLabel">Subtotal</div>
          <div class="summaryValue">${{ $money($sumSubtotal) }}</div>
        </td>
        <td style="width: 20%;">
          <div class="summaryLabel">IVA</div>
          <div class="summaryValue">${{ $money($sumIva) }}</div>
        </td>
        <td style="width: 20%;">
          <div class="summaryLabel">Ajustes netos</div>
          <div class="summaryValue">${{ $money($sumAjustesNetos) }}</div>
        </td>
        <td style="width: 20%;">
          <div class="summaryLabel">Total final</div>
          <div class="summaryValue">${{ $money($sumTotalFinal) }}</div>
        </td>
      </tr>
    </table>

    <table class="report">
      <thead>
        <tr>
          <th class="c-folio">Folio / Estatus</th>
          <th class="c-origen">Origen</th>
          <th class="c-proveedor">Proveedor</th>
          <th class="c-concepto">Concepto</th>
          <th class="c-desc">Descripción / Observación</th>
          <th class="c-importes right">Importes</th>
        </tr>
      </thead>

      <tbody>
        @forelse(($rows ?? []) as $r)
          <tr class="{{ ($r['row_kind'] ?? '') === 'AJUSTE' ? 'row-ajuste' : '' }}">
            <td>
              @if(!empty($r['folio']))
                <div class="cellTitle">{{ $r['folio'] }}</div>
              @else
                <div class="cellTitle muted">—</div>
              @endif

              @if(!empty($r['estatus']))
                <div class="cellLine" style="margin-top:3px;">
                  <span class="{{ $badgeClass($r['estatus']) }}">{{ $r['estatus'] }}</span>
                </div>
              @endif

              @if(!empty($r['tipo']))
                <div class="cellLine tiny muted" style="margin-top:2px;">{{ $r['tipo'] }}</div>
              @endif

              @if(!empty($r['fecha_captura']))
                <div class="dateRow"><span class="dateLabel">Registro:</span> {{ $dateSafe($r['fecha_captura']) }}</div>
              @endif

              @if(!empty($r['fecha_solicitud']))
                <div class="dateRow"><span class="dateLabel">Solicitud:</span> {{ $dateSafe($r['fecha_solicitud']) }}</div>
              @endif

              @if(!empty($r['fecha_pago']))
                <div class="dateRow"><span class="dateLabel">Pago:</span> {{ $dateSafe($r['fecha_pago']) }}</div>
              @endif
            </td>

            <td>
              @if(!empty($r['corporativo']))
                <div class="cellTitle break">Corporativo: {{ $r['corporativo'] }}</div>
              @endif

              @if(!empty($r['sucursal']))
                <div class="cellLine break">Sucursal: {{ $r['sucursal'] }}</div>
              @endif

              @if(!empty($r['solicitante']))
                <div class="cellLine small muted break">Solicitante: {{ $r['solicitante'] }}</div>
              @endif
            </td>

            <td>
              <div class="break">{{ $r['proveedor'] ?? '—' }}</div>

            </td>

            <td>
              <div class="break">{{ $r['concepto'] ?? '—' }}</div>
            </td>

            <td>
              @if(!empty($r['descripcion_item']))
                <div class="break">{{ $r['descripcion_item'] }}</div>
              @else
                <div class="muted">—</div>
              @endif

              @if(!empty($r['observaciones']))
                <div class="cellLine small muted break" style="margin-top: 4px;">
                  Observaciones: {{ $r['observaciones'] }}
                </div>
              @endif
            </td>

            <td class="right">
              @if(isset($r['cantidad']) && $r['cantidad'] !== '')
                <div class="moneyLine small muted">Cant: {{ $r['cantidad'] }}</div>
              @endif

              @if(isset($r['total_item']) && $r['total_item'] !== '')
                <div class="moneyLine">Ítem: ${{ $money($r['total_item']) }}</div>
              @endif

              @if(isset($r['subtotal']) && $r['subtotal'] !== '')
                <div class="moneyLine small muted">Subtotal: ${{ $money($r['subtotal']) }}</div>
              @endif

              @if(isset($r['iva']) && $r['iva'] !== '')
                <div class="moneyLine small muted">IVA: ${{ $money($r['iva']) }}</div>
              @endif

              @if(isset($r['ajustes_netos']) && $r['ajustes_netos'] !== '')
                <div class="moneyLine small muted">Ajuste: ${{ $money($r['ajustes_netos']) }}</div>
              @endif

              @if(isset($r['total_final']) && $r['total_final'] !== '')
                <div class="finalMoney">Final: ${{ $money($r['total_final']) }}</div>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="empty">
              Sin resultados con los filtros actuales.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

</body>
</html>
