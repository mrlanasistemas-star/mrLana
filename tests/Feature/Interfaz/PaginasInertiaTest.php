<?php

namespace Tests\Feature\Interfaz;

use App\Models\Role;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/**
 * Evita rutas Inertia que apunten a componentes Vue inexistentes
 * (la pantalla quedaría en blanco en producción).
 */
class PaginasInertiaTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    public function test_todo_componente_renderizado_existe_en_resources_js_pages(): void
    {
        $missing = [];
        foreach (File::allFiles(app_path()) as $file) {
            preg_match_all("/(?:Inertia::render|inertia)\(\s*'([^']+)'/", $file->getContents(), $m);
            foreach ($m[1] as $component) {
                if (! File::exists(resource_path("js/Pages/{$component}.vue"))) {
                    $missing[] = "{$component} ({$file->getRelativePathname()})";
                }
            }
        }

        $this->assertSame([], $missing, 'Componentes Inertia inexistentes: '.implode(', ', $missing));
    }

    public function test_administrador_abre_las_pantallas_de_administracion(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);
        $role = Role::where('name', PermissionCatalog::ROLE_CONTABILIDAD)->firstOrFail();

        $pages = [
            [route('usuarios.index'), 'Usuarios/Index'],
            [route('usuarios.create'), 'Usuarios/Form'],
            [route('usuarios.edit', $admin), 'Usuarios/Form'],
            [route('roles.index'), 'Roles/Index'],
            [route('roles.create'), 'Roles/Form'],
            [route('roles.edit', $role), 'Roles/Form'],
            [route('notificaciones.index'), 'Notificaciones/Index'],
            [route('configuracion.edit'), 'Configuracion/Edit'],
        ];

        foreach ($pages as [$url, $component]) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component($component, true));
        }
    }

    public function test_formulario_de_rol_solo_expone_etiquetas_humanas_agrupadas(): void
    {
        $admin = $this->makeUser(PermissionCatalog::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('roles.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('modules', PermissionCatalog::forUi())
                ->where('modules.0.scope.0.label', 'Ver mi dashboard de gastos')
                ->where('modules.0.scope.3.label', 'Ver dashboard general'));
    }
}
