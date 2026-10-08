<?php

use App\Support\Database\SafeMigration;
use App\Support\Permissions\PermissionTransition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permisos con alcance (propio / sucursal / corporativo / global).
 *
 * Aditiva e idempotente:
 * - Crea la tabla `permission_transitions` (registro de transiciones aplicadas).
 * - Crea los permisos nuevos del catálogo y los asigna a los roles existentes
 *   según lo que ya podían hacer (PermissionTransition::mapFor). No quita
 *   permisos, roles, usuarios ni asignaciones; no cambia IDs.
 * - Los permisos anteriores quedan como legados ocultos.
 *
 * Si la transición ya se registró (p. ej. con `erp:permissions-transition`),
 * no se vuelve a aplicar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Si ya existe con registros (p. ej. tras un intento previo), se reutiliza.
        if (! Schema::hasTable('permission_transitions') || ! DB::table('permission_transitions')->exists()) {
            SafeMigration::createTable('permission_transitions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->json('summary')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
            });
        }

        if (DB::pretending()) {
            DB::select("SELECT 'Transición de permisos con alcance (PermissionTransition): solo agrega permisos a los roles existentes' AS paso");

            return;
        }

        if (! Schema::hasTable(config('permission.table_names.roles'))) {
            return;
        }

        $transition = app(PermissionTransition::class);
        if (! $transition->alreadyApplied()) {
            $transition->run();
        }
    }

    /**
     * No retira permisos: los roles pudieron personalizarse después y las
     * asignaciones son información real. Solo elimina el registro de
     * transiciones si no contiene datos (o con autorización explícita).
     */
    public function down(): void
    {
        SafeMigration::assertCanDiscard(['permission_transitions' => null], 'el registro de transiciones de permisos aplicadas');

        Schema::dropIfExists('permission_transitions');
    }
};
