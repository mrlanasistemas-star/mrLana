<?php

use App\Models\Role;
use App\Support\Database\SafeMigration;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\RoleSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Registro de cada eliminación de requisición:
 * - eliminada_por_id / eliminada_at: quién la eliminó (o autorizó la
 *   solicitud) y cuándo.
 * - eliminacion_motivo: por qué.
 * - pagado_al_eliminar: lo pagado en ese momento (0 = sin pagos).
 *
 * Las eliminadas anteriores se completan con lo que ya existe: lo pagado
 * (los pagos no se borran) y, si vinieron de una solicitud autorizada, quién
 * la autorizó, cuándo y el motivo.
 *
 * Permiso nuevo «Eliminar requisiciones con pagos» (requisiciones.eliminar_con_pago)
 * para Administrador y Contabilidad. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicions', function (Blueprint $table) {
            if (! Schema::hasColumn('requisicions', 'eliminada_por_id')) {
                $table->unsignedBigInteger('eliminada_por_id')->nullable()->after('status');
            }
            if (! Schema::hasColumn('requisicions', 'eliminada_at')) {
                $table->timestamp('eliminada_at')->nullable()->after('eliminada_por_id');
            }
            if (! Schema::hasColumn('requisicions', 'eliminacion_motivo')) {
                $table->text('eliminacion_motivo')->nullable()->after('eliminada_at');
            }
            if (! Schema::hasColumn('requisicions', 'pagado_al_eliminar')) {
                $table->decimal('pagado_al_eliminar', 14, 2)->nullable()->after('eliminacion_motivo');
            }
        });

        if (! SafeMigration::hasForeignKey('requisicions', 'eliminada_por_id')) {
            Schema::table('requisicions', fn (Blueprint $t) => $t->foreign('eliminada_por_id')->references('id')->on('users')->nullOnDelete());
        }

        if (DB::connection()->pretending()) {
            return;
        }

        // Eliminadas anteriores: lo pagado y, si hubo solicitud autorizada, quién, cuándo y por qué.
        DB::table('requisicions')
            ->where('status', 'ELIMINADA')
            ->whereNull('pagado_al_eliminar')
            ->orderBy('id')
            ->select('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $pagado = (float) DB::table('pagos')->where('requisicion_id', $row->id)->sum('monto');
                    $solicitud = Schema::hasTable('requisicion_eliminacion_solicitudes')
                        ? DB::table('requisicion_eliminacion_solicitudes')
                            ->where('requisicion_id', $row->id)
                            ->where('estatus', 'APROBADA')
                            ->orderByDesc('id')
                            ->first(['revisado_por_id', 'fecha_resolucion', 'motivo'])
                        : null;

                    DB::table('requisicions')->where('id', $row->id)->update(array_filter([
                        'pagado_al_eliminar' => round($pagado, 2),
                        'eliminada_por_id' => $solicitud?->revisado_por_id,
                        'eliminada_at' => $solicitud?->fecha_resolucion,
                        'eliminacion_motivo' => $solicitud?->motivo,
                    ], fn ($v) => $v !== null));
                }
            });

        if (! Schema::hasTable(config('permission.table_names.roles'))) {
            return;
        }

        app(RoleSynchronizer::class)->syncPermissions();
        Role::query()
            ->whereIn('name', [PermissionCatalog::ROLE_ADMIN, PermissionCatalog::ROLE_CONTABILIDAD])
            ->where('guard_name', RoleSynchronizer::GUARD)
            ->get()
            ->each(fn (Role $role) => $role->hasPermissionTo('requisiciones.eliminar_con_pago') || $role->givePermissionTo('requisiciones.eliminar_con_pago'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        SafeMigration::assertCanDiscard(['requisicions' => fn ($q) => $q->whereNotNull('eliminada_por_id')], 'quién eliminó cada requisición');

        if (SafeMigration::hasForeignKey('requisicions', 'eliminada_por_id')) {
            Schema::table('requisicions', fn (Blueprint $t) => $t->dropForeign(['eliminada_por_id']));
        }
        Schema::table('requisicions', function (Blueprint $table) {
            foreach (['eliminada_por_id', 'eliminada_at', 'eliminacion_motivo', 'pagado_al_eliminar'] as $col) {
                if (Schema::hasColumn('requisicions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
