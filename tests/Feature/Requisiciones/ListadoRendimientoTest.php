<?php

namespace Tests\Feature\Requisiciones;

use App\Http\Controllers\RequisicionController;
use App\Models\Requisicion;
use App\Support\BusinessDate;
use App\Support\Permissions\AccessScope;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/** Listado de requisiciones: conteos con filtros, tope de "Todas" y sin N+1. */
class ListadoRendimientoTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    public function test_conteos_respetan_los_filtros_salvo_el_estatus(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $colab = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->makeRequisicion($colab, ['status' => 'PAGADA']);
        $this->makeRequisicion($colab, ['status' => 'ELIMINADA']);
        $this->makeRequisicion($admin, ['status' => 'PAGADA']);
        $this->makeRequisicion($admin, ['status' => 'PAGADA']);

        $this->actingAs($admin)
            ->get(route('requisiciones.index', ['solicitante_id' => $colab->empleado_id, 'status' => 'PAGADA']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('requisiciones.data', 1)
                ->where('conteos.PAGADA', 1)
                // La clave ELIMINADA se mantiene para la pestaña Eliminadas.
                ->where('conteos.ELIMINADA', 1));

        $this->actingAs($admin)
            ->get(route('requisiciones.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('conteos.PAGADA', 3)
                ->where('conteos.ELIMINADA', 1));
    }

    public function test_todas_llega_en_bloques_si_supera_el_tope(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $base = $this->makeRequisicion($admin)->only([
            'solicitante_id', 'sucursal_id', 'comprador_corp_id', 'proveedor_id', 'concepto_id', 'creada_por_user_id',
        ]);

        $rows = [];
        foreach (range(1, RequisicionController::ALL_LIMIT) as $i) {
            $rows[] = $base + [
                'folio' => 'REQ-T'.$i,
                'status' => 'CAPTURADA',
                'monto_subtotal' => 1, 'monto_total' => 1,
                'fecha_solicitud' => BusinessDate::today(),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            Requisicion::query()->insert($chunk);
        }

        $this->actingAs($admin)
            ->get(route('requisiciones.index', ['perPage' => 'all']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.perPage', 'all')
                ->where('pagination.all_limit', RequisicionController::ALL_LIMIT)
                ->where('pagination.all_truncated', true)
                ->has('requisiciones.data', RequisicionController::ALL_LIMIT)
                ->where('requisiciones.meta.total', RequisicionController::ALL_LIMIT + 1)
                ->where('requisiciones.meta.last_page', 2));
    }

    public function test_el_numero_de_consultas_no_crece_con_las_filas(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $colab = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $contar = function () use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get(route('requisiciones.index', ['perPage' => 'all']))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        foreach (['CAPTURADA', 'BORRADOR', 'PAGADA'] as $status) {
            $this->makeRequisicion($colab, ['status' => $status]);
        }
        $contar(); // calienta cachés (permisos de Spatie, etc.)
        $pocas = $contar();

        foreach (range(1, 4) as $i) {
            foreach (['CAPTURADA', 'BORRADOR', 'PAGADA', 'POR_COMPROBAR'] as $status) {
                $this->makeRequisicion($colab, ['status' => $status]);
            }
        }
        $muchas = $contar();

        $this->assertSame($pocas, $muchas, "El listado hace {$pocas} consultas con 3 filas y {$muchas} con 19: hay consultas por fila.");
    }

    public function test_la_memoizacion_no_sobrevive_al_bloque(): void
    {
        $user = $this->makeUser(PermissionCatalog::ROLE_ADMIN);

        AccessScope::memoize(function () use ($user) {
            $this->assertTrue($user->can('requisiciones.imprimir'));
            $this->assertSame('global', AccessScope::for($user, 'requisiciones')->key());
        });

        $user->syncRoles([]);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->assertFalse($user->can('requisiciones.imprimir'));
        $this->assertSame('none', AccessScope::for($user, 'requisiciones')->key());
    }
}
