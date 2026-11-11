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
            'sucursal_id' => $this->sucursal->id,
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

    public function test_usuario_nuevo_sin_colaborador_queda_como_solicitante_y_puede_crear_requisiciones(): void
    {
        Mail::fake();
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN, false);

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Héctor Pérez García',
            'email' => 'hector@example.com',
            'role_id' => $this->roleNamed(PermissionCatalog::ROLE_COLABORADOR)->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('usuarios.index'));

        $user = User::where('email', 'hector@example.com')->firstOrFail();
        $colaborador = $user->empleado;
        $this->assertNotNull($colaborador, 'La cuenta nueva queda vinculada a su colaborador.');
        $this->assertSame(['Héctor', 'Pérez', 'García'], [$colaborador->nombre, $colaborador->apellido_paterno, $colaborador->apellido_materno]);
        $this->assertSame($this->sucursal->id, $colaborador->sucursal_id);
        $this->assertTrue($colaborador->activo);

        // Aparece en la lista de solicitantes de quien puede elegirlos…
        $this->actingAs($admin)->get(route('requisiciones.create'))
            ->assertInertia(fn ($page) => $page->where('catalogos.empleados', fn ($rows) => collect($rows)->contains('id', $colaborador->id)));

        // …y puede crear requisiciones a su nombre de inmediato.
        $payload = $this->requisicionPayload($user);
        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('requisicions', ['solicitante_id' => $colaborador->id, 'creada_por_user_id' => $user->id]);
    }

    public function test_usuario_nuevo_exige_colaborador_o_sucursal(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN, false);
        $payload = [
            'name' => 'Sin Vínculo',
            'email' => 'sinvinculo@example.com',
            'role_id' => $this->roleNamed(PermissionCatalog::ROLE_COLABORADOR)->id,
        ];

        $this->actingAs($admin)->post(route('usuarios.store'), $payload)->assertSessionHasErrors('sucursal_id');

        $this->sucursal->update(['activo' => false]);
        $this->actingAs($admin)->post(route('usuarios.store'), $payload + ['sucursal_id' => $this->sucursal->id])
            ->assertSessionHasErrors('sucursal_id');

        $this->assertDatabaseMissing('users', ['email' => 'sinvinculo@example.com']);
        $this->assertSame(0, Empleado::count());
    }

    public function test_cuenta_existente_sin_colaborador_se_repara_al_elegir_sucursal(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN, false);
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR, false, ['name' => 'Héctor Ruiz']);

        $this->actingAs($admin)->put(route('usuarios.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->roles->first()->id,
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Ruiz', $user->fresh()->empleado?->apellido_paterno);
    }

    public function test_migracion_vincula_cuentas_sin_colaborador_sin_tocar_las_demas(): void
    {
        $migration = require database_path('migrations/2026_11_11_120000_link_colaborador_to_users_without_one.php');

        $conColaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $original = $conColaborador->empleado_id;
        $porCorreo = $this->makeEmpleado(['nombre' => 'Lucía', 'email' => 'Lucia@Example.com']);
        $lucia = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR, false, ['email' => 'lucia@example.com']);
        $hector = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR, false, ['name' => 'Héctor Pérez', 'email' => 'hector@example.com']);
        $inactiva = User::factory()->colaborador()->inactive()->create();

        $migration->up();

        $this->assertSame($original, $conColaborador->fresh()->empleado_id);
        $this->assertSame($porCorreo->id, $lucia->fresh()->empleado_id);
        $nuevo = $hector->fresh()->empleado;
        $this->assertSame(['Héctor', 'Pérez', $this->sucursal->id], [$nuevo->nombre, $nuevo->apellido_paterno, $nuevo->sucursal_id]);
        $this->assertNull($inactiva->fresh()->empleado_id);

        // Idempotente: una segunda ejecución no crea ni cambia nada.
        $total = Empleado::count();
        $migration->up();
        $this->assertSame($total, Empleado::count());
        $this->assertSame($nuevo->id, $hector->fresh()->empleado_id);
    }

    public function test_migracion_no_adivina_la_sucursal_cuando_hay_varias(): void
    {
        $migration = require database_path('migrations/2026_11_11_120000_link_colaborador_to_users_without_one.php');
        \App\Models\Sucursal::create(['corporativo_id' => $this->corporativo->id, 'nombre' => 'Norte', 'activo' => true]);
        $hector = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR, false);

        $migration->up();

        $this->assertNull($hector->fresh()->empleado_id);
        $this->assertSame(0, Empleado::count());
    }
}
