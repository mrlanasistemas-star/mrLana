<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>{{ $meta['title'] ?? 'Reporte de Requisiciones' }}</title>

  <style>
    @page { margin: 18px 18px 44px 18px; }

    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 9.5px; color: #111827; }

    .muted { color: #6b7280; }
    .strong { font-weight: 800; }
    .small { font-size: 9.5px; }

    .header {
      position: fixed;
      top: -6px;
      left: 0;
      right: 0;
      height: 70px;
    }

    .footer {
      position: fixed;
      bottom: -26px;
      left: 0;
      right: 0;
      height: 28px;
      border-top: 1px solid #e5e7eb;
      padding-top: 6px;
      font-size: 9.5px;
      color: #6b7280;
    }

    .hwrap { width: 100%; border-collapse: collapse; }
    .hwrap td { vertical-align: top; padding: 0; }

    .title { font-size: 16px; font-weight: 900; margin: 0; }
    .subtitle { margin: 2px 0 0 0; }

    .metaBox {
      text-align: right;
      font-size: 9.8px;
      line-height: 1.35;
      white-space: nowrap;
    }
    .metaLine b { color:#111827; }

    .filters { margin-top: 6px; }
    .pill {
      display: inline-block;
      border: 1px solid #e5e7eb;
      background: #f9fafb;
      padding: 3px 7px;
      border-radius: 999px;
      margin: 2px 6px 0 0;
      font-size: 9.6px;
      white-space: nowrap;
    }

    .content { margin-top: 105px; }

    table.report {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }

    table.report thead th {
      background: #111827;
      color: #ffffff;
      font-weight: 900;
      text-transform: uppercase;
      font-size: 9px;
      letter-spacing: .03em;
      padding: 7px 6px;
      border: 1px solid #111827;
    }

    table.report tbody td {
      border: 1px solid #e5e7eb;
      padding: 6px 6px;
      vertical-align: top;
    }

    table.report tbody tr:nth-child(even) td { background: #f9fafb; }

    .nowrap { white-space: nowrap; }
    .right { text-align: right; }
    .center { text-align: center; }

    .cut {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .row-ajuste td {
      background: #f8fafc !important;
      font-weight: 700;
    }

    th.c-folio,      td.c-folio      { width: 11%; }
    th.c-prov,       td.c-prov       { width: 10%; }
    th.c-conc,       td.c-conc       { width: 7%; }
    th.c-qty,        td.c-qty        { width: 5%; }
    th.c-desc,       td.c-desc       { width: 17%; }
    th.c-price,      td.c-price      { width: 6%; }
    th.c-iva-item,   td.c-iva-item   { width: 6%; }
    th.c-total-item, td.c-total-item { width: 7%; }
    th.c-subreq,     td.c-subreq     { width: 7%; }
    th.c-ivareq,     td.c-ivareq     { width: 6%; }
    th.c-totreq,     td.c-totreq     { width: 7%; }
    th.c-ajuste,     td.c-ajuste     { width: 7%; }
    th.c-final,      td.c-final      { width: 7%; }
    th.c-cap,        td.c-cap        { width: 8%; }
  </style>
</head>

<body>
  @php
    $money = function($v) {
      $n = (float)($v ?? 0);
      return number_format($n, 2, '.', ',');
    };

    $sumSubtotal = 0.0;
    $sumIva = 0.0;
    $sumTotalItems = 0.0;
    $sumAjustesNetos = 0.0;
    $sumTotalFinal = 0.0;

    foreach(($rows ?? []) as $rr){
      // Solo sumar una vez por requisición, cuando la fila trae encabezado de requisición
      if (!empty($rr['folio'])) {
        $sumSubtotal     += (float)($rr['subtotal'] ?? 0);
        $sumIva          += (float)($rr['iva'] ?? 0);
        $sumTotalItems   += (float)($rr['total'] ?? 0);
        $sumAjustesNetos += (float)($rr['ajustes_netos'] ?? 0);
        $sumTotalFinal   += (float)($rr['total_final'] ?? ($rr['total'] ?? 0));
      }
    }
  @endphp

  <div class="header">
    <table class="report">
  <thead>
    <tr>
      <th style="width: 14%;">Folio / Estatus</th>
      <th style="width: 20%;">Origen</th>
      <th style="width: 18%;">Proveedor</th>
      <th style="width: 16%;">Concepto</th>
      <th style="width: 20%;">Descripción / Observación</th>
      <th style="width: 12%;" class="right">Importes</th>
    </tr>
  </thead>

  <tbody>
    @forelse(($rows ?? []) as $r)
      <tr class="{{ ($r['row_kind'] ?? '') === 'AJUSTE' ? 'row-ajuste' : '' }}">
        <td>
          <div class="strong">{{ $r['folio'] ?? '—' }}</div>
          @if(!empty($r['estatus']))
            <div class="small muted">{{ $r['estatus'] }}</div>
          @endif
          @if(!empty($r['tipo']))
            <div class="small muted">{{ $r['tipo'] }}</div>
          @endif
          @if(!empty($r['fecha_captura']))
            <div class="small muted">Cap: {{ \Carbon\Carbon::parse($r['fecha_captura'])->format('Y-m-d') }}</div>
          @endif
          @if(!empty($r['fecha_solicitud']))
            <div class="small muted">Entrega: {{ $r['fecha_solicitud'] }}</div>
          @endif
        </td>

        <td>
          @if(!empty($r['corporativo']))
            <div class="strong">{{ $r['corporativo'] }}</div>
          @endif
          @if(!empty($r['sucursal']))
            <div>{{ $r['sucursal'] }}</div>
          @endif
          @if(!empty($r['sucursal_codigo']))
            <div class="small muted">Código: {{ $r['sucursal_codigo'] }}</div>
          @endif
          @if(!empty($r['solicitante']))
            <div class="small muted">Solicita: {{ $r['solicitante'] }}</div>
          @endif
        </td>

        <td>
          <div>{{ $r['proveedor'] ?? '—' }}</div>
          @if(!empty($r['proveedor_rfc']))
            <div class="small muted">RFC: {{ $r['proveedor_rfc'] }}</div>
          @endif
        </td>

        <td>
          {{ $r['concepto'] ?? '—' }}
        </td>

        <td>
          <div>{{ $r['descripcion_item'] ?? '' }}</div>
          @if(!empty($r['observaciones']))
            <div class="small muted" style="margin-top: 4px;">
              Obs: {{ $r['observaciones'] }}
            </div>
          @endif
        </td>

        <td class="right nowrap">
          @if(isset($r['cantidad']) && $r['cantidad'] !== '')
            <div class="small muted">Cant: {{ $r['cantidad'] }}</div>
          @endif

          @if(isset($r['total_item']) && $r['total_item'] !== '')
            <div>Ítem: {{ number_format((float)$r['total_item'], 2, '.', ',') }}</div>
          @endif

          @if(isset($r['subtotal']) && $r['subtotal'] !== '')
            <div class="small muted">Sub: {{ number_format((float)$r['subtotal'], 2, '.', ',') }}</div>
          @endif

          @if(isset($r['iva']) && $r['iva'] !== '')
            <div class="small muted">IVA: {{ number_format((float)$r['iva'], 2, '.', ',') }}</div>
          @endif

          @if(isset($r['ajustes_netos']) && $r['ajustes_netos'] !== '')
            <div class="small muted">Ajuste: {{ number_format((float)$r['ajustes_netos'], 2, '.', ',') }}</div>
          @endif

          @if(isset($r['total_final']) && $r['total_final'] !== '')
            <div class="strong">Final: {{ number_format((float)$r['total_final'], 2, '.', ',') }}</div>
          @endif
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="6" class="muted" style="padding:10px;">
          Sin resultados con los filtros actuales.
        </td>
      </tr>
    @endforelse
  </tbody>
</table>
  </div>

  <script type="text/php">
    if (isset($pdf)) {
      $pdf->page_text(760, 560, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 9, array(107,114,128));
    }
  </script>
</body>
</html>
