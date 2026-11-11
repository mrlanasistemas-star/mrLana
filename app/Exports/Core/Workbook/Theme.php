<?php

namespace App\Exports\Core\Workbook;

use App\Models\AppSetting;

/**
 * Colores y formatos compartidos por los libros de Excel con resumen.
 * El color principal y la paleta salen de la configuración de la empresa;
 * si no se pueden leer (p. ej. sin base de datos) se usan los de respaldo.
 */
final class Theme
{
    public const MONEY = '"$"#,##0.00;-"$"#,##0.00';

    public const MONEY_KPI = '"$"#,##0;-"$"#,##0';

    public const INT = '#,##0';

    public const PCT = '0.0%';

    public const DATE = 'dd/mm/yyyy';

    public const DATETIME = 'dd/mm/yyyy hh:mm';

    /** Estatus de requisición (mismos colores que la aplicación). */
    public const REQUISICION_STATUS = [
        'BORRADOR' => '94A3B8',
        'CAPTURADA' => '0EA5E9',
        'PAGO_AUTORIZADO' => 'F59E0B',
        'PAGO_RECHAZADO' => 'F43F5E',
        'PAGADA' => '10B981',
        'POR_COMPROBAR' => '8B5CF6',
        'COMPROBACION_ACEPTADA' => '14B8A6',
        'COMPROBACION_RECHAZADA' => 'D946EF',
        'ELIMINADA' => '71717A',
    ];

    /** Activo / baja en catálogos. */
    public const ACTIVE = '10B981';

    public const INACTIVE = '94A3B8';

    public const PENDING = 'F59E0B';

    public const REJECTED = 'F43F5E';

    private const FALLBACK_PALETTE = ['6419FF', '0EA5E9', '10B981', 'F59E0B', 'F43F5E', '8B5CF6', '14B8A6', 'D946EF'];

    public static function primary(): string
    {
        return self::resolve()['primary'];
    }

    /** @return list<string> Colores HEX sin '#'. */
    public static function palette(): array
    {
        return self::resolve()['palette'];
    }

    public static function hex(?string $color, string $fallback = 'A1A1AA'): string
    {
        $c = strtoupper(ltrim((string) $color, '#'));

        return preg_match('/^[0-9A-F]{6}$/', $c) ? $c : $fallback;
    }

    /** @return array{primary: string, palette: list<string>} */
    private static function resolve(): array
    {
        $primary = '6419FF';
        $palette = self::FALLBACK_PALETTE;
        try {
            $settings = AppSetting::resolved();
            $primary = self::hex($settings['colors']['primary_color'] ?? null, $primary);
            $custom = array_values(array_filter(array_map(fn ($c) => self::hex($c, ''), $settings['chart_palette'] ?? [])));
            if (count($custom) >= 3) {
                $palette = $custom;
            }
        } catch (\Throwable) {
            // Sin configuración disponible: colores de respaldo.
        }

        return ['primary' => $primary, 'palette' => $palette];
    }
}
