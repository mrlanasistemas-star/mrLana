<?php

namespace Tests\Feature\Interfaz;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/**
 * Dashboard por vistas (personal, sucursal, corporativo, general) con sus
 * propios permisos, y exportaciones limitadas a la vista autorizada.
 */
class DashboardAlcanceTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganizacion();
        $creador = $this->userWith(['requisiciones.ver_propios']);
        $this->requisicionEn($this->sucursal, $creador, ['monto_total' => 100]);
        $this->requisicionEn($this->sucursal2, $creador, ['monto_total' => 200]);
        $this->requisicionEn($this->sucursalB, $creador, ['monto_total' => 400]);
    }

    public function test_cada_vista_suma_solo_su_alcance(): void
    {
        $user = $this->userWith(['dashboard.general'], $this->sucursal);
        $this->requisicionEn($this->sucursalB, $user, ['monto_total' => 1000]); // propia

        $esperado = ['personal' => 1000, 'sucursal' => 100, 'corporativo' => 300, 'general' => 1700];
        foreach ($esperado as $vista => $monto) {
            $this->actingAs($user)->get(route('dashboard', ['vista' => $vista]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $p) => $p->where('dashboard.view', $vista)->where('dashboard.cards.0.value', $monto));
        }
    }

    public function test_la_vista_inicial_es_la_mas_amplia_y_ofrece_las_inferiores(): void
    {
        $user = $this->userWith(['dashboard.corporativo'], $this->sucursal);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('dashboard.view', 'corporativo')
                ->where('dashboard.views', fn ($views) => collect($views)->pluck('value')->all() === ['corporativo', 'sucursal', 'personal']));
    }

    public function test_no_puede_abrir_ni_exportar_una_vista_superior(): void
    {
        $user = $this->userWith(['dashboard.sucursal', 'reportes.dashboard'], $this->sucursal);

        $this->actingAs($user)->get(route('dashboard', ['vista' => 'general']))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard', ['vista' => 'corporativo']))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard.export.excel', ['vista' => 'general']))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard.export.pdf', ['vista' => 'corporativo']))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard.export.excel', ['vista' => 'admin']))->assertNotFound();

        // La exportación de su vista solo incluye su sucursal, aunque pida otro corporativo.
        $text = $this->excelText($this->actingAs($user)->get(route('dashboard.export.excel', ['vista' => 'sucursal', 'corporativo_id' => $this->corporativoB->id]))->assertOk());
        $this->assertStringNotContainsString('400', $text);
    }

    public function test_sin_colaborador_no_hay_vistas_de_sucursal_ni_corporativo(): void
    {
        $user = $this->userWith(['dashboard.corporativo'], withEmpleado: false);

        // Sin colaborador el permiso de corporativo no da acceso (nunca se vuelve general).
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('profile.edit'));
        $this->actingAs($user)->get(route('dashboard.export.excel', ['vista' => 'general']))->assertForbidden();

        // Con "Ver mi dashboard" conserva la vista personal (lo que capturó).
        $mixto = $this->userWith(['dashboard.personal', 'dashboard.corporativo'], withEmpleado: false);
        $this->actingAs($mixto)->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('dashboard.view', 'personal')->where('dashboard.views', fn ($v) => count($v) === 1));
    }

    public function test_el_dashboard_no_se_deduce_de_otros_permisos(): void
    {
        $user = $this->userWith(['requisiciones.ver_todos', 'usuarios.ver'], $this->sucursal);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('requisiciones.index'));
        $this->actingAs($user)->get(route('dashboard', ['vista' => 'general']))->assertRedirect(route('requisiciones.index'));
    }
}
