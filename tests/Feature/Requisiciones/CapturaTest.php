<?php

namespace Tests\Feature\Requisiciones;

use App\Models\Plantilla;
use App\Models\Requisicion;
use App\Models\SystemLog;
use App\Support\BusinessDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/**
 * Reglas de captura: solicitante, sucursal y corporativo según permisos
 * especiales (nunca según "ver todas las requisiciones").
 */
class CapturaTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    private const BASE = ['requisiciones.ver_propios', 'requisiciones.registrar', 'requisiciones.guardar_borrador', 'requisiciones.enviar'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganizacion();
    }

    private function payloadEn($user, $sucursal, array $overrides = []): array
    {
        return $this->requisicionPayload($user, $overrides + [
            'sucursal_id' => $sucursal->id,
            'comprador_corp_id' => $sucursal->corporativo_id,
            'detalles' => [['sucursal_id' => $sucursal->id, 'cantidad' => 1, 'descripcion' => 'Hojas', 'precio_unitario' => 10, 'genera_iva' => true]],
        ]);
    }

    public function test_sin_permisos_especiales_el_formulario_llega_bloqueado_y_sin_catalogos_completos(): void
    {
        $user = $this->userWith(self::BASE, $this->sucursal);

        $this->actingAs($user)->get(route('requisiciones.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('catalogos.captura.solicitante_fijo', true)
                ->where('catalogos.captura.sucursal_fija', true)
                ->where('catalogos.captura.corporativo_fijo', true)
                ->where('catalogos.captura.propio.solicitante_id', $user->empleado_id)
                ->has('catalogos.empleados', 1)
                ->has('catalogos.sucursales', 1)
                ->has('catalogos.corporativos', 1));
    }

    public function test_solicitante_y_sucursal_automaticos(): void
    {
        $user = $this->userWith(self::BASE, $this->sucursal);
        $payload = $this->payloadEn($user, $this->sucursal);
        unset($payload['solicitante_id'], $payload['sucursal_id'], $payload['comprador_corp_id']);

        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $payload)->assertSessionHasNoErrors();

        $req = Requisicion::latest('id')->firstOrFail();
        $this->assertSame((int) $user->empleado_id, (int) $req->solicitante_id);
        $this->assertSame($this->sucursal->id, (int) $req->sucursal_id);
        $this->assertSame($this->corporativo->id, (int) $req->comprador_corp_id);
        $this->assertSame($user->id, (int) $req->creada_por_user_id);
    }

    public function test_no_puede_cambiar_solicitante_sin_permiso(): void
    {
        $user = $this->userWith(self::BASE, $this->sucursal);
        $otro = $this->makeEmpleado(['nombre' => 'Otra persona']);

        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursal, ['solicitante_id' => $otro->id]))
            ->assertSessionHasErrors('solicitante_id');
        $this->assertSame(0, Requisicion::count());
    }

    public function test_puede_elegir_solicitante_con_permiso_y_queda_en_bitacora(): void
    {
        $user = $this->userWith([...self::BASE, 'requisiciones.elegir_solicitante'], $this->sucursal);
        $otro = $this->makeEmpleado(['nombre' => 'Bea', 'sucursal_id' => $this->sucursal->id]);
        $fuera = $this->makeEmpleado(['nombre' => 'Fuera', 'sucursal_id' => $this->sucursalB->id]);

        // Fuera de las sucursales que puede elegir: rechazado.
        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursal, ['solicitante_id' => $fuera->id]))
            ->assertSessionHasErrors('solicitante_id');

        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursal, ['solicitante_id' => $otro->id]))
            ->assertSessionHasNoErrors();

        $req = Requisicion::latest('id')->firstOrFail();
        $this->assertSame($otro->id, (int) $req->solicitante_id);
        $this->assertSame($user->id, (int) $req->creada_por_user_id);
        $this->assertTrue(SystemLog::where('tabla', 'requisicions')->where('registro_id', $req->id)->where('accion', 'CAPTURA_A_NOMBRE')->exists());
    }

    public function test_sin_permiso_no_puede_elegir_otra_sucursal(): void
    {
        $user = $this->userWith(self::BASE, $this->sucursal);

        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursal2))
            ->assertSessionHasErrors('sucursal_id');
    }

    public function test_sucursal_del_mismo_corporativo_si_otro_corporativo_no(): void
    {
        $user = $this->userWith([...self::BASE, 'requisiciones.elegir_sucursal_corporativo'], $this->sucursal);

        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursal2))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->sucursal2->id, (int) Requisicion::latest('id')->value('sucursal_id'));

        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursalB))
            ->assertSessionHasErrors('sucursal_id');

        // Combinación manipulada: sucursal de A con comprador B.
        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursal2, ['comprador_corp_id' => $this->corporativoB->id]))
            ->assertSessionHasErrors('sucursal_id');

        $this->actingAs($user)->get(route('requisiciones.create'))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('catalogos.sucursales', 2)->where('catalogos.captura.corporativo_fijo', true));
    }

    public function test_cualquier_corporativo_requiere_ambos_permisos_explicitos(): void
    {
        $user = $this->userWith([...self::BASE, 'requisiciones.elegir_corporativo', 'requisiciones.elegir_sucursal_global'], $this->sucursal);

        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursalB))
            ->assertSessionHasNoErrors();
        $req = Requisicion::latest('id')->firstOrFail();
        $this->assertSame($this->corporativoB->id, (int) $req->comprador_corp_id);

        // Item cargado a una sucursal de otro corporativo distinto al comprador: rechazado.
        $payload = $this->payloadEn($user, $this->sucursalB);
        $payload['detalles'][0]['sucursal_id'] = $this->sucursal->id;
        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $payload)->assertSessionHasErrors('detalles.0.sucursal_id');
    }

    public function test_ver_todas_no_sustituye_elegir_solicitante(): void
    {
        $user = $this->userWith(['requisiciones.ver_todos', 'requisiciones.registrar', 'requisiciones.guardar_borrador'], $this->sucursal);
        $otro = $this->makeEmpleado(['nombre' => 'Ajeno']);

        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursal, ['solicitante_id' => $otro->id]))
            ->assertSessionHasErrors('solicitante_id');
    }

    public function test_sin_colaborador_vinculado_y_sin_permisos_no_puede_capturar(): void
    {
        $user = $this->userWith(self::BASE, withEmpleado: false);
        $payload = $this->payloadEn($user, $this->sucursal);
        unset($payload['solicitante_id']);

        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $payload)->assertSessionHasErrors('solicitante_id');
    }

    public function test_reglas_de_proveedor_y_fechas_se_conservan(): void
    {
        $user = $this->userWith(self::BASE, $this->sucursal);

        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursal, ['proveedor_id' => null]))
            ->assertSessionHasErrors('proveedor_id');
        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursal, ['fecha_solicitud' => BusinessDate::today()->subDay()->toDateString()]))
            ->assertSessionHasErrors('fecha_solicitud');
        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursal, ['fecha_pago_esperada' => BusinessDate::today()->subDay()->toDateString()]))
            ->assertSessionHasErrors('fecha_pago_esperada');
        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursal, ['detalles' => []]))
            ->assertSessionHasErrors('detalles');

        // Proveedor activo de otra persona: solo con "Utilizar cualquier proveedor activo".
        $ajeno = $this->makeProveedor($this->userWith([]));
        $this->actingAs($user)->post(route('requisiciones.storeDraft'), $this->payloadEn($user, $this->sucursal, ['proveedor_id' => $ajeno->id]))
            ->assertSessionHasErrors('proveedor_id');
        $usa = $this->userWith([...self::BASE, 'proveedores.usar_todos'], $this->sucursal);
        $this->actingAs($usa)->post(route('requisiciones.storeDraft'), $this->payloadEn($usa, $this->sucursal, ['proveedor_id' => $ajeno->id]))
            ->assertSessionHasNoErrors();
    }

    public function test_guardar_y_enviar_son_permisos_separados(): void
    {
        $soloBorrador = $this->userWith(['requisiciones.ver_propios', 'requisiciones.registrar', 'requisiciones.guardar_borrador'], $this->sucursal);

        $this->actingAs($soloBorrador)->post(route('requisiciones.storeCaptured'), $this->payloadEn($soloBorrador, $this->sucursal))->assertForbidden();
        $this->actingAs($soloBorrador)->post(route('requisiciones.storeDraft'), $this->payloadEn($soloBorrador, $this->sucursal))->assertSessionHasNoErrors();
    }

    public function test_plantillas_no_evaden_las_restricciones(): void
    {
        $user = $this->userWith([...self::BASE, 'plantillas.ver_propios', 'plantillas.registrar'], $this->sucursal);
        $otro = $this->makeEmpleado(['nombre' => 'Otro', 'sucursal_id' => $this->sucursalB->id]);
        $proveedor = $this->makeProveedor($user);

        // Crear plantilla con solicitante y sucursal ajenos: rechazado por las mismas reglas.
        $this->actingAs($user)->post(route('plantillas.store'), [
            'nombre' => 'Renta', 'solicitante_id' => $otro->id, 'sucursal_id' => $this->sucursalB->id,
            'proveedor_id' => $proveedor->id, 'concepto_id' => $this->concepto->id,
            'monto_subtotal' => 10, 'monto_total' => 11.6, 'fecha_solicitud' => BusinessDate::todayString(),
            'detalles' => [['cantidad' => 1, 'descripcion' => 'Renta', 'precio_unitario' => 10, 'subtotal' => 10, 'iva' => 1.6, 'total' => 11.6]],
        ])->assertSessionHasErrors();
        $this->assertSame(0, Plantilla::count());

        // Una plantilla existente con datos ajenos se precarga con los valores permitidos.
        $plantilla = Plantilla::create([
            'user_id' => $user->id, 'nombre' => 'Vieja', 'status' => 'BORRADOR', 'solicitante_id' => $otro->id,
            'sucursal_id' => $this->sucursalB->id, 'comprador_corp_id' => $this->corporativoB->id, 'proveedor_id' => $proveedor->id,
            'concepto_id' => $this->concepto->id, 'monto_subtotal' => 10, 'monto_total' => 11.6,
        ]);
        $this->actingAs($user)->get(route('requisiciones.create', ['plantilla' => $plantilla->id]))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('plantilla.solicitante_id', $user->empleado_id)
                ->where('plantilla.sucursal_id', $this->sucursal->id)
                ->where('plantilla.comprador_corp_id', $this->corporativo->id));
    }

    public function test_editar_borrador_ajeno_requiere_permiso_y_alcance(): void
    {
        $duena = $this->userWith([...self::BASE, 'requisiciones.editar'], $this->sucursal);
        $req = $this->requisicionEn($this->sucursal, $duena, ['status' => 'BORRADOR']);

        $editorSucursal = $this->userWith(['requisiciones.ver_sucursal', 'requisiciones.editar_cualquiera'], $this->sucursal);
        $editorOtra = $this->userWith(['requisiciones.ver_sucursal', 'requisiciones.editar_cualquiera'], $this->sucursalB);

        $payload = $this->payloadEn($duena, $this->sucursal, ['observaciones' => 'Corregida', 'proveedor_id' => $req->proveedor_id]);
        $this->actingAs($editorOtra)->put(route('requisiciones.update', $req), $payload)->assertForbidden();
        $this->actingAs($editorSucursal)->put(route('requisiciones.update', $req), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Corregida', $req->fresh()->observaciones);
        // El solicitante original se conserva aunque el editor no pueda elegir solicitante.
        $this->assertSame((int) $duena->empleado_id, (int) $req->fresh()->solicitante_id);
    }
}
