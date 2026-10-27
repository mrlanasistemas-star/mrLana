<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pide a los buscadores no indexar nada salvo las rutas públicas de acceso,
 * robots.txt y el sitemap (todo lo demás requiere sesión).
 */
class AddRobotsHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Seo::indexable($request) && ! $request->routeIs('seo.*') && ! $response->headers->has('X-Robots-Tag')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
