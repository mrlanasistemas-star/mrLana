/**
 * Motor de recorridos interactivos (sin dependencias de Vue ni del DOM).
 *
 * Máquina de estados:
 *   idle → navigating → waiting → showing → (navigating | waiting | showing) … → finishing → idle
 *
 * Garantías:
 * - Nunca se queda "cargando": la navegación y la espera del objetivo tienen
 *   límite de tiempo. Si el objetivo no aparece (por permisos o diseño
 *   responsivo) el paso se salta y el recorrido continúa en la misma dirección.
 * - Un segundo clic mientras se navega o se espera se ignora (no avanza dos pasos).
 * - Cada transición lleva un token: una operación asíncrona vieja nunca pisa
 *   a una nueva (p. ej. si la persona sale a mitad de una navegación).
 *
 * Este archivo solo usa `import type` para poder probarse con `node --test`.
 */

export type TourPhase = 'idle' | 'navigating' | 'waiting' | 'showing' | 'finishing'

export interface EngineStep {
    id: string
    /** Clave `data-tour` del elemento a resaltar; sin ella el paso se muestra centrado. */
    target?: string | null
    /** Ruta (nombre) donde vive el paso; si no es la actual, se navega primero. */
    route?: string | null
}

export interface EngineTour<S extends EngineStep = EngineStep> {
    id: string
    steps: S[]
}

export interface EngineDeps<S extends EngineStep, T = unknown> {
    isOnRoute(route: string): boolean
    /** Resuelve cuando la navegación terminó (éxito o error). */
    navigate(route: string): Promise<void>
    /** Devuelve el objetivo visible o null. */
    findTarget(step: S): T | null
    sleep(ms: number): Promise<void>
    onChange(state: EngineState<S, T>): void
    onFinish?(completed: boolean): void
    waitTimeoutMs?: number
    pollMs?: number
    navigationTimeoutMs?: number
}

export interface EngineState<S extends EngineStep, T = unknown> {
    phase: TourPhase
    tours: EngineTour<S>[]
    tourIndex: number
    stepIndex: number
    step: S | null
    target: T | null
    /** Pasos saltados automáticamente por no encontrar su objetivo. */
    skipped: string[]
}

export function createTourEngine<S extends EngineStep, T = unknown>(deps: EngineDeps<S, T>) {
    const waitTimeout = deps.waitTimeoutMs ?? 2500
    const poll = deps.pollMs ?? 100
    const navTimeout = deps.navigationTimeoutMs ?? 8000

    const state: EngineState<S, T> = { phase: 'idle', tours: [], tourIndex: 0, stepIndex: 0, step: null, target: null, skipped: [] }
    let token = 0

    const emit = () => deps.onChange({ ...state, skipped: [...state.skipped] })
    const set = (patch: Partial<EngineState<S, T>>) => {
        Object.assign(state, patch)
        emit()
    }
    const busy = () => state.phase === 'navigating' || state.phase === 'waiting' || state.phase === 'finishing'

    /** Posición siguiente/anterior a través de todos los recorridos. */
    function neighbor(tourIndex: number, stepIndex: number, dir: 1 | -1): [number, number] | null {
        let t = tourIndex
        let s = stepIndex + dir
        while (t >= 0 && t < state.tours.length) {
            const steps = state.tours[t]!.steps
            if (s >= 0 && s < steps.length) return [t, s]
            t += dir
            if (t < 0 || t >= state.tours.length) return null
            s = dir === 1 ? 0 : state.tours[t]!.steps.length - 1
        }
        return null
    }

    async function waitForTarget(step: S, my: number): Promise<T | null> {
        if (!step.target) return null
        const started = Date.now()
        // Al menos un intento, luego sondeo limitado.
        for (;;) {
            if (my !== token) return null
            const found = deps.findTarget(step)
            if (found) return found
            if (Date.now() - started >= waitTimeout) return null
            await deps.sleep(poll)
        }
    }

    async function go(tourIndex: number, stepIndex: number, dir: 1 | -1): Promise<void> {
        const my = ++token
        const tour = state.tours[tourIndex]
        const step = tour?.steps[stepIndex]
        if (!tour || !step) return finish(true)

        set({ tourIndex, stepIndex, step, target: null })

        if (step.route && !deps.isOnRoute(step.route)) {
            set({ phase: 'navigating' })
            await Promise.race([deps.navigate(step.route).catch(() => undefined), deps.sleep(navTimeout)])
            if (my !== token) return
        }

        set({ phase: 'waiting' })
        const target = await waitForTarget(step, my)
        if (my !== token) return

        if (step.target && !target) {
            // Objetivo inexistente: se salta sin bloquear el recorrido.
            state.skipped.push(step.id)
            const nextPos = neighbor(tourIndex, stepIndex, dir)
            if (nextPos) return go(nextPos[0], nextPos[1], dir)
            // Al retroceder sin más pasos se queda en el primero mostrable; al avanzar, termina.
            return dir === 1 ? finish(true) : go(tourIndex, stepIndex, 1)
        }

        set({ phase: 'showing', target })
    }

    function finish(completed: boolean) {
        token++
        set({ phase: 'finishing' })
        deps.onFinish?.(completed)
        set({ phase: 'idle', tours: [], step: null, target: null, tourIndex: 0, stepIndex: 0 })
    }

    return {
        get state() {
            return state
        },
        start(tours: EngineTour<S>[], from = { tourIndex: 0, stepIndex: 0 }) {
            const usable = tours.filter((t) => t.steps.length > 0)
            if (!usable.length) return Promise.resolve()
            state.tours = usable
            state.skipped = []

            return go(Math.min(from.tourIndex, usable.length - 1), from.stepIndex, 1)
        },
        next() {
            if (busy() || state.phase === 'idle') return Promise.resolve()
            const pos = neighbor(state.tourIndex, state.stepIndex, 1)

            return pos ? go(pos[0], pos[1], 1) : Promise.resolve(finish(true))
        },
        prev() {
            if (busy() || state.phase === 'idle') return Promise.resolve()
            const pos = neighbor(state.tourIndex, state.stepIndex, -1)

            return pos ? go(pos[0], pos[1], -1) : Promise.resolve()
        },
        /** Salta el paso actual (igual que siguiente, pero también funciona mientras se espera). */
        skipStep() {
            if (state.phase === 'idle' || state.phase === 'finishing') return Promise.resolve()
            const pos = neighbor(state.tourIndex, state.stepIndex, 1)

            return pos ? go(pos[0], pos[1], 1) : Promise.resolve(finish(true))
        },
        /** Salta al primer paso del siguiente módulo. */
        skipModule() {
            if (state.phase === 'idle' || state.phase === 'finishing') return Promise.resolve()
            const t = state.tourIndex + 1

            return t < state.tours.length ? go(t, 0, 1) : Promise.resolve(finish(true))
        },
        exit() {
            if (state.phase !== 'idle') finish(false)
        },
        /** Termina el recorrido como completado. */
        complete() {
            if (state.phase !== 'idle') finish(true)
        },
        /** Posición global (para la barra de progreso). */
        progress() {
            const total = state.tours.reduce((a, t) => a + t.steps.length, 0)
            const before = state.tours.slice(0, state.tourIndex).reduce((a, t) => a + t.steps.length, 0)

            return { current: before + state.stepIndex + 1, total }
        },
    }
}

export type TourEngine<S extends EngineStep, T = unknown> = ReturnType<typeof createTourEngine<S, T>>
