@component('mail::message')
    # Requisición pagada

    Estimado(a) {{ $requisicion->solicitante->nombre }}:

    Te informamos que tu requisición **{{ $requisicion->folio }}** ha sido **pagada**.

    Puedes consultar el comprobante y los detalles del pago en el portal.

    Saludos cordiales.
@endcomponent
