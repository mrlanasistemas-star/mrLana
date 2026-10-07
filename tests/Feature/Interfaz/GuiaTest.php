<?php

namespace Tests\Feature\Interfaz;

use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

class GuiaTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    private function contenido(): string
    {
        return (string) file_get_contents(resource_path('js/Pages/Ayuda/guiaContenido.ts'));
    }

    public function test_la_guia_exige_sesion(): void
    {
        $this->get(route('ayuda.guia'))->assertRedirect(route('login'));
    }

    public function test_la_guia_recibe_etiquetas_humanas_de_permisos(): void
    {
        $this->setUpCatalogos();
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($user)->get(route('ayuda.guia'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Ayuda/Guia')
                ->where('catalogo.pagos', fn ($labels) => $labels['pagos.autorizar'] === 'Autorizar pagos'));
    }

    public function test_cada_opcion_del_menu_tiene_su_tema_en_la_guia(): void
    {
        preg_match_all("/routeName: '([^']+)'/", (string) file_get_contents(resource_path('js/Layouts/navigation.ts')), $m);
        $this->assertNotEmpty($m[1]);

        $contenido = $this->contenido();
        foreach ($m[1] as $ruta) {
            $modulo = explode('.', $ruta)[0];
            $this->assertTrue(
                str_contains($contenido, "ruta: '{$ruta}'") || str_contains($contenido, "catalogoSimple('{$modulo}'"),
                "La guía no tiene un tema para «{$ruta}».",
            );
        }
    }

    public function test_temas_obligatorios_y_sin_terminos_antiguos(): void
    {
        $contenido = $this->contenido();
        foreach (['ajustes', 'eliminaciones', 'requisiciones-crear', 'perfil', 'app-escritorio', 'app-android', 'exportaciones'] as $id) {
            $this->assertStringContainsString("id: '{$id}'", $contenido);
        }

        // Solo se comparan los textos visibles (entre comillas simples), no las claves de permisos.
        preg_match_all("/(?:texto|titulo|resumen|paraQue|quien|nombre): '([^']*)'/u", $contenido, $textos);
        $visible = implode("\n", $textos[1]);
        $this->assertStringNotContainsString('Empleados', $visible);
        $this->assertDoesNotMatchRegularExpression('/\bpartidas?\b/iu', $visible);
        $this->assertDoesNotMatchRegularExpression('/\b[a-z_]+\.(ver|registrar|editar|autorizar|ver_todos)\b/', $visible);
    }

    public function test_el_pdf_de_la_guia_existe(): void
    {
        $pdf = public_path('ayuda/mr-lana-ayuda.pdf');
        $this->assertFileExists($pdf);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($pdf, false, null, 0, 4));
    }
}
