<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>{{ $meta['title'] ?? 'Reporte de Requisiciones' }}</title>

  <style>
    @page {
      margin: 120px 18px 42px 18px;
    }

    * {
      font-family: DejaVu Sans, sans-serif;
      box-sizing: border-box;
    }

    body {
      font-size: 8.7px;
      color: #111827;
      line-height: 1.28;
      margin: 0;
      padding: 0;
    }

    .muted {
      color: #64748b;
    }

    .strong {
      font-weight: 800;
      color: #0f172a;
    }

    .small {
      font-size: 8px;
      line-height: 1.25;
    }

    .tiny {
      font-size: 7.5px;
      line-height: 1.2;
    }

    .right {
      text-align: right;
    }

    .center {
      text-align: center;
    }

    .nowrap {
      white-space: nowrap;
    }

    .break {
      word-break: break-word;
      overflow-wrap: break-word;
    }

    .header {
      position: fixed;
      top: -102px;
      left: 0;
      right: 0;
      height: 98px;
    }

    .footer {
      position: fixed;
      bottom: -28px;
      left: 0;
      right: 0;
      height: 24px;
      border-top: 1px solid #e5e7eb;
      padding-top: 5px;
      font-size: 8px;
      color: #64748b;
    }

    .hwrap {
      width: 100%;
      border-collapse: collapse;
    }

    .hwrap td {
      vertical-align: top;
      padding: 0;
      border: 0;
    }

    .title {
      font-size: 15px;
      font-weight: 900;
      margin: 0;
      color: #0f172a;
    }

    .subtitle {
      margin: 2px 0 0 0;
      font-size: 9px;
      color: #64748b;
    }

    .metaBox {
      text-align: right;
      font-size: 8px;
      line-height: 1.3;
      white-space: nowrap;
    }

    .metaLine b {
      color: #0f172a;
    }

    .filters {
      margin-top: 6px;
      max-height: 42px;
      overflow: hidden;
    }

    .pill {
      display: inline-block;
      border: 1px solid #e2e8f0;
      background: #f8fafc;
      padding: 2px 6px;
      border-radius: 999px;
      margin: 1px 4px 2px 0;
      font-size: 7.6px;
      white-space: nowrap;
      color: #475569;
    }

    .summary {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 8px;
    }

    .summary td {
      border: 1px solid #e5e7eb;
      padding: 5px 7px;
      background: #f8fafc;
      vertical-align: top;
    }

    .summaryLabel {
      font-size: 7.5px;
      color: #64748b;
      text-transform: uppercase;
      font-weight: 800;
      letter-spacing: .02em;
    }

    .summaryValue {
      margin-top: 2px;
      font-size: 10px;
      font-weight: 900;
      color: #0f172a;
    }

    .content {
      width: 100%;
    }

    table.report {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      page-break-inside: auto;
    }

    table.report thead {
      display: table-header-group;
    }

    table.report tbody {
      display: table-row-group;
    }

    table.report tr {
      page-break-inside: avoid;
      page-break-after: auto;
    }

    table.report thead th {
      background: #111827;
      color: #ffffff;
      font-weight: 900;
      text-transform: uppercase;
      font-size: 7.4px;
      letter-spacing: .02em;
      padding: 6px 5px;
      border: 1px solid #111827;
      vertical-align: middle;
    }

    table.report tbody td {
      border: 1px solid #e5e7eb;
      padding: 5px 5px;
      vertical-align: top;
      overflow: visible;
      word-break: break-word;
      overflow-wrap: break-word;
    }

    table.report tbody tr:nth-child(even) td {
      background: #f8fafc;
    }

    .row-ajuste td {
      background: #eef2ff !important;
      font-weight: 700;
    }

    .cellTitle {
      font-weight: 800;
      color: #0f172a;
      margin-bottom: 2px;
    }

    .cellLine {
      margin-top: 1px;
    }

    .moneyLine {
      margin-bottom: 2px;
      white-space: nowrap;
    }

    .finalMoney {
      font-weight: 900;
      color: #0f172a;
      border-top: 1px solid #cbd5e1;
      margin-top: 3px;
      padding-top: 3px;
    }

    .empty {
      padding: 14px;
      text-align: center;
      color: #64748b;
      font-weight: 700;
    }

    .c-folio { width: 14%; }
    .c-origen { width: 20%; }
    .c-proveedor { width: 18%; }
    .c-concepto { width: 14%; }
    .c-desc { width: 22%; }
    .c-importes { width: 12%; }
  </style>
</head>

<body>
  @php
    $money = function($v) {
      if ($v === null || $v === '') {
        return '';
      }

      $n = (float) $v;
      return number_format($n, 2, '.', ',');
    };

    $dateSafe = function($v, $format = 'Y-m-d') {
      if (empty($v)) {
        return '';
      }

      try {
        return \Carbon\Carbon::parse($v)->format($format);
      } catch (\Throwable $e) {
        return (string) $v;
      }
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
    <table class="hwrap">
      <tr>
        <td style="width: 68%;">
          <div class="title">{{ $meta['title'] ?? 'Reporte de Requisiciones' }}</div>
          <div class="subtitle">{{ $meta['subtitle'] ?? 'Exportación con filtros actuales' }}</div>

          @if(!empty($filters) && is_array($filters))
            <div class="filters">
              @foreach($filters as $k => $v)
                @php $vv = is_array($v) ? implode(', ', $v) : (string) $v; @endphp
                @if(trim($vv) !== '')
                  <span class="pill">
                    <span class="strong">{{ $k }}:</span>
                    {{ $vv }}
                  </span>
                @endif
              @endforeach
            </div>
          @endif
        </td>

        <td style="width: 32%;" class="metaBox">
          <div class="metaLine"><b>Generado:</b> {{ $meta['generated_at'] ?? now()->format('Y-m-d H:i') }}</div>
          @if(!empty($meta['generated_by']))
            <div class="metaLine"><b>Por:</b> {{ $meta['generated_by'] }}</div>
          @endif
          <div class="metaLine"><b>Registros:</b> {{ $reqCount }}</div>
          <div class="metaLine"><b>Total final:</b> ${{ $money($sumTotalFinal) }}</div>
        </td>
      </tr>
    </table>
  </div>

  <div class="footer">
    {{ $meta['footer_left'] ?? 'ERP MR-Lana' }} · Reporte generado automáticamente
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
                <div class="cellLine small muted">{{ $r['estatus'] }}</div>
              @endif

              @if(!empty($r['tipo']))
                <div class="cellLine small muted">{{ $r['tipo'] }}</div>
              @endif

              @if(!empty($r['fecha_captura']))
                <div class="cellLine small muted">Cap: {{ $dateSafe($r['fecha_captura']) }}</div>
              @endif

              @if(!empty($r['fecha_solicitud']))
                <div class="cellLine small muted">Entrega: {{ $dateSafe($r['fecha_solicitud']) }}</div>
              @endif

              @if(!empty($r['fecha_pago']))
                <div class="cellLine small muted">Pago: {{ $dateSafe($r['fecha_pago']) }}</div>
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
                <div class="cellLine small muted break">Solicita: {{ $r['solicitante'] }}</div>
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
                  Obs: {{ $r['observaciones'] }}
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
                <div class="moneyLine small muted">Sub: ${{ $money($r['subtotal']) }}</div>
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

  <script type="text/php">
    if (isset($pdf)) {
      $pdf->page_text(730, 568, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 8, array(100,116,139));
    }
  </script>
</body>
</html>
