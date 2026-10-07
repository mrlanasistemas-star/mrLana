<?php

namespace Tests\Feature\Interfaz;

use App\Models\Comprobante;
use App\Models\Pago;
use App\Models\Requisicion;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

class ComprobantesPagosModulosTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
        Storage::fake('public');
    }

    private function comprobante(Requisicion $req, User $by, array $attrs = []): Comprobante
    {
        $path = UploadedFile::fake()->image('ticket.jpg')->store("requisiciones/{$req->id}/comprobantes", 'public');

        return Comprobante::create($attrs + [
            'requisicion_id' => $req->id, 'tipo_doc' => 'TICKET', 'monto' => 250, 'archivo_path' => $path,
            'archivo_original' => 'ticket.jpg', 'estatus' => 'PENDIENTE', 'user_carga_id' => $by->id,
        ]);
    }

    private function pago(Requisicion $req, User $by, array $attrs = []): Pago
    {
        $path = UploadedFile::fake()->create('spei.pdf', 20, 'application/pdf')->store("requisiciones/{$req->id}/pagos", 'public');

        return Pago::create($attrs + [
            'requisicion_id' => $req->id, 'beneficiario_nombre' => 'Proveedor SA', 'tipo_pago' => 'TRANSFERENCIA',
            'monto' => 1160, 'fecha_pago' => '2026-10-01', 'archivo_path' => $path, 'archivo_original' => 'spei.pdf', 'user_carga_id' => $by->id,
        ]);
    }

    public function test_comprobantes_filtra_y_respeta_alcance(): void
    {
        $conta = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $ana = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $beto = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $c1 = $this->comprobante($this->makeRequisicion($ana), $ana);
        $this->comprobante($this->makeRequisicion($beto), $beto, ['estatus' => 'APROBADO', 'user_revision_id' => $conta->id, 'tipo_doc' => 'FACTURA']);

        $this->actingAs($conta)->get(route('comprobantes.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Comprobantes/Index', true)->has('comprobantes.data', 2)->where('kpis.aprobados', 1));
        $this->actingAs($conta)->get(route('comprobantes.index', ['user_revision_id' => $conta->id, 'tipo_doc' => 'FACTURA']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('comprobantes.data', 1)->where('comprobantes.data.0.estatus', 'APROBADO'));

        // La colaboradora solo ve los de sus requisiciones y no puede abrir archivos ajenos.
        $this->actingAs($ana)->get(route('comprobantes.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('comprobantes.data', 1)->where('comprobantes.data.0.id', $c1->id)->where('can.exportar', true));
        $this->actingAs($ana)->get(route('comprobantes.archivo', $c1))->assertOk();
        $ajeno = Comprobante::where('id', '!=', $c1->id)->first();
        $this->actingAs($ana)->get(route('comprobantes.archivo', $ajeno))->assertForbidden();
        // Puede exportar, pero el reporte solo incluye sus comprobantes (mismo alcance que la pantalla).
        $this->actingAs($ana)->get(route('comprobantes.export.excel'))->assertOk();
    }

    public function test_pagos_filtra_por_quien_autorizo_y_registro(): void
    {
        $conta = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $otro = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $ana = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($ana, ['status' => 'PAGADA']);
        $req->forceFill(['pago_autorizado_por_id' => $conta->id])->save();
        $this->pago($req, $otro);
        $this->pago($this->makeRequisicion($ana, ['status' => 'PAGADA']), $conta, ['tipo_pago' => 'EFECTIVO']);

        $this->actingAs($conta)->get(route('pagos.index', ['autorizo_id' => $conta->id]))
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Pagos/Index', true)->has('pagos.data', 1)
                ->where('pagos.data.0.requisicion.autorizo', $conta->name)
                ->where('pagos.data.0.user_carga', $otro->name)
                ->where('pagos.data.0.kind', 'pdf'));
        $this->actingAs($conta)->get(route('pagos.index', ['tipo_pago' => 'EFECTIVO']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('pagos.data', 1)->where('kpis.otros', 1160));
    }

    public function test_exportaciones_de_comprobantes_y_pagos(): void
    {
        $conta = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $ana = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($ana, ['status' => 'POR_COMPROBAR']);
        $this->comprobante($req, $ana);
        $this->pago($req, $conta);

        foreach (['comprobantes', 'pagos'] as $m) {
            $pdf = $this->actingAs($conta)->get(route("{$m}.export.pdf"));
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());

            $xlsx = $this->actingAs($conta)->get(route("{$m}.export.excel"));
            $xlsx->assertOk();
            $this->assertStringStartsWith('PK', (string) file_get_contents($xlsx->baseResponse->getFile()->getPathname()));
        }
    }
}
