<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes de monto:
 * - motivo y notas pasan de VARCHAR(255) a TEXT (motivos largos sin truncar).
 * - comentario_revision: comentario de quien aprueba/rechaza (antes se guardaba en notas).
 * - user_aplica_id / fecha_aplicacion: auditoría del paso "Aplicar".
 *
 * Datos existentes: no se borra nada. Para ajustes ya resueltos se COPIA el
 * contenido de `notas` a `comentario_revision` (la revisión anterior lo
 * guardaba ahí); `notas` conserva su valor original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ajustes', function (Blueprint $table) {
            $table->text('motivo')->nullable()->change();
            $table->text('notas')->nullable()->change();
        });

        Schema::table('ajustes', function (Blueprint $table) {
            $table->text('comentario_revision')->nullable()->after('notas');
            $table->foreignId('user_aplica_id')->nullable()->after('user_resuelve_id')->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_aplicacion')->nullable()->after('fecha_resolucion');
        });

        DB::table('ajustes')
            ->whereNotNull('fecha_resolucion')
            ->whereNotNull('notas')
            ->whereNull('comentario_revision')
            ->update(['comentario_revision' => DB::raw('notas')]);
    }

    public function down(): void
    {
        $tooLong = DB::table('ajustes')
            ->where(function ($q) {
                $q->whereRaw('LENGTH(motivo) > 255')->orWhereRaw('LENGTH(notas) > 255');
            })
            ->count();

        if ($tooLong > 0) {
            throw new RuntimeException(
                "No se puede revertir: {$tooLong} ajuste(s) tienen motivo o notas de más de 255 caracteres y se truncarían."
            );
        }

        Schema::table('ajustes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_aplica_id');
            $table->dropColumn(['comentario_revision', 'fecha_aplicacion']);
        });

        Schema::table('ajustes', function (Blueprint $table) {
            $table->string('motivo', 255)->nullable()->change();
            $table->string('notas', 255)->nullable()->change();
        });
    }
};
