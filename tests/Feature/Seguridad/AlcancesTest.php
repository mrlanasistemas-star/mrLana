<?php

namespace Tests\Feature\Seguridad;

use App\Models\Comprobante;
use App\Models\Pago;
use App\Models\Requisicion;
use App\Models\User;
use App\Support\Permissions\AccessScope;
use App\Support\Permissions\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/**
 * Alcance de lectura por módulo financiero: sin permiso, propio, sucursal,
 * corporativo y global. El mismo criterio aplica a la vista, a los registros
 * concretos (URL) y a las exportaciones.
 */
class AlcancesTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    /** @var array<string, Requisicion> */
    private array $req = [];

    private User $viewerEmpleadoA1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganizacion();

        $base = ['requisiciones.ver_propios', 'requisiciones.registrar'];
        $this->req['A1'] = $this->requisicionEn($this->sucursal, $this->userWith($base, $this->sucursal), ['folio' => 'REQ-A1']);
        $this->req['A2'] = $this->requisicionEn($this->sucursal2, $this->userWith($base, $this->sucursal2), ['folio' => 'REQ-A2']);
        $this->req['B1'] = $this->requisicionEn($this->sucursalB, $this->userWith($base, $this->sucursalB), ['folio' => 'REQ-B1']);
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function niveles(): array
    {
        return [
            'propio' => ['ver_propios', ['PROPIA']],
            'sucursal' => ['ver_sucursal', ['PROPIA', 'A1']],
            'corporativo' => ['ver_corporativo', ['PROPIA', 'A1', 'A2']],
            'global' => ['ver_todos', ['PROPIA', 'A1', 'A2', 'B1']],
        ];
    }

    private function viewer(string $module, string $level): User
    {
        $user = $this->userWith(["{$module}.{$level}"], $this->sucursal);
        // Una requisición propia creada en OTRA sucursal: el alcance superior incluye lo propio.
        $this->req['PROPIA'] = $this->requisicionEn($this->sucursalB, $user, ['folio' => 'REQ-PROPIA']);

        return $user;
    }

    private function folios($query): array
    {
        return $query->pluck('folio')->map(fn ($f) => str_replace('REQ-', '', $f))->sort()->values()->all();
    }

    #[DataProvider('niveles')]
    public function test_requisiciones_por_nivel(string $level, array $esperadas): void
    {
        $user = $this->viewer('requisiciones', $level);
        sort($esperadas);

        $this->assertSame($esperadas, $this->folios(Requisicion::query()->visibleTo($user)));

        $this->actingAs($user)->get(route('requisiciones.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('requisiciones.data', count($esperadas)));

        foreach ($this->req as $key => $r) {
            $this->actingAs($user)->get(route('requisiciones.show', $r))
                ->assertStatus(in_array($key, $esperadas, true) ? 200 : 403);
        }
    }

    #[DataProvider('niveles')]
    public function test_pagos_comprobaciones_y_ajustes_heredan_el_alcance(string $level, array $esperadas): void
    {
        sort($esperadas);
        foreach (['pagos', 'comprobaciones', 'ajustes'] as $module) {
            $user = $this->viewer($module, $level);
            $this->assertSame($esperadas, $this->folios(Requisicion::query()->visibleTo($user, $module)), "Módulo {$module}");
            // Sin alcance en requisiciones no las lista, aunque vea sus pagos.
            $this->assertSame([], Requisicion::query()->visibleTo($user)->pluck('id')->all());
            Requisicion::query()->where('folio', 'REQ-PROPIA')->delete();
        }
    }

    public function test_sin_permiso_no_ve_nada_y_recibe_403(): void
    {
        $user = $this->userWith(['dashboard.personal'], $this->sucursal);

        $this->assertSame([], Requisicion::query()->visibleTo($user)->pluck('id')->all());
        $this->actingAs($user)->get(route('requisiciones.index'))->assertForbidden();
        $this->actingAs($user)->get(route('pagos.index'))->assertForbidden();
        $this->actingAs($user)->get(route('comprobantes.index'))->assertForbidden();
        $this->actingAs($user)->get(route('requisiciones.ajustes', $this->req['A1']))->assertForbidden();
    }

    public function test_sin_colaborador_sucursal_y_corporativo_no_se_vuelven_globales(): void
    {
        $sinColaborador = $this->userWith(['requisiciones.ver_corporativo'], withEmpleado: false);

        $this->assertSame(Scope::None, AccessScope::for($sinColaborador, 'requisiciones'));
        $this->assertSame([], Requisicion::query()->visibleTo($sinColaborador)->pluck('id')->all());

        // Con "propio" además, conserva lo que creó.
        $mixto = $this->userWith(['requisiciones.ver_propios', 'requisiciones.ver_corporativo'], withEmpleado: false);
        $this->assertSame(Scope::Own, AccessScope::for($mixto, 'requisiciones'));
    }

    public function test_varios_permisos_de_alcance_aplican_el_mas_amplio(): void
    {
        $user = $this->userWith(['requisiciones.ver_propios', 'requisiciones.ver_sucursal', 'requisiciones.ver_corporativo'], $this->sucursal);

        $this->assertSame(Scope::Corporativo, AccessScope::for($user, 'requisiciones'));
        $this->assertSame(['A1', 'A2'], $this->folios(Requisicion::query()->visibleTo($user)));
    }

    public function test_permiso_de_accion_sin_alcance_sobre_el_registro_devuelve_403(): void
    {
        $user = $this->userWith(['pagos.ver_sucursal', 'pagos.autorizar', 'pagos.registrar'], $this->sucursal);

        $this->actingAs($user)->post(route('requisiciones.autorizarPago', $this->req['B1']), ['fecha_pago' => now()->toDateString()])
            ->assertForbidden();
        $this->assertSame('CAPTURADA', $this->req['B1']->fresh()->status);

        $this->actingAs($user)->post(route('requisiciones.autorizarPago', $this->req['A1']), ['fecha_pago' => now()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame('PAGO_AUTORIZADO', $this->req['A1']->fresh()->status);
    }

    public function test_listado_de_pagos_y_archivos_respetan_el_alcance(): void
    {
        $conta = $this->userWith(['pagos.ver_todos']);
        $pagoA = Pago::create(['requisicion_id' => $this->req['A1']->id, 'beneficiario_nombre' => 'X', 'tipo_pago' => 'TRANSFERENCIA', 'monto' => 10, 'fecha_pago' => now(), 'user_carga_id' => $conta->id]);
        Pago::create(['requisicion_id' => $this->req['B1']->id, 'beneficiario_nombre' => 'Y', 'tipo_pago' => 'TRANSFERENCIA', 'monto' => 20, 'fecha_pago' => now(), 'user_carga_id' => $conta->id]);

        $sucursal = $this->userWith(['pagos.ver_sucursal', 'pagos.descargar'], $this->sucursal);
        $this->actingAs($sucursal)->get(route('pagos.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('pagos.data', 1)->where('pagos.data.0.id', $pagoA->id));

        $sinDescarga = $this->userWith(['pagos.ver_todos'], $this->sucursal);
        $this->actingAs($sinDescarga)->get(route('pagos.archivo', $pagoA))->assertForbidden();
    }

    public function test_comprobantes_fuera_de_alcance_no_se_abren(): void
    {
        $c = Comprobante::create(['requisicion_id' => $this->req['B1']->id, 'tipo_doc' => 'TICKET', 'monto' => 5, 'estatus' => 'PENDIENTE', 'user_carga_id' => $this->req['B1']->creada_por_user_id]);
        $user = $this->userWith(['comprobaciones.ver_corporativo'], $this->sucursal);

        $this->actingAs($user)->get(route('comprobantes.archivo', $c))->assertForbidden();
    }

    public function test_la_exportacion_no_amplia_el_alcance(): void
    {
        $user = $this->userWith(['requisiciones.ver_sucursal', 'requisiciones.exportar'], $this->sucursal);

        $text = $this->excelText($this->actingAs($user)->get(route('requisiciones.export.excel'))->assertOk());
        $this->assertStringContainsString('REQ-A1', $text);
        $this->assertStringNotContainsString('REQ-A2', $text);
        $this->assertStringNotContainsString('REQ-B1', $text);

        // Ni manipulando filtros de corporativo o sucursal.
        $text = $this->excelText($this->actingAs($user)->get(route('requisiciones.export.excel', ['comprador_corp_id' => $this->corporativoB->id]))->assertOk());
        $this->assertStringNotContainsString('REQ-B1', $text);
    }

    public function test_catalogos_organizacionales_por_alcance(): void
    {
        $miCorp = $this->userWith(['sucursales.ver_corporativo', 'sucursales.editar'], $this->sucursal);

        $this->actingAs($miCorp)->get(route('sucursales.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('sucursales.data', 2));
        $this->actingAs($miCorp)->put(route('sucursales.update', $this->sucursalB), ['corporativo_id' => $this->corporativoB->id, 'nombre' => 'Hack'])
            ->assertForbidden();
        $this->assertSame('Sur B', $this->sucursalB->fresh()->nombre);

        $miCorporativo = $this->userWith(['corporativos.ver_propio'], $this->sucursal);
        $this->actingAs($miCorporativo)->get(route('corporativos.index', ['activo' => 'all']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('corporativos.data', 1)->where('corporativos.data.0.id', $this->corporativo->id));

        $colabSucursal = $this->userWith(['colaboradores.ver_sucursal', 'colaboradores.exportar'], $this->sucursal);
        $this->actingAs($colabSucursal)->get(route('colaboradores.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('counts.total', \App\Models\Empleado::where('sucursal_id', $this->sucursal->id)->count()));
    }

    public function test_usuarios_por_alcance_y_sin_escalar_roles(): void
    {
        $gestor = $this->userWith(['usuarios.ver_sucursal', 'usuarios.editar', 'usuarios.cambiar_rol'], $this->sucursal);
        $mismo = $this->userWith(['requisiciones.ver_propios'], $this->sucursal);
        $otro = $this->userWith(['requisiciones.ver_propios'], $this->sucursalB);

        $this->actingAs($gestor)->get(route('usuarios.edit', $otro))->assertForbidden();
        $this->actingAs($gestor)->get(route('usuarios.edit', $mismo))->assertOk();

        // No puede asignar un rol con permisos que él no tiene (p. ej. Administrador).
        $admin = $this->roleWith(\App\Support\Permissions\PermissionCatalog::all(), 'Super');
        $this->actingAs($gestor)->put(route('usuarios.update', $mismo), [
            'name' => $mismo->name, 'email' => $mismo->email, 'role_id' => $admin->id, 'activo' => true, 'empleado_id' => $mismo->empleado_id,
        ])->assertSessionHasErrors('role_id');
        $this->assertFalse($mismo->fresh()->hasRole('Super'));
    }

    public function test_bitacora_por_alcance(): void
    {
        $propia = $this->userWith(['logs.ver_propios'], $this->sucursal);
        $this->actingAs($propia)->get(route('systemlogs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('logs.data', fn ($rows) => collect($rows)->every(fn ($r) => ($r['user']['id'] ?? null) === $propia->id)));
    }
}
