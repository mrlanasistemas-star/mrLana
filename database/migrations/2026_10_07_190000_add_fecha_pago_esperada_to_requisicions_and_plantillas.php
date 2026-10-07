<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la "Fecha esperada de pago" (opcional, capturada por el solicitante).
 *
 * No reutiliza `fecha_autorizacion`: esa columna registra el momento real en
 * que se autorizó el pago (RequisicionPagoController::authorizePago) y no se
 * modifica ni se reinterpreta aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicions', function (Blueprint $table) {
            $table->date('fecha_pago_esperada')->nullable()->after('fecha_solicitud');
        });

        Schema::table('plantillas', function (Blueprint $table) {
            $table->date('fecha_pago_esperada')->nullable()->after('fecha_solicitud');
        });
    }

    public function down(): void
    {
        Schema::table('requisicions', function (Blueprint $table) {
            $table->dropColumn('fecha_pago_esperada');
        });

        Schema::table('plantillas', function (Blueprint $table) {
            $table->dropColumn('fecha_pago_esperada');
        });
    }
};
