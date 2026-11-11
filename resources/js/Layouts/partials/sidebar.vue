<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, TooltipProvider } from 'reka-ui'
import { LogOut, PanelLeftClose, PanelLeftOpen, X } from 'lucide-vue-next'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import DownloadAppButton from '@/Components/layout/DownloadAppButton.vue'
import SidebarTooltip from '@/Components/layout/SidebarTooltip.vue'
import SidebarNav from '@/Layouts/partials/SidebarNav.vue'
import { useLogout } from '@/Composables/useLogout'
import { usePermissions } from '@/Composables/usePermissions'
import { useSidebar } from '@/Composables/useSidebar'

/**
 * Sidebar del ERP.
 * - lg+ : fijo o no fijo (botón al pie o Ctrl/⌘ + B; se recuerda).
 *         Fijo: menú expandido (16rem) que reserva su ancho.
 *         No fijo: rail de íconos (4.5rem) que se abre al pasar el puntero o al llegar
 *         con el teclado, flotando sobre el contenido, y se cierra al salir.
 *         Cuadrícula común: padding lateral de 12 px + celda de ícono de 48 px, de modo que
 *         logo, íconos, avatar y botones comparten el mismo eje (36 px desde el borde).
 * - <lg : drawer con overlay (lo abren la hamburguesa y "Más" de la barra inferior);
 *         se cierra al navegar, con Escape o tocando fuera.
 */
const { mobileOpen, pinned, closeMobile, togglePinned } = useSidebar()

// Sin fijar, el rail se abre al pasar el puntero (con una pausa breve para no
// abrirse al cruzarlo de paso) o cuando el foco del teclado entra en él.
const peek = ref(false)
let peekTimer: ReturnType<typeof setTimeout> | undefined
const canHover = typeof window !== 'undefined' && (window.matchMedia?.('(hover: hover) and (pointer: fine)').matches ?? true)

function openPeek(delay = 120) {
    if (pinned.value) return
    clearTimeout(peekTimer)
    peekTimer = setTimeout(() => (peek.value = true), delay)
}
function closePeek(delay = 180) {
    clearTimeout(peekTimer)
    peekTimer = setTimeout(() => (peek.value = false), delay)
}
function onPointerEnter() {
    if (canHover) openPeek()
}
function onPointerLeave() {
    if (canHover) closePeek()
}
function onFocusOut(e: FocusEvent) {
    const next = e.relatedTarget as Node | null
    if (!next || !(e.currentTarget as HTMLElement).contains(next)) closePeek(0)
}
function onTogglePinned() {
    togglePinned()
    clearTimeout(peekTimer)
    peek.value = false
}

/** Etiquetas visibles: fijo o abierto con el puntero. */
const expanded = computed(() => pinned.value || peek.value)
/** Abierto sin fijar: flota sobre el contenido. */
const floating = computed(() => !pinned.value && peek.value)
const { confirmLogout } = useLogout()
const { roles, user } = usePermissions()

const toggleLabel = computed(() => (pinned.value ? 'Dejar de fijar el menú' : 'Fijar el menú abierto'))

function onKeydown(e: KeyboardEvent) {
    if (!(e.ctrlKey || e.metaKey) || e.altKey || e.shiftKey || e.key.toLowerCase() !== 'b') return
    const t = e.target as HTMLElement | null
    if (t?.closest('input, textarea, select, [contenteditable="true"]')) return
    if (!window.matchMedia('(min-width: 1024px)').matches) return
    e.preventDefault()
    onTogglePinned()
}

let removeNavListener: (() => void) | undefined
onMounted(() => {
    removeNavListener = router.on('navigate', () => closeMobile())
    window.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
    removeNavListener?.()
    window.removeEventListener('keydown', onKeydown)
    clearTimeout(peekTimer)
})

const roleLabel = computed(() => roles.value.join(', ') || 'Sin rol')
const initials = computed(() => {
    const parts = String(user.value?.name ?? 'ML').trim().split(/\s+/).filter(Boolean)
    return (parts.slice(0, 2).map((p) => p[0] ?? '').join('') || 'ML').toUpperCase()
})

