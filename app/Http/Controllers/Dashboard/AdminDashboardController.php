<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\RendersDashboard;
use App\Services\Dashboard\DashboardProfile;
use Illuminate\Http\Request;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    use RendersDashboard;

    public function index(Request $request): Response
    {
        return $this->renderDashboard($request, DashboardProfile::Ejecutivo);
    }
}
