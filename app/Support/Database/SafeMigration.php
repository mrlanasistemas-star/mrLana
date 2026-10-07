<?php

namespace App\Support\Database;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Utilidades para migraciones que deben correr sobre la base productiva sin
 * perder información.
 *
 * - MySQL/MariaDB no revierte DDL: si un intento anterior falló a la mitad,
 *   pueden quedar tablas vacías sin índices o llaves foráneas. createTable()
 *   las reconstruye solo si están vacías; si tienen datos, se detiene.
 * - Todas las tablas nuevas se crean explícitamente en InnoDB (las llaves
 *   foráneas no existen en MyISAM), sin depender del motor del servidor.
 * - assertCanDiscard() impide que un rollback borre información real salvo
 *   autorización explícita (ERP_ALLOW_DESTRUCTIVE_ROLLBACK=true).
 */
final class SafeMigration
{
    /** Crea la tabla en InnoDB; reutiliza el nombre si solo hay restos vacíos de un intento fallido. */
    public static function createTable(string $table, Closure $definition): void
    {
        if (Schema::hasTable($table)) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException(
                    "La tabla «{$table}» ya existe y contiene datos, pero esta migración no está registrada. "
                    .'Revisa su origen antes de continuar; no se modificó nada.'
                );
            }

            Schema::withoutForeignKeyConstraints(fn () => Schema::drop($table));
        }

        Schema::create($table, function (Blueprint $blueprint) use ($definition) {
            $blueprint->engine('InnoDB');
            $definition($blueprint);
        });
    }

    /** Llave foránea existente sobre la columna (para no duplicarla al reintentar). */
    public static function hasForeignKey(string $table, string $column): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $fk) => $fk['columns'] === [$column]);
    }

    /**
     * Detiene el rollback si borraría registros. $checks: tabla => condición
     * opcional (closure sobre el query builder) que identifica datos reales.
     *
     * @param  array<string, Closure|null>  $checks
     */
    public static function assertCanDiscard(array $checks, string $what): void
    {
        if (config('erp.allow_destructive_rollback')) {
            return;
        }

        foreach ($checks as $table => $condition) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $query = DB::table($table);
            if ($condition) {
                $condition($query);
            }
            if ($query->exists()) {
                throw new RuntimeException(
                    "Rollback detenido: se perderían {$what} (tabla «{$table}»). "
                    .'Restaura el respaldo de la base de datos, o define ERP_ALLOW_DESTRUCTIVE_ROLLBACK=true '
                    .'temporalmente si confirmas que esa información puede descartarse.'
                );
            }
        }
    }
}
