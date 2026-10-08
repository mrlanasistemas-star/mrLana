import type { Component } from 'vue'
import type { Visibility } from '@/Composables/usePermissions'
import type { EngineStep } from './engine'

export type Placement = 'auto' | 'top' | 'bottom' | 'left' | 'right'

/** Paso de un recorrido. `target` es la clave del atributo `data-tour` del elemento. */
export type TourStep = EngineStep & {
    title: string
    body: string
    /** Consejo opcional, mostrado en un recuadro aparte. */
    tip?: string
    placement?: Placement
    /** Solo se muestra a quien cumpla la regla (permisos o alcance). */
    visibility?: Visibility
}

export type TourDefinition = {
    id: string
    /** Nombre del módulo (se muestra en el encabezado del recorrido). */
    module: string
    title: string
    description: string
    icon: Component
    /** Patrón de ruta donde el recorrido es "de esta pantalla" (p. ej. 'requisiciones.*'). */
    match?: string
    visibility: Visibility
    steps: TourStep[]
}
