/**
 * Utilidades de color para aplicar la marca configurada con buen contraste.
 * Mantener alineado con App\Support\BrandCss (primer pintado en el servidor).
 */
export const HEX_RE = /^#[0-9A-Fa-f]{6}$/

export const LIGHT_BG = '#FFFFFF'
export const DARK_BG = '#09090B'

export function hexToRgb(hex: string): [number, number, number] | null {
    if (!HEX_RE.test(hex)) return null
    const n = parseInt(hex.slice(1), 16)
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255]
}

function toHex(rgb: [number, number, number]): string {
    return '#' + rgb.map((c) => Math.round(c).toString(16).padStart(2, '0')).join('').toUpperCase()
}

/** Triplete "r g b" para usar en rgb(var(--x) / <alpha>). */
export function rgbTriplet(hex: string): string {
    const rgb = hexToRgb(hex)
    return rgb ? rgb.join(' ') : '0 0 0'
}

function channel(c: number): number {
    const s = c / 255
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
}

export function luminance(hex: string): number {
    const rgb = hexToRgb(hex)
    if (!rgb) return 0
    return 0.2126 * channel(rgb[0]) + 0.7152 * channel(rgb[1]) + 0.0722 * channel(rgb[2])
}

export function contrastRatio(a: string, b: string): number {
    const [l1, l2] = [luminance(a), luminance(b)].sort((x, y) => y - x)
    return (l1 + 0.05) / (l2 + 0.05)
}

/** Texto legible (blanco o casi negro) sobre el color dado. */
export function readableOn(hex: string): string {
    return contrastRatio(hex, '#FFFFFF') >= contrastRatio(hex, DARK_BG) ? '#FFFFFF' : DARK_BG
}

/**
 * Ajusta el color hacia blanco (fondo oscuro) o hacia negro (fondo claro)
 * hasta alcanzar el contraste mínimo contra el fondo.
 */
export function ensureContrast(hex: string, background: string, min = 3): string {
    const rgb = hexToRgb(hex)
    if (!rgb || contrastRatio(hex, background) >= min) return hex
    const target = luminance(background) < 0.5 ? 255 : 0
    for (let step = 1; step <= 20; step++) {
        const t = step / 20
        const mixed = toHex(rgb.map((c) => c + (target - c) * t) as [number, number, number])
        if (contrastRatio(mixed, background) >= min) return mixed
    }
    return target === 255 ? '#FFFFFF' : DARK_BG
}
