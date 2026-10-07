<?php

namespace Tests\Feature\Requisiciones;

use App\Models\Requisicion;
use App\Rules\ActiveProveedor;
use App\Support\BusinessDate;
use App\Support\Permissions\PermissionCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

class RequisicionValidationTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    public function test_fecha_de_solicitud_pasada_es_rechazada(): void
    {
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($user)
            ->post(route('requisiciones.storeDraft'), $this->requisicionPayload($user, [
                'fecha_solicitud' => BusinessDate::today()->subDay()->toDateString(),
            ]))
            ->assertSessionHasErrors(['fecha_solicitud' => 'La fecha de solicitud no puede ser anterior a hoy.']);

        $this->assertSame(0, Requisicion::count());
    }

    public function test_fecha_de_hoy_es_aceptada(): void
    {
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($user)
            ->post(route('requisiciones.storeDraft'), $this->requisicionPayload($user))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('requisiciones.index'));

        $this->assertSame(BusinessDate::todayString(), Requisicion::first()->fecha_solicitud->toDateString());
    }

    public function test_fecha_futura_es_aceptada(): void
    {
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $futura = BusinessDate::today()->addDays(10)->toDateString();

        $this->actingAs($user)
            ->post(route('requisiciones.storeCaptured'), $this->requisicionPayload($user, ['fecha_solicitud' => $futura]))
            ->assertSessionHasNoErrors();

        $this->assertSame($futura, Requisicion::first()->fecha_solicitud->toDateString());
        $this->assertSame('CAPTURADA', Requisicion::first()->status);
    }

    public function test_hoy_se_calcula_con_la_zona_horaria_de_negocio(): void
    {
        // 23:30 en Ciudad de México = 05:30 UTC del día siguiente.
        $this->travelTo(CarbonImmutable::parse('2026-03-10 23:30:00', 'America/Mexico_City'));
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($user)
            ->post(route('requisiciones.storeDraft'), $this->requisicionPayload($user, ['fecha_solicitud' => '2026-03-10']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Requisicion::count());
    }

    public function test_fecha_esperada_anterior_a_la_solicitud_es_rechazada(): void
    {
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($user)
            ->post(route('requisiciones.storeDraft'), $this->requisicionPayload($user, [
                'fecha_solicitud' => BusinessDate::today()->addDays(5)->toDateString(),
                'fecha_pago_esperada' => BusinessDate::today()->addDays(2)->toDateString(),
            ]))
            ->assertSessionHasErrors(['fecha_pago_esperada' => 'La fecha esperada de pago no puede ser anterior a la fecha de solicitud.']);
    }

    public function test_fecha_esperada_es_opcional_y_se_guarda_si_se_captura(): void
    {
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($user)
            ->post(route('requisiciones.storeDraft'), $this->requisicionPayload($user, ['fecha_pago_esperada' => null]))
            ->assertSessionHasNoErrors();
        $this->assertNull(Requisicion::first()->fecha_pago_esperada);

        $esperada = BusinessDate::today()->addDays(7)->toDateString();
        $this->actingAs($user)
            ->post(route('requisiciones.storeDraft'), $this->requisicionPayload($user, ['fecha_pago_esperada' => $esperada]))
            ->assertSessionHasNoErrors();
        $this->assertSame($esperada, Requisicion::latest('id')->first()->fecha_pago_esperada->toDateString());
    }

    public function test_fecha_esperada_y_autorizacion_real_permanecen_separadas(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $esperada = BusinessDate::today()->addDays(3)->toDateString();

        $this->actingAs($colaborador)
            ->post(route('requisiciones.storeCaptured'), $this->requisicionPayload($colaborador, ['fecha_pago_esperada' => $esperada]))
            ->assertSessionHasNoErrors();

        $req = Requisicion::first();
        $this->assertNull($req->fecha_autorizacion, 'Registrar la fecha esperada no debe tocar la autorización real.');

        $programada = BusinessDate::today()->addDays(4)->toDateString();
        $this->actingAs($contador)
            ->post(route('requisiciones.autorizarPago', $req), ['fecha_pago' => $programada])
            ->assertSessionHasNoErrors();

        $req->refresh();
        $this->assertSame('PAGO_AUTORIZADO', $req->status);
        $this->assertNotNull($req->fecha_autorizacion);
        $this->assertSame($esperada, $req->fecha_pago_esperada->toDateString());
        $this->assertSame($programada, $req->fecha_pago->toDateString());
    }

    public function test_sin_proveedor_no_se_guarda_borrador(): void
    {
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($user)
            ->post(route('requisiciones.storeDraft'), $this->requisicionPayload($user, ['proveedor_id' => null]))
            ->assertSessionHasErrors(['proveedor_id' => ActiveProveedor::MESSAGE]);

        $this->assertSame(0, Requisicion::count());
    }

    public function test_sin_proveedor_no_se_envia(): void
    {
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($user)
            ->post(route('requisiciones.storeCaptured'), $this->requisicionPayload($user, ['proveedor_id' => '']))
            ->assertSessionHasErrors(['proveedor_id' => ActiveProveedor::MESSAGE]);

        $this->actingAs($user)
            ->post(route('requisiciones.store'), array_diff_key($this->requisicionPayload($user), ['proveedor_id' => true]))
            ->assertSessionHasErrors('proveedor_id');

        $this->assertSame(0, Requisicion::count());
    }

    public function test_un_borrador_historico_sin_proveedor_no_se_captura(): void
    {
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($user, ['status' => 'BORRADOR', 'proveedor_id' => null]);

        $this->actingAs($user)
            ->post(route('requisiciones.capturar', $req))
            ->assertSessionHasErrors(['proveedor_id' => ActiveProveedor::MESSAGE]);

        $this->assertSame('BORRADOR', $req->fresh()->status);
    }

    public function test_proveedor_inactivo_es_rechazado(): void
    {
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $inactivo = $this->makeProveedor($user, 'INACTIVO');

        $this->actingAs($user)
            ->post(route('requisiciones.storeCaptured'), $this->requisicionPayload($user, ['proveedor_id' => $inactivo->id]))
            ->assertSessionHasErrors(['proveedor_id' => ActiveProveedor::MESSAGE]);

        $this->assertSame(0, Requisicion::count());
    }

    public function test_paginacion_predeterminada_es_20(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        foreach (range(1, 25) as $i) {
            $this->makeRequisicion($admin);
        }

        $this->actingAs($admin)
            ->get(route('requisiciones.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Requisiciones/Index')
                ->where('filters.perPage', 20)
                ->where('pagination.default_per_page', 20)
                ->has('requisiciones.data', 20)
                ->where('requisiciones.meta.per_page', 20));

        // Un valor no permitido vuelve al predeterminado 20, no a 10.
        $this->actingAs($admin)
            ->get(route('requisiciones.index', ['perPage' => 7]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.perPage', 20));

        $this->actingAs($admin)
            ->get(route('requisiciones.index', ['perPage' => 'all']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.perPage', 'all')
                ->has('requisiciones.data', 25));
    }
}