const fade = 'transition-opacity duration-200 motion-reduce:transition-none'
const width = 'transition-[width] duration-200 ease-out motion-reduce:transition-none'
const row =
    'flex h-10 w-full items-center overflow-hidden rounded-[10px] text-[13.5px] font-medium transition-colors [@media(max-height:820px)]:h-9 ' +
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-white/80'
</script>

<template>
    <TooltipProvider :delay-duration="250" :skip-delay-duration="150">
        <!-- Escritorio (lg+): reserva el ancho del estado actual; el panel fijo ocupa todo el alto -->
        <aside
            id="erp-sidebar"
            class="relative z-[210] hidden shrink-0 lg:block"
            :class="[width, pinned ? 'w-64' : 'w-[4.5rem]']"
        >
            <div
                class="fixed inset-y-0 left-0 z-[210] flex h-dvh flex-col overflow-hidden border-r border-white/[0.06]
                       bg-brand-ink text-zinc-100 transition-shadow dark:bg-[#100D1B]"
                :class="[width, expanded ? 'w-64' : 'w-[4.5rem]', floating ? 'shadow-[8px_0_32px_-8px_rgba(15,10,40,0.45)]' : '']"
                @pointerenter="onPointerEnter"
                @pointerleave="onPointerLeave"
                @focusin="openPeek(0)"
                @focusout="onFocusOut"
            >
                <!-- Marca: misma altura y borde que la barra superior -->
                <Link
                    :href="route('dashboard')"
                    class="flex h-16 shrink-0 items-center gap-3 border-b border-white/[0.06] px-4 focus:outline-none
                           focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-white/80"
                    aria-label="MR-Lana, ir al inicio"
                >
                    <span
                        class="ui-brand-gradient flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white
                               shadow-[0_6px_18px_-6px_rgba(100,25,255,0.7)] ring-1 ring-inset ring-white/15"
                    >
                        <ApplicationLogo class="h-5 w-5" />
                    </span>
                    <span class="min-w-0" :class="[fade, expanded ? 'opacity-100' : 'opacity-0']" aria-hidden="true">
                        <span class="block whitespace-nowrap text-[15px] font-semibold leading-5 tracking-tight text-white">MR-Lana</span>
                        <span class="block whitespace-nowrap text-[11.5px] font-medium leading-4 text-[#8C87A6]">ERP de gastos</span>
                    </span>
                </Link>

                <!-- Opciones: scroll propio con desvanecido en los bordes -->
                <div
                    class="sidebar-scroll min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-3 py-3 [@media(max-height:820px)]:py-2"
                >
                    <SidebarNav :expanded="expanded" />
                </div>

                <!-- Pie: misma cuadrícula que las opciones -->
                <div class="shrink-0 space-y-0.5 border-t border-white/[0.06] px-3 py-2.5">
                    <SidebarTooltip label="Instalar o descargar la app" :disabled="expanded">
                        <div>
                            <DownloadAppButton v-slot="{ label }" variant="menu" class="[@media(max-height:820px)]:h-9">
                                <span :class="[fade, expanded ? 'opacity-100' : 'opacity-0']">{{ label }}</span>
                            </DownloadAppButton>
                        </div>
                    </SidebarTooltip>

                    <SidebarTooltip :label="`${toggleLabel} (Ctrl+B)`" :disabled="expanded">
                        <button
                            type="button"
                            :class="[row, 'text-zinc-400 hover:bg-white/[0.06] hover:text-zinc-50']"
                            :aria-pressed="pinned"
                            aria-controls="erp-sidebar"
                            @click="onTogglePinned"
                        >
                            <span class="flex w-12 shrink-0 items-center justify-center">
                                <component :is="pinned ? PanelLeftClose : PanelLeftOpen" class="h-[18px] w-[18px]" :stroke-width="1.8" aria-hidden="true" />
                            </span>
                            <span class="flex min-w-0 flex-1 items-center justify-between gap-2 pr-3" :class="[fade, expanded ? 'opacity-100' : 'opacity-0']">
                                <span class="truncate">{{ toggleLabel }}</span>
                                <kbd class="hidden rounded-md border border-white/10 px-1.5 py-px font-sans text-[10.5px] font-medium text-zinc-500 xl:inline">Ctrl B</kbd>
                            </span>
                        </button>
                    </SidebarTooltip>

                    <!-- Usuario -->
                    <div class="flex h-12 items-center overflow-hidden rounded-[10px] [@media(max-height:820px)]:h-11">
                        <SidebarTooltip :label="`${user?.name ?? ''} (${roleLabel})`" :disabled="expanded">
                            <span class="flex w-12 shrink-0 items-center justify-center">
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-[11px] font-semibold text-white
                                           ring-1 ring-inset ring-white/15"
                                    aria-hidden="true"
                                >
                                    {{ initials }}
                                </span>
                            </span>
                        </SidebarTooltip>
                        <div class="min-w-0 flex-1" :class="[fade, expanded ? 'opacity-100' : 'opacity-0']">
                            <p class="truncate text-[13px] font-semibold leading-tight text-white">{{ user?.name }}</p>
                            <p class="truncate text-[11.5px] leading-tight text-[#8C87A6]">{{ roleLabel }}</p>
                        </div>
                        <button
                            v-show="expanded"
                            type="button"
                            class="mr-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition
                                   hover:bg-red-500/15 hover:text-red-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400/60"
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
                <div class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-white/[0.06] pl-4 pr-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="ui-brand-gradient flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white
                                   shadow-[0_6px_18px_-6px_rgba(100,25,255,0.7)] ring-1 ring-inset ring-white/15"
                        >
                            <ApplicationLogo class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <DialogTitle class="truncate text-[15px] font-semibold leading-5 tracking-tight text-white">MR-Lana</DialogTitle>
                            <DialogDescription class="truncate text-[11.5px] font-medium leading-4 text-[#8C87A6]">ERP de gastos</DialogDescription>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-[10px] text-zinc-400 hover:bg-white/10 hover:text-white
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-white/80"
                        aria-label="Cerrar menú"
                        @click="closeMobile"
                    >
                        <X class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>

                <div class="sidebar-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3">
                    <SidebarNav :expanded="true" @navigate="closeMobile" />
                </div>

                <div class="shrink-0 space-y-0.5 border-t border-white/[0.06] px-3 py-2.5">
                    <DownloadAppButton variant="menu" />
                    <div class="flex h-12 items-center overflow-hidden rounded-[10px] [@media(max-height:820px)]:h-11">
                        <span class="flex w-12 shrink-0 items-center justify-center">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-[11px] font-semibold text-white ring-1 ring-inset ring-white/15"
                                aria-hidden="true"
                            >
                                {{ initials }}
                            </span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold leading-tight text-white">{{ user?.name }}</p>
                            <p class="truncate text-[11.5px] leading-tight text-[#8C87A6]">{{ roleLabel }}</p>
                        </div>
                        <button
                            type="button"
                            class="mr-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] text-zinc-400 transition
                                   hover:bg-red-500/15 hover:text-red-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400/60"
                            aria-label="Cerrar sesión"
                            @click="confirmLogout"
                        >
                            <LogOut class="h-[18px] w-[18px]" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
    </TooltipProvider>
</template>

<style scoped>
/* Scroll interno discreto con desvanecido arriba/abajo para indicar que hay más opciones. */
.sidebar-scroll {
    scrollbar-width: thin;
    scrollbar-color: transparent transparent;
    -webkit-mask-image: linear-gradient(to bottom, transparent 0, #000 10px, #000 calc(100% - 14px), transparent 100%);
    mask-image: linear-gradient(to bottom, transparent 0, #000 10px, #000 calc(100% - 14px), transparent 100%);
}
.sidebar-scroll:hover,
.sidebar-scroll:focus-within {
    scrollbar-color: rgb(255 255 255 / 0.18) transparent;
}
</style>
