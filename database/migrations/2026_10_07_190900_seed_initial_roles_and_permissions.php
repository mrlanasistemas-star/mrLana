<?php

use App\Support\Permissions\RoleSynchronizer;
use Illuminate\Database\Migrations\Migration;

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
