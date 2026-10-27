<?php

namespace Tests\Feature\Requisiciones;

use App\Models\Folio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use LogicException;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/**
 * Folios de factura: el número de folio es fijo una vez registrado; con
 * "Editar folios de factura" solo se corrige el monto, y únicamente en folios
 * dentro del alcance de comprobaciones (calculado sobre quien lo registró).
 */
class FoliosTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganizacion();
    }

    private function folioDe(User $registrador, string $numero, ?float $monto = 100): Folio
    {
        return Folio::create(['folio' => $numero, 'monto_total' => $monto, 'user_registro_id' => $registrador->id]);
    }

    private function editor(string $nivel = 'ver_sucursal'): User
    {
        return $this->userWith(["comprobaciones.{$nivel}", 'comprobaciones.administrar_folios'], $this->sucursal);
    }

    public function test_el_numero_de_folio_no_cambia_aunque_se_envie(): void
    {
        $editor = $this->editor();
        $folio = $this->folioDe($editor, 'F-ORIGINAL', 100);

        $this->actingAs($editor)
            ->patchJson(route('folios.update', $folio), ['folio' => 'F-CAMBIADO', 'monto_total' => 250.5])
            ->assertOk()
            ->assertJsonPath('data.folio', 'F-ORIGINAL');

        $folio->refresh();
        $this->assertSame('F-ORIGINAL', $folio->folio);
        $this->assertEquals(250.5, (float) $folio->monto_total);
        $this->assertDatabaseMissing('folios', ['folio' => 'F-CAMBIADO']);
    }

    public function test_los_demas_campos_si_se_actualizan(): void
    {
        $editor = $this->editor();
        $otro = $this->userWith(['comprobaciones.ver_propios'], $this->sucursal);
        $folio = $this->folioDe($otro, 'F-MONTO', 100);

        $this->actingAs($editor)
            ->patch(route('folios.update', $folio), ['monto_total' => 999.99], ['X-Inertia' => 'true'])
            ->assertStatus(303)
            ->assertSessionHas('success', 'Folio actualizado.');

        $this->assertEquals(999.99, (float) $folio->fresh()->monto_total);

        $this->actingAs($editor)->patchJson(route('folios.update', $folio), ['monto_total' => null])->assertOk();
        $this->assertNull($folio->fresh()->monto_total);
    }

    public function test_sin_alcance_sobre_el_folio_recibe_403(): void
    {
        $editor = $this->editor('ver_sucursal');
        $ajeno = $this->userWith(['comprobaciones.ver_propios'], $this->sucursalB);
        $folio = $this->folioDe($ajeno, 'F-AJENO', 100);

        $this->actingAs($editor)
            ->patchJson(route('folios.update', $folio), ['monto_total' => 1])
            ->assertForbidden();

        $this->assertEquals(100, (float) $folio->fresh()->monto_total);

        // Con alcance "propio" tampoco puede editar folios registrados por otra persona de su sucursal.
        $propio = $this->editor('ver_propios');
        $vecino = $this->folioDe($this->userWith(['comprobaciones.ver_propios'], $this->sucursal), 'F-VECINO');
        $this->actingAs($propio)->patchJson(route('folios.update', $vecino), ['monto_total' => 1])->assertForbidden();
        $this->actingAs($propio)->patchJson(route('folios.update', $this->folioDe($propio, 'F-MIO')), ['monto_total' => 1])->assertOk();
    }

    public function test_alcance_sin_permiso_de_editar_recibe_403(): void
    {
        $lector = $this->userWith(['comprobaciones.ver_todos'], $this->sucursal);
        $folio = $this->folioDe($lector, 'F-LECTOR');

        $this->actingAs($lector)->patchJson(route('folios.update', $folio), ['monto_total' => 1])->assertForbidden();
    }

    public function test_el_listado_solo_muestra_folios_del_alcance(): void
    {
        $editor = $this->editor('ver_corporativo');
        $this->folioDe($editor, 'F-PROPIO');
        $this->folioDe($this->userWith(['comprobaciones.ver_propios'], $this->sucursal2), 'F-CORP-A');
        $this->folioDe($this->userWith(['comprobaciones.ver_propios'], $this->sucursalB), 'F-CORP-B');

        $folios = $this->actingAs($editor)->getJson(route('folios.index'))->assertOk()->json('*.folio');
        sort($folios);
        $this->assertSame(['F-CORP-A', 'F-PROPIO'], $folios);

        $req = $this->requisicionEn($this->sucursal, $editor);
        $this->actingAs($editor)->get(route('requisiciones.comprobar', $req))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('folios', 2));
    }

    public function test_el_modelo_impide_cambiar_el_numero_de_folio(): void
    {
        $folio = $this->folioDe($this->editor(), 'F-FIJO');

        $this->expectException(LogicException::class);
        $folio->update(['folio' => 'F-OTRO']);
    }
}
