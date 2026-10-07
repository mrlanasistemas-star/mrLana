<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import { LogOut, PanelLeftClose, PanelLeftOpen, X } from 'lucide-vue-next'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import DownloadAppButton from '@/Components/layout/DownloadAppButton.vue'
import SidebarNav from '@/Layouts/partials/SidebarNav.vue'
import { useLogout } from '@/Composables/useLogout'
import { usePermissions } from '@/Composables/usePermissions'
import { useSidebar } from '@/Composables/useSidebar'

/**
 * Sidebar del ERP.
 * - lg+ : rail pegado al borde (4rem). Se expande encima del contenido al pasar el puntero
 *         (dispositivos con hover) o reserva 15rem con "Fijar menú" (tabletas táctiles).
 * - <lg : drawer con overlay (lo abren la hamburguesa y "Más" de la barra inferior);
 *         se cierra al navegar, con Escape o tocando fuera.
 */
const { mobileOpen, pinned, hovering, closeMobile, togglePinned } = useSidebar()
const { confirmLogout } = useLogout()
const { roles, user } = usePermissions()

const canHover = ref(false)
let mq: MediaQueryList | null = null
const syncHover = () => (canHover.value = mq?.matches ?? false)
let removeNavListener: (() => void) | undefined

onMounted(() => {
    mq = window.matchMedia('(hover: hover) and (pointer: fine)')
    syncHover()
    mq.addEventListener('change', syncHover)
    removeNavListener = router.on('navigate', () => closeMobile())
})

onBeforeUnmount(() => {
    mq?.removeEventListener('change', syncHover)
    removeNavListener?.()
})

const expanded = computed(() => pinned.value || (canHover.value && hovering.value))
const roleLabel = computed(() => roles.value.join(', ') || 'Sin rol')
const initials = computed(() => {
    const parts = String(user.value?.name ?? 'ML').trim().split(/\s+/).filter(Boolean)
    return (parts.slice(0, 2).map((p) => p[0] ?? '').join('') || 'ML').toUpperCase()
})

const footerBtn =
    'flex min-h-[42px] w-full items-center rounded-xl px-[15px] text-[13.5px] font-medium transition-colors [@media(max-height:820px)]:min-h-[34px] ' +
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40'
</script>

