<?php

namespace Tests\Feature\Requisiciones;

use App\Models\Ajuste;
use App\Models\Requisicion;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

class AjustesTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    private function solicitar($user, Requisicion $req, array $data = [])
    {
        return $this->actingAs($user)->post(route('requisiciones.ajustes.store', $req), $data + [
            'tipo' => 'INCREMENTO_AUTORIZADO',
            'monto' => 100,
            'motivo' => 'Subió el precio del proveedor.',
        ]);
    }

    public function test_solo_usuarios_autorizados_solicitan_revisan_y_aplican(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($colaborador);

        $this->solicitar($colaborador, $req)->assertSessionHasNoErrors();
        $ajuste = Ajuste::firstOrFail();
        $this->assertSame(Ajuste::ESTATUS_PENDIENTE, $ajuste->estatus);

        // El colaborador no puede revisar ni aplicar.
        $this->actingAs($colaborador)->patch(route('requisiciones.ajustes.review', $ajuste), ['accion' => 'APROBAR'])->assertForbidden();
        $this->actingAs($colaborador)->post(route('requisiciones.ajustes.apply', $ajuste))->assertForbidden();

        // Un rol que ve todas las requisiciones pero sin permisos de ajustes no puede solicitar.
        \App\Models\Role::create(['name' => 'Consulta', 'guard_name' => 'web'])
            ->syncPermissions(['requisiciones.ver_todos']);
        $sinPermiso = $this->makeUser('Consulta');
        $this->solicitar($sinPermiso, $req)->assertForbidden();

        $this->actingAs($contador)->patch(route('requisiciones.ajustes.review', $ajuste), ['accion' => 'APROBAR'])->assertSessionHasNoErrors();
        $this->actingAs($contador)->post(route('requisiciones.ajustes.apply', $ajuste))->assertSessionHasNoErrors();

        $this->assertSame(Ajuste::ESTATUS_APLICADO, $ajuste->fresh()->estatus);
    }

    public function test_colaborador_no_accede_a_requisiciones_ajenas(): void
    {
        $duena = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $ajeno = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($duena);

        $this->solicitar($ajeno, $req)->assertForbidden();
        $this->actingAs($ajeno)->get(route('requisiciones.ajustes', $req))->assertForbidden();
        $this->assertSame(0, Ajuste::count());
    }

    public function test_no_se_ajusta_una_requisicion_eliminada_o_en_borrador(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->solicitar($colaborador, $this->makeRequisicion($colaborador, ['status' => 'ELIMINADA']))->assertForbidden();
        $this->solicitar($colaborador, $this->makeRequisicion($colaborador, ['status' => 'BORRADOR']))->assertForbidden();
        $this->assertSame(0, Ajuste::count());
    }

    public function test_rechazar_exige_motivo_y_aprobar_no(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($colaborador);
        $this->solicitar($colaborador, $req);
        $this->solicitar($colaborador, $req);
        [$a, $b] = Ajuste::orderBy('id')->get();

        $this->actingAs($contador)
            ->patch(route('requisiciones.ajustes.review', $a), ['accion' => 'RECHAZAR', 'comentario_revision' => '  '])
            ->assertSessionHasErrors(['comentario_revision' => 'Escribe el motivo del rechazo.']);
        $this->assertSame(Ajuste::ESTATUS_PENDIENTE, $a->fresh()->estatus);

        $this->actingAs($contador)
            ->patch(route('requisiciones.ajustes.review', $a), ['accion' => 'RECHAZAR', 'comentario_revision' => 'No procede'])
            ->assertSessionHasNoErrors();
        $this->assertSame('No procede', $a->fresh()->comentario_revision);

        $this->actingAs($contador)
            ->patch(route('requisiciones.ajustes.review', $b), ['accion' => 'APROBAR'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Ajuste::ESTATUS_APROBADO, $b->fresh()->estatus);
        $this->assertNull($b->fresh()->comentario_revision);
    }

    public function test_motivos_largos_se_guardan_completos(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($colaborador);
        $motivo = Str::repeat('Motivo detallado del ajuste. ', 68).'Fin.'; // ~1,980 caracteres

        $this->solicitar($colaborador, $req, ['motivo' => $motivo])->assertSessionHasNoErrors();
        $this->assertSame($motivo, Ajuste::firstOrFail()->motivo);

        $this->solicitar($colaborador, $req, ['motivo' => Str::repeat('a', 2001)])
            ->assertSessionHasErrors(['motivo' => 'El motivo no debe exceder 2,000 caracteres.']);
    }

    public function test_se_registra_quien_reviso_y_quien_aplico(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $revisor = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $aplicador = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $req = $this->makeRequisicion($colaborador);

        $this->solicitar($colaborador, $req);
        $ajuste = Ajuste::firstOrFail();
        $this->actingAs($revisor)->patch(route('requisiciones.ajustes.review', $ajuste), ['accion' => 'APROBAR', 'comentario_revision' => 'Ok']);
        $this->actingAs($aplicador)->post(route('requisiciones.ajustes.apply', $ajuste));

        $ajuste->refresh();
        $this->assertSame($colaborador->id, $ajuste->user_registro_id);
        $this->assertSame($revisor->id, $ajuste->user_resuelve_id);
        $this->assertNotNull($ajuste->fecha_resolucion);
        $this->assertSame($aplicador->id, $ajuste->user_aplica_id);
        $this->assertNotNull($ajuste->fecha_aplicacion);
        $this->assertSame('Ok', $ajuste->comentario_revision);
    }

    public function test_un_ajuste_no_se_aplica_dos_veces_y_el_monto_cambia_una_sola_vez(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($colaborador, ['monto_total' => 1160]);

        $this->solicitar($colaborador, $req, ['monto' => 40]);
        $ajuste = Ajuste::firstOrFail();
        $this->actingAs($contador)->patch(route('requisiciones.ajustes.review', $ajuste), ['accion' => 'APROBAR']);

        $this->actingAs($contador)->post(route('requisiciones.ajustes.apply', $ajuste))->assertSessionHas('success');
        $this->actingAs($contador)->post(route('requisiciones.ajustes.apply', $ajuste))
            ->assertSessionHas('error', 'Este ajuste ya fue aplicado. El monto no se modificó de nuevo.');

        $this->assertEquals(1200.00, (float) $req->fresh()->monto_total);
        $this->assertEquals(1160.00, (float) $ajuste->fresh()->monto_anterior);
        $this->assertEquals(1200.00, (float) $ajuste->fresh()->monto_nuevo);

        // Una segunda revisión sobre un ajuste ya procesado también es controlada.
        $this->actingAs($contador)->patch(route('requisiciones.ajustes.review', $ajuste), ['accion' => 'RECHAZAR', 'comentario_revision' => 'tarde'])
            ->assertSessionHas('error');
        $this->assertSame(Ajuste::ESTATUS_APLICADO, $ajuste->fresh()->estatus);
    }

    public function test_el_monto_se_actualiza_dentro_de_una_transaccion(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($colaborador, ['monto_total' => 500]);
        $this->solicitar($colaborador, $req, ['monto' => 50]);
        $ajuste = Ajuste::firstOrFail();
        $this->actingAs($contador)->patch(route('requisiciones.ajustes.review', $ajuste), ['accion' => 'APROBAR']);

        $levels = [];
        DB::listen(function ($query) use (&$levels) {
            if (str_starts_with(strtolower($query->sql), 'update "requisicions"')) {
                $levels[] = DB::transactionLevel();
            }
        });

        $this->actingAs($contador)->post(route('requisiciones.ajustes.apply', $ajuste));

        $this->assertNotEmpty($levels, 'Se esperaba actualizar el monto de la requisición.');
        // RefreshDatabase abre 1 nivel; la aplicación debe abrir el suyo.
        $this->assertTrue(collect($levels)->every(fn ($l) => $l >= 2));
        $this->assertEquals(550.00, (float) $req->fresh()->monto_total);
    }
}
