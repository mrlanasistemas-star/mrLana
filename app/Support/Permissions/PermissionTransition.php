<?php

namespace App\Support\Permissions;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Transición de los permisos generales a permisos con alcance (octubre 2026).
 *
 * Solo AGREGA permisos; nunca quita permisos, roles ni asignaciones. Los
 * permisos anteriores quedan en el rol como legados ocultos (ya no autorizan
 * nada) hasta que alguien guarde el rol desde la interfaz.
 *
 * Reglas (mapFor):
 * - Administrador recibe el catálogo completo.
 * - "Ver todas las requisiciones" conserva el acceso global y, para no romper
 *   la operación, la captura a nombre de otros (elegir corporativo, sucursal y
 *   solicitante), editar cualquier borrador visible y el dashboard general.
 * - "Ver requisiciones propias" → "Ver mis requisiciones" (misma clave).
 * - Dashboard: con acceso global previo a requisiciones → general; si no → personal.
 * - "Ver notificaciones" → "Ver mis notificaciones" (misma clave). "Ver todas
 *   las notificaciones" solo para Administrador.
 * - Pagos / comprobaciones / ajustes: global si el rol veía todas las
 *   requisiciones, propio si solo las propias, nada si no tenía acceso.
 * - Acciones partidas (revisar → aceptar + rechazar, etc.) se conceden solo a
 *   quien ya tenía la acción equivalente.
 *
 * Idempotente: volver a ejecutarla sobre el mismo estado no agrega nada. Se
 * registra en `permission_transitions` para no repetirse sobre roles que se
 * personalizaron después.
 */
class PermissionTransition
{
    public const NAME = 'scoped-permissions-2026-10';

    /**
     * Permisos que se agregan a un rol según los que ya tiene.
     *
     * @param  list<string>  $had
     * @return list<string>
     */
    public static function mapFor(array $had): array
    {
        $has = fn (string ...$p) => array_values(array_intersect($p, $had)) === $p;

        $global = $has('requisiciones.ver_todos');
        $own = $has('requisiciones.ver_propios');
        $add = [];
        $give = function (bool $cond, string ...$perms) use (&$add) {
            if ($cond) {
                array_push($add, ...$perms);
            }
        };

        // Requisiciones
        $give($global, 'requisiciones.elegir_solicitante', 'requisiciones.elegir_sucursal_corporativo',
            'requisiciones.elegir_sucursal_global', 'requisiciones.elegir_corporativo');
        $give($has('requisiciones.registrar'), 'requisiciones.guardar_borrador', 'requisiciones.enviar', 'requisiciones.eliminar_borrador');
        $give($global && $has('requisiciones.editar'), 'requisiciones.editar_cualquiera');
        $give($global || $own, 'requisiciones.imprimir');

        // Dashboard
        $give($has('dashboard.ver') && $global, 'dashboard.general');
        $give($has('dashboard.ver') && ! $global, 'dashboard.personal');

        // Pagos, comprobaciones y ajustes heredan el alcance previo de requisiciones.
        foreach (['pagos', 'comprobaciones', 'ajustes'] as $m) {
            $sees = $has("{$m}.ver");
            $give($sees && $global, "{$m}.ver_todos");
            $give($sees && ! $global && $own, "{$m}.ver_propios");
        }
        $sawPagos = $has('pagos.ver') && ($global || $own);
        $give($sawPagos, 'pagos.descargar');
        $give($has('pagos.registrar'), 'pagos.editar');
        $give($has('pagos.autorizar'), 'pagos.rechazar');

        $give($has('comprobaciones.subir') && $global, 'comprobaciones.subir_cualquiera');
        $give($has('comprobaciones.revisar'), 'comprobaciones.aceptar', 'comprobaciones.rechazar');

        $give($has('ajustes.solicitar') && $global, 'ajustes.solicitar_cualquiera');
        $give($has('ajustes.revisar'), 'ajustes.autorizar', 'ajustes.rechazar');

        // Plantillas
        $give($has('plantillas.ver_todos', 'plantillas.editar'), 'plantillas.editar_cualquiera');
        $give($has('plantillas.ver_todos', 'plantillas.eliminar'), 'plantillas.eliminar_cualquiera');

        // Proveedores: quien veía todos sigue pudiendo usarlos al capturar.
        $give($has('proveedores.ver_todos'), 'proveedores.usar_todos');

        // Usuarios: editar incluía cambiar el rol; ver incluía la relación con el colaborador.
        $give($has('usuarios.editar'), 'usuarios.cambiar_rol');
        $give($has('usuarios.ver'), 'usuarios.ver_vinculo');

        return array_values(array_diff(array_unique($add), $had));
    }

    public function alreadyApplied(): bool
    {
        return Schema::hasTable('permission_transitions')
            && DB::table('permission_transitions')->where('name', self::NAME)->exists();
    }

    /**
     * Ejecuta la transición dentro de una transacción y devuelve qué se
     * agregó a cada rol.
     *
     * @return array<string, list<string>>
     */
    public function run(): array
    {
        $report = DB::transaction(function () {
            app(RoleSynchronizer::class)->syncPermissions();

            $report = [];
            $catalog = PermissionCatalog::all();

            Role::query()->with('permissions:id,name')->orderBy('id')->each(function (Role $role) use (&$report, $catalog) {
                $had = $role->permissions->pluck('name')->all();
                $add = $role->name === PermissionCatalog::ROLE_ADMIN
                    ? array_values(array_diff($catalog, $had))
                    : self::mapFor($had);

                if ($add !== []) {
                    $role->givePermissionTo($add);
                    $report[$role->name] = $add;
                }
            });

            if (Schema::hasTable('permission_transitions')) {
                DB::table('permission_transitions')->updateOrInsert(
                    ['name' => self::NAME],
                    ['summary' => json_encode($report, JSON_UNESCAPED_UNICODE), 'applied_at' => now(), 'updated_at' => now(), 'created_at' => now()],
                );
            }

            return $report;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $report;
    }
}