<template>
    <!--
        Escritorio (lg+): rail fijo a todo el alto, pegado al borde. Reserva 4rem; al pasar el puntero
        se expande encima del contenido (sin moverlo). "Fijar menú" reserva 15rem.
    -->
    <aside
        id="erp-sidebar"
        class="relative z-[210] hidden shrink-0 transition-[width] duration-200 ease-out motion-reduce:transition-none lg:block"
        :class="pinned ? 'w-60' : 'w-16'"
        @mouseenter="hovering = true"
        @mouseleave="hovering = false"
        @focusin="hovering = true"
        @focusout="hovering = false"
    >
        <div
            class="fixed inset-y-0 left-0 z-[210] flex h-dvh flex-col overflow-hidden border-r border-slate-200/80 bg-white text-slate-800
                   transition-[width,box-shadow] duration-200 ease-out motion-reduce:transition-none
                   dark:border-zinc-800/60 dark:bg-zinc-950 dark:text-zinc-100"
            :class="[expanded ? 'w-60' : 'w-16', expanded && !pinned ? 'shadow-[8px_0_32px_-12px_rgba(0,0,0,0.25)]' : '']"
        >
            <!-- Marca: misma altura y borde que la barra superior -->
            <Link
                :href="route('dashboard')"
                class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-200/80 px-3.5 focus:outline-none focus-visible:ring-2
                       focus-visible:ring-inset focus-visible:ring-brand-primary/40 dark:border-zinc-800/60"
                aria-label="Ir al inicio"
            >
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-slate-800 to-slate-950 text-white
                           shadow-sm ring-1 ring-black/5 dark:from-zinc-100 dark:to-zinc-300 dark:text-zinc-900"
                >
                    <ApplicationLogo class="h-5 w-5" />
                </span>
                <span class="min-w-0 overflow-hidden transition-opacity duration-200" :class="expanded ? 'opacity-100' : 'opacity-0'">
                    <span class="block whitespace-nowrap text-[15px] font-extrabold leading-tight tracking-tight">MR-Lana</span>
                    <span class="block whitespace-nowrap text-[11px] font-medium text-slate-400 dark:text-zinc-500">ERP de gastos</span>
                </span>
            </Link>

            <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-2 py-2.5 [@media(max-height:820px)]:py-1 [scrollbar-width:none] hover:[scrollbar-width:thin]">
                <SidebarNav :expanded="expanded" />
            </div>

            <div class="space-y-0.5 border-t border-slate-200/80 p-2 dark:border-zinc-800/60">
                <DownloadAppButton v-slot="{ label }" variant="menu" class="!px-[15px] [@media(max-height:820px)]:!min-h-[34px]">
                    <span class="overflow-hidden whitespace-nowrap transition-opacity" :class="expanded ? 'opacity-100' : 'opacity-0'">{{ label }}</span>
                </DownloadAppButton>

                <button
                    type="button"
                    :class="[footerBtn, 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-white/[0.06] dark:hover:text-zinc-100']"
                    :aria-pressed="pinned"
                    :aria-label="pinned ? 'Contraer menú' : 'Fijar menú abierto'"
                    :title="pinned ? 'Contraer menú' : 'Fijar menú abierto'"
                    @click="togglePinned"
                >
                    <component :is="pinned ? PanelLeftClose : PanelLeftOpen" class="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
                    <span class="ml-3 overflow-hidden whitespace-nowrap transition-opacity" :class="expanded ? 'opacity-100' : 'opacity-0'">
                        {{ pinned ? 'Contraer menú' : 'Fijar menú' }}
                    </span>
                </button>

                <!-- Usuario -->
                <div class="flex items-center gap-2.5 rounded-xl px-2 py-1.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-primary text-[11px] font-bold text-brand-primary-fg" :title="user?.name">
                        {{ initials }}
                    </span>
                    <div class="min-w-0 flex-1 overflow-hidden transition-opacity" :class="expanded ? 'opacity-100' : 'opacity-0'">
                        <p class="truncate text-[13px] font-semibold leading-tight">{{ user?.name }}</p>
                        <p class="truncate text-[11px] text-slate-500 dark:text-zinc-400">{{ roleLabel }}</p>
                    </div>
                    <button
                        v-if="expanded"
                        type="button"
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 transition
                               hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400/40
                               dark:text-zinc-400 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                        aria-label="Cerrar sesión"
                        title="Cerrar sesión"
                        @click="confirmLogout"
                    >
                        <LogOut class="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </div>
    </aside>

    <!-- Móvil y tableta vertical (<lg): drawer -->
    <DialogRoot :open="mobileOpen" @update:open="(v) => (mobileOpen = v)">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-[350] bg-slate-950/40 backdrop-blur-sm lg:hidden
                       data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0
                       motion-reduce:animate-none"
            />
            <DialogContent
                class="fixed inset-y-0 left-0 z-[351] flex w-[min(18rem,85vw)] flex-col overflow-hidden rounded-r-2xl
                       border-r border-slate-200/80 bg-white text-slate-800 shadow-2xl focus:outline-none
                       pb-[env(safe-area-inset-bottom)] dark:border-zinc-800/60 dark:bg-zinc-950 dark:text-zinc-100 lg:hidden
                       data-[state=open]:animate-in data-[state=closed]:animate-out
                       data-[state=open]:slide-in-from-left data-[state=closed]:slide-out-to-left duration-200 motion-reduce:animate-none"
            >
                <div class="flex h-[4.5rem] shrink-0 items-center justify-between gap-3 px-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-slate-800 to-slate-950 text-white shadow-md dark:from-zinc-100 dark:to-zinc-300 dark:text-zinc-900">
                            <ApplicationLogo class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <DialogTitle class="truncate text-[15px] font-extrabold tracking-tight">MR-Lana</DialogTitle>
                            <DialogDescription class="truncate text-[11px] text-slate-500 dark:text-zinc-400">ERP de gastos</DialogDescription>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60 dark:hover:bg-white/10"
                        aria-label="Cerrar menú"
                        @click="closeMobile"
                    >
                        <X class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>

                <div class="mx-4 flex items-center gap-3 rounded-2xl bg-slate-50 p-3 dark:bg-white/[0.04]">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-primary text-sm font-bold text-brand-primary-fg">
                        {{ initials }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ user?.name }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-zinc-400">{{ roleLabel }}</p>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
                    <SidebarNav :expanded="true" @navigate="closeMobile" />
                </div>

                <div class="space-y-1 border-t border-slate-100 p-3 dark:border-white/[0.06]">
                    <DownloadAppButton variant="menu" />
                    <button
                        type="button"
                        :class="[footerBtn, 'gap-3 text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10']"
                        @click="confirmLogout"
                    >
                        <LogOut class="h-[18px] w-[18px]" aria-hidden="true" />
                        Cerrar sesión
                    </button>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
