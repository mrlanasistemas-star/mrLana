<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void {
        Schema::table('proveedors', function (Blueprint $table) {
            // Quitar restricciones unique actuales
            $table->dropUnique('proveedors_user_rfc_unique');
            $table->dropUnique('proveedors_user_clabe_unique');
            // Quita esta también SOLO si quieres permitir razón social repetida
            // $table->dropUnique('proveedors_user_razon_unique');
        });
    }

    public function down(): void {
        Schema::table('proveedors', function (Blueprint $table) {
            $table->unique(
                ['user_duenio_id', 'rfc'],
                'proveedors_user_rfc_unique'
            );

            $table->unique(
                ['user_duenio_id', 'clabe'],
                'proveedors_user_clabe_unique'
            );
            // Restáurala solo si la quitaste en up()
            // $table->unique(
            //     ['user_duenio_id', 'razon_social'],
            //     'proveedors_user_razon_unique'
            // );
        });
    }

};
