<?php

namespace Tests\Feature\Seguridad;

use App\Models\Role;
use App\Models\User;
use App\Services\Users\AdministratorGuard;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\RoleSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

class RolesPermisosTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    public function test_usuario_sin_permiso_recibe_403_aunque_conozca_la_url(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        foreach ([
            route('usuarios.index'),
            route('roles.index'),
            route('colaboradores.index'),
            route('corporativos.index'),
            route('systemlogs.index'),
            route('configuracion.edit'),
            route('dashboard.admin'),
            route('colaboradores.export.pdf'),
        ] as $url) {
            $this->actingAs($colaborador)->get($url)->assertForbidden();
        }

        $this->actingAs($colaborador)
            ->post(route('usuarios.store'), ['name' => 'X', 'email' => 'x@example.com', 'role_id' => 1])
            ->assertForbidden();
    }

    public function test_permisos_compartidos_con_la_interfaz_ocultan_acciones(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($colaborador);

        $this->actingAs($colaborador)
            ->get(route('requisiciones.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.permissions', fn ($perms) => collect($perms)->contains('requisiciones.registrar')
                    && ! collect($perms)->contains('usuarios.ver')
                    && ! collect($perms)->contains('pagos.autorizar'))
                ->where('requisiciones.data.0.id', $req->id)
                ->where('requisiciones.data.0.can.autorizar_pago', false)
                ->where('requisiciones.data.0.can.solicitar_ajuste', true)
                ->where('requisiciones.data.0.can.solicitar_eliminacion', true));

        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $this->actingAs($contador)
            ->get(route('requisiciones.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('requisiciones.data.0.can.autorizar_pago', true)
                ->where('requisiciones.data.0.can.solicitar_eliminacion', false));
    }

    public function test_ver_propios_no_permite_acceder_a_registros_ajenos(): void
    {
        $duena = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $ajeno = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($duena);

        $this->actingAs($ajeno)->get(route('requisiciones.show', $req))->assertForbidden();
        $this->actingAs($ajeno)->get(route('requisiciones.print', $req))->assertForbidden();
        $this->actingAs($ajeno)->get(route('requisiciones.ajustes', $req))->assertForbidden();
        $this->actingAs($ajeno)->get(route('requisiciones.comprobar', $req))->assertForbidden();
        $this->actingAs($ajeno)->get(route('requisiciones.pagar', $req))->assertForbidden();
        $this->actingAs($ajeno)->delete(route('requisiciones.destroy', $req))->assertForbidden();

        $this->actingAs($ajeno)
            ->get(route('requisiciones.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('requisiciones.data', 0));

        $this->actingAs($duena)->get(route('requisiciones.show', $req))->assertOk();
    }

    public function test_migracion_asigna_roles_segun_valor_legado(): void
    {
        $admin = User::factory()->create(['rol' => 'ADMIN']);
        $contador = User::factory()->create(['rol' => 'CONTADOR']);
        $colaborador = User::factory()->create(['rol' => 'COLABORADOR']);

        app(RoleSynchronizer::class)->sync();
        app(RoleSynchronizer::class)->sync(); // idempotente

        $this->assertTrue($admin->fresh()->hasRole(PermissionCatalog::ROLE_ADMIN));
        $this->assertTrue($contador->fresh()->hasRole(PermissionCatalog::ROLE_CONTABILIDAD));
        $this->assertTrue($colaborador->fresh()->hasRole(PermissionCatalog::ROLE_COLABORADOR));
        $this->assertCount(1, $admin->fresh()->roles);
        $this->assertSame(3, Role::count());

        $this->assertTrue($admin->fresh()->hasFullAdministration());
        $this->assertTrue($contador->fresh()->can('pagos.autorizar'));
        $this->assertFalse($colaborador->fresh()->can('pagos.autorizar'));
        $this->assertTrue($colaborador->fresh()->can('requisiciones.ver_propios'));
    }

    public function test_crear_rol_con_permisos_parciales(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'Auditor',
            'descripcion' => 'Solo consulta',
            'permissions' => ['dashboard.ver', 'requisiciones.ver_todos', 'notificaciones.ver'],
            'receive_all' => false,
            'topics' => ['pagos'],
        ])->assertSessionHasNoErrors()->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'Auditor')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['dashboard.ver', 'requisiciones.ver_todos', 'notificaciones.ver'],
            $role->permissions->pluck('name')->all()
        );
        $this->assertSame(['pagos'], $role->notificationPreference->topics);

        $auditor = User::factory()->withRole('Auditor')->create();
        $this->actingAs($auditor)->get(route('requisiciones.index'))->assertOk();
        $this->actingAs($auditor)->get(route('requisiciones.create'))->assertForbidden();
    }

    public function test_rol_que_recibe_notificaciones_debe_poder_verlas(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'Incoherente',
            'permissions' => ['dashboard.ver'],
            'receive_all' => true,
        ])->assertSessionHasErrors('topics');

        $this->assertDatabaseMissing('roles', ['name' => 'Incoherente']);
    }

    public function test_proteccion_del_ultimo_administrador(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $colaboradorRole = $this->roleNamed(PermissionCatalog::ROLE_COLABORADOR);

        // No puede desactivarse a sí mismo si es el único administrador.
        $this->actingAs($admin)
            ->patch(route('usuarios.deactivate', $admin))
            ->assertSessionHasErrors('user');
        $this->assertTrue($admin->fresh()->activo);

        // No puede quitarse el rol de administración.
        $this->actingAs($admin)->put(route('usuarios.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role_id' => $colaboradorRole->id,
            'activo' => true,
        ])->assertSessionHasErrors('role_id');
        $this->assertTrue($admin->fresh()->hasRole(PermissionCatalog::ROLE_ADMIN));

        // Un rol personalizado con administración total no puede perderla si es el último.
        $custom = Role::create(['name' => 'Superusuario', 'guard_name' => 'web']);
        $custom->syncPermissions(PermissionCatalog::all());
        User::factory()->withRole('Superusuario')->create();
        $admin->forceFill(['activo' => false])->save();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(AdministratorGuard::class)->assertCanChangeRolePermissions($custom, ['dashboard.ver']);
    }

    public function test_con_dos_administradores_si_se_puede_desactivar_uno(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $otro = $this->makeUser(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($admin)
            ->patch(route('usuarios.deactivate', $otro))
            ->assertSessionHasNoErrors();

        $this->assertFalse($otro->fresh()->activo);
    }

    public function test_no_se_eliminan_roles_iniciales_ni_roles_con_usuarios(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($admin)->delete(route('roles.destroy', $this->roleNamed(PermissionCatalog::ROLE_ADMIN)))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['name' => PermissionCatalog::ROLE_ADMIN]);

        $temporal = Role::create(['name' => 'Temporal', 'guard_name' => 'web']);
        User::factory()->withRole('Temporal')->create();
        $this->actingAs($admin)->delete(route('roles.destroy', $temporal))->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['name' => 'Temporal']);
    }
}
