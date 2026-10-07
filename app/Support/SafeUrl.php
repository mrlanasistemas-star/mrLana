<?php

namespace App\Support;

/**
 * Normaliza URLs de notificaciones para que solo apunten a la propia
 * aplicación. Devuelve una ruta relativa ("/requisicione/5") o null.
 */
final class SafeUrl
{
    public static function internal(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        // Ruta relativa a la app: "/algo", pero no "//host" ni "/\host".
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_starts_with($url, '/\\')) {
            return self::stripControlChars($url);
        }

        $parts = parse_url($url);
        $appParts = parse_url((string) config('app.url'));

        if (! is_array($parts) || ! isset($parts['host'], $parts['scheme'])) {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        if (strtolower($parts['host']) !== strtolower((string) ($appParts['host'] ?? ''))) {
            return null;
        }

        $path = ($parts['path'] ?? '/') ?: '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return self::internal($path.$query.$fragment);
    }

    private static function stripControlChars(string $value): ?string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        return $clean === '' ? null : $clean;
    }
}
