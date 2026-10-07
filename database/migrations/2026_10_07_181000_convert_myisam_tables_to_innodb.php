<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte a InnoDB las tablas del ERP que estén en MyISAM.
 *
 * MyISAM no soporta transacciones, bloqueo de filas ni llaves foráneas, que
 * este incremento necesita (p. ej. evitar la doble aprobación de un ajuste).
 * ALTER TABLE ... ENGINE=InnoDB conserva todos los datos e índices.
 *
 * - En servidores que ya usan InnoDB no hace nada.
 * - Guarda el motor original de cada tabla convertida para poder revertir.
 * - Solo aplica a MySQL/MariaDB.
 */
return new class extends Migration
{
    private const LOG_TABLE = 'erp_engine_conversions';

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // Alias explícito: MySQL 8 devuelve TABLE_NAME en mayúsculas.
        $tables = collect(DB::select(
            "SELECT table_name AS nombre FROM information_schema.tables
             WHERE table_schema = ? AND table_type = 'BASE TABLE' AND engine = 'MyISAM'",
            [DB::getDatabaseName()]
        ))->pluck('nombre')->all();

        if ($tables === []) {
            return;
        }

        if (! Schema::hasTable(self::LOG_TABLE)) {
            Schema::create(self::LOG_TABLE, function (Blueprint $table) {
                $table->engine('InnoDB');
                $table->string('table_name', 64)->primary();
                $table->string('original_engine', 32);
                $table->timestamp('converted_at')->useCurrent();
            });
        }

        foreach ($tables as $table) {
            DB::statement('ALTER TABLE `'.str_replace('`', '', $table).'` ENGINE=InnoDB');
            DB::table(self::LOG_TABLE)->updateOrInsert(
                ['table_name' => $table],
                ['original_engine' => 'MyISAM', 'converted_at' => now()],
            );
        }
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || ! Schema::hasTable(self::LOG_TABLE)) {
            return;
        }

        foreach (DB::table(self::LOG_TABLE)->get() as $row) {
            if (Schema::hasTable($row->table_name)) {
                DB::statement('ALTER TABLE `'.str_replace('`', '', $row->table_name).'` ENGINE='.$row->original_engine);
            }
        }

        Schema::drop(self::LOG_TABLE);
    }
};
