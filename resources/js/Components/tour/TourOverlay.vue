<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ArrowLeft, ArrowRight, Check, Lightbulb, Loader2, SkipForward, X } from 'lucide-vue-next'
import { useTour } from '@/Composables/useTour'
import { computePosition, type Box, type TooltipPosition } from '@/tour/positioning'

/**
 * Capa del recorrido: oscurece la pantalla, resalta el elemento (spotlight) y
 * muestra el tooltip junto a él. En pantallas angostas usa una hoja inferior
 * o superior para no tapar el control. Se monta una vez en el layout y se
 * teletransporta al <body>.
 */
const tour = useTour()
const { state } = tour

const active = computed(() => state.phase !== 'idle')
const loading = computed(() => state.phase === 'navigating' || state.phase === 'waiting')
const showing = computed(() => state.phase === 'showing' && !!state.step)

const tipEl = ref<HTMLElement | null>(null)
const rect = ref<Box | null>(null)
const position = ref<TooltipPosition>({ mode: 'center' })
const reduceMotion = ref(false)

const PAD = 6

function measure() {
    const el = state.target
    if (!showing.value || !el || !el.isConnected) {
        rect.value = null
        position.value = { mode: 'center' }
        return
    }
    const r = el.getBoundingClientRect()
    rect.value = { top: r.top - PAD, left: r.left - PAD, width: r.width + PAD * 2, height: r.height + PAD * 2 }
    const tip = tipEl.value?.getBoundingClientRect()
    position.value = computePosition(
        rect.value,
        { width: tip?.width ?? 360, height: tip?.height ?? 220 },
        { width: window.innerWidth, height: window.innerHeight },
        state.step?.placement ?? 'auto',
    )
}

let raf = 0
const schedule = () => {
    cancelAnimationFrame(raf)
    raf = requestAnimationFrame(measure)
}

// Al mostrar un paso: llevar el elemento a la vista, medir y enfocar el tooltip.
watch(() => [state.phase, state.step?.id, state.target] as const, async () => {
    if (!showing.value) return
    state.target?.scrollIntoView({ block: 'center', inline: 'nearest', behavior: reduceMotion.value ? 'auto' : 'smooth' })
    await nextTick()
    measure()
    // El desplazamiento suave termina después: se vuelve a medir.
    window.setTimeout(measure, reduceMotion.value ? 0 : 320)
    tipEl.value?.focus({ preventScroll: true })
})

/* ---------- Teclado ---------- */
let lastAction = 0
function guarded(fn: () => unknown) {
    // Evita dobles activaciones (doble clic o tecla repetida).
    const now = Date.now()
    if (now - lastAction < 250) return
    lastAction = now
    fn()
}
const isLast = computed(() => tour.progress.value.current >= tour.progress.value.total)
const next = () => guarded(() => (isLast.value ? tour.finish() : tour.next()))
const prev = () => guarded(() => tour.prev())

function onKey(e: KeyboardEvent) {
    if (!active.value) return
    if (e.key === 'Escape') {
        e.preventDefault()
        tour.exit()
        return
    }
    if (!showing.value) return
    const tag = (document.activeElement?.tagName ?? '').toLowerCase()
    const inControl = ['input', 'textarea', 'select', 'button', 'a'].includes(tag)
    if (e.key === 'ArrowRight') {
        e.preventDefault()
        next()
    } else if (e.key === 'ArrowLeft') {
        e.preventDefault()
        prev()
    } else if (e.key === 'Enter' && !inControl) {
        e.preventDefault()
        next()
    }
}

/* ---------- Recalcular posición ---------- */
let ro: ResizeObserver | null = null
let ticker = 0
onMounted(() => {
    reduceMotion.value = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
    window.addEventListener('keydown', onKey)
    window.addEventListener('resize', schedule)
    window.addEventListener('scroll', schedule, true)
    ro = new ResizeObserver(schedule)
    ro.observe(document.body)
    // Respaldo ligero: cambios de layout (sidebar, acordeones) que no disparan eventos.
    ticker = window.setInterval(() => showing.value && schedule(), 600)
})
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey)
    window.removeEventListener('resize', schedule)
    window.removeEventListener('scroll', schedule, true)
    ro?.disconnect()
    window.clearInterval(ticker)
    cancelAnimationFrame(raf)
})
watch(() => state.target, (el, old) => {
    if (old) ro?.unobserve(old)
    if (el) ro?.observe(el)
})

/* ---------- Presentación ---------- */
const moduleName = computed(() => tour.current.value?.module ?? 'Recorrido')
const stepInModule = computed(() => ({ index: state.stepIndex + 1, total: tour.current.value?.steps.length ?? 0 }))
const percent = computed(() => (tour.progress.value.total ? Math.round((tour.progress.value.current / tour.progress.value.total) * 100) : 0))
const multiModule = computed(() => (tour.run.value?.tours.length ?? 0) > 1)

const spotlightStyle = computed(() => rect.value ? {
    top: `${rect.value.top}px`,
    left: `${rect.value.left}px`,
    width: `${rect.value.width}px`,
    height: `${rect.value.height}px`,
} : {})

const tipStyle = computed(() => position.value.mode === 'floating' ? { top: `${position.value.top}px`, left: `${position.value.left}px` } : {})
const tipClass = computed(() => {
    const p = position.value
    if (p.mode === 'sheet') {
        return p.edge === 'bottom'
            ? 'inset-x-2 bottom-[max(0.5rem,env(safe-area-inset-bottom))] rounded-3xl'
            : 'inset-x-2 top-[max(0.5rem,env(safe-area-inset-top))] rounded-3xl'
    }
    if (p.mode === 'center') return 'left-1/2 top-1/2 w-[min(26rem,calc(100vw-1.5rem))] -translate-x-1/2 -translate-y-1/2 rounded-3xl'
    return 'w-[min(24rem,calc(100vw-1.5rem))] rounded-2xl'
})
</script>

