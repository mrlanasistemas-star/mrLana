<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>{{ $meta['title'] ?? 'Reporte de Requisiciones' }}</title>

  <style>
    /* DomPDF friendly */
    @page { margin: 18px 18px 44px 18px; } /* top right bottom left */

    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10.5px; color: #111827; }

    .muted { color: #6b7280; }
    .strong { font-weight: 800; }
    .small { font-size: 9.5px; }

    /* Header / Footer fixed for every page */
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

    /* Header block uses tables (more reliable than flex in DomPDF) */
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

    /* Filters pills */
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

    /* Content starts below header: mayor margen para separar el encabezado de la tabla */
    .content { margin-top: 90px; }

    /* Table */
    table.report {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed; /* key to avoid stretching */
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

    /* Text clamp-ish for DomPDF */
    .cut {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    /* Status badges */
    .badge {
      display: inline-block;
      font-weight: 900;
      font-size: 9px;
      padding: 2px 7px;
      border-radius: 999px;
      border: 1px solid transparent;
      white-space: nowrap;
    }
    .b-draft { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
    .b-pend  { background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
    .b-ok    { background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
    .b-bad   { background:#fff1f2; color:#9f1239; border-color:#fecdd3; }
    .b-dead  { background:#f3f4f6; color:#374151; border-color:#e5e7eb; }

    /* Column widths (landscape) para tabla con detalles de items */
    th.c-folio,        td.c-folio        { width: 12%; }
    th.c-prov,         td.c-prov         { width: 10%; }
    th.c-conc,         td.c-conc         { width: 7%; }
    th.c-qty,          td.c-qty          { width: 5%; }
    th.c-desc,         td.c-desc         { width: 20%; }
    th.c-price,        td.c-price        { width: 7%; }
    th.c-iva-item,     td.c-iva-item     { width: 6%; }
    th.c-total-item,   td.c-total-item   { width: 8%; }
    th.c-subreq,       td.c-subreq       { width: 7%; }
    th.c-ivareq,       td.c-ivareq       { width: 6%; }
    th.c-totreq,       td.c-totreq       { width: 7%; }
    th.c-cap,          td.c-cap          { width: 10%; }
  </style>
</head>

<body>
  @php
    // Helpers “DomPDF-safe”
    $money = function($v) {
      $n = (float)($v ?? 0);
      return number_format($n, 2, '.', ',');
    };

    $statusClass = function(string $st) {
      $st = strtoupper(trim($st));
      if (in_array($st, ['BORRADOR','CAPTURADA'], true)) return 'b-draft';
      if (in_array($st, ['POR_COMPROBAR'], true)) return 'b-pend';
      if (in_array($st, ['PAGO_AUTORIZADO','PAGADA','COMPROBACION_ACEPTADA'], true)) return 'b-ok';
      if (in_array($st, ['PAGO_RECHAZADO','COMPROBACION_RECHAZADA'], true)) return 'b-bad';
      if (in_array($st, ['ELIMINADA'], true)) return 'b-dead';
      return 'b-dead';
    };

    // Totales para mostrar en encabezado
    $totalRows   = (int)($totals['total'] ?? (is_array($rows ?? null) ? count($rows) : 0));
    $sumSubtotal = 0.0; $sumIva = 0.0; $sumTotal = 0.0;
    foreach(($rows ?? []) as $rr){
      $sumSubtotal += (float)($rr['subtotal'] ?? 0);
      $sumIva      += (float)($rr['iva'] ?? 0);
      $sumTotal    += (float)($rr['total'] ?? 0);
    }
  @endphp

  <!-- HEADER -->
  <div class="header">
    <table class="hwrap">
      <tr>
        <td style="width: 70%;">
          <div class="title">{{ $meta['title'] ?? 'Reporte de Requisiciones' }}</div>
          <div class="subtitle muted">{{ $meta['subtitle'] ?? 'Exportación con filtros actuales' }}</div>

          @if(!empty($filters) && is_array($filters))
            <div class="filters">
              @foreach($filters as $k => $v)
                @php $vv = is_array($v) ? implode(', ', $v) : (string)$v; @endphp
                @if(trim($vv) !== '')
                  <span class="pill"><span class="strong">{{ $k }}:</span> <span class="muted">{{ $vv }}</span></span>
                @endif
              @endforeach
            </div>
          @endif
        </td>

        <td style="width: 30%;" class="metaBox">
          <div class="metaLine"><b>Generado:</b> {{ $meta['generated_at'] ?? now()->format('Y-m-d H:i') }}</div>
          @if(!empty($meta['generated_by']))
            <div class="metaLine"><b>Por:</b> {{ $meta['generated_by'] }}</div>
          @endif
          <div class="metaLine"><b>Subtotal:</b> {{ $money($sumSubtotal) }}</div>
          <div class="metaLine"><b>IVA:</b> {{ $money($sumIva) }}</div>
          <div class="metaLine"><b>Total:</b> {{ $money($sumTotal) }}</div>
          <br><br>
        </td>
      </tr>
    </table>
  </div>

  <!-- CONTENT -->
  <div class="content">
    <table class="report">
      <thead>
        <tr>
          <th class="c-folio">Folio</th>
          <th class="c-prov">Proveedor</th>
          <th class="c-conc">Concepto</th>
          <th class="c-qty right">Cantidad</th>
          <th class="c-desc">Descripción</th>
          <th class="c-price right">P.Unit</th>
          <th class="c-iva-item right">IVA item</th>
          <th class="c-total-item right">Total item</th>
          <th class="c-subreq right">Subtotal req.</th>
          <th class="c-ivareq right">IVA req.</th>
          <th class="c-totreq right">Total req.</th>
          <th class="c-cap">Captura</th>
        </tr>
      </thead>

      <tbody>
        @forelse(($rows ?? []) as $r)
          @php
            $st  = strtoupper((string)($r['estatus'] ?? $r['status'] ?? ''));
            $cls = $statusClass($st);
          @endphp
          <tr>
            <td class="c-folio">{{ $r['folio'] ?? '—' }}</td>
            <td class="c-prov cut">{{ $r['proveedor'] ?? '—' }}</td>
            <td class="c-conc cut">{{ $r['concepto'] ?? '—' }}</td>
            <td class="c-qty right nowrap">{{ $r['cantidad'] ?? '' }}</td>
            <td class="c-desc cut">{{ $r['descripcion_item'] ?? '' }}</td>
            <td class="c-price right nowrap">{{ isset($r['precio_unitario']) ? number_format((float)$r['precio_unitario'], 2, '.', ',') : '' }}</td>
            <td class="c-iva-item right nowrap">{{ isset($r['iva_item']) ? number_format((float)$r['iva_item'], 2, '.', ',') : '' }}</td>
            <td class="c-total-item right nowrap">{{ isset($r['total_item']) ? number_format((float)$r['total_item'], 2, '.', ',') : '' }}</td>
            <td class="c-subreq right nowrap">{{ isset($r['subtotal']) ? number_format((float)$r['subtotal'], 2, '.', ',') : '' }}</td>
            <td class="c-ivareq right nowrap">{{ isset($r['iva']) ? number_format((float)$r['iva'], 2, '.', ',') : '' }}</td>
            <td class="c-totreq right nowrap">{{ isset($r['total']) ? number_format((float)$r['total'], 2, '.', ',') : '' }}</td>
            <td class="c-cap nowrap small">
                {{ isset($r['fecha_captura']) && $r['fecha_captura'] !== '' ? \Carbon\Carbon::parse($r['fecha_captura'])->format('Y-m-d') : '—' }}
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="14" class="muted" style="padding:10px;">
              Sin resultados con los filtros actuales.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- DomPDF page numbers --}}
  <script type="text/php">
    if (isset($pdf)) {
      $pdf->page_text(760, 560, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 9, array(107,114,128));
    }
  </script>
</body>
</html>
