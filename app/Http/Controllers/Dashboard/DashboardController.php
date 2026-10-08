<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardDataService;
use App\Services\Dashboard\DashboardView;
use App\Support\Permissions\AccessScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dashboard con la vista elegida (?vista=personal|sucursal|corporativo|general).
     * Sin vista se muestra la más amplia disponible. Pedir una vista no
     * autorizada devuelve 403. Sin permiso de dashboard lleva al primer módulo.
     */
    public function index(Request $request, DashboardDataService $service): Response|RedirectResponse
    {
        $user = $request->user();
        $available = DashboardView::availableFor($user);

        if ($available === []) {
            return AccessScope::for($user, 'requisiciones')->allows()
                ? redirect()->route('requisiciones.index')
                : redirect()->route('profile.edit');
        }

        $requested = $request->query('vista');
        $view = $requested !== null ? DashboardView::tryFrom((string) $requested) : $available[0];
        abort_unless($view !== null && in_array($view, $available, true), 403);

        $data = $service->build($view, $user, $request->query());

        return Inertia::render('Dashboard/Index', [
            'dashboard' => $data + [
                'view' => $view->value,
                'views' => array_map(fn (DashboardView $v) => [
                    'value' => $v->value,
                    'label' => $v->label(),
                    'description' => $v->description(),
                ], $available),
                'canExport' => $user->can('reportes.dashboard'),
                'scopeLabel' => $view->description(),
            ],
        ]);
    }
}
