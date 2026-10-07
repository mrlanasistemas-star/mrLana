<?php

use App\Support\Database\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la "Fecha esperada de pago" (opcional, capturada por el solicitante).
 *
 * No reutiliza `fecha_autorizacion`: esa columna registra el momento real en
 * que se autorizó el pago (RequisicionPagoController::authorizePago) y no se
 * modifica ni se reinterpreta aquí. Idempotente: omite columnas ya creadas.
 */
return new class extends Migration
{
    private const TABLES = ['requisicions', 'plantillas'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasColumn($name, 'fecha_pago_esperada')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->date('fecha_pago_esperada')->nullable()->after('fecha_solicitud');
                });
            }
        }
    }

    public function down(): void
    {
        SafeMigration::assertCanDiscard(
            array_fill_keys(self::TABLES, fn ($q) => $q->whereNotNull('fecha_pago_esperada')),
            'fechas esperadas de pago capturadas',
        );

        foreach (self::TABLES as $name) {
            if (Schema::hasColumn($name, 'fecha_pago_esperada')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('fecha_pago_esperada'));
            }
        }
    }
};
