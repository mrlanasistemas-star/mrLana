<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Redirige al panel que corresponde a los permisos del usuario.
     * Sin permiso de dashboard, lleva al primer módulo disponible.
     */
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->can('dashboard.ver')) {
            return redirect()->route(DashboardProfile::forUser($user)->routeName());
        }

        if ($user->canAny(['requisiciones.ver_todos', 'requisiciones.ver_propios'])) {
            return redirect()->route('requisiciones.index');
        }

        return redirect()->route('profile.edit');
    }
}
