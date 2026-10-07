<?php

namespace Tests\Feature\Pagos;

use App\Models\Requisicion;
use App\Models\SystemLog;
use App\Support\Permissions\PermissionCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

class BackfillAutorizadorPagoTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    /** Requisición autorizada con el código anterior (sin autorizador guardado). */
    private function autorizadaAntes(string $fecha = '2026-05-10 10:00:00'): Requisicion
    {
        $req = $this->makeRequisicion($this->makeUser(PermissionCatalog::ROLE_COLABORADOR));
        Requisicion::query()->whereKey($req->id)->update([
            'status' => 'PAGO_AUTORIZADO', 'fecha_autorizacion' => $fecha, 'pago_autorizado_por_id' => null,
        ]);

        return $req->fresh();
    }

    private function log(Requisicion $req, ?int $userId, string $fecha, string $flecha = '->'): SystemLog
    {
        $log = SystemLog::create([
            'user_id' => $userId, 'accion' => 'ACTUALIZACION', 'tabla' => 'requisicions', 'registro_id' => $req->id,
            'descripcion' => "Actualización: requisicions#{$req->id}.\nCambios:\n- fecha_autorizacion: null {$flecha} {$fecha}\n- status: CAPTURADA {$flecha} PAGO_AUTORIZADO",
        ]);
        $log->forceFill(['created_at' => Carbon::parse($fecha)])->save();

        return $log;
    }

    public function test_dry_run_no_guarda_cambios(): void
    {
        $conta = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->autorizadaAntes();
        $this->log($req, $conta->id, '2026-05-10 10:00:00');

        $this->artisan('erp:backfill-autorizador-pago', ['--dry-run' => true])
            ->expectsOutputToContain('Recuperable')
            ->assertSuccessful();

        $this->assertNull($req->fresh()->pago_autorizado_por_id);
    }

    public function test_asigna_cuando_la_bitacora_es_inequivoca(): void
    {
        $conta = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $antiguo = $this->autorizadaAntes();
        $this->log($antiguo, $conta->id, '2026-05-10 10:00:00');
        $actual = $this->autorizadaAntes('2026-06-01 09:00:00');
        $this->log($actual, $conta->id, '2026-06-01 09:00:30', '→');

        $this->artisan('erp:backfill-autorizador-pago')->assertSuccessful();

        $this->assertSame($conta->id, (int) $antiguo->fresh()->pago_autorizado_por_id);
        $this->assertSame($conta->id, (int) $actual->fresh()->pago_autorizado_por_id);
        $this->assertDatabaseHas('system_logs', ['registro_id' => $antiguo->id, 'tabla' => 'requisicions', 'user_id' => null, 'accion' => 'ACTUALIZACION']);
    }

    public function test_no_asigna_sin_bitacora(): void
    {
        $req = $this->autorizadaAntes();

        $this->artisan('erp:backfill-autorizador-pago')->expectsOutputToContain('No registrado')->assertSuccessful();

        $this->assertNull($req->fresh()->pago_autorizado_por_id);
    }

    public function test_no_asigna_si_hay_usuarios_distintos(): void
    {
        $a = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $b = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $req = $this->autorizadaAntes();
        $this->log($req, $a->id, '2026-05-09 10:00:00');
        $this->log($req, $b->id, '2026-05-10 10:00:00');

        $this->artisan('erp:backfill-autorizador-pago')->expectsOutputToContain('distintos usuarios')->assertSuccessful();

        $this->assertNull($req->fresh()->pago_autorizado_por_id);
    }

    public function test_no_asigna_si_el_log_no_tiene_usuario_o_la_fecha_no_coincide(): void
    {
        $conta = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $sinUsuario = $this->autorizadaAntes();
        $this->log($sinUsuario, null, '2026-05-10 10:00:00');
        $lejana = $this->autorizadaAntes();
        $this->log($lejana, $conta->id, '2026-05-12 18:00:00');

        $this->artisan('erp:backfill-autorizador-pago')->assertSuccessful();

        $this->assertNull($sinUsuario->fresh()->pago_autorizado_por_id);
        $this->assertNull($lejana->fresh()->pago_autorizado_por_id);
    }

    public function test_no_toma_otros_cambios_de_estatus(): void
    {
        $conta = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->autorizadaAntes();
        // Un pago parcial deja el estatus en PAGO_AUTORIZADO, pero no es la autorización.
        SystemLog::create([
            'user_id' => $conta->id, 'accion' => 'ACTUALIZACION', 'tabla' => 'requisicions', 'registro_id' => $req->id,
            'descripcion' => "Actualización: requisicions#{$req->id}.\nCambios:\n- status: PAGO_AUTORIZADO -> PAGADA",
        ]);

        $this->artisan('erp:backfill-autorizador-pago')->assertSuccessful();

        $this->assertNull($req->fresh()->pago_autorizado_por_id);
    }

    public function test_no_sobrescribe_autorizador_existente(): void
    {
        $conta = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $otro = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $req = $this->autorizadaAntes();
        Requisicion::query()->whereKey($req->id)->update(['pago_autorizado_por_id' => $otro->id]);
        $this->log($req, $conta->id, '2026-05-10 10:00:00');

        $this->artisan('erp:backfill-autorizador-pago')->assertSuccessful();

        $this->assertSame($otro->id, (int) $req->fresh()->pago_autorizado_por_id);
    }
}
