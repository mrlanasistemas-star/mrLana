<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Bell, FileText, LayoutDashboard, Menu, Plus } from 'lucide-vue-next'
import { usePermissions } from '@/Composables/usePermissions'
import { useNotifications } from '@/Composables/useNotifications'
import { useSidebar } from '@/Composables/useSidebar'
import type { SharedProps } from '@/types/shared'

/**
 * Barra de navegación inferior para móvil (< lg), estilo app nativa.
 * Muestra solo destinos permitidos; "Más" abre el menú completo.
 */
const page = usePage<SharedProps>()
const { can, canAny } = usePermissions()
const { openMobile, mobileOpen } = useSidebar()
const { unread } = useNotifications()

const current = (pattern: string) => {
    try {
        return route().current(pattern)
    } catch {
        return false
    }
}

type Tab = { key: string; label: string; href: string; icon: typeof Bell; active: boolean; badge?: number }

const tabs = computed<Tab[]>(() => {
    // Dependencia reactiva: recalcula al navegar.
    void page.url
    const list: Tab[] = []
    if (can('dashboard.ver')) {
        list.push({ key: 'inicio', label: 'Inicio', href: route('dashboard'), icon: LayoutDashboard, active: !!current('dashboard*') })
    }
    if (canAny(['requisiciones.ver_todos', 'requisiciones.ver_propios'])) {
        list.push({
            key: 'req', label: 'Requisiciones', href: route('requisiciones.index'), icon: FileText,
            active: !!current('requisiciones.*') && !current('requisiciones.create') && !current('requisiciones.registrar'),
        })
    }
    if (can('notificaciones.ver')) {
        list.push({ key: 'avisos', label: 'Avisos', href: route('notificaciones.index'), icon: Bell, active: !!current('notificaciones.*'), badge: unread.value })
    }
    return list
})

const canCreate = computed(() => can('requisiciones.registrar'))
const createActive = computed(() => {
    void page.url
    return !!current('requisiciones.create') || !!current('requisiciones.registrar')
})

const left = computed(() => tabs.value.slice(0, 2))
const right = computed(() => tabs.value.slice(2))

const tabClass = (active: boolean) =>
    [
        'relative flex min-h-[56px] flex-1 flex-col items-center justify-center gap-0.5 rounded-2xl text-[11px] font-semibold transition-colors',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60',
        active ? 'text-brand-primary' : 'text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-zinc-100',
    ].join(' ')
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-[180] border-t border-slate-200/80 bg-white/90 backdrop-blur-xl
               pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_30px_-12px_rgba(0,0,0,0.18)]
               dark:border-zinc-800/80 dark:bg-zinc-950/90 lg:hidden"
        aria-label="Navegación principal móvil"
    >
        <div class="mx-auto flex max-w-lg items-stretch gap-1 px-2 pt-1.5 pb-1">
            <Link
                v-for="t in left"
                :key="t.key"
                :href="t.href"
                :class="tabClass(t.active)"
                :aria-current="t.active ? 'page' : undefined"
            >
                <span
                    class="flex h-7 w-12 items-center justify-center rounded-full transition-colors"
                    :class="t.active ? 'bg-brand-primary/10 dark:bg-brand-primary/15' : ''"
                >
                    <component :is="t.icon" class="h-[20px] w-[20px]" aria-hidden="true" />
                </span>
                <span class="max-w-full truncate px-1">{{ t.label }}</span>
            </Link>

            <div v-if="canCreate" class="flex flex-1 items-start justify-center">
                <Link
                    :href="route('requisiciones.create')"
                    class="-mt-6 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-button text-brand-button-fg
                           shadow-lg shadow-black/20 ring-4 ring-white transition active:scale-95
                           focus:outline-none focus-visible:ring-slate-400 dark:ring-zinc-950 motion-reduce:transform-none"
                    :class="createActive ? 'scale-105' : ''"
                    aria-label="Nueva requisición"
                >
                    <Plus class="h-6 w-6" aria-hidden="true" />
                </Link>
            </div>

            <Link
                v-for="t in right"
                :key="t.key"
                :href="t.href"
                :class="tabClass(t.active)"
                :aria-current="t.active ? 'page' : undefined"
            >
                <span
                    class="relative flex h-7 w-12 items-center justify-center rounded-full transition-colors"
                    :class="t.active ? 'bg-brand-primary/10 dark:bg-brand-primary/15' : ''"
                >
                    <component :is="t.icon" class="h-[20px] w-[20px]" aria-hidden="true" />
                    <span
                        v-if="t.badge"
                        class="absolute -top-1 right-1 min-w-[18px] rounded-full bg-brand-danger px-1 text-center text-[10px] font-bold leading-[18px] text-brand-danger-fg"
                    >
                        {{ t.badge > 99 ? '99+' : t.badge }}
                    </span>
                </span>
                <span class="max-w-full truncate px-1">{{ t.label }}</span>
            </Link>

            <button
                type="button"
                :class="tabClass(mobileOpen)"
                :aria-expanded="mobileOpen"
                aria-label="Más opciones del menú"
                @click="openMobile"
            >
                <span class="flex h-7 w-12 items-center justify-center rounded-full">
                    <Menu class="h-[20px] w-[20px]" aria-hidden="true" />
                </span>
                <span>Más</span>
            </button>
        </div>
    </nav>
</template>
