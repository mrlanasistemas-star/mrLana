<?php

namespace Tests\Feature\Seguridad;

use App\Models\Area;
use App\Models\Requisicion;
use App\Models\SystemLog;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

class BitacoraTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    public function test_eliminacion_logica_registra_quien_y_el_cambio(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $colab = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($colab);

        $this->actingAs($admin)->delete(route('requisiciones.destroy', $req))->assertRedirect();

        $log = SystemLog::where('tabla', 'requisicions')->where('registro_id', $req->id)->where('accion', 'BAJA')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($req->folio, $log->etiqueta);
        $this->assertSame(['antes' => 'CAPTURADA', 'despues' => 'ELIMINADA'], $log->cambios['status']);
    }

    public function test_bajas_masivas_quedan_registradas_por_registro(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $colab = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $ids = [$this->makeRequisicion($colab)->id, $this->makeRequisicion($colab)->id];

        $this->actingAs($admin)->delete(route('requisiciones.bulkDestroy'), ['ids' => $ids])->assertRedirect();

        $this->assertSame(2, SystemLog::where('tabla', 'requisicions')->whereIn('registro_id', $ids)->where('accion', 'BAJA')->where('user_id', $admin->id)->count());
        $this->assertSame(2, Requisicion::whereIn('id', $ids)->where('status', 'ELIMINADA')->count());
    }

    public function test_baja_masiva_de_areas_ya_no_borra_fisicamente(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $area = Area::create(['corporativo_id' => $this->corporativo->id, 'nombre' => 'Compras', 'activo' => true]);

        $this->actingAs($admin)->post(route('areas.bulkDestroy'), ['ids' => [$area->id]])->assertRedirect();

        $this->assertDatabaseHas('areas', ['id' => $area->id, 'activo' => false]);
        $this->assertTrue(SystemLog::where('tabla', 'areas')->where('registro_id', $area->id)->where('accion', 'BAJA')->where('user_id', $admin->id)->exists());
    }

    public function test_bitacora_filtra_y_rastrea_un_registro(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $colab = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($colab);
        $this->actingAs($admin)->delete(route('requisiciones.destroy', $req));

        $this->actingAs($admin)->get(route('systemlogs.index', ['tabla' => 'requisicions', 'registro_id' => $req->id, 'accion' => 'BAJA', 'user_id' => $admin->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('SystemLogs/Index', true)
                ->has('logs.data', 1)
                ->where('logs.data.0.accion', 'BAJA')
                ->where('logs.data.0.modulo', 'Requisiciones'));

        $this->actingAs($colab)->get(route('systemlogs.index'))->assertForbidden();
    }
}
