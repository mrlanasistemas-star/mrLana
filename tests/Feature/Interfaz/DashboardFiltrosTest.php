<?php

namespace Tests\Feature\Interfaz;

use App\Models\Concepto;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

class DashboardFiltrosTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    public function test_filtra_por_concepto_y_excluye_eliminadas(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $colab = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $otro = Concepto::create(['nombre' => 'Viáticos', 'activo' => true]);

        $this->makeRequisicion($colab, ['monto_total' => 1000]);
        $this->makeRequisicion($colab, ['monto_total' => 500, 'concepto_id' => $otro->id]);
        $this->makeRequisicion($colab, ['monto_total' => 9999, 'status' => 'ELIMINADA']);

        $this->actingAs($admin)->get(route('dashboard', ['vista' => 'general']))
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Dashboard/Index', true)
                ->where('dashboard.cards.0.value', 1500)
                ->where('dashboard.cards.1.value', 2));

        $this->actingAs($admin)->get(route('dashboard', ['vista' => 'general', 'concepto_id' => $otro->id]))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('dashboard.cards.0.value', 500)
                ->where('dashboard.filters.concepto_id', $otro->id)
                ->where('dashboard.byConcepto.0.name', 'Viáticos'));

        $this->actingAs($admin)->get(route('dashboard', ['vista' => 'general', 'status' => 'ELIMINADA']))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('dashboard.cards.0.value', 9999));
    }

    public function test_colaborador_solo_ve_sus_requisiciones(): void
    {
        $mia = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $ajena = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $this->makeRequisicion($mia, ['monto_total' => 300]);
        $this->makeRequisicion($ajena, ['monto_total' => 7000]);

        $this->actingAs($mia)->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('dashboard.profile', 'personal')
                ->where('dashboard.cards.0.value', 300)
                ->where('dashboard.bySucursal', []));

        $this->actingAs($mia)->get(route('dashboard', ['vista' => 'general']))->assertForbidden();
    }

    public function test_rango_personalizado_y_tendencia_mensual(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('dashboard', ['vista' => 'general', 'preset' => 'rango', 'desde' => '2026-01-01', 'hasta' => '2026-06-30']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('dashboard.filters.desde', '2026-01-01')
                ->where('dashboard.trend.granularity', 'month')
                ->has('dashboard.trend.points', 6)
                ->has('dashboard.monthly', 12));
    }
}
