<?php

namespace Tests\Feature\Interfaz;

use App\Models\Ajuste;
use App\Models\Requisicion;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/**
 * El reporte de requisiciones lista cada item pedido, los ajustes
 * (devoluciones, faltantes…) y el total efectuado.
 */
class ReporteRequisicionesItemsTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    public function test_excel_lista_items_ajustes_y_total_efectuado(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $req = $this->makeRequisicion($this->makeUser(PermissionCatalog::ROLE_COLABORADOR), ['folio' => 'REQ-PC-001']);
        $req->detalles()->delete();
        $req->detalles()->createMany([
            ['cantidad' => 1, 'descripcion' => 'Gabinete ATX', 'precio_unitario' => 1000, 'subtotal' => 1000, 'iva' => 160, 'total' => 1160, 'genera_iva' => true],
            ['cantidad' => 2, 'descripcion' => 'Memoria RAM 16 GB', 'precio_unitario' => 500, 'subtotal' => 1000, 'iva' => 160, 'total' => 1160, 'genera_iva' => true],
        ]);
        Requisicion::query()->whereKey($req->id)->update(['monto_total' => 2120, 'status' => 'PAGADA']);
        Ajuste::create([
            'requisicion_id' => $req->id, 'tipo' => 'DEVOLUCION', 'sentido' => 'A_FAVOR_EMPRESA', 'monto' => 200,
            'monto_anterior' => 2320, 'monto_nuevo' => 2120, 'estatus' => Ajuste::ESTATUS_APLICADO,
            'motivo' => 'Devolución de cable', 'fecha_registro' => now(), 'user_registro_id' => $admin->id,
        ]);
        Ajuste::create([
            'requisicion_id' => $req->id, 'tipo' => 'FALTANTE', 'sentido' => 'A_FAVOR_SOLICITANTE', 'monto' => 50,
            'monto_anterior' => 2120, 'monto_nuevo' => 2170, 'estatus' => Ajuste::ESTATUS_PENDIENTE,
            'motivo' => 'Envío', 'fecha_registro' => now(), 'user_registro_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('requisiciones.export.excel'));
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        copy($response->baseResponse->getFile()->getPathname(), $path);
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false);
        @unlink($path);

        $flat = collect($rows)->filter(fn ($r) => ($r[0] ?? null) === 'REQ-PC-001')->values();
        $tipos = $flat->pluck(2)->all();
        $this->assertSame(['Item', 'Item', 'Ajuste aplicado', 'Ajuste no aplicado', 'Total efectuado'], $tipos);
        $this->assertSame('Gabinete ATX', $flat[0][12]);
        $this->assertSame('Memoria RAM 16 GB', $flat[1][12]);
        $this->assertEquals(2, $flat[1][13]);
        $this->assertStringContainsString('Devolución', $flat[2][12]);
        $this->assertEquals(-200, $flat[2][18]);
        $this->assertEquals(2120, $flat[4][18]);

        // Items + ajustes aplicados = total efectuado.
        $suma = $flat->filter(fn ($r) => in_array($r[2], ['Item', 'Ajuste aplicado'], true))->sum(fn ($r) => (float) $r[18]);
        $this->assertEqualsWithDelta(2120, $suma, 0.001);
    }

    public function test_pdf_se_genera_con_items_y_ajustes(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $this->makeRequisicion($this->makeUser(PermissionCatalog::ROLE_COLABORADOR));

        $html = view('exports.requisiciones.index', [
            'rows' => [[
                'folio' => 'REQ-X', 'estatus' => 'PAGADA', 'fecha_captura' => null, 'fecha_solicitud' => '2026-10-01',
                'fecha_pago_esperada' => null, 'fecha_autorizacion' => null, 'fecha_pago' => null, 'corporativo' => 'Corp',
                'sucursal' => 'Centro', 'sucursal_codigo' => null, 'solicitante' => 'Ana', 'proveedor' => 'Prov', 'proveedor_rfc' => null,
                'concepto' => 'Cómputo', 'observaciones' => null,
                'items' => [['n' => 1, 'item' => 'Gabinete ATX', 'cantidad' => 1, 'precio_unitario' => 1000, 'genera_iva' => true, 'subtotal' => 1000, 'iva' => 160, 'total' => 1160]],
                'ajustes' => [['tipo' => 'Devolución', 'motivo' => 'Cable', 'estatus' => 'Aplicado', 'aplicado' => true, 'fecha' => '2026-10-02', 'monto' => -160]],
                'subtotal' => 1000, 'total_items' => 1160, 'ajustes_aplicados' => -160, 'otras_diferencias' => 0,
                'total_efectuado' => 1000, 'pagado' => 1000, 'comprobado' => 0,
            ]],
            'filters' => [], 'meta' => [],
        ])->render();

        $this->assertStringContainsString('Gabinete ATX', $html);
        $this->assertStringContainsString('Devolución', $html);
        $this->assertStringContainsString('Total efectuado', $html);
        $this->assertStringNotContainsString('Partida', $html);

        $this->actingAs($admin)->get(route('requisiciones.export.pdf'))->assertOk();
    }
}
