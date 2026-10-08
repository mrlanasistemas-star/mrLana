import type { Placement } from './types'

export type Box = { top: number; left: number; width: number; height: number }
export type Size = { width: number; height: number }

export type TooltipPosition =
    | { mode: 'floating'; top: number; left: number; placement: Exclude<Placement, 'auto'> }
    | { mode: 'sheet'; edge: 'top' | 'bottom' }
    | { mode: 'center' }

/** Ancho a partir del cual se usa tooltip flotante; debajo, hoja inferior/superior. */
export const SHEET_BREAKPOINT = 640

/**
 * Calcula dónde colocar el tooltip junto al elemento resaltado sin salirse de
 * la pantalla. En móvil usa una hoja en el borde opuesto al elemento para no
 * taparlo.
 */
export function computePosition(target: Box | null, tip: Size, viewport: Size, preferred: Placement = 'auto', gap = 14, margin = 12): TooltipPosition {
    if (!target) return { mode: 'center' }

    if (viewport.width < SHEET_BREAKPOINT) {
        const center = target.top + target.height / 2
        return { mode: 'sheet', edge: center > viewport.height / 2 ? 'top' : 'bottom' }
    }

    const space = {
        bottom: viewport.height - (target.top + target.height),
        top: target.top,
        right: viewport.width - (target.left + target.width),
        left: target.left,
    }
    const fits = {
        bottom: space.bottom >= tip.height + gap + margin,
        top: space.top >= tip.height + gap + margin,
        right: space.right >= tip.width + gap + margin,
        left: space.left >= tip.width + gap + margin,
    }

    const order: Exclude<Placement, 'auto'>[] = preferred !== 'auto'
        ? [preferred, 'bottom', 'top', 'right', 'left']
        : ['bottom', 'top', 'right', 'left']
    const placement = order.find((p) => fits[p]) ?? (space.bottom >= space.top ? 'bottom' : 'top')

    const clamp = (v: number, min: number, max: number) => Math.min(Math.max(v, min), Math.max(min, max))
    let top: number
    let left: number

    if (placement === 'bottom' || placement === 'top') {
        top = placement === 'bottom' ? target.top + target.height + gap : target.top - tip.height - gap
        left = target.left + target.width / 2 - tip.width / 2
    } else {
        top = target.top + target.height / 2 - tip.height / 2
        left = placement === 'right' ? target.left + target.width + gap : target.left - tip.width - gap
    }

    return {
        mode: 'floating',
        placement,
        top: clamp(top, margin, viewport.height - tip.height - margin),
        left: clamp(left, margin, viewport.width - tip.width - margin),
    }
}
