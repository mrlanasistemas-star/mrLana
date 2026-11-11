<?php

namespace Tests\Feature\Requisiciones;

use App\Models\Pago;
use App\Models\Requisicion;
use App\Models\RequisicionEliminacionSolicitud;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/**
 * Una requisición con pagos registrados solo la elimina Contabilidad, con
 * motivo, y queda registrado quién la eliminó y cuánto se había pagado.
 */
class EliminacionConPagosTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    private function pagar(Requisicion $req, User $por, float $monto): void
    {
        Pago::create([
            'requisicion_id' => $req->id, 'beneficiario_nombre' => 'Proveedor', 'tipo_pago' => 'TRANSFERENCIA',
            'monto' => $monto, 'fecha_pago' => now(), 'user_carga_id' => $por->id,
        ]);
    }

    public function test_no_se_puede_solicitar_eliminar_una_requisicion_con_pagos(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($colaborador, ['status' => 'CAPTURADA']);
        $this->pagar($req, $contador, 100);

        $this->actingAs($colaborador)
            ->post(route('requisiciones.eliminacion.store', $req), ['motivo' => 'Ya no se necesita'])
            ->assertSessionHas('error');

        $this->assertSame(0, RequisicionEliminacionSolicitud::count());
        $this->assertSame('CAPTURADA', $req->fresh()->status);
    }

    public function test_contabilidad_elimina_con_motivo_y_queda_registro_de_pagos(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($colaborador, ['status' => 'PAGADA']);
        $this->pagar($req, $contador, 250.5);

        // Sin motivo no procede.
        $this->actingAs($contador)
            ->delete(route('requisiciones.destroy', $req))
            ->assertSessionHasErrors('motivo');
        $this->assertSame('PAGADA', $req->fresh()->status);

        $this->actingAs($contador)
            ->delete(route('requisiciones.destroy', $req), ['motivo' => 'Pago duplicado, se reembolsó'])
            ->assertSessionHasNoErrors();

        $req->refresh();
        $this->assertSame('ELIMINADA', $req->status);
        $this->assertSame($contador->id, (int) $req->eliminada_por_id);
        $this->assertNotNull($req->eliminada_at);
        $this->assertSame('250.50', (string) $req->pagado_al_eliminar);
        $this->assertSame('Pago duplicado, se reembolsó', $req->eliminacion_motivo);
    }

    public function test_sin_el_permiso_no_se_elimina_una_requisicion_con_pagos(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $contador->roles->first()->revokePermissionTo('requisiciones.eliminar_con_pago');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $req = $this->makeRequisicion($colaborador, ['status' => 'PAGADA']);
        $this->pagar($req, $contador, 10);

        $this->actingAs($contador->fresh())
            ->delete(route('requisiciones.destroy', $req), ['motivo' => 'Prueba de permiso'])
            ->assertForbidden();

        $this->assertSame('PAGADA', $req->fresh()->status);
    }

    public function test_eliminacion_sin_pagos_registra_quien_y_cero_pagado(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($colaborador, ['status' => 'CAPTURADA']);

        $this->actingAs($colaborador)->post(route('requisiciones.eliminacion.store', $req), ['motivo' => 'Se duplicó la captura']);
        $this->actingAs($contador)
            ->patch(route('requisiciones.eliminacion.review', RequisicionEliminacionSolicitud::firstOrFail()), ['accion' => 'APROBAR'])
            ->assertSessionHas('success');

        $req->refresh();
        $this->assertSame('ELIMINADA', $req->status);
        $this->assertSame($contador->id, (int) $req->eliminada_por_id);
        $this->assertSame('0.00', (string) $req->pagado_al_eliminar);
        $this->assertSame('Se duplicó la captura', $req->eliminacion_motivo);
    }
}
