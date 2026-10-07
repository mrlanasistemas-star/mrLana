<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite razón social repetida entre proveedores del mismo dueño.
 *
 * Idempotente: en servidores donde el índice único ya se había quitado a mano,
 * o donde una ejecución previa falló a la mitad (MySQL no revierte DDL), cada
 * paso se aplica solo si hace falta.
 */
return new class extends Migration
{
    private const SIMPLE_INDEX = 'proveedors_user_duenio_id_index';

    private const UNIQUE_INDEX = 'proveedors_user_razon_unique';

    public function up(): void
    {
        // Primero un índice simple para que la FK siga teniendo soporte.
        if (! Schema::hasIndex('proveedors', self::SIMPLE_INDEX)) {
            Schema::table('proveedors', function (Blueprint $table) {
                $table->index('user_duenio_id', self::SIMPLE_INDEX);
            });
        }

        if (Schema::hasIndex('proveedors', self::UNIQUE_INDEX)) {
            Schema::table('proveedors', function (Blueprint $table) {
                $table->dropUnique(self::UNIQUE_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('proveedors', self::UNIQUE_INDEX)) {
            Schema::table('proveedors', function (Blueprint $table) {
                $table->unique(['user_duenio_id', 'razon_social'], self::UNIQUE_INDEX);
            });
        }

        if (Schema::hasIndex('proveedors', self::SIMPLE_INDEX)) {
            Schema::table('proveedors', function (Blueprint $table) {
                $table->dropIndex(self::SIMPLE_INDEX);
            });
        }
    }
};
