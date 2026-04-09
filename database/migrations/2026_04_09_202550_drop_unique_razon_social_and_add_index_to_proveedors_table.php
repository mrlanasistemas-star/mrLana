<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('proveedors', function (Blueprint $table) {
            // Primero crea un índice simple para que la FK siga teniendo soporte
            $table->index('user_duenio_id', 'proveedors_user_duenio_id_index');
        });

        Schema::table('proveedors', function (Blueprint $table) {
            // Ya ahora sí puedes quitar el unique
            $table->dropUnique('proveedors_user_razon_unique');
        });
    }

    public function down(): void
    {
        Schema::table('proveedors', function (Blueprint $table) {
            $table->unique(
                ['user_duenio_id', 'razon_social'],
                'proveedors_user_razon_unique'
            );

            $table->dropIndex('proveedors_user_duenio_id_index');
        });
    }
    
};
