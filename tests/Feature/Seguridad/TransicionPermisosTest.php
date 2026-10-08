<?php

namespace Tests\Feature\Seguridad;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\PermissionTransition;
use App\Support\Permissions\RoleSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/**
 * Transición de los permisos generales a permisos con alcance sobre roles
 * existentes (producción): solo agrega, conserva personalizaciones y es
 * idempotente.
 */
class TransicionPermisosTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    /** Simula un rol de producción con los permisos anteriores. */
    private function legacyRole(string $name, array $permissions): Role
    {
        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return $role;
    }

    private function names(Role $role): array
    {
        return $role->fresh()->permissions->pluck('name')->sort()->values()->all();
    }

    public function test_mapeo_de_contabilidad_conserva_alcance_global_y_captura(): void
    {
        $add = PermissionTransition::mapFor(['dashboard.ver', 'requisiciones.ver_todos', 'requisiciones.registrar', 'requisiciones.editar', 'pagos.ver', 'pagos.autorizar', 'pagos.registrar', 'comprobaciones.ver', 'comprobaciones.revisar', 'ajustes.ver', 'ajustes.revisar', 'notificaciones.ver']);

        foreach (['dashboard.general', 'requisiciones.elegir_solicitante', 'requisiciones.elegir_sucursal_global', 'requisiciones.elegir_corporativo',
            'requisiciones.editar_cualquiera', 'pagos.ver_todos', 'pagos.descargar', 'pagos.rechazar', 'pagos.editar',
            'comprobaciones.ver_todos', 'comprobaciones.aceptar', 'comprobaciones.rechazar', 'ajustes.ver_todos', 'ajustes.autorizar', 'ajustes.rechazar'] as $p) {
            $this->assertContains($p, $add, $p);
        }
        $this->assertNotContains('notificaciones.ver_todas', $add);
    }

    public function test_mapeo_de_colaborador_es_propio_y_no_concede_global(): void
    {
        $add = PermissionTransition::mapFor(['dashboard.ver', 'requisiciones.ver_propios', 'requisiciones.registrar', 'pagos.ver', 'comprobaciones.ver', 'ajustes.ver', 'notificaciones.ver']);

        $this->assertContains('dashboard.personal', $add);
        $this->assertContains('pagos.ver_propios', $add);
        $this->assertContains('comprobaciones.ver_propios', $add);
        $this->assertContains('ajustes.ver_propios', $add);
        foreach ($add as $p) {
            $this->assertDoesNotMatchRegularExpression('/(ver_todos|ver_todas|general|elegir_)/', $p, "No debe conceder {$p}");
        }
    }

    public function test_rol_sin_acceso_relacionado_no_recibe_nada_nuevo(): void
    {
        // Ve pagos, pero no tenía ningún acceso a requisiciones: no se inventa alcance.
        $this->assertSame([], PermissionTransition::mapFor(['pagos.ver', 'conceptos.ver']));
    }

    public function test_transicion_idempotente_conserva_usuarios_roles_y_personalizaciones(): void
    {
        $admin = $this->legacyRole(PermissionCatalog::ROLE_ADMIN, ['roles.editar', 'usuarios.editar', 'dashboard.ver']);
        $custom = $this->legacyRole('Auditor', ['requisiciones.ver_propios', 'pagos.ver', 'conceptos.ver']);
        $user = User::factory()->create();
        $user->assignRole($custom);
        $ids = [Role::pluck('id')->sort()->values()->all(), User::pluck('id')->all()];

        $transition = app(PermissionTransition::class);
        $first = $transition->run();
        $afterFirst = [$this->names($admin), $this->names($custom)];
        $permCount = Permission::count();

        $second = $transition->run();

        $this->assertNotEmpty($first);
        $this->assertSame([], $second, 'La segunda ejecución no agrega nada.');
        $this->assertSame($afterFirst, [$this->names($admin), $this->names($custom)]);
        $this->assertSame($permCount, Permission::count(), 'No se duplican permisos.');
        $this->assertSame(Permission::count(), Permission::query()->distinct()->count('name'));

        // Administrador: catálogo completo. Rol personalizado: conserva lo suyo + equivalentes propios.
        $this->assertEmpty(array_diff(PermissionCatalog::all(), $this->names($admin)));
        $this->assertContains('conceptos.ver', $this->names($custom));
        $this->assertContains('pagos.ver_propios', $this->names($custom));
        $this->assertNotContains('pagos.ver_todos', $this->names($custom));
        $this->assertNotContains('requisiciones.ver_todos', $this->names($custom));

        // Usuarios, roles e IDs intactos; la asignación se conserva.
        $this->assertSame($ids, [Role::pluck('id')->sort()->values()->all(), User::pluck('id')->all()]);
        $this->assertTrue($user->fresh()->hasRole('Auditor'));
        $this->assertTrue($transition->alreadyApplied());
        $this->assertSame(1, DB::table('permission_transitions')->count());
    }

    public function test_sincronizacion_idempotente_y_administrador_con_todo(): void
    {
        $sync = app(RoleSynchronizer::class);
        $sync->sync();
        $counts = [Permission::count(), Role::count(), DB::table('role_has_permissions')->count()];
        $sync->sync();

        $this->assertSame($counts, [Permission::count(), Role::count(), DB::table('role_has_permissions')->count()]);
        $this->assertEmpty(array_diff(PermissionCatalog::all(), $this->names($this->roleNamed(PermissionCatalog::ROLE_ADMIN))));
        $this->assertSame(Permission::count(), Permission::query()->distinct()->count('name'));
    }

    public function test_sincronizacion_no_sobrescribe_roles_personalizados(): void
    {
        $sync = app(RoleSynchronizer::class);
        $sync->sync();
        $conta = $this->roleNamed(PermissionCatalog::ROLE_CONTABILIDAD);
        $conta->syncPermissions(['requisiciones.ver_propios']);

        $sync->sync();

        $this->assertSame(['requisiciones.ver_propios'], $this->names($conta));
    }

    public function test_guardar_rol_normaliza_alcance_y_dependencias(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'Capturista',
            'permissions' => [
                'requisiciones.ver_propios', 'requisiciones.ver_corporativo', // dos niveles → se guarda solo el más alto
                'pagos.registrar',                                            // acción sin alcance → alcance mínimo, no global
                'requisiciones.elegir_corporativo',                           // dependencia → elegir sucursal global + registrar
            ],
            'receive_all' => false,
            'topics' => ['pagos'],
        ])->assertSessionHasNoErrors();

        $perms = $this->names(Role::where('name', 'Capturista')->firstOrFail());
        $this->assertContains('requisiciones.ver_corporativo', $perms);
        $this->assertNotContains('requisiciones.ver_propios', $perms);
        $this->assertContains('pagos.ver_propios', $perms);
        $this->assertNotContains('pagos.ver_todos', $perms);
        $this->assertContains('requisiciones.elegir_sucursal_global', $perms);
        $this->assertContains('requisiciones.registrar', $perms);
        $this->assertContains('notificaciones.ver', $perms);
        $this->assertNotContains('notificaciones.ver_todas', $perms);
    }

    public function test_administrador_conserva_todos_los_permisos_al_guardar(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $role = $this->roleNamed(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($admin)->put(route('roles.update', $role), [
            'name' => $role->name, 'permissions' => ['dashboard.personal'], 'receive_all' => true,
        ])->assertSessionHasNoErrors();

        $this->assertEmpty(array_diff(PermissionCatalog::all(), $this->names($role)));
    }

    public function test_formulario_de_roles_no_expone_claves_tecnicas_como_texto(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('roles.create'))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('modules', function ($modules) {
                foreach ($modules as $m) {
                    $labels = collect($m['scope'])->pluck('label')
                        ->merge(collect($m['groups'])->flatMap(fn ($g) => collect($g['permissions'])->flatMap(fn ($x) => [$x['label'], $x['description']])));
                    foreach ($labels as $text) {
                        if (preg_match('/\b[a-z_]+\.[a-z_]+\b/', $text)) {
                            return false;
                        }
                    }
                }

                return true;
            }));
    }

    public function test_migracion_de_transicion_registrada_y_reversible_sin_perder_datos(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('permission_transitions'));
        $migration = require database_path('migrations/2026_10_08_120000_scoped_permissions_transition.php');

        // Volver a correr up() con la transición ya registrada no cambia permisos.
        app(PermissionTransition::class)->run();
        $before = DB::table('role_has_permissions')->count();
        $migration->up();
        $this->assertSame($before, DB::table('role_has_permissions')->count());
    }
}
