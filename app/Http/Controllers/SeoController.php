<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Response;

/**
 * robots.txt y sitemap.xml generados con APP_URL, para que apunten siempre
 * al dominio en el que corre el ERP.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = [
            '# ERP Mr. Lana: solo la pantalla de acceso es pública; el resto requiere sesión.',
            'User-agent: *',
            'Allow: /$',
            'Allow: /login',
            'Allow: /og-image.png',
            'Allow: /icons/',
            'Allow: /build/',
            'Disallow: /dashboard',
            'Disallow: /requisicione',
            'Disallow: /requisiciones',
            'Disallow: /comprobantes',
            'Disallow: /pagos',
            'Disallow: /folios',
            'Disallow: /plantillas',
            'Disallow: /proveedores',
            'Disallow: /conceptos',
            'Disallow: /corporativos',
            'Disallow: /sucursales',
            'Disallow: /areas',
            'Disallow: /colaboradores',
            'Disallow: /usuarios',
            'Disallow: /roles',
            'Disallow: /configuracion',
            'Disallow: /system-logs',
            'Disallow: /notificaciones',
            'Disallow: /profile',
            'Disallow: /ayuda',
            'Disallow: /exports/',
            'Disallow: /storage/',
            'Disallow: /api/',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /verify-email',
            'Disallow: /confirm-password',
            '',
            'Sitemap: '.route('seo.sitemap'),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $urls = collect(Seo::publicUrls())
            ->map(fn (string $url) => '    <url><loc>'.e($url).'</loc><changefreq>monthly</changefreq><priority>1.0</priority></url>')
            ->implode("\n");

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls."\n"
            .'</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
