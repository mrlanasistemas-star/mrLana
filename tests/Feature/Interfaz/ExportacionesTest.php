<?php

namespace Tests\Feature\Interfaz;

use App\Models\Area;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

    /** @return array<string, array{string, array<string, string>, string}> */
    public static function exportaciones(): array
    {
        $cases = [];
        foreach (['corporativos', 'sucursales', 'areas', 'conceptos', 'proveedores', 'colaboradores', 'requisiciones'] as $m) {
            $cases["{$m} pdf"] = ["{$m}.export.pdf", [], 'pdf'];
            $cases["{$m} excel"] = ["{$m}.export.excel", [], 'xlsx'];
        }
        foreach (['personal', 'sucursal', 'corporativo', 'general'] as $vista) {
            $cases["dashboard {$vista} pdf"] = ['dashboard.export.pdf', ['vista' => $vista], 'pdf'];
            $cases["dashboard {$vista} excel"] = ['dashboard.export.excel', ['vista' => $vista], 'xlsx'];
        }

        return $cases;
    }

    #[DataProvider('exportaciones')]
    public function test_la_exportacion_descarga_un_archivo_valido(string $route, array $params, string $type): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        Area::create(['corporativo_id' => $this->corporativo->id, 'nombre' => 'Compras', 'activo' => true]);
        $this->makeRequisicion($this->makeUser(PermissionCatalog::ROLE_COLABORADOR), [
            'observaciones' => str_repeat('Texto largo sin espacios_', 40),
        ]);

        $response = $this->actingAs($admin)->get(route($route, $params));

        $type === 'pdf' ? $this->assertPdf($response) : $this->assertXlsx($response);
    }

    public function test_pdf_de_una_requisicion_individual(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $req = $this->makeRequisicion($colaborador);

        $this->assertPdf($this->actingAs($colaborador)->get(route('requisiciones.print', $req)));
    }

    public function test_exportar_sin_permiso_responde_403(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        foreach (['corporativos', 'sucursales', 'areas', 'conceptos', 'colaboradores'] as $m) {
            $this->actingAs($colaborador)->get(route("{$m}.export.pdf"))->assertForbidden();
            $this->actingAs($colaborador)->get(route("{$m}.export.excel"))->assertForbidden();
        }
    }

    private function assertPdf(TestResponse $response): void
    {
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $this->body($response), 'No es un PDF válido');
    }

    private function assertXlsx(TestResponse $response): void
    {
        $response->assertOk();
        // Un .xlsx es un ZIP: empieza con la firma "PK".
        $this->assertStringStartsWith('PK', $this->body($response), 'No es un Excel válido');
    }

    private function body(TestResponse $response): string
    {
        $base = $response->baseResponse;

        return $base instanceof BinaryFileResponse
            ? (string) file_get_contents($base->getFile()->getPathname())
            : (string) $response->getContent();
    }
}
