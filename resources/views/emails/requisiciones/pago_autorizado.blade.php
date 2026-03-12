@component('mail::message')
    # Requisición autorizada para pago

    Estimado(a) {{ $requisicion->solicitante->nombre }}:

    Tu requisición **{{ $requisicion->folio }}** ha sido autorizada para pago.
    La fecha de pago programada es **{{ \Carbon\Carbon::parse($fechaPago)->format('d/m/Y') }}**.

    Puedes ingresar al portal para revisar los detalles.

@endcomponent
