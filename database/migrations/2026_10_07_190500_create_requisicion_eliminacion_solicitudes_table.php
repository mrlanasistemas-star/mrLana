<?php

use App\Support\Database\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de eliminación de requisiciones: un colaborador solicita y
 * Contabilidad autoriza o rechaza. Solo procede con estatus CAPTURADA.
 * InnoDB explícito; idempotente ante un intento fallido.
 */
return new class extends Migration
{
    public function up(): void
    {
        SafeMigration::createTable('requisicion_eliminacion_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicion_id')->constrained('requisicions')->cascadeOnDelete();
            $table->foreignId('solicitado_por_id')->constrained('users');
            $table->text('motivo');
            $table->enum('estatus', ['PENDIENTE', 'APROBADA', 'RECHAZADA', 'CANCELADA'])->default('PENDIENTE');
            $table->foreignId('revisado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comentario_revision')->nullable();
            $table->dateTime('fecha_resolucion')->nullable();
            $table->timestamps();

            $table->index(['requisicion_id', 'estatus'], 'req_elim_req_estatus_idx');
        });
    }

    public function down(): void
    {
        SafeMigration::assertCanDiscard(['requisicion_eliminacion_solicitudes' => null], 'solicitudes de eliminación auditadas');

        Schema::dropIfExists('requisicion_eliminacion_solicitudes');
    }
};
