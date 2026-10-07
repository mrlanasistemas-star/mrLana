<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de una cuenta que fue desactivada mientras estaba conectada.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->activo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                abort(403, 'Tu cuenta está desactivada.');
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta está desactivada. Contacta a un administrador.',
            ]);
        }

        return $next($request);
    }
}
