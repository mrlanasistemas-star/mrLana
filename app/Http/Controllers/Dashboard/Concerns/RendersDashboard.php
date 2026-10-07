<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use App\Services\Dashboard\DashboardDataService;
use App\Services\Dashboard\DashboardProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Los tres paneles comparten página y datos; solo cambia el alcance del perfil. */
trait RendersDashboard
{
    protected function renderDashboard(Request $request, DashboardProfile $profile): Response
    {
        $user = $request->user();
        abort_unless($profile->allowedFor($user), 403);

        $data = app(DashboardDataService::class)->build($profile, $user, $request->query());

        return Inertia::render('Dashboard/Index', [
            'dashboard' => $data + [
                'exportSegment' => $profile->exportSegment(),
                'canExport' => $user->can('reportes.dashboard'),
                'routeName' => $profile->routeName(),
                'scopeLabel' => $profile === DashboardProfile::Personal ? 'Solo tus requisiciones' : 'Todas las requisiciones',
            ],
        ]);
    }
}
