<?php

use App\Support\Database\SafeMigration;
use App\Support\Permissions\RoleSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * - requisicions.pago_autorizado_por_id: quién autorizó el pago (antes solo
 *   se guardaba la fecha). Los registros anteriores quedan vacíos.
 * - Permisos nuevos para exportar Comprobantes y Pagos: se otorgan a los
 *   roles que ya pueden ver esos módulos (no amplía lo que ya veían).
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('requisicions', 'pago_autorizado_por_id')) {
            Schema::table('requisicions', function (Blueprint $table) {
                $table->unsignedBigInteger('pago_autorizado_por_id')->nullable()->after('fecha_autorizacion');
            });
        }
        if (! SafeMigration::hasForeignKey('requisicions', 'pago_autorizado_por_id')) {
            Schema::table('requisicions', fn (Blueprint $t) => $t->foreign('pago_autorizado_por_id')->references('id')->on('users')->nullOnDelete());
        }

        if (DB::connection()->pretending() || ! Schema::hasTable(config('permission.table_names.roles'))) {
            return;
        }

        app(RoleSynchronizer::class)->syncPermissions();
        foreach (['comprobaciones.ver' => 'comprobaciones.exportar', 'pagos.ver' => 'pagos.exportar'] as $view => $export) {
            \App\Models\Role::query()->whereHas('permissions', fn ($q) => $q->where('name', $view))->get()
                ->each(fn ($role) => $role->givePermissionTo($export));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        SafeMigration::assertCanDiscard(['requisicions' => fn ($q) => $q->whereNotNull('pago_autorizado_por_id')], 'quién autorizó cada pago');

        if (SafeMigration::hasForeignKey('requisicions', 'pago_autorizado_por_id')) {
            Schema::table('requisicions', fn (Blueprint $t) => $t->dropForeign(['pago_autorizado_por_id']));
        }
        if (Schema::hasColumn('requisicions', 'pago_autorizado_por_id')) {
            Schema::table('requisicions', fn (Blueprint $t) => $t->dropColumn('pago_autorizado_por_id'));
        }
    }
};
