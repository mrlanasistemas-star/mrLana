<?php

namespace App\Support\Pdf;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;

/**
 * Recursos embebidos para PDF. Se usan data URIs porque Chrome headless no
 * puede leer archivos locales desde un documento generado en memoria.
 */
final class PdfAssets
{
    public static function dataUri(?string $absolutePath): ?string
    {
        if (! $absolutePath || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolutePath));
    }

    /** Logo general configurado o, en su defecto, el logo del sistema. */
    public static function logo(): ?string
    {
        $row = AppSetting::query()->find(1);
        if ($row?->logo_path && Storage::disk('public')->exists($row->logo_path)) {
            return self::dataUri(Storage::disk('public')->path($row->logo_path));
        }

        return self::dataUri(resource_path('js/img/logo-mr-lana.png'));
    }

    /** Logo de un corporativo (logo_path relativo a public/ o al disco public). */
    public static function corporativoLogo(?string $logoPath): ?string
    {
        if (! $logoPath) {
            return null;
        }

        $relative = ltrim(preg_replace('#^/?storage/#', '', $logoPath), '/');

        return self::dataUri(Storage::disk('public')->path($relative))
            ?? self::dataUri(public_path(ltrim($logoPath, '/')));
    }

    /** Colores de la marca para estilos de PDF. */
    public static function colors(): array
    {
        return AppSetting::resolved()['colors'];
    }
}
