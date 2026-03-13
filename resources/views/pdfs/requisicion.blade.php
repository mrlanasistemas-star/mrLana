<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>{{ $requisicion->folio ?? 'Requisición' }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
    .muted { color:#6B7280; }
    .h1 { font-size: 18px; font-weight: 800; margin: 0 0 6px; }
    .card { border: 1px solid #E5E7EB; border-radius: 10px; padding: 12px; margin-bottom: 12px; }
    .row { display: table; width: 100%; }
    .col { display: table-cell; vertical-align: top; }
    .col-50 { width: 50%; }
    .k { font-size: 10px; letter-spacing: .08em; text-transform: uppercase; font-weight: 800; color:#6B7280; }
    .v { font-size: 12px; font-weight: 700; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border-bottom: 1px solid #E5E7EB; padding: 8px 6px; }
    th { font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color:#6B7280; text-align: left; }
    td.num { text-align: right; }
    .tot { text-align: right; font-weight: 800; }
    .pill {
      display: inline-block;
      font-size: 10px;
      font-weight: 800;
      padding: 2px 8px;
      border-radius: 999px;
      border: 1px solid #E5E7EB;
    }
    .pill-ok { background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
    .pill-bad { background:#fff1f2; color:#9f1239; border-color:#fecdd3; }
    .pill-mid { background:#eef2ff; color:#3730a3; border-color:#c7d2fe; }
    .pill-warn { background:#fffbeb; color:#92400e; border-color:#fde68a; }
    .miniBox {
      border: 1px solid #E5E7EB;
      border-radius: 8px;
      padding: 8px 10px;
      margin-top: 8px;
    }
  </style>
</head>
<body>

  @php
    $money = fn($v) => '$' . number_format((float)($v ?? 0), 2);
    $ajustes = collect($requisicion->ajustes ?? [])->sortByDesc('id')->values();

    $totalItemsOriginal = isset($totalItemsOriginal)
      ? (float) $totalItemsOriginal
      : (float) collect($requisicion->detalles ?? [])->sum(fn($d) => (float)($d->total ?? 0));

    $totalFinalAuditado = isset($totalFinalAuditado)
      ? (float) $totalFinalAuditado
      : (float) ($requisicion->monto_total ?? 0);

    $totalAjustesAplicados = isset($totalAjustesAplicados)
      ? (float) $totalAjustesAplicados
      : round($totalFinalAuditado - $totalItemsOriginal, 2);
  @endphp

  <div class="card">
    <div class="h1">Requisición {{ $requisicion->folio ?? '' }}</div>
    <div class="muted">
      Estado: <strong>{{ $requisicion->status ?? '—' }}</strong> •
      Capturada: <strong>{{ optional($requisicion->created_at)->format('d M Y, h:i a') }}</strong> •
      Actualizada: <strong>{{ optional($requisicion->updated_at)->format('d M Y, h:i a') }}</strong>
    </div>
  </div>

  <div class="card">
    <div class="row">
      <div class="col col-50">
        <div class="k">Comprador</div>
        <div class="v">{{ $requisicion->comprador->nombre ?? '—' }}</div>
      </div>
      <div class="col col-50">
        <div class="k">Sucursal</div>
        <div class="v">{{ $requisicion->sucursal->nombre ?? '—' }}</div>
      </div>
    </div>

    <div style="height:10px"></div>

    <div class="row">
      <div class="col col-50">
        <div class="k">Solicitante</div>
        <div class="v">
          {{ $requisicion->solicitante
              ? trim(($requisicion->solicitante->nombre ?? '').' '.($requisicion->solicitante->apellido_paterno ?? '').' '.($requisicion->solicitante->apellido_materno ?? ''))
              : '—'
          }}
        </div>
      </div>
      <div class="col col-50">
        <div class="k">Proveedor</div>
        <div class="v">{{ $requisicion->proveedor->razon_social ?? ($requisicion->proveedor->nombre ?? '—') }}</div>
      </div>
    </div>

    <div style="height:10px"></div>

    <div class="row">
      <div class="col col-50">
        <div class="k">Concepto</div>
        <div class="v">{{ $requisicion->concepto->nombre ?? '—' }}</div>
      </div>
      <div class="col col-50">
        <div class="k">Fechas</div>
        <div class="v">
          Solicitud: {{ optional($requisicion->fecha_solicitud)->format('d M Y') ?? '—' }} |
          Autorización: {{ optional($requisicion->fecha_autorizacion)->format('d M Y') ?? '—' }} |
          Pago: {{ optional($requisicion->fecha_pago)->format('d M Y') ?? '—' }}
        </div>
      </div>
    </div>

    <div style="height:10px"></div>
    <div class="k">Observaciones</div>
    <div class="v" style="font-weight:600">{{ $requisicion->observaciones ?? '—' }}</div>
  </div>

  <div class="card">
    <div class="k" style="margin-bottom:6px">Items</div>

    <table>
      <thead>
        <tr>
          <th style="width:70px">Cant.</th>
          <th style="width:140px">Sucursal</th>
          <th>Descripción</th>
          <th style="width:110px; text-align:right">Importe</th>
          <th style="width:80px; text-align:right">IVA</th>
          <th style="width:110px; text-align:right">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach(($requisicion->detalles ?? []) as $d)
          <tr>
            <td>{{ $d->cantidad ?? '—' }}</td>
            <td>{{ $d->sucursal->nombre ?? '—' }}</td>
            <td>{{ $d->descripcion ?? '—' }}</td>
            <td class="num">${{ number_format((float)($d->subtotal ?? 0), 2) }}</td>
            <td class="num">${{ number_format((float)($d->iva ?? 0), 2) }}</td>
            <td class="num"><strong>${{ number_format((float)($d->total ?? 0), 2) }}</strong></td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div style="height:10px"></div>

    <div class="tot">
      Subtotal: ${{ number_format((float)($requisicion->monto_subtotal ?? 0), 2) }} <br>
      Total items: ${{ number_format((float)$totalItemsOriginal, 2) }}
    </div>
  </div>

  <div class="card">
    <div class="k" style="margin-bottom:6px">Resumen de auditoría</div>

    <div class="miniBox">
      <div class="k">Total original por items</div>
      <div class="v">{{ $money($totalItemsOriginal) }}</div>
    </div>

    <div class="miniBox">
      <div class="k">Ajuste neto aplicado</div>
      <div class="v">{{ $money($totalAjustesAplicados) }}</div>
    </div>

    <div class="miniBox">
      <div class="k">Total final requisición</div>
      <div class="v">{{ $money($totalFinalAuditado) }}</div>
    </div>
  </div>

  <div class="card">
    <div class="k" style="margin-bottom:6px">Ajustes registrados</div>

    @if($ajustes->isEmpty())
      <div class="v" style="font-weight:600">No hay ajustes registrados.</div>
    @else
      <table>
        <thead>
          <tr>
            <th style="width:58px">ID</th>
            <th style="width:125px">Tipo</th>
            <th style="width:100px">Estatus</th>
            <th style="width:95px; text-align:right">Impacto</th>
            <th style="width:95px; text-align:right">Anterior</th>
            <th style="width:95px; text-align:right">Nuevo</th>
            <th>Motivo</th>
          </tr>
        </thead>
        <tbody>
          @foreach($ajustes as $a)
            @php
              $tipo = strtoupper((string)($a->tipo ?? ''));
              $estatus = strtoupper((string)($a->estatus ?? ''));
              $sentido = strtoupper((string)($a->sentido ?? ''));

              $tipoLabel =
                $tipo === 'INCREMENTO_AUTORIZADO' ? 'Incremento autorizado' :
                ($tipo === 'FALTANTE' ? 'Faltante' :
                ($tipo === 'DEVOLUCION' ? 'Devolución' : $tipo));

              $impacto = $sentido === 'A_FAVOR_EMPRESA'
                ? -1 * (float)($a->monto ?? 0)
                : (float)($a->monto ?? 0);

              $pillClass =
                $estatus === 'APLICADO' ? 'pill-mid' :
                ($estatus === 'APROBADO' ? 'pill-ok' :
                ($estatus === 'RECHAZADO' ? 'pill-bad' :
                ($estatus === 'CANCELADO' ? 'pill-bad' : 'pill-warn')));
            @endphp
            <tr>
              <td>#{{ $a->id }}</td>
              <td>{{ $tipoLabel }}</td>
              <td><span class="pill {{ $pillClass }}">{{ $estatus }}</span></td>
              <td class="num">{{ $money($impacto) }}</td>
              <td class="num">{{ $money($a->monto_anterior ?? 0) }}</td>
              <td class="num">{{ $money($a->monto_nuevo ?? 0) }}</td>
              <td>{{ $a->motivo ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

</body>
</html>
