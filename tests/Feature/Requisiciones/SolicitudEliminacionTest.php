<?php

namespace Tests\Feature\Requisiciones;

use App\Models\RequisicionEliminacionSolicitud;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

class SolicitudEliminacionTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    public function test_colaborador_solicita_y_contabilidad_autoriza(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($colaborador, ['status' => 'CAPTURADA']);

        $this->actingAs($colaborador)
            ->post(route('requisiciones.eliminacion.store', $req), ['motivo' => 'Se duplicó la captura'])
            ->assertSessionHasNoErrors();

        $solicitud = RequisicionEliminacionSolicitud::firstOrFail();
        $this->assertSame('CAPTURADA', $req->fresh()->status, 'Solicitar no elimina.');
        $this->assertSame('requisicion.eliminacion_solicitada', $contador->notifications()->first()?->type);

        // Una segunda solicitud pendiente no se permite.
        $this->actingAs($colaborador)
            ->post(route('requisiciones.eliminacion.store', $req), ['motivo' => 'Otra vez'])
            ->assertForbidden();

        // El colaborador no puede autorizarse a sí mismo.
        $this->actingAs($colaborador)
            ->patch(route('requisiciones.eliminacion.review', $solicitud), ['accion' => 'APROBAR'])
            ->assertForbidden();

        $this->actingAs($contador)
            ->patch(route('requisiciones.eliminacion.review', $solicitud), ['accion' => 'APROBAR'])
            ->assertSessionHas('success');

        $this->assertSame('ELIMINADA', $req->fresh()->status);
        $this->assertSame(RequisicionEliminacionSolicitud::APROBADA, $solicitud->fresh()->estatus);
        $this->assertSame($contador->id, $solicitud->fresh()->revisado_por_id);
        $this->assertSame('requisicion.eliminacion_aprobada', $colaborador->notifications()->first()?->type);
    }

    public function test_solo_procede_con_estatus_capturada(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        foreach (['BORRADOR', 'PAGO_AUTORIZADO', 'PAGADA'] as $status) {
            $req = $this->makeRequisicion($colaborador, ['status' => $status]);
            $this->actingAs($colaborador)
                ->post(route('requisiciones.eliminacion.store', $req), ['motivo' => 'No aplica'])
                ->assertSessionHas('error');
        }

        $this->assertSame(0, RequisicionEliminacionSolicitud::count());
    }

    public function test_no_se_autoriza_si_la_requisicion_ya_avanzo(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($colaborador, ['status' => 'CAPTURADA']);
        $this->actingAs($colaborador)->post(route('requisiciones.eliminacion.store', $req), ['motivo' => 'Ya no se necesita']);

        $req->update(['status' => 'PAGO_AUTORIZADO']);

        $this->actingAs($contador)
            ->patch(route('requisiciones.eliminacion.review', RequisicionEliminacionSolicitud::firstOrFail()), ['accion' => 'APROBAR'])
            ->assertSessionHas('error');

        $this->assertSame('PAGO_AUTORIZADO', $req->fresh()->status);
    }

    public function test_rechazo_exige_comentario(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($colaborador, ['status' => 'CAPTURADA']);
        $this->actingAs($colaborador)->post(route('requisiciones.eliminacion.store', $req), ['motivo' => 'Error de captura']);
        $solicitud = RequisicionEliminacionSolicitud::firstOrFail();

        $this->actingAs($contador)
            ->patch(route('requisiciones.eliminacion.review', $solicitud), ['accion' => 'RECHAZAR'])
            ->assertSessionHasErrors('comentario_revision');

        $this->actingAs($contador)
            ->patch(route('requisiciones.eliminacion.review', $solicitud), ['accion' => 'RECHAZAR', 'comentario_revision' => 'Se usará el mes próximo'])
            ->assertSessionHas('success');

        $this->assertSame('CAPTURADA', $req->fresh()->status);
        $this->assertSame(RequisicionEliminacionSolicitud::RECHAZADA, $solicitud->fresh()->estatus);
    }
}
