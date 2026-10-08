<?php

namespace Tests\Feature\Interfaz;

use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
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

    private function registro(): string
    {
        return (string) file_get_contents(resource_path('js/tour/registry.ts'));
    }

    public function test_la_guia_exige_sesion(): void
    {
        $this->get(route('ayuda.guia'))->assertRedirect(route('login'));
        $this->get(route('ayuda.index'))->assertRedirect(route('login'));
    }

    public function test_la_guia_recibe_etiquetas_humanas_de_permisos(): void
    {
        $this->setUpCatalogos();
        $user = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);

        $this->actingAs($user)->get(route('ayuda.guia'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Ayuda/Guia')
                ->where('catalogo.pagos', fn ($labels) => $labels['pagos.autorizar'] === 'Autorizar pagos')
                ->where('auth.scopes.requisiciones', 'own'));
    }

    public function test_la_ayuda_esta_en_la_navegacion_para_cualquier_cuenta(): void
    {
        $nav = (string) file_get_contents(resource_path('js/Layouts/navigation.ts'));

        $this->assertMatchesRegularExpression("/title: 'Soporte'[\s\S]*routeName: 'ayuda\.guia'/", $nav);
        // La opción de ayuda no exige permisos (sin anyOf ni views).
        $this->assertMatchesRegularExpression("/\{ key: 'ayuda', label: 'Ayuda', routeName: 'ayuda\.guia', activePattern: 'ayuda\.\*', icon: CircleHelp \}/", $nav);

        // Cualquier cuenta autenticada, incluso sin permisos, puede abrirla.
        $this->setUpCatalogos();
        $this->actingAs($this->userWith([]))->get(route('ayuda.guia'))->assertOk();
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
        foreach (['ajustes', 'eliminaciones', 'requisiciones-crear', 'perfil', 'app-escritorio', 'app-android', 'exportaciones', 'ayuda'] as $id) {
            $this->assertStringContainsString("id: '{$id}'", $contenido);
        }

        // Solo se comparan los textos visibles (entre comillas simples), no las claves de permisos.
        preg_match_all("/(?:texto|titulo|resumen|paraQue|quien|nombre): '([^']*)'/u", $contenido, $textos);
        $visible = implode("\n", $textos[1]);
        $this->assertStringNotContainsString('Empleados', $visible);
        $this->assertDoesNotMatchRegularExpression('/\bpartidas?\b/iu', $visible);
        $this->assertDoesNotMatchRegularExpression('/\b[a-z_]+\.(ver|registrar|editar|autorizar|ver_todos)\b/', $visible);
    }

    public function test_la_guia_no_usa_permisos_que_no_existen(): void
    {
        preg_match_all("/'([a-z_]+\.[a-z_]+)'/", $this->contenido().$this->registro(), $m);
        $validos = array_merge(PermissionCatalog::all(), ['ayuda.guia']);
        $rutas = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutesByName())->keys()->all();

        foreach (array_unique($m[1]) as $clave) {
            if (in_array($clave, $rutas, true) || str_ends_with($clave, '.*')) {
                continue;
            }
            $this->assertContains($clave, $validos, "Permiso inexistente en la guía o los recorridos: {$clave}");
        }
    }

    public function test_cada_modulo_navegable_tiene_recorrido_y_sus_anclas_data_tour_existen(): void
    {
        $registro = $this->registro();

        // Recorrido para cada módulo del menú (por id del recorrido).
        preg_match_all("/key: '([^']+)'/", (string) file_get_contents(resource_path('js/Layouts/navigation.ts')), $nav);
        foreach ($nav[1] as $key) {
            $this->assertMatchesRegularExpression("/(id: '{$key}'|listado\('{$key}')/", $registro, "Falta el recorrido de «{$key}».");
        }

        // Cada objetivo de los recorridos existe como data-tour en alguna vista.
        $vistas = collect(File::allFiles(resource_path('js')))
            ->filter(fn ($f) => in_array($f->getExtension(), ['vue', 'ts'], true))
            ->map(fn ($f) => $f->getContents())->implode("\n");

        preg_match_all("/target: '([^']+)'/", $registro, $t);
        $objetivos = array_unique($t[1]);
        // Los de listados genéricos: «<modulo>-lista».
        preg_match_all("/listado\('([^']+)'/", $registro, $l);
        foreach ($l[1] as $mod) {
            $objetivos[] = "{$mod}-lista";
        }

        $this->assertNotEmpty($objetivos);
        foreach ($objetivos as $objetivo) {
            $this->assertTrue(
                str_contains($vistas, "data-tour=\"{$objetivo}\""),
                "No existe ningún elemento con data-tour=\"{$objetivo}\".",
            );
        }
    }

    public function test_el_motor_de_recorridos_no_se_bloquea_cuando_falta_un_objetivo(): void
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node no está disponible para ejecutar las pruebas del motor.');
        }

        $process = new Process([$node, '--test', 'tests/js/tour-engine.test.ts'], base_path());
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "Pruebas del motor de recorridos:\n".$process->getOutput().$process->getErrorOutput());
        $this->assertStringContainsString('no se bloquea cuando falta un objetivo', $process->getOutput());
    }

    public function test_el_pdf_de_la_guia_existe(): void
    {
        $pdf = public_path('ayuda/mr-lana-ayuda.pdf');
        $this->assertFileExists($pdf);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($pdf, false, null, 0, 4));
    }
}
