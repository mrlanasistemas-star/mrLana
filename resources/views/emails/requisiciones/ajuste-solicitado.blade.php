<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo ajuste solicitado</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#111827; line-height:1.6;">
    <h2 style="margin-bottom: 8px;">Nuevo ajuste solicitado</h2>

    <p>
        Se registró una nueva solicitud de ajuste para la requisición
        <strong>{{ $requisicion->folio ?? ('#' . $requisicion->id) }}</strong>.
    </p>

    <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
        <tr>
            <td><strong>Solicitado por:</strong></td>
            <td>{{ $senderName }}</td>
        </tr>
        <tr>
            <td><strong>Tipo:</strong></td>
            <td>{{ $ajuste->tipo }}</td>
        </tr>
        <tr>
            <td><strong>Sentido:</strong></td>
            <td>{{ $ajuste->sentido }}</td>
        </tr>
        <tr>
            <td><strong>Monto:</strong></td>
            <td>${{ number_format((float) $ajuste->monto, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Monto anterior:</strong></td>
            <td>${{ number_format((float) $ajuste->monto_anterior, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Monto nuevo estimado:</strong></td>
            <td>${{ number_format((float) $ajuste->monto_nuevo, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Fecha:</strong></td>
            <td>{{ optional($ajuste->fecha_registro)->format('Y-m-d') ?? $ajuste->fecha_registro }}</td>
        </tr>
        <tr>
            <td><strong>Motivo:</strong></td>
            <td style="white-space: pre-wrap; word-break: break-word;">{{ $ajuste->motivo ?? '—' }}</td>
        </tr>
    </table>

    <p style="margin-top: 18px;">
        Favor de revisar la solicitud en el ERP.
    </p>
</body>
</html>
