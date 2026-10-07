import type { route as ziggyRoute } from 'ziggy-js'

/**
 * ZiggyVue expone `route()` en todas las plantillas; esta declaración lo tipa.
 */
declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof ziggyRoute
    }
}

export {}
