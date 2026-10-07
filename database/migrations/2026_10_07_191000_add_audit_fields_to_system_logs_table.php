<?php

use App\Support\Database\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora rastreable: guarda el nombre legible del registro y los cambios
 * (antes/después) en JSON para filtrar y mostrar el historial de cada
 * registro. Idempotente; no modifica los registros existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('system_logs', 'etiqueta')) {
                $table->string('etiqueta', 255)->nullable()->after('registro_id');
            }
            if (! Schema::hasColumn('system_logs', 'cambios')) {
                $table->json('cambios')->nullable()->after('descripcion');
            }
        });

        if (! Schema::hasIndex('system_logs', 'system_logs_tabla_registro_idx')) {
            Schema::table('system_logs', fn (Blueprint $t) => $t->index(['tabla', 'registro_id'], 'system_logs_tabla_registro_idx'));
        }
        if (! Schema::hasIndex('system_logs', 'system_logs_created_at_idx')) {
            Schema::table('system_logs', fn (Blueprint $t) => $t->index('created_at', 'system_logs_created_at_idx'));
        }
    }

    public function down(): void
    {
        SafeMigration::assertCanDiscard([
            'system_logs' => fn ($q) => $q->whereNotNull('cambios')->orWhereNotNull('etiqueta'),
        ], 'el detalle de cambios de la bitácora');

        foreach (['system_logs_tabla_registro_idx', 'system_logs_created_at_idx'] as $index) {
            if (Schema::hasIndex('system_logs', $index)) {
                Schema::table('system_logs', fn (Blueprint $t) => $t->dropIndex($index));
            }
        }
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(['etiqueta', 'cambios'], fn ($c) => Schema::hasColumn('system_logs', $c))));
        });
    }
};
