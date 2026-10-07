<?php

use App\Support\Permissions\RoleSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración de datos (idempotente): crea el catálogo de permisos, los roles
 * Administrador / Contabilidad / Colaborador y asigna a cada usuario existente
 * el rol equivalente a su valor legado de users.rol (ADMIN → Administrador,
 * CONTADOR → Contabilidad, cualquier otro → Colaborador).
 *
 * users.rol se conserva como campo legado de compatibilidad; ya no es la
 * fuente de autorización.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Con `migrate --pretend` no hay escrituras reales: la siembra depende
        // de sus propios inserts, así que solo se describe.
        if (DB::pretending()) {
            DB::select("SELECT 'Siembra de permisos, roles iniciales y asignación por users.rol (RoleSynchronizer)' AS paso");

            return;
        }

        app(RoleSynchronizer::class)->sync();
    }

    /**
     * No borra nada: los roles pudieron personalizarse desde la interfaz y las
     * asignaciones son información real. Las tablas se eliminan (con su propia
     * protección) al revertir la migración que las creó.
     */
    public function down(): void
    {
        //
    }
};