<template>
    <Teleport to="body">
        <div v-if="active" class="fixed inset-0 z-[600]" data-tour-overlay>
            <!-- Bloquea la interacción con la página durante el recorrido -->
            <div class="absolute inset-0" :class="!rect ? 'bg-slate-950/55 backdrop-blur-[1px]' : ''" aria-hidden="true" />

            <!-- Spotlight -->
            <div
                v-if="rect"
                class="pointer-events-none absolute rounded-2xl ring-2 ring-white/80 transition-all duration-200 ease-out motion-reduce:transition-none"
                :style="[spotlightStyle, { boxShadow: '0 0 0 9999px rgba(2, 6, 23, 0.58)' }]"
                aria-hidden="true"
            />

            <!-- Cargando (navegando o esperando el elemento) -->
            <div
                v-if="loading"
                class="absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center gap-3 rounded-2xl bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-2xl dark:bg-zinc-900 dark:text-zinc-200"
                role="status"
                aria-live="polite"
            >
                <Loader2 class="h-4 w-4 animate-spin motion-reduce:animate-none" aria-hidden="true" />
                {{ state.phase === 'navigating' ? 'Abriendo el módulo…' : 'Preparando el paso…' }}
                <button type="button" class="ml-2 rounded-lg px-2 py-1 text-xs text-slate-500 hover:bg-slate-100 dark:hover:bg-white/10" @click="tour.exit()">Salir</button>
            </div>

            <!-- Tooltip / hoja -->
            <div
                v-if="showing"
                ref="tipEl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="tour-title"
                aria-describedby="tour-body"
                tabindex="-1"
                class="absolute flex max-h-[min(70dvh,32rem)] flex-col overflow-hidden border border-slate-200/80 bg-white text-slate-800 shadow-2xl outline-none
                       focus-visible:ring-2 focus-visible:ring-brand-accent/50 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100
                       transition-[top,left] duration-200 ease-out motion-reduce:transition-none"
                :class="tipClass"
                :style="tipStyle"
            >
                <div class="h-1 w-full bg-slate-100 dark:bg-white/10" aria-hidden="true">
                    <div class="h-full bg-brand-accent transition-[width] duration-300 motion-reduce:transition-none" :style="{ width: percent + '%' }" />
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <p class="min-w-0 text-[11px] font-black uppercase tracking-widest text-brand-accent">
                            {{ moduleName }}
                            <span class="font-semibold normal-case tracking-normal text-slate-400">· Paso {{ stepInModule.index }} de {{ stepInModule.total }}</span>
                        </p>
                        <button type="button" class="-mr-1 -mt-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/40 dark:hover:bg-white/10" aria-label="Salir del recorrido" title="Salir (Esc)" @click="tour.exit()">
                            <X class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>
                    <h2 id="tour-title" class="mt-1 break-words text-base font-black leading-snug">{{ state.step?.title }}</h2>
                    <p id="tour-body" class="mt-1.5 whitespace-pre-line break-words text-sm leading-relaxed text-slate-600 dark:text-zinc-300">{{ state.step?.body }}</p>
                    <p v-if="state.step?.tip" class="mt-3 flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                        <Lightbulb class="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" /> <span class="min-w-0 break-words">{{ state.step.tip }}</span>
                    </p>
                    <p class="mt-3 text-[11px] text-slate-400">{{ tour.progress.value.current }} de {{ tour.progress.value.total }} en total</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/70 px-3 py-2.5 dark:border-white/10 dark:bg-white/[0.03]">
                    <div class="flex flex-wrap gap-1">
                        <button type="button" class="tour-ghost" title="Saltar este paso" @click="guarded(() => tour.skipStep())">
                            <SkipForward class="h-3.5 w-3.5" aria-hidden="true" /> Saltar paso
                        </button>
                        <button v-if="multiModule" type="button" class="tour-ghost" title="Ir al siguiente módulo" @click="guarded(() => tour.skipModule())">Saltar módulo</button>
                    </div>
                    <div class="flex gap-1.5">
                        <button type="button" class="tour-btn border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 dark:border-white/10 dark:bg-white/5 dark:text-zinc-200" :disabled="tour.progress.value.current <= 1" @click="prev">
                            <ArrowLeft class="h-4 w-4" aria-hidden="true" /> Atrás
                        </button>
                        <button type="button" class="tour-btn bg-brand-button text-brand-button-fg hover:bg-brand-button/90" @click="next">
                            <template v-if="isLast"><Check class="h-4 w-4" aria-hidden="true" /> Terminar</template>
                            <template v-else>Siguiente <ArrowRight class="h-4 w-4" aria-hidden="true" /></template>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.tour-btn {
    @apply inline-flex min-h-[40px] items-center gap-1.5 rounded-xl px-3 text-sm font-bold transition duration-150 active:scale-[0.98]
        focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 motion-reduce:transform-none;
}
.tour-ghost {
    @apply inline-flex min-h-[40px] items-center gap-1 rounded-xl px-2.5 text-xs font-semibold text-slate-500 transition duration-150
        hover:bg-slate-200/60 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/40
        dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-zinc-100;
}
</style>
