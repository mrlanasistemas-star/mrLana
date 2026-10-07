<?php

namespace Tests\Feature\Seguridad;

use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/**
 * Regresión: una persona dada de baja (como usuario o como colaborador) no
 * debe poder seguir entrando al sistema por ninguna vía.
 */
class BajaAccesoTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    public function test_desactivar_usuario_impide_iniciar_sesion_despues(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($admin)->patch(route('usuarios.deactivate', $user))->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->activo);

        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_desactivar_usuario_revoca_sesiones_y_recordarme(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $user->forceFill(['remember_token' => 'token-anterior'])->save();
        DB::table('sessions')->insert([
            'id' => 'sesion-abierta', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => '', 'last_activity' => time(),
        ]);

        $user->forceFill(['activo' => false])->save();

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertNotSame('token-anterior', $user->fresh()->remember_token);
    }

    public function test_cookie_recordarme_de_cuenta_desactivada_no_da_acceso(): void
    {
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $user->forceFill(['activo' => false])->save();

        // Aunque el guard resuelva al usuario (cookie o sesión previa), el middleware lo expulsa.
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_baja_de_colaborador_desactiva_su_cuenta(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($admin)->delete(route('colaboradores.destroy', $user->empleado_id))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Colaborador dado de baja. Su cuenta de acceso también se desactivó.');

        $this->assertFalse($user->empleado->fresh()->activo);
        $this->assertFalse($user->fresh()->activo);

        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_baja_masiva_de_colaboradores_desactiva_sus_cuentas(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $a = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $b = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);

        $this->actingAs($admin)->post(route('colaboradores.bulkDestroy'), ['ids' => [$a->empleado_id, $b->empleado_id]])
            ->assertSessionHasNoErrors();

        $this->assertFalse($a->fresh()->activo);
        $this->assertFalse($b->fresh()->activo);
    }

    public function test_no_puede_darse_de_baja_a_si_mismo_como_colaborador(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $this->makeUser(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($admin)->delete(route('colaboradores.destroy', $admin->empleado_id))->assertSessionHasErrors('ids');

        $this->assertTrue($admin->empleado->fresh()->activo);
        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_baja_de_colaborador_no_deja_sin_administradores(): void
    {
        $gestor = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $gestor->givePermissionTo('colaboradores.ver', 'colaboradores.desactivar');
        $unicoAdmin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($gestor)->delete(route('colaboradores.destroy', $unicoAdmin->empleado_id))->assertSessionHasErrors('ids');

        $this->assertTrue($unicoAdmin->empleado->fresh()->activo);
        $this->assertTrue($unicoAdmin->fresh()->activo);
    }

    public function test_reactivar_colaborador_no_reactiva_la_cuenta(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($admin)->delete(route('colaboradores.destroy', $user->empleado_id));
        $this->actingAs($admin)->patch(route('colaboradores.activate', $user->empleado_id));

        $this->assertTrue($user->empleado->fresh()->activo);
        $this->assertFalse($user->fresh()->activo);
    }
}
