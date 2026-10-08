import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { ScopeLevel, SharedProps } from '@/types/shared'

const ORDER: ScopeLevel[] = ['none', 'own', 'sucursal', 'corporativo', 'global']

/**
 * Permisos y alcances del usuario para construir la interfaz (menú, botones).
 * Solo es una ayuda visual: el backend vuelve a autorizar cada acción.
 *
 * Para "ver" un módulo usa `scope(modulo)` / `canView(modulo)`, nunca un
 * permiso de alcance suelto: un rol guarda solo su nivel más alto.
 */
export function usePermissions() {
    const page = usePage<SharedProps>()

    const permissions = computed(() => new Set(page.props.auth?.permissions ?? []))
    const scopes = computed(() => page.props.auth?.scopes ?? {})
    const user = computed(() => page.props.auth?.user ?? null)
    const roles = computed(() => user.value?.roles ?? [])

    const can = (permission: string) => permissions.value.has(permission)
    const canAny = (list: string[]) => list.some((p) => permissions.value.has(p))
    const canAll = (list: string[]) => list.every((p) => permissions.value.has(p))

    /** Alcance efectivo de lectura del módulo. */
    const scope = (module: string): ScopeLevel => scopes.value[module] ?? 'none'
    /** ¿Puede ver el módulo con al menos el nivel indicado? */
    const canView = (module: string, atLeast: ScopeLevel = 'own') => ORDER.indexOf(scope(module)) >= ORDER.indexOf(atLeast)

    return { permissions, scopes, user, roles, can, canAny, canAll, scope, canView }
}

/**
 * Regla de visibilidad reutilizable (menú, guía, recorridos):
 * - `anyOf`: basta uno de estos permisos.
 * - `views`: basta poder ver alguno de estos módulos.
 * Sin ninguna de las dos, es visible para cualquier cuenta autenticada.
 */
export type Visibility = { anyOf?: string[]; views?: string[] }

export function useVisibility() {
    const { canAny, canView } = usePermissions()

    return (rule: Visibility): boolean => {
        const hasRule = (rule.anyOf?.length ?? 0) > 0 || (rule.views?.length ?? 0) > 0
        if (!hasRule) return true

        return canAny(rule.anyOf ?? []) || (rule.views ?? []).some((m) => canView(m))
    }
}
