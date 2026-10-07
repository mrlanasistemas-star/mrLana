<?php

namespace Tests\Feature\Interfaz;

use App\Models\Area;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/**
 * Descarga real de cada exportación (PDF con DomPDF en pruebas y Excel):
 * no basta con que compile, el archivo debe generarse y ser válido.
 */
class ExportacionesTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    public function test_todas_las_exportaciones_descargan_archivos_validos(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        Area::create(['corporativo_id' => $this->corporativo->id, 'nombre' => 'Compras', 'activo' => true]);
        $req = $this->makeRequisicion($colaborador, [
            'observaciones' => str_repeat('Texto largo sin espacios_', 40),
        ]);

        $modulos = ['corporativos', 'sucursales', 'areas', 'conceptos', 'proveedores', 'colaboradores', 'requisiciones'];
        foreach ($modulos as $m) {
            $this->assertPdf($this->actingAs($admin)->get(route("{$m}.export.pdf")), "{$m} PDF");
            $this->assertXlsx($this->actingAs($admin)->get(route("{$m}.export.excel")), "{$m} Excel");
        }

        foreach (['admin', 'contador', 'colaborador'] as $perfil) {
            $this->assertPdf($this->actingAs($admin)->get(route('dashboard.export.pdf', $perfil)), "Dashboard {$perfil} PDF");
            $this->assertXlsx($this->actingAs($admin)->get(route('dashboard.export.excel', $perfil)), "Dashboard {$perfil} Excel");
        }

        $this->assertPdf($this->actingAs($colaborador)->get(route('requisiciones.print', $req)), 'Requisición individual');
    }

    public function test_exportar_sin_permiso_responde_403(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        foreach (['corporativos', 'sucursales', 'areas', 'conceptos', 'colaboradores'] as $m) {
            $this->actingAs($colaborador)->get(route("{$m}.export.pdf"))->assertForbidden();
            $this->actingAs($colaborador)->get(route("{$m}.export.excel"))->assertForbidden();
        }
    }

    private function assertPdf(TestResponse $response, string $label): void
    {
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'), $label);
        $this->assertStringStartsWith('%PDF-', $this->body($response), "{$label}: no es un PDF válido");
    }

    private function assertXlsx(TestResponse $response, string $label): void
    {
        $response->assertOk();
        // Un .xlsx es un ZIP: empieza con la firma "PK".
        $this->assertStringStartsWith('PK', $this->body($response), "{$label}: no es un Excel válido");
    }

    private function body(TestResponse $response): string
    {
        $base = $response->baseResponse;

        return $base instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            ? (string) file_get_contents($base->getFile()->getPathname())
            : (string) $response->getContent();
    }
}
