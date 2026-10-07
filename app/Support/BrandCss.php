<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Variables CSS de la marca para el primer pintado (app.blade.php), sin
 * esperar a JavaScript. Misma lógica que resources/js/Utils/color.ts.
 */
final class BrandCss
{
    private const LIGHT_BG = '#FFFFFF';

    private const DARK_BG = '#09090B';

    private const NAMES = [
        'primary_color' => 'brand-primary',
        'accent_color' => 'brand-accent',
        'button_color' => 'brand-button',
        'success_color' => 'brand-success',
        'warning_color' => 'brand-warning',
        'danger_color' => 'brand-danger',
    ];

    public static function render(): string
    {
        $settings = AppSetting::resolved();
        $vars = [];

        foreach (self::NAMES as $key => $name) {
            $hex = $settings['colors'][$key] ?? null;
            if (! ($settings['customized'][$key] ?? false) || ! $hex || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
                continue;
            }
            $light = self::ensureContrast($hex, self::LIGHT_BG);
            $dark = self::ensureContrast($hex, self::DARK_BG);
            $vars[] = "--{$name}-light: ".self::triplet($light);
            $vars[] = "--{$name}-light-fg: ".self::triplet(self::readableOn($light));
            $vars[] = "--{$name}-dark: ".self::triplet($dark);
            $vars[] = "--{$name}-dark-fg: ".self::triplet(self::readableOn($dark));
        }

        foreach (array_values($settings['chart_palette']) as $i => $hex) {
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
                $vars[] = '--chart-palette-'.($i + 1).": {$hex}";
            }
        }

        // html:root gana a los valores por defecto de app.css aunque se cargue antes.
        return 'html:root{'.implode(';', $vars).'}';
    }

    /** @return array{int,int,int} */
    private static function rgb(string $hex): array
    {
        $n = hexdec(substr($hex, 1));

        return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
    }

    private static function triplet(string $hex): string
    {
        return implode(' ', self::rgb($hex));
    }

    private static function luminance(string $hex): float
    {
        $c = array_map(function ($v) {
            $s = $v / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private static function contrast(string $a, string $b): float
    {
        $l = [self::luminance($a), self::luminance($b)];
        rsort($l);

        return ($l[0] + 0.05) / ($l[1] + 0.05);
    }

    private static function readableOn(string $hex): string
    {
        return self::contrast($hex, '#FFFFFF') >= self::contrast($hex, self::DARK_BG) ? '#FFFFFF' : self::DARK_BG;
    }

    private static function ensureContrast(string $hex, string $background, float $min = 3.0): string
    {
        if (self::contrast($hex, $background) >= $min) {
            return strtoupper($hex);
        }

        $rgb = self::rgb($hex);
        $target = self::luminance($background) < 0.5 ? 255 : 0;

        for ($step = 1; $step <= 20; $step++) {
            $t = $step / 20;
            $mixed = sprintf('#%02X%02X%02X', ...array_map(fn ($c) => (int) round($c + ($target - $c) * $t), $rgb));
            if (self::contrast($mixed, $background) >= $min) {
                return $mixed;
            }
        }

        return $target === 255 ? '#FFFFFF' : self::DARK_BG;
    }
}
