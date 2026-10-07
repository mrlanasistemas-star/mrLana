<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relación uno a uno colaborador ↔ usuario: un colaborador solo puede tener
 * una cuenta. Los NULL (usuarios sin colaborador) siguen permitidos.
 *
 * Si ya existen duplicados, la migración se detiene con un mensaje claro en
 * lugar de borrar o reasignar cuentas automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicados = DB::table('users')
            ->whereNotNull('empleado_id')
            ->select('empleado_id')
            ->groupBy('empleado_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('empleado_id');

        if ($duplicados->isNotEmpty()) {
            throw new RuntimeException(
                'Hay colaboradores con más de un usuario vinculado (empleado_id: '
                .$duplicados->implode(', ')
                .'). Desvincula las cuentas sobrantes antes de migrar.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('empleado_id', 'users_empleado_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // La FK necesita un índice; se crea uno simple antes de quitar el único.
            $table->index('empleado_id', 'users_empleado_id_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_empleado_id_unique');
        });
    }
};
