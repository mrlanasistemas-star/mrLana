<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Requisición enviada</title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;">
  <div style="max-width:760px;margin:0 auto;padding:24px;">
    <div style="background:#ffffff;border:1px solid #e6e8f0;border-radius:18px;padding:22px;">
      <h2 style="margin:0 0 10px 0;font-size:18px;color:#111827;">Requisición enviada</h2>
      <p style="margin:0 0 16px 0;color:#374151;font-size:13px;line-height:1.5;">
        Se ha generado una requisición y se envía para seguimiento.
      </p>

      <div style="background:#f9fafb;border:1px solid #eef2f7;border-radius:14px;padding:14px;margin-bottom:16px;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;color:#111827;">
          <tr>
            <td style="padding:6px 0;color:#6b7280;width:180px;">Folio</td>
            <td style="padding:6px 0;font-weight:700;">{{ $r->folio ?? $r->id }}</td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:#6b7280;">Status</td>
            <td style="padding:6px 0;font-weight:700;">{{ $r->status }}</td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:#6b7280;">Comprador</td>
            <td style="padding:6px 0;font-weight:700;">{{ optional($r->comprador)->nombre }}</td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:#6b7280;">Sucursal</td>
            <td style="padding:6px 0;font-weight:700;">{{ optional($r->sucursal)->nombre }}</td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:#6b7280;">Solicitante</td>
            <td style="padding:6px 0;font-weight:700;">
              {{ optional($r->solicitante)->nombre }}
              {{ optional($r->solicitante)->apellido_paterno }}
              {{ optional($r->solicitante)->apellido_materno }}
            </td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:#6b7280;">Concepto</td>
            <td style="padding:6px 0;font-weight:700;">{{ optional($r->concepto)->nombre }}</td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:#6b7280;">Proveedor</td>
            <td style="padding:6px 0;font-weight:700;">{{ optional($r->proveedor)->razon_social }}</td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:#6b7280;">Fecha de solicitud</td>
            <td style="padding:6px 0;font-weight:700;">{{ optional($r->fecha_solicitud)->format('d/m/Y') ?? '—' }}</td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:#6b7280;">Fecha esperada de pago</td>
            <td style="padding:6px 0;font-weight:700;">{{ optional($r->fecha_pago_esperada)->format('d/m/Y') ?? 'Sin definir' }}</td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:#6b7280;">Autorización de pago</td>
            <td style="padding:6px 0;font-weight:700;">{{ optional($r->fecha_autorizacion)->format('d/m/Y H:i') ?? 'Pendiente' }}</td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:#6b7280;">Total</td>
            <td style="padding:6px 0;font-weight:700;">${{ number_format((float)$r->monto_total, 2) }}</td>
          </tr>
        </table>

        @if(!empty($r->observaciones))
          <div style="margin-top:12px;">
            <div style="font-size:12px;color:#6b7280;font-weight:700;margin-bottom:6px;">Observaciones</div>
            <div style="font-size:13px;color:#111827;">{{ $r->observaciones }}</div>
          </div>
        @endif
      </div>

      <h3 style="margin:0 0 10px 0;font-size:14px;color:#111827;">Items</h3>

      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead>
          <tr>
            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;color:#6b7280;">Cant.</th>
            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;color:#6b7280;">Descripción</th>
            <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb;color:#6b7280;">P. Unit</th>
            <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb;color:#6b7280;">IVA</th>
            <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb;color:#6b7280;">Total</th>
          </tr>
        </thead>
        <tbody>
          @foreach(($r->detalles ?? []) as $d)
            <tr>
              <td style="padding:10px;border-bottom:1px solid #f1f5f9;color:#111827;">{{ $d->cantidad }}</td>
              <td style="padding:10px;border-bottom:1px solid #f1f5f9;color:#111827;">{{ $d->descripcion }}</td>
              <td style="padding:10px;border-bottom:1px solid #f1f5f9;color:#111827;text-align:right;">
                ${{ number_format((float)$d->precio_unitario, 2) }}
              </td>
              <td style="padding:10px;border-bottom:1px solid #f1f5f9;color:#111827;text-align:right;">
                ${{ number_format((float)$d->iva, 2) }}
              </td>
              <td style="padding:10px;border-bottom:1px solid #f1f5f9;color:#111827;text-align:right;font-weight:700;">
                ${{ number_format((float)$d->total, 2) }}
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <p style="margin:16px 0 0 0;color:#6b7280;font-size:12px;line-height:1.5;">
        Este correo fue generado automáticamente por el sistema MR-Lana.
      </p>
    </div>
  </div>
</body>
</html>
