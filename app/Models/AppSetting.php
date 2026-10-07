<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Configuración global del ERP (registro único).
 *
 * @property string|null $primary_color
 * @property string|null $accent_color
 * @property string|null $button_color
 * @property string|null $success_color
 * @property string|null $warning_color
 * @property string|null $danger_color
 * @property list<string>|null $chart_palette
 * @property string|null $mobile_app_url
 * @property string|null $logo_path
 */
class AppSetting extends Model
{
    // Versionada: cambiar la estructura de resolved() exige una llave nueva.
    private const CACHE_KEY = 'erp.app_settings.v2';

    public const DEFAULTS = [
        'primary_color' => '#18181B',
        'accent_color' => '#0F766E',
        'button_color' => '#18181B',
        'success_color' => '#16A34A',
        'warning_color' => '#D97706',
        'danger_color' => '#DC2626',
        'chart_palette' => ['#2563EB', '#0D9488', '#D97706', '#7C3AED', '#DB2777', '#0891B2'],
    ];

    public const COLOR_FIELDS = [
        'primary_color', 'accent_color', 'button_color', 'success_color', 'warning_color', 'danger_color',
    ];

    protected $fillable = [
        'primary_color', 'accent_color', 'button_color', 'success_color', 'warning_color', 'danger_color',
        'chart_palette', 'mobile_app_url', 'logo_path', 'updated_by',
    ];

    protected $casts = [
        'chart_palette' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Registro único (se crea vacío si no existe). */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }

    /**
     * Configuración efectiva con valores predeterminados aplicados, lista para
     * la interfaz. Se cachea; tolera que la tabla aún no exista (antes de migrar).
     *
     * @return array<string, mixed>
     */
    public static function resolved(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $row = Schema::hasTable('app_settings') ? self::query()->find(1) : null;

            $colors = [];
            $customized = [];
            foreach (self::COLOR_FIELDS as $field) {
                $colors[$field] = strtoupper($row?->{$field} ?: self::DEFAULTS[$field]);
                $customized[$field] = (bool) $row?->{$field};
            }

            $palette = $row?->chart_palette;
            if (! is_array($palette) || count($palette) === 0) {
                $palette = self::DEFAULTS['chart_palette'];
            }

            $mobileUrl = $row?->mobile_app_url ?: config('erp.mobile_app_url');

            return [
                'colors' => $colors,
                // Solo los colores personalizados sobrescriben el tema base (claro/oscuro).
                'customized' => $customized,
                'chart_palette' => array_values(array_map('strtoupper', $palette)),
                'mobile_app_url' => $mobileUrl ?: null,
                'logo_url' => $row?->logo_path ? Storage::disk('public')->url($row->logo_path) : null,
            ];
        });
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
