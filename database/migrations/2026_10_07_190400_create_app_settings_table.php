<?php

use App\Support\Database\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración global (registro único). No guarda secretos: solo apariencia
 * y enlaces públicos. InnoDB explícito; idempotente ante un intento fallido.
 */
return new class extends Migration
{
    public function up(): void
    {
        SafeMigration::createTable('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('primary_color', 7)->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->string('button_color', 7)->nullable();
            $table->string('success_color', 7)->nullable();
            $table->string('warning_color', 7)->nullable();
            $table->string('danger_color', 7)->nullable();
            $table->json('chart_palette')->nullable();
            $table->string('mobile_app_url', 500)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        SafeMigration::assertCanDiscard(['app_settings' => null], 'la configuración del sistema (colores, logo y URL)');

        Schema::dropIfExists('app_settings');
    }
};
