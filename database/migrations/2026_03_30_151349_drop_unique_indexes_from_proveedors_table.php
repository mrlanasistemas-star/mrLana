<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quita las restricciones únicas de RFC y CLABE por dueño.
 * Idempotente: solo elimina/restaura los índices que existan o falten.
 */
return new class extends Migration
{
    private const INDEXES = [
        'proveedors_user_rfc_unique' => ['user_duenio_id', 'rfc'],
        'proveedors_user_clabe_unique' => ['user_duenio_id', 'clabe'],
    ];

    public function up(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            if (Schema::hasIndex('proveedors', $name)) {
                Schema::table('proveedors', fn (Blueprint $table) => $table->dropUnique($name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $name => $columns) {
            if (! Schema::hasIndex('proveedors', $name)) {
                Schema::table('proveedors', fn (Blueprint $table) => $table->unique($columns, $name));
            }
        }
    }
};
