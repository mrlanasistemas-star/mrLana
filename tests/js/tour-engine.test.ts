/**
 * Pruebas del motor de recorridos (sin navegador): `npm run test:tour`
 * o `node --test tests/js/`. También las ejecuta GuiaTest (PHP).
 */
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createTourEngine, type EngineState, type EngineStep } from '../../resources/js/tour/engine.ts'

type Step = EngineStep & { title?: string }

function setup(present: Set<string>, opts: { route?: string; navigateFails?: boolean } = {}) {
    let route = opts.route ?? 'dashboard'
    const states: EngineState<Step, string>[] = []
    let finished: boolean | null = null
    const engine = createTourEngine<Step, string>({
        isOnRoute: (r) => r === route,
        navigate: async (r) => {
            if (opts.navigateFails) throw new Error('fallo de red')
            route = r
        },
        findTarget: (s) => (s.target && present.has(s.target) ? s.target : null),
        sleep: () => new Promise((r) => setTimeout(r, 1)),
        onChange: (s) => states.push(s),
        onFinish: (c) => (finished = c),
        waitTimeoutMs: 20,
        pollMs: 2,
        navigationTimeoutMs: 50,
    })

    return { engine, states, finished: () => finished, route: () => route }
}

test('muestra el primer paso con objetivo presente', async () => {
    const { engine } = setup(new Set(['a']))
    await engine.start([{ id: 't', steps: [{ id: '1', target: 'a' }] }])
    assert.equal(engine.state.phase, 'showing')
    assert.equal(engine.state.target, 'a')
})

test('no se bloquea cuando falta un objetivo: lo salta y continúa', async () => {
    const { engine } = setup(new Set(['c']))
    await engine.start([{ id: 't', steps: [{ id: '1', target: 'falta' }, { id: '2', target: 'tampoco' }, { id: '3', target: 'c' }] }])
    assert.equal(engine.state.phase, 'showing')
    assert.equal(engine.state.step?.id, '3')
    assert.deepEqual(engine.state.skipped, ['1', '2'])
})

test('si ningún objetivo existe, termina en lugar de quedarse cargando', async () => {
    const { engine, finished } = setup(new Set())
    await engine.start([{ id: 't', steps: [{ id: '1', target: 'x' }, { id: '2', target: 'y' }] }])
    assert.equal(engine.state.phase, 'idle')
    assert.equal(finished(), true)
})

test('un paso sin objetivo se muestra centrado', async () => {
    const { engine } = setup(new Set())
    await engine.start([{ id: 't', steps: [{ id: 'intro' }] }])
    assert.equal(engine.state.phase, 'showing')
    assert.equal(engine.state.target, null)
})

test('navega entre módulos y avanza al siguiente recorrido', async () => {
    const { engine, route } = setup(new Set(['a', 'b']))
    await engine.start([
        { id: 'uno', steps: [{ id: '1', target: 'a', route: 'dashboard' }] },
        { id: 'dos', steps: [{ id: '2', target: 'b', route: 'requisiciones.index' }] },
    ])
    await engine.next()
    assert.equal(route(), 'requisiciones.index')
    assert.equal(engine.state.tourIndex, 1)
    assert.equal(engine.state.phase, 'showing')
    assert.deepEqual(engine.progress(), { current: 2, total: 2 })
})

test('si la navegación falla, no se congela: salta el paso', async () => {
    const { engine, finished } = setup(new Set(['solo-en-otra-ruta']), { navigateFails: true })
    await engine.start([{ id: 't', steps: [{ id: '1', target: 'inexistente', route: 'otra' }] }])
    assert.equal(engine.state.phase, 'idle')
    assert.equal(finished(), true)
})

test('doble clic mientras espera no avanza dos pasos', async () => {
    const present = new Set(['a', 'c'])
    const { engine } = setup(present)
    await engine.start([{ id: 't', steps: [{ id: '1', target: 'a' }, { id: '2', target: 'b-tardio' }, { id: '3', target: 'c' }] }])
    const first = engine.next()
    present.add('b-tardio')
    const second = engine.next() // ignorado: el motor está esperando el objetivo
    await Promise.all([first, second])
    assert.equal(engine.state.step?.id, '2')
})

test('atrás regresa y salir termina sin completar', async () => {
    const { engine, finished } = setup(new Set(['a', 'b']))
    await engine.start([{ id: 't', steps: [{ id: '1', target: 'a' }, { id: '2', target: 'b' }] }])
    await engine.next()
    await engine.prev()
    assert.equal(engine.state.step?.id, '1')
    engine.exit()
    assert.equal(engine.state.phase, 'idle')
    assert.equal(finished(), false)
})

test('saltar módulo va al primer paso del siguiente recorrido', async () => {
    const { engine } = setup(new Set(['a', 'b', 'c']))
    await engine.start([
        { id: 'uno', steps: [{ id: '1', target: 'a' }, { id: '2', target: 'b' }] },
        { id: 'dos', steps: [{ id: '3', target: 'c' }] },
    ])
    await engine.skipModule()
    assert.equal(engine.state.step?.id, '3')
})
