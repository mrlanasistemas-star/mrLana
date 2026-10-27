<?php

namespace Tests\Feature\Requisiciones;

use App\Models\Ajuste;
use App\Models\Concepto;
use App\Models\Pago;
use App\Models\SystemLog;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

class CorreccionTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganizacion();
    }

    private function payload(\App\Models\Requisicion $req, array $overrides = []): array
    {
        return array_replace([
            'comprador_corp_id' => $req->comprador_corp_id,
            'sucursal_id' => $req->sucursal_id,
            'solicitante_id' => $req->solicitante_id,
            'concepto_id' => $req->concepto_id,
            'proveedor_id' => $req->proveedor_id,
            'observaciones' => $req->observaciones,
            'motivo' => 'Se capturó con datos equivocados',
        ], $overrides);
    }

    public function test_el_permiso_esta_en_el_catalogo_y_no_se_asigna_a_contabilidad_ni_colaborador(): void
    {
        $this->assertContains('requisiciones.corregir', PermissionCatalog::all());
        $this->assertTrue(PermissionCatalog::isSensitive('requisiciones.corregir'));

        $defaults = PermissionCatalog::defaultRolePermissions();
        $this->assertContains('requisiciones.corregir', $defaults[PermissionCatalog::ROLE_ADMIN]);
        $this->assertNotContains('requisiciones.corregir', $defaults[PermissionCatalog::ROLE_CONTABILIDAD]);
        $this->assertNotContains('requisiciones.corregir', $defaults[PermissionCatalog::ROLE_COLABORADOR]);
    }

    public function test_sin_permiso_recibe_403(): void
    {
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($contador);
        $otro = Concepto::create(['nombre' => 'Viáticos', 'activo' => true]);

        $this->actingAs($contador)->get(route('requisiciones.corregir', $req))->assertForbidden();
        $this->actingAs($contador)
            ->patch(route('requisiciones.corregir.update', $req), $this->payload($req, ['concepto_id' => $otro->id]))
            ->assertForbidden();

        $this->assertSame($this->concepto->id, (int) $req->fresh()->concepto_id);

        // Sin permiso, el botón no se ofrece.
        $this->actingAs($contador)->get(route('requisiciones.show', $req))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('requisicion.can.corregir', false));
    }

    public function test_con_permiso_ve_catalogos_completos_y_corrige(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($colaborador, ['status' => 'PAGADA']);
        $req->detalles()->update(['sucursal_id' => $this->sucursal->id]);
        $admin = $this->userWith(['requisiciones.ver_todos', 'requisiciones.corregir', 'notificaciones.ver']);

        $conceptoInactivo = Concepto::create(['nombre' => 'Concepto viejo', 'activo' => false]);
        $solicitanteB = $this->makeEmpleado(['sucursal_id' => $this->sucursalB->id, 'nombre' => 'Beto']);
        $proveedor = $this->makeProveedor($admin);

        $this->actingAs($admin)->get(route('requisiciones.show', $req))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('requisicion.can.corregir', true));

        $this->actingAs($admin)->get(route('requisiciones.corregir', $req))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Requisiciones/Corregir')
                ->where('requisicion.folio', $req->folio)
                ->has('catalogos.conceptos', 2) // incluye el inactivo
                ->has('catalogos.sucursales', 3)
                ->has('catalogos.corporativos', 2));

        // La sucursal debe pertenecer al corporativo.
        $this->actingAs($admin)
            ->patch(route('requisiciones.corregir.update', $req), $this->payload($req, ['sucursal_id' => $this->sucursalB->id]))
            ->assertSessionHasErrors('sucursal_id');

        // Valida que existan y que haya motivo.
        $this->actingAs($admin)
            ->patch(route('requisiciones.corregir.update', $req), $this->payload($req, ['concepto_id' => 999999, 'motivo' => '']))
            ->assertSessionHasErrors(['concepto_id', 'motivo']);

        $this->actingAs($admin)
            ->patch(route('requisiciones.corregir.update', $req), $this->payload($req, [
                'comprador_corp_id' => $this->corporativoB->id,
                'sucursal_id' => $this->sucursalB->id,
                'solicitante_id' => $solicitanteB->id,
                'concepto_id' => $conceptoInactivo->id,
                'proveedor_id' => $proveedor->id,
                'actualizar_items_sucursal' => true,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('requisiciones.show', $req->id));

        $fresh = $req->fresh();
        $this->assertSame($this->corporativoB->id, (int) $fresh->comprador_corp_id);
        $this->assertSame($this->sucursalB->id, (int) $fresh->sucursal_id);
        $this->assertSame($solicitanteB->id, (int) $fresh->solicitante_id);
        $this->assertSame($conceptoInactivo->id, (int) $fresh->concepto_id);
        $this->assertSame($proveedor->id, (int) $fresh->proveedor_id);
        $this->assertSame('PAGADA', $fresh->status, 'No cambia el estatus.');
        $this->assertSame('1160.00', (string) $fresh->monto_total, 'No cambia montos.');
        $this->assertSame($this->sucursalB->id, (int) $fresh->detalles()->first()->sucursal_id);

        $log = SystemLog::where('accion', 'CORRECCION')->where('registro_id', $req->id)->firstOrFail();
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertStringContainsString('Se capturó con datos equivocados', $log->descripcion);
        $this->assertStringContainsString('Papelería', (string) $log->cambios['concepto_id']['antes']);
        $this->assertStringContainsString('Concepto viejo', (string) $log->cambios['concepto_id']['despues']);

        // Se avisa a quien creó la requisición.
        $this->assertSame('requisicion.corregida', $colaborador->notifications()->first()?->type);
    }

    public function test_no_corrige_requisiciones_eliminadas_ni_fuera_de_alcance(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $eliminada = $this->makeRequisicion($colaborador, ['status' => 'ELIMINADA']);
        $ajena = $this->requisicionEn($this->sucursalB, $colaborador);

        $admin = $this->userWith(['requisiciones.ver_todos', 'requisiciones.corregir']);
        $this->actingAs($admin)->get(route('requisiciones.corregir', $eliminada))->assertForbidden();

        // Con alcance de sucursal, el permiso no amplía lo que se ve.
        $local = $this->userWith(['requisiciones.ver_sucursal', 'requisiciones.corregir'], $this->sucursal);
        $this->actingAs($local)->get(route('requisiciones.corregir', $ajena))->assertForbidden();
    }

    public function test_sin_pagos_edita_items_fechas_y_recalcula_montos_en_servidor(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($colaborador, ['status' => 'CAPTURADA']);
        $admin = $this->userWith(['requisiciones.ver_todos', 'requisiciones.corregir']);

        $this->actingAs($admin)->get(route('requisiciones.corregir', $req))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('requisicion.items_editables', true)
                ->where('requisicion.items_bloqueo', null)
                ->has('requisicion.detalles', 1)
                ->where('requisicion.detalles.0.descripcion', 'Hojas'));

        // Valida los items como el alta.
        $this->actingAs($admin)
            ->patch(route('requisiciones.corregir.update', $req), $this->payload($req, [
                'detalles' => [['cantidad' => 0, 'descripcion' => 'x', 'precio_unitario' => -1, 'genera_iva' => true]],
            ]))
            ->assertSessionHasErrors(['detalles.0.cantidad', 'detalles.0.descripcion', 'detalles.0.precio_unitario']);

        $logsDeItems = SystemLog::where('tabla', 'detalles')->count();

        $this->actingAs($admin)
            ->patch(route('requisiciones.corregir.update', $req), $this->payload($req, [
                'fecha_solicitud' => '2026-01-15', // al corregir se permite una fecha pasada
                'fecha_pago_esperada' => '2026-01-20',
                'monto_total' => 1, // se ignora: el total lo calcula el servidor
                'detalles' => [
                    ['sucursal_id' => $this->sucursal->id, 'cantidad' => 2, 'descripcion' => 'Toner', 'precio_unitario' => 100, 'genera_iva' => true, 'total' => 9],
                    ['sucursal_id' => null, 'cantidad' => 1, 'descripcion' => 'Envío', 'precio_unitario' => 50, 'genera_iva' => false],
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('requisiciones.show', $req->id));

        $fresh = $req->fresh();
        $this->assertSame('250.00', (string) $fresh->monto_subtotal);
        $this->assertSame('282.00', (string) $fresh->monto_total); // 200 + 32 de IVA + 50
        $this->assertSame('CAPTURADA', $fresh->status);
        $this->assertSame($req->folio, $fresh->folio);
        $this->assertSame('2026-01-15', $fresh->fecha_solicitud->format('Y-m-d'));
        $this->assertSame('2026-01-20', $fresh->fecha_pago_esperada->format('Y-m-d'));

        $items = $fresh->detalles()->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertSame(['Toner', 'Envío'], $items->pluck('descripcion')->all());
        $this->assertEquals(232, (float) $items[0]->total);
        $this->assertEquals(0, (float) $items[1]->iva);

        $log = SystemLog::where('accion', 'CORRECCION')->where('registro_id', $req->id)->firstOrFail();
        $this->assertStringContainsString('1 → 2 item(s)', $log->descripcion);
        $this->assertStringContainsString('Hojas', (string) $log->cambios['items']['antes']);
        $this->assertStringContainsString('Toner', (string) $log->cambios['items']['despues']);
        $this->assertEquals(1160, $log->cambios['monto_total']['antes']);
        $this->assertEquals(282, $log->cambios['monto_total']['despues']);
        $this->assertSame('2026-01-15', $log->cambios['fecha_solicitud']['despues']);
        // Una sola entrada de bitácora: sin registros sueltos por item.
        $this->assertSame($logsDeItems, SystemLog::where('tabla', 'detalles')->count());

        $aviso = $colaborador->notifications()->first();
        $this->assertSame('requisicion.corregida', $aviso?->type);
        $this->assertStringContainsString('items', (string) ($aviso?->data['message'] ?? ''));
    }

    public function test_con_pagos_no_cambia_items_ni_montos_pero_si_la_cabecera(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($colaborador, ['status' => 'PAGO_AUTORIZADO']);
        $admin = $this->userWith(['requisiciones.ver_todos', 'requisiciones.corregir']);
        // Pago parcial: el estatus sigue en PAGO_AUTORIZADO, pero ya hay dinero de por medio.
        Pago::create([
            'requisicion_id' => $req->id, 'beneficiario_nombre' => 'Proveedor SA', 'tipo_pago' => 'TRANSFERENCIA',
            'monto' => 500, 'fecha_pago' => '2026-10-01', 'user_carga_id' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('requisiciones.corregir', $req))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('requisicion.items_editables', false)
                ->where('requisicion.items_bloqueo', 'Esta requisición ya tiene pagos; los montos e items no se pueden cambiar. Usa Ajustes.'));

        $this->actingAs($admin)
            ->patch(route('requisiciones.corregir.update', $req), $this->payload($req, [
                'detalles' => [['cantidad' => 5, 'descripcion' => 'Hojas', 'precio_unitario' => 1000, 'genera_iva' => true]],
            ]))
            ->assertSessionHasErrors('detalles');

        $this->assertSame('1160.00', (string) $req->fresh()->monto_total);
        $this->assertEquals(1, (float) $req->detalles()->first()->cantidad);
        $this->assertSame(0, SystemLog::where('accion', 'CORRECCION')->where('registro_id', $req->id)->count());

        // Reenviar los mismos items no cuenta como cambio; la cabecera sí se corrige.
        $otro = Concepto::create(['nombre' => 'Viáticos', 'activo' => true]);
        $this->actingAs($admin)
            ->patch(route('requisiciones.corregir.update', $req), $this->payload($req, [
                'concepto_id' => $otro->id,
                'detalles' => [['cantidad' => 1, 'descripcion' => 'Hojas', 'precio_unitario' => 1000, 'genera_iva' => true]],
            ]))
            ->assertSessionHasNoErrors();
        $this->assertSame($otro->id, (int) $req->fresh()->concepto_id);
        $this->assertSame('1160.00', (string) $req->fresh()->monto_total);
    }

    public function test_items_bloqueados_por_estatus_o_ajustes_pendientes(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $admin = $this->userWith(['requisiciones.ver_todos', 'requisiciones.corregir']);
        $items = ['detalles' => [['cantidad' => 3, 'descripcion' => 'Hojas', 'precio_unitario' => 10, 'genera_iva' => true]]];

        $porComprobar = $this->makeRequisicion($colaborador, ['status' => 'POR_COMPROBAR']);
        $this->actingAs($admin)->patch(route('requisiciones.corregir.update', $porComprobar), $this->payload($porComprobar, $items))
            ->assertSessionHasErrors('detalles');

        $conAjuste = $this->makeRequisicion($colaborador, ['status' => 'CAPTURADA']);
        Ajuste::create([
            'requisicion_id' => $conAjuste->id, 'tipo' => 'FALTANTE', 'sentido' => 'A_FAVOR_SOLICITANTE', 'monto' => 50,
            'monto_anterior' => 1160, 'monto_nuevo' => 1210, 'estatus' => Ajuste::ESTATUS_PENDIENTE,
            'motivo' => 'Envío', 'fecha_registro' => now(), 'user_registro_id' => $admin->id,
        ]);
        $this->actingAs($admin)->get(route('requisiciones.corregir', $conAjuste))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('requisicion.items_editables', false));
        $this->actingAs($admin)->patch(route('requisiciones.corregir.update', $conAjuste), $this->payload($conAjuste, $items))
            ->assertSessionHasErrors('detalles');

        $this->assertSame('1160.00', (string) $porComprobar->fresh()->monto_total);
        $this->assertSame('1160.00', (string) $conAjuste->fresh()->monto_total);
    }

    public function test_conserva_ajustes_aplicados_al_recalcular_el_total(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        // Items por 1,160 y un ajuste aplicado de −160: total actual 1,000.
        $req = $this->makeRequisicion($colaborador, ['status' => 'CAPTURADA', 'monto_total' => 1000]);
        $admin = $this->userWith(['requisiciones.ver_todos', 'requisiciones.corregir']);
        Ajuste::create([
            'requisicion_id' => $req->id, 'tipo' => 'DEVOLUCION', 'sentido' => 'A_FAVOR_EMPRESA', 'monto' => 160,
            'monto_anterior' => 1160, 'monto_nuevo' => 1000, 'estatus' => Ajuste::ESTATUS_APLICADO,
            'motivo' => 'Devolución', 'fecha_registro' => now(), 'user_registro_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('requisiciones.corregir.update', $req), $this->payload($req, [
                'detalles' => [['cantidad' => 2, 'descripcion' => 'Hojas', 'precio_unitario' => 1000, 'genera_iva' => true]],
            ]))
            ->assertSessionHasNoErrors();

        $fresh = $req->fresh();
        $this->assertSame('2000.00', (string) $fresh->monto_subtotal);
        $this->assertSame('2160.00', (string) $fresh->monto_total); // 2,320 de items − 160 ya aplicados
    }
}
