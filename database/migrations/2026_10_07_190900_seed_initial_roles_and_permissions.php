<?php

use App\Support\Permissions\RoleSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración de datos (idempotente): crea el catálogo de permisos, los roles
 * Administrador / Contabilidad / Colaborador y asigna a cada usuario existente
 * el rol equivalente a su valor legado de users.rol.
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

    public function down(): void
    {
        $tables = config('permission.table_names');

        DB::table($tables['model_has_roles'])->delete();
        DB::table($tables['role_has_permissions'])->delete();
        DB::table('role_notification_preferences')->delete();
        DB::table($tables['roles'])->delete();
        DB::table($tables['permissions'])->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
