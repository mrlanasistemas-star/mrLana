import { watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { AppSettings, SharedProps } from '@/types/shared'
import { DARK_BG, LIGHT_BG, ensureContrast, readableOn, rgbTriplet } from '@/Utils/color'

export const BRAND_VARS: Array<[keyof AppSettings['colors'], string]> = [
    ['primary_color', 'brand-primary'],
    ['accent_color', 'brand-accent'],
    ['button_color', 'brand-button'],
    ['success_color', 'brand-success'],
    ['warning_color', 'brand-warning'],
    ['danger_color', 'brand-danger'],
]

/**
 * Aplica los colores configurados como variables CSS globales.
 * Por cada color define variante clara y oscura (ajustada para contraste)
 * y su color de texto legible: --brand-x-light, --brand-x-dark, --brand-x-light-fg...
 * app.css elige la variante según el tema. También expone la paleta de gráficas.
 * Mantener alineado con App\Support\BrandCss (primer pintado del servidor).
 */
export function applyBranding(
    settings: AppSettings | undefined,
    target: HTMLElement = document.documentElement,
    onlyCustomized = true,
) {
    if (!settings?.colors) return
    for (const [key, name] of BRAND_VARS) {
        const hex = settings.colors[key]
        const variants = [`--${name}-light`, `--${name}-light-fg`, `--${name}-dark`, `--${name}-dark-fg`]
        // Colores sin personalizar: se usan los valores base de app.css (diseño claro/oscuro original).
        if (!hex || (onlyCustomized && !settings.customized?.[key])) {
            variants.forEach((v) => target.style.removeProperty(v))
            continue
        }
        const light = ensureContrast(hex, LIGHT_BG)
        const dark = ensureContrast(hex, DARK_BG)
        target.style.setProperty(`--${name}-light`, rgbTriplet(light))
        target.style.setProperty(`--${name}-light-fg`, rgbTriplet(readableOn(light)))
        target.style.setProperty(`--${name}-dark`, rgbTriplet(dark))
        target.style.setProperty(`--${name}-dark-fg`, rgbTriplet(readableOn(dark)))
    }
    settings.chart_palette.forEach((hex, i) => target.style.setProperty(`--chart-palette-${i + 1}`, hex))
}

export function useBranding() {
    const page = usePage<SharedProps>()
    watch(() => page.props.appSettings, (s) => applyBranding(s), { immediate: true, deep: true })
}
