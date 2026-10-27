<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Datos de posicionamiento en buscadores (SEO) del ERP.
 *
 * Solo la pantalla de acceso es pública e indexable: el resto requiere sesión
 * y se marca como noindex (meta robots + cabecera X-Robots-Tag).
 */
class Seo
{
    public const NAME = 'ERP Mr. Lana';

    public const ALTERNATE_NAMES = ['Mr Lana ERP', 'MR LANA', 'mrLana', 'MR-Lana ERP'];

    public const DESCRIPTION = 'ERP Mr. Lana: sistema interno de control de gastos para requisiciones, autorización de pagos, comprobaciones, proveedores y reportes, desde la web, escritorio o Android.';

    /** Rutas públicas que los buscadores pueden indexar. */
    public const INDEXABLE_ROUTES = ['login'];

    public static function indexable(?Request $request = null): bool
    {
        $request ??= request();

        return $request->user() === null && $request->routeIs(...self::INDEXABLE_ROUTES);
    }

    /** URLs públicas para el sitemap. */
    public static function publicUrls(): array
    {
        return array_map(fn (string $name) => route($name), self::INDEXABLE_ROUTES);
    }

    /** JSON-LD (schema.org) de la organización, el sitio y la aplicación. */
    public static function jsonLd(): array
    {
        $home = url('/');
        $logo = url('icons/icon-512.png');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $home.'#organization',
                    'name' => self::NAME,
                    'alternateName' => self::ALTERNATE_NAMES,
                    'url' => $home,
                    'logo' => $logo,
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $home.'#website',
                    'name' => self::NAME,
                    'alternateName' => self::ALTERNATE_NAMES,
                    'url' => $home,
                    'inLanguage' => 'es-MX',
                    'publisher' => ['@id' => $home.'#organization'],
                ],
                [
                    '@type' => 'SoftwareApplication',
                    'name' => self::NAME,
                    'alternateName' => self::ALTERNATE_NAMES,
                    'url' => route('login'),
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web, Windows, Android',
                    'inLanguage' => 'es-MX',
                    'description' => self::DESCRIPTION,
                    'image' => url('og-image.png'),
                    'publisher' => ['@id' => $home.'#organization'],
                ],
            ],
        ];
    }
}
