import { computed, nextTick, reactive, shallowRef } from 'vue'
import { router } from '@inertiajs/vue3'
import { createTourEngine, type EngineState, type TourEngine } from '@/tour/engine'
import { TOURS, visibleSteps } from '@/tour/registry'
import type { TourDefinition, TourStep } from '@/tour/types'
import { useVisibility } from '@/Composables/usePermissions'

/**
 * Estado global (singleton) de los recorridos. Sobrevive a las navegaciones
 * de Inertia porque vive a nivel de módulo, no en una página.
 *
 * - Recorridos vistos: localStorage (si no está disponible, simplemente no se recuerdan).
 * - Al terminar o salir se regresa a la pantalla y posición de origen.
 */
const SEEN_KEY = 'mrlana.tours.seen.v1'

type Run = { tours: TourDefinition[] }

const state = reactive({
    phase: 'idle' as EngineState<TourStep, HTMLElement>['phase'],
    tourIndex: 0,
    stepIndex: 0,
    step: null as TourStep | null,
    target: null as HTMLElement | null,
    skipped: [] as string[],
})
const run = shallowRef<Run | null>(null)
const seen = reactive(new Set<string>(readSeen()))
let origin: { url: string; scroll: number } | null = null
let engine: TourEngine<TourStep, HTMLElement> | null = null

function readSeen(): string[] {
    try {
        const raw = window.localStorage.getItem(SEEN_KEY)
        const parsed = raw ? JSON.parse(raw) : []
        return Array.isArray(parsed) ? parsed.filter((x) => typeof x === 'string') : []
    } catch {
        return []
    }
}

function markSeen(ids: string[]) {
    ids.forEach((id) => seen.add(id))
    try {
        window.localStorage.setItem(SEEN_KEY, JSON.stringify([...seen]))
    } catch {
        // Almacenamiento no disponible: no es crítico.
    }
}

const isVisibleElement = (el: Element) => {
    if (!(el instanceof HTMLElement)) return false
    if (el.getClientRects().length === 0) return false
    const style = window.getComputedStyle(el)
    return style.visibility !== 'hidden' && style.display !== 'none'
}

export function findTourTarget(key: string): HTMLElement | null {
    const all = document.querySelectorAll(`[data-tour="${CSS.escape(key)}"]`)
    for (const el of Array.from(all)) {
        if (isVisibleElement(el)) return el as HTMLElement
    }
    return null
}

function isOnRoute(name: string): boolean {
    try {
        return !!route().current(name)
    } catch {
        return false
    }
}

function navigate(name: string): Promise<void> {
    return new Promise((resolve) => {
        let url: string
        try {
            url = route(name)
        } catch {
            resolve()
            return
        }
        router.visit(url, {
            preserveScroll: false,
            onFinish: () => {
                void nextTick().then(() => resolve())
            },
        })
    })
}

function getEngine(): TourEngine<TourStep, HTMLElement> {
    if (engine) return engine
    engine = createTourEngine<TourStep, HTMLElement>({
        isOnRoute,
        navigate,
        findTarget: (step) => (step.target ? findTourTarget(step.target) : null),
        sleep: (ms) => new Promise((r) => setTimeout(r, ms)),
        onChange: (s) => {
            state.phase = s.phase
            state.tourIndex = s.tourIndex
            state.stepIndex = s.stepIndex
            state.step = s.step
            state.target = s.target
            state.skipped = s.skipped
        },
        onFinish: (completed) => {
            const r = run.value
            if (r) {
                // Completos: todos; al salir, los módulos ya recorridos.
                const done = completed ? r.tours : r.tours.slice(0, state.tourIndex)
                markSeen(done.map((t) => t.id))
            }
            run.value = null
            returnToOrigin()
        },
    })

    return engine
}

function returnToOrigin() {
    const o = origin
    origin = null
    if (!o) return
    const here = window.location.pathname + window.location.search
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    if (here !== o.url) {
        router.visit(o.url, { preserveScroll: false, onFinish: () => window.scrollTo({ top: o.scroll, behavior: 'auto' }) })
    } else {
        window.scrollTo({ top: o.scroll, behavior: reduce ? 'auto' : 'smooth' })
    }
}

export function useTour() {
    const visible = useVisibility()

    /** Recorridos disponibles para el usuario (con al menos un paso visible). */
    const available = computed(() =>
        TOURS.filter((t) => visible(t.visibility))
            .map((t) => ({ ...t, steps: visibleSteps(t.steps, visible) }))
            .filter((t) => t.steps.length > 0),
    )

    /** Recorridos de la pantalla actual. */
    function forCurrentScreen(): TourDefinition[] {
        return available.value.filter((t) => t.match && isOnRoute(t.match))
    }

    function startTours(tours: TourDefinition[]) {
        if (!tours.length || state.phase !== 'idle') return
        origin = { url: window.location.pathname + window.location.search, scroll: window.scrollY }
        run.value = { tours }
        void getEngine().start(tours)
    }

    function start(id: string) {
        const t = available.value.find((x) => x.id === id)
        if (t) startTours([t])
    }

    /** Recorrido completo: bienvenida + todos los módulos permitidos. */
    function startFull() {
        startTours(available.value)
    }

    const current = computed(() => (run.value ? run.value.tours[state.tourIndex] ?? null : null))
    const progress = computed(() => {
        const r = run.value
        if (!r) return { current: 0, total: 0 }
        const total = r.tours.reduce((a, t) => a + t.steps.length, 0)
        const before = r.tours.slice(0, state.tourIndex).reduce((a, t) => a + t.steps.length, 0)
        return { current: Math.min(total, before + state.stepIndex + 1), total }
    })
    const hasUnseenHere = computed(() => {
        void seen.size
        return forCurrentScreen().some((t) => !seen.has(t.id))
    })

    return {
        state,
        run,
        seen,
        available,
        current,
        progress,
        hasUnseenHere,
        forCurrentScreen,
        start,
        startFull,
        next: () => getEngine().next(),
        prev: () => getEngine().prev(),
        skipStep: () => getEngine().skipStep(),
        skipModule: () => getEngine().skipModule(),
        exit: () => getEngine().exit(),
        finish: () => getEngine().complete(),
    }
}
