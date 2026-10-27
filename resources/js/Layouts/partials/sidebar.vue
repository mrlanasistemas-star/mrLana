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
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40'
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
            class="fixed inset-y-0 left-0 z-[210] flex h-dvh flex-col overflow-hidden border-r border-white/[0.06] bg-brand-ink text-zinc-100
                   transition-[width,box-shadow] duration-200 ease-out motion-reduce:transition-none
                   dark:bg-[#100D1B]"
            :class="[expanded ? 'w-60' : 'w-16', expanded && !pinned ? 'shadow-[8px_0_32px_-12px_rgba(20,10,60,0.45)]' : '']"
        >
            <!-- Marca: misma altura y borde que la barra superior -->
            <Link
                :href="route('dashboard')"
                class="flex h-16 shrink-0 items-center gap-3 border-b border-white/[0.06] px-3.5 focus:outline-none focus-visible:ring-2
                       focus-visible:ring-inset focus-visible:ring-white/40"
                aria-label="Ir al inicio"
            >
                <span
                    class="ui-brand-gradient flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white
                           shadow-sm shadow-[#6419FF]/40 ring-1 ring-white/10"
                >
                    <ApplicationLogo class="h-5 w-5" />
                </span>
                <span class="min-w-0 overflow-hidden transition-opacity duration-200" :class="expanded ? 'opacity-100' : 'opacity-0'">
                    <span class="block whitespace-nowrap text-[15px] font-semibold leading-tight tracking-tight text-white">MR-Lana</span>
                    <span class="block whitespace-nowrap text-[11px] font-medium text-zinc-400">ERP de gastos</span>
                </span>
            </Link>

            <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-2 py-2.5 [@media(max-height:820px)]:py-1 [scrollbar-width:none] hover:[scrollbar-width:thin]">
                <SidebarNav :expanded="expanded" />
            </div>

            <div class="space-y-0.5 border-t border-white/[0.06] p-2">
                <DownloadAppButton v-slot="{ label }" variant="menu" class="!px-[15px] [@media(max-height:820px)]:!min-h-[34px]">
                    <span class="overflow-hidden whitespace-nowrap transition-opacity" :class="expanded ? 'opacity-100' : 'opacity-0'">{{ label }}</span>
                </DownloadAppButton>

                <button
                    type="button"
                    :class="[footerBtn, 'text-zinc-400 hover:bg-white/[0.07] hover:text-white']"
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
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-[11px] font-semibold text-white ring-1 ring-inset ring-white/15" :title="user?.name">
                        {{ initials }}
                    </span>
                    <div class="min-w-0 flex-1 overflow-hidden transition-opacity" :class="expanded ? 'opacity-100' : 'opacity-0'">
                        <p class="truncate text-[13px] font-semibold leading-tight text-white">{{ user?.name }}</p>
                        <p class="truncate text-[11px] text-zinc-400">{{ roleLabel }}</p>
                    </div>
                    <button
                        v-if="expanded"
                        type="button"
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition
                               hover:bg-red-500/15 hover:text-red-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400/50"
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
                class="fixed inset-0 z-[350] bg-[#141120]/50 backdrop-blur-sm lg:hidden
                       data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0
                       motion-reduce:animate-none"
            />
            <DialogContent
                class="fixed inset-y-0 left-0 z-[351] flex w-[min(18rem,85vw)] flex-col overflow-hidden rounded-r-2xl
                       border-r border-white/[0.06] bg-brand-ink text-zinc-100 shadow-2xl focus:outline-none
                       pb-[env(safe-area-inset-bottom)] dark:bg-[#100D1B] lg:hidden
                       data-[state=open]:animate-in data-[state=closed]:animate-out
                       data-[state=open]:slide-in-from-left data-[state=closed]:slide-out-to-left duration-200 motion-reduce:animate-none"
            >
                <div class="flex h-[4.5rem] shrink-0 items-center justify-between gap-3 px-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="ui-brand-gradient flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white shadow-md shadow-[#6419FF]/40">
                            <ApplicationLogo class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <DialogTitle class="truncate text-[15px] font-semibold tracking-tight text-white">MR-Lana</DialogTitle>
                            <DialogDescription class="truncate text-[11px] text-zinc-400">ERP de gastos</DialogDescription>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-zinc-400 hover:bg-white/10 hover:text-white
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40"
                        aria-label="Cerrar menú"
                        @click="closeMobile"
                    >
                        <X class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>

                <div class="mx-4 flex items-center gap-3 rounded-2xl bg-white/[0.05] p-3 ring-1 ring-inset ring-white/[0.06]">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm font-semibold text-white ring-1 ring-inset ring-white/15">
                        {{ initials }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-white">{{ user?.name }}</p>
                        <p class="truncate text-xs text-zinc-400">{{ roleLabel }}</p>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
                    <SidebarNav :expanded="true" @navigate="closeMobile" />
                </div>

                <div class="space-y-1 border-t border-white/[0.06] p-3">
                    <DownloadAppButton variant="menu" />
                    <button
                        type="button"
                        :class="[footerBtn, 'gap-3 text-red-300 hover:bg-red-500/15']"
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
