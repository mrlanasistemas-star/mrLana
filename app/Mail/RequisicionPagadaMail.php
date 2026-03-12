<?php

namespace App\Mail;

use App\Models\Requisicion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RequisicionPagadaMail extends Mailable {

    use Queueable, SerializesModels;

    public Requisicion $requisicion;

    public function __construct(Requisicion $requisicion) {
        $this->requisicion = $requisicion;
    }

    public function build(): self {
        return $this->subject('Requisición pagada #' . $this->requisicion->folio)
            ->markdown('emails.requisiciones.pagada', [
                'requisicion' => $this->requisicion,
            ]);
    }

}
