<?php

namespace Tests\Feature\Requisiciones;

use App\Models\Concepto;
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
}
