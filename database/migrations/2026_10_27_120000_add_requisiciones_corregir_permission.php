<?php

use App\Models\Role;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\RoleSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permiso nuevo «Corregir datos de requisiciones» (requisiciones.corregir).
 *
 * Solo crea los permisos faltantes del catálogo y se lo da al rol
 * Administrador (que siempre tiene el catálogo completo). Los demás roles no
 * lo reciben: se asigna desde Roles y permisos. Sin cambios de esquema;
 * idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->pretending() || ! Schema::hasTable(config('permission.table_names.roles'))) {
            return;
        }

        app(RoleSynchronizer::class)->syncPermissions();

        Role::query()->where('name', PermissionCatalog::ROLE_ADMIN)->where('guard_name', RoleSynchronizer::GUARD)->get()
            ->each(fn (Role $role) => $role->hasPermissionTo('requisiciones.corregir') || $role->givePermissionTo('requisiciones.corregir'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** No retira el permiso: pudo asignarse a otros roles desde la interfaz. */
    public function down(): void {}
};
