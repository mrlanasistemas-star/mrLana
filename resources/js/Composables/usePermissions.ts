import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { SharedProps } from '@/types/shared'

/**
 * Permisos del usuario para construir la interfaz (menú, botones).
 * Solo es una ayuda visual: el backend vuelve a autorizar cada acción.
 */
export function usePermissions() {
    const page = usePage<SharedProps>()

    const permissions = computed(() => new Set(page.props.auth?.permissions ?? []))
    const user = computed(() => page.props.auth?.user ?? null)
    const roles = computed(() => user.value?.roles ?? [])

    const can = (permission: string) => permissions.value.has(permission)
    const canAny = (list: string[]) => list.some((p) => permissions.value.has(p))
    const canAll = (list: string[]) => list.every((p) => permissions.value.has(p))

    return { permissions, user, roles, can, canAny, canAll }
}
