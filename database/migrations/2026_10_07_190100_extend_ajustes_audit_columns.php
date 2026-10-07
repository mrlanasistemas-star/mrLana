<?php

use App\Support\Database\SafeMigration;
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
        // TEXT es idempotente: repetir el cambio no altera los datos.
        Schema::table('ajustes', function (Blueprint $table) {
            $table->text('motivo')->nullable()->change();
            $table->text('notas')->nullable()->change();
        });

        // Columnas nuevas, omitiendo las que un intento previo ya creó.
        Schema::table('ajustes', function (Blueprint $table) {
            if (! Schema::hasColumn('ajustes', 'comentario_revision')) {
                $table->text('comentario_revision')->nullable()->after('notas');
            }
            if (! Schema::hasColumn('ajustes', 'user_aplica_id')) {
                $table->unsignedBigInteger('user_aplica_id')->nullable()->after('user_resuelve_id');
            }
            if (! Schema::hasColumn('ajustes', 'fecha_aplicacion')) {
                $table->dateTime('fecha_aplicacion')->nullable()->after('fecha_resolucion');
            }
        });

        if (! SafeMigration::hasForeignKey('ajustes', 'user_aplica_id')) {
            Schema::table('ajustes', function (Blueprint $table) {
                $table->foreign('user_aplica_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        DB::table('ajustes')
            ->whereNotNull('fecha_resolucion')
            ->whereNotNull('notas')
            ->whereNull('comentario_revision')
            ->update(['comentario_revision' => DB::raw('notas')]);
    }

    public function down(): void
    {
        // La auditoría de aplicación y los comentarios de revisión son información real.
        SafeMigration::assertCanDiscard([
            'ajustes' => fn ($q) => $q->whereNotNull('user_aplica_id')
                ->orWhereNotNull('fecha_aplicacion')
                ->orWhere(fn ($w) => $w->whereNotNull('comentario_revision')->whereColumn('comentario_revision', '!=', 'notas')),
        ], 'datos de auditoría de ajustes (quién aplicó y comentarios de revisión)');

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

        if (SafeMigration::hasForeignKey('ajustes', 'user_aplica_id')) {
            Schema::table('ajustes', fn (Blueprint $table) => $table->dropForeign(['user_aplica_id']));
        }
        Schema::table('ajustes', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['user_aplica_id', 'comentario_revision', 'fecha_aplicacion'],
                fn ($c) => Schema::hasColumn('ajustes', $c),
            )));
        });

        Schema::table('ajustes', function (Blueprint $table) {
            $table->string('motivo', 255)->nullable()->change();
            $table->string('notas', 255)->nullable()->change();
        });
    }
};
