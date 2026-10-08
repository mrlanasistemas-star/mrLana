<?php

namespace App\Support;

/**
 * Tipo de vista previa de un archivo según su extensión. Las URLs protegidas
 * (p. ej. /comprobantes/5/archivo) no llevan extensión, así que la interfaz
 * no puede deducirlo de la URL.
 */
final class FileKind
{
    public const IMAGES = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

    /** @return 'image'|'pdf'|'file'|'none' */
    public static function of(?string $name): string
    {
        if (! $name) {
            return 'none';
        }

        $ext = self::ext($name);

        return in_array($ext, self::IMAGES, true) ? 'image' : ($ext === 'pdf' ? 'pdf' : 'file');
    }

    public static function ext(?string $name): string
    {
        return strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
    }
}
