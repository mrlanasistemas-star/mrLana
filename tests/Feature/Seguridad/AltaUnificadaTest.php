<?php

namespace Tests\Feature\Seguridad;

use App\Mail\EmpleadoAccesoCreadoMail;
use App\Models\Empleado;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/**
 * Colaboradores y Usuarios en un solo módulo: la persona y su acceso se
 * registran y editan en la misma pantalla, sin saltarse los permisos de
 * cada parte.
 */
class AltaUnificadaTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
        Mail::fake();
    }

    private function persona(array $extra = []): array
    {
        return $extra + [
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Rosa',
            'apellido_paterno' => 'Méndez',
            'apellido_materno' => 'Ortiz',
            'puesto' => 'Compras',
            'email' => 'rosa@example.com',
        ];
    }

    private function acceso(int $roleId, array $extra = []): array
    {
        return $extra + ['acceso' => true, 'acceso_email' => 'rosa@example.com', 'acceso_role_id' => $roleId, 'acceso_activo' => true];
    }

    public function test_registra_persona_con_acceso_en_un_solo_paso(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $rol = $this->roleNamed(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($admin)->post(route('colaboradores.store'), $this->persona() + $this->acceso($rol->id, ['acceso_email' => 'Rosa@Example.com ']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('colaboradores.index'))
            ->assertSessionHas('success');

        $colaborador = Empleado::where('nombre', 'Rosa')->firstOrFail();
        $user = $colaborador->user;
        $this->assertNotNull($user, 'La cuenta queda ligada a la persona.');
        $this->assertSame('rosa@example.com', $user->email);
        $this->assertSame('Rosa Méndez', $user->name);
        $this->assertTrue($user->hasRole(PermissionCatalog::ROLE_COLABORADOR));
        $this->assertTrue($user->activo);
        Mail::assertSent(EmpleadoAccesoCreadoMail::class, fn ($m) => $m->hasTo('rosa@example.com'));
    }

    public function test_registra_persona_sin_acceso(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $usuarios = User::count();

        $this->actingAs($admin)->post(route('colaboradores.store'), $this->persona(['acceso' => false]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('colaboradores.index'));

        $this->assertNull(Empleado::where('nombre', 'Rosa')->firstOrFail()->user);
        $this->assertSame($usuarios, User::count());
        Mail::assertNothingSent();
    }

    public function test_sin_permiso_de_usuarios_no_puede_crear_accesos(): void
    {
        $gestor = $this->userWith(['colaboradores.ver', 'colaboradores.registrar', 'colaboradores.editar']);
        $rol = $this->roleWith(['colaboradores.ver']);

        $this->actingAs($gestor)->post(route('colaboradores.store'), $this->persona() + $this->acceso($rol->id))->assertForbidden();
        $this->assertDatabaseMissing('empleados', ['nombre' => 'Rosa']);
        $this->assertDatabaseMissing('users', ['email' => 'rosa@example.com']);

        // Tampoco al editar a una persona ya registrada.
        $persona = $this->makeEmpleado(['nombre' => 'Lalo']);
        $this->actingAs($gestor)->put(route('colaboradores.update', $persona), $this->persona(['nombre' => 'Lalo']) + $this->acceso($rol->id))->assertForbidden();
        $this->assertNull($persona->fresh()->user);

        // Pero sí puede registrar personas sin acceso.
        $this->actingAs($gestor)->post(route('colaboradores.store'), $this->persona())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('empleados', ['nombre' => 'Rosa']);
    }

    public function test_no_puede_asignar_un_rol_con_permisos_que_no_tiene(): void
    {
        $gestor = $this->userWith(['colaboradores.ver', 'colaboradores.registrar', 'usuarios.ver', 'usuarios.registrar', 'usuarios.ver_vinculo']);
        $admin = $this->roleNamed(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($gestor)->post(route('colaboradores.store'), $this->persona() + $this->acceso($admin->id))
            ->assertSessionHasErrors(['acceso_role_id' => 'No puedes asignar un rol con permisos que tú no tienes.']);

        $this->assertDatabaseMissing('empleados', ['nombre' => 'Rosa']);
        $this->assertDatabaseMissing('users', ['email' => 'rosa@example.com']);

        // Con un rol que sí puede asignar, se crean ambos.
        $propio = $this->roleWith(['colaboradores.ver']);
        $this->actingAs($gestor)->post(route('colaboradores.store'), $this->persona() + $this->acceso($propio->id))->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'rosa@example.com')->firstOrFail()->hasRole($propio->name));
    }

    public function test_correo_de_acceso_repetido_no_crea_nada(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        User::factory()->create(['email' => 'rosa@example.com']);

        $this->actingAs($admin)->post(route('colaboradores.store'), $this->persona() + $this->acceso($this->roleNamed(PermissionCatalog::ROLE_COLABORADOR)->id))
            ->assertSessionHasErrors(['acceso_email' => 'Ya existe una cuenta con ese correo.']);
        $this->assertDatabaseMissing('empleados', ['nombre' => 'Rosa']);
    }

    public function test_solo_con_permisos_de_usuarios_registra_personas_unicamente_con_acceso(): void
    {
        $gestor = $this->userWith(['usuarios.ver', 'usuarios.registrar']);
        $rol = $this->roleWith(['usuarios.ver']);

        $this->actingAs($gestor)->get(route('colaboradores.create'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Colaboradores/Form')->where('can.registrar_persona', false)->where('can.crear_cuenta', true));

        $this->actingAs($gestor)->post(route('colaboradores.store'), $this->persona())->assertForbidden();
        $this->actingAs($gestor)->post(route('colaboradores.store'), $this->persona() + $this->acceso($rol->id))->assertSessionHasNoErrors();
        $this->assertNotNull(User::where('email', 'rosa@example.com')->firstOrFail()->empleado_id);

        // Su menú lo lleva directo a «Cuentas de acceso».
        $this->actingAs($gestor)->get(route('colaboradores.index'))->assertRedirect(route('usuarios.index'));
    }

    public function test_editar_persona_le_da_acceso_y_administra_su_cuenta(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $colab = $this->roleNamed(PermissionCatalog::ROLE_COLABORADOR);
        $conta = $this->roleNamed(PermissionCatalog::ROLE_CONTABILIDAD);
        $persona = $this->makeEmpleado(['nombre' => 'Rosa', 'apellido_paterno' => 'Méndez', 'email' => null]);

        // Darle acceso desde la edición.
        $this->actingAs($admin)->put(route('colaboradores.update', $persona), $this->persona(['email' => null]) + $this->acceso($colab->id))
            ->assertSessionHasNoErrors()->assertRedirect(route('colaboradores.index'));
        $user = $persona->fresh()->user;
        $this->assertNotNull($user);
        $this->assertSame('rosa@example.com', $persona->fresh()->email, 'La persona toma el correo de su acceso si no tenía.');
        Mail::assertSent(EmpleadoAccesoCreadoMail::class, 1);

        // Cambiar rol, correo, nombre y desactivar la cuenta en el mismo guardado.
        $this->actingAs($admin)->put(route('colaboradores.update', $persona), $this->persona(['nombre' => 'Rosalía'])
            + $this->acceso($conta->id, ['acceso_email' => 'rosalia@example.com', 'acceso_activo' => false]))
            ->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue($user->hasRole(PermissionCatalog::ROLE_CONTABILIDAD));
        $this->assertFalse($user->hasRole(PermissionCatalog::ROLE_COLABORADOR));
        $this->assertSame('rosalia@example.com', $user->email);
        $this->assertSame('Rosalía Méndez', $user->name, 'El nombre de la cuenta sigue al de la persona.');
        $this->assertFalse($user->activo);
        $this->assertSame(1, User::where('empleado_id', $persona->id)->count(), 'Sigue habiendo una sola cuenta.');
    }

    public function test_edicion_respeta_permisos_de_la_cuenta(): void
    {
        // Edita personas y crea cuentas, pero no cambia roles ni desactiva cuentas.
        $gestor = $this->userWith(['colaboradores.ver', 'colaboradores.editar', 'usuarios.ver', 'usuarios.ver_vinculo', 'usuarios.registrar', 'usuarios.editar']);
        $persona = $this->makeEmpleado(['nombre' => 'Rosa']);
        $cuenta = User::factory()->colaborador()->create(['empleado_id' => $persona->id, 'email' => 'rosa@example.com']);
        $colab = $cuenta->roles->first();

        $this->actingAs($gestor)->put(route('colaboradores.update', $persona), $this->persona() + $this->acceso($this->roleWith(['colaboradores.ver'])->id))
            ->assertForbidden();
        $this->actingAs($gestor)->put(route('colaboradores.update', $persona), $this->persona() + $this->acceso($colab->id, ['acceso_activo' => false]))
            ->assertForbidden();
        $this->assertTrue($cuenta->fresh()->activo);

        // Sin tocar rol ni estado, guarda la persona y el correo de la cuenta.
        $this->actingAs($gestor)->put(route('colaboradores.update', $persona), $this->persona(['puesto' => 'Almacén']) + $this->acceso($colab->id, ['acceso_email' => 'rosa.m@example.com']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Almacén', $persona->fresh()->puesto);
        $this->assertSame('rosa.m@example.com', $cuenta->fresh()->email);
    }

    public function test_nadie_desactiva_su_propia_cuenta_desde_la_edicion(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $this->makeUser(PermissionCatalog::ROLE_ADMIN); // otro administrador activo
        $persona = $admin->empleado;

        $this->actingAs($admin)->put(route('colaboradores.update', $persona), $this->persona(['nombre' => $persona->nombre, 'apellido_paterno' => $persona->apellido_paterno])
            + $this->acceso($admin->roles->first()->id, ['acceso_email' => $admin->email, 'acceso_activo' => false]))
            ->assertSessionHasErrors(['acceso_activo' => 'No puedes desactivar tu propia cuenta.']);
        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_rutas_anteriores_de_usuarios_llevan_al_modulo_unificado(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $persona = $this->makeEmpleado(['nombre' => 'Sin cuenta']);
        $ligada = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $suelta = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR, false);

        $this->actingAs($admin)->get(route('usuarios.create'))->assertRedirect(route('colaboradores.create', ['acceso' => 1]));
        $this->actingAs($admin)->get(route('usuarios.create', ['colaborador' => $persona->id]))
            ->assertRedirect(route('colaboradores.edit', ['empleado' => $persona->id, 'acceso' => 1]));
        $this->actingAs($admin)->get(route('usuarios.edit', $ligada))->assertRedirect(route('colaboradores.edit', $ligada->empleado_id));
        // Las cuentas sin colaborador se ligan o completan en su propia pantalla.
        $this->actingAs($admin)->get(route('usuarios.edit', $suelta))->assertOk()->assertInertia(fn ($page) => $page->component('Usuarios/Form'));

        $this->actingAs($admin)->get(route('usuarios.index', ['vinculo' => 'sin']))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Usuarios/Index')
                ->where('counts.sin_colaborador', 1)
                ->has('users.data', 1)
                ->where('users.data.0.id', $suelta->id));

        $this->actingAs($admin)->get(route('colaboradores.edit', $ligada->empleado_id))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Colaboradores/Form')
                ->where('cuenta.id', $ligada->id)
                ->where('can.cambiar_rol', true));
    }

    public function test_quien_no_ve_la_cuenta_no_la_administra(): void
    {
        // Ve y edita colaboradores, pero no la relación con las cuentas.
        $gestor = $this->userWith(['colaboradores.ver', 'colaboradores.editar', 'usuarios.ver', 'usuarios.editar']);
        $persona = $this->makeEmpleado(['nombre' => 'Rosa']);
        $cuenta = User::factory()->colaborador()->create(['empleado_id' => $persona->id]);

        $this->actingAs($gestor)->get(route('colaboradores.edit', $persona))->assertOk()
            ->assertInertia(fn ($page) => $page->where('cuenta', null)->where('cuentaOculta', true));
        $this->actingAs($gestor)->put(route('colaboradores.update', $persona), $this->persona() + $this->acceso($cuenta->roles->first()->id, ['acceso_email' => 'otro@example.com']))
            ->assertForbidden();
        $this->assertNotSame('otro@example.com', $cuenta->fresh()->email);
    }
}
