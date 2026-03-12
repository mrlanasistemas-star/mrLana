<?php

namespace App\Mail;

use App\Models\Requisicion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RequisicionPagoAutorizadoMail extends Mailable {

    use Queueable, SerializesModels;

    public Requisicion $requisicion;
    public string $fechaPago;

    public function __construct(Requisicion $requisicion, string $fechaPago) {
        $this->requisicion = $requisicion;
        $this->fechaPago = $fechaPago;
    }

    public function build(): self {
        return $this->subject('Requisición autorizada para pago #' . $this->requisicion->folio)
            ->markdown('emails.requisiciones.pago_autorizado', [
                'requisicion' => $this->requisicion,
                'fechaPago'   => $this->fechaPago,
            ]);
    }

}
