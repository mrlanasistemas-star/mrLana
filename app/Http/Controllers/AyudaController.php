<?php

namespace App\Http\Controllers;

use App\Support\Permissions\PermissionCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Centro de ayuda. El contenido y los recorridos se filtran en el navegador
 * con los permisos y alcances reales del usuario (compartidos por Inertia);
 * aquí solo se envían las etiquetas humanas de los permisos.
 */
class AyudaController extends Controller
{
    public function guia(Request $request): Response
    {
        return Inertia::render('Ayuda/Guia', [
            // Etiquetas humanas de permisos para el resumen «Tu acceso» de cada módulo.
            'catalogo' => collect(PermissionCatalog::modules())
                ->map(fn (array $m) => collect($m['permissions'])->map(fn (array $p) => $p['label'])),
        ]);
    }
}
