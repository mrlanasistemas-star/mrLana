<?php

namespace Tests\Feature\Seguridad;

use App\Mail\EmpleadoAccesoCreadoMail;
use App\Models\Empleado;
use App\Models\User;
use App\Services\Colaboradores\ColaboradorQuery;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

class ColaboradoresUsuariosTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    public function test_se_puede_crear_colaborador_sin_usuario(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN, false);
        $usuariosAntes = User::count();

        $this->actingAs($admin)->post(route('colaboradores.store'), [
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Mario',
            'apellido_paterno' => 'Ruiz',
            'email' => 'mario@example.com',
            'puesto' => 'Chofer',
        ])->assertSessionHasNoErrors();

        $colaborador = Empleado::where('nombre', 'Mario')->firstOrFail();
        $this->assertNull($colaborador->user);
        $this->assertSame($usuariosAntes, User::count());
    }

    public function test_se_puede_crear_y_vincular_usuario_despues(): void
    {
        Mail::fake();
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN, false);
        $colaborador = $this->makeEmpleado(['nombre' => 'Lucía', 'email' => 'lucia@example.com']);

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Lucía López',
            'email' => 'lucia@example.com',
            'role_id' => $this->roleNamed(PermissionCatalog::ROLE_COLABORADOR)->id,
            'empleado_id' => $colaborador->id,
            'activo' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('usuarios.index'));

        $user = User::where('email', 'lucia@example.com')->firstOrFail();
        $this->assertSame($colaborador->id, $user->empleado_id);
        $this->assertTrue($user->hasRole(PermissionCatalog::ROLE_COLABORADOR));
        $this->assertSame('COLABORADOR', $user->rol, 'El campo legado se mantiene coherente.');
        Mail::assertSent(EmpleadoAccesoCreadoMail::class, fn ($m) => $m->hasTo('lucia@example.com'));
    }

    public function test_fallo_smtp_no_deja_datos_inconsistentes(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN, false);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Sin correo',
            'email' => 'sincorreo@example.com',
            'role_id' => $this->roleNamed(PermissionCatalog::ROLE_COLABORADOR)->id,
            'activo' => true,
        ])->assertSessionHas('warning');

        $user = User::where('email', 'sincorreo@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(PermissionCatalog::ROLE_COLABORADOR));
    }

    public function test_no_se_pueden_vincular_dos_usuarios_al_mismo_colaborador(): void
    {
        Mail::fake();
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN, false);
        $colaborador = $this->makeEmpleado();
        User::factory()->colaborador()->create(['empleado_id' => $colaborador->id]);

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Duplicado',
            'email' => 'dup@example.com',
            'role_id' => $this->roleNamed(PermissionCatalog::ROLE_COLABORADOR)->id,
            'empleado_id' => $colaborador->id,
        ])->assertSessionHasErrors(['empleado_id' => 'Ese colaborador ya tiene una cuenta vinculada.']);

        $this->assertSame(1, User::where('empleado_id', $colaborador->id)->count());

        // La base de datos también lo impide (índice único).
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['empleado_id' => $colaborador->id]);
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        $user = User::factory()->colaborador()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Tu cuenta está desactivada. Contacta a un administrador.']);

        $this->assertGuest();
    }

    public function test_sesion_activa_se_cierra_si_la_cuenta_se_desactiva(): void
    {
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $user->forceFill(['activo' => false])->save();

        $this->actingAs($user)->get(route('requisiciones.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_conteos_con_y_sin_usuario_son_correctos(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN); // 1 colaborador con usuario
        $this->makeEmpleado(['nombre' => 'Sin1']);
        $this->makeEmpleado(['nombre' => 'Sin2']);
        $this->makeEmpleado(['nombre' => 'Inactivo', 'activo' => false]);
        $conUsuario = $this->makeEmpleado(['nombre' => 'Con']);
        User::factory()->colaborador()->create(['empleado_id' => $conUsuario->id]);

        $counts = ColaboradorQuery::counts(ColaboradorQuery::filters(new Request), $admin);
        $this->assertSame(['total' => 5, 'con_usuario' => 2, 'sin_usuario' => 3], $counts);

        $activos = ColaboradorQuery::counts(ColaboradorQuery::filters(new Request(['activo' => '1'])), $admin);
        $this->assertSame(['total' => 4, 'con_usuario' => 2, 'sin_usuario' => 2], $activos);

        $this->assertSame(3, ColaboradorQuery::build(ColaboradorQuery::filters(new Request(['acceso' => 'sin'])), $admin)->count());

        $this->actingAs($admin)
            ->get(route('colaboradores.index', ['acceso' => 'con']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('counts.total', 5)
                ->where('counts.con_usuario', 2)
                ->where('counts.sin_usuario', 3)
                ->has('colaboradores.data', 2));
    }

    public function test_url_anterior_de_empleados_redirige_a_colaboradores(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($admin)->get('/empleados')->assertRedirect('/colaboradores');
    }
}
