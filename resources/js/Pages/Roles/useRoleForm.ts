import { computed, reactive, ref, watch } from 'vue'

/* ------------------------------------------------------------------ Tipos */

export type ScopeKey = 'none' | 'own' | 'sucursal' | 'corporativo' | 'global'

export type PermissionItem = {
    name: string
    label: string
    description: string
    sensitive: boolean
    /** Permisos que se agregan automáticamente al marcar éste. */
    requires: string[]
    /** ¿La acción necesita poder ver registros del módulo? */
    scoped: boolean
}

export type ScopeOption = { level: Exclude<ScopeKey, 'none'>; name: string; label: string; description: string }

export type ModuleUi = {
    key: string
    label: string
    description: string
    scope: ScopeOption[]
    groups: { key: string; label: string; permissions: PermissionItem[] }[]
}

export type RoleFilter = 'todos' | 'seleccionados' | 'sin_acceso' | 'advertencias'

const LEVELS: ScopeKey[] = ['none', 'own', 'sucursal', 'corporativo', 'global']
const NOTIF_MODULE = 'notificaciones'

const normalizeText = (s: string) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')

/**
 * Estado y reglas del formulario de roles. Replica en el navegador la
 * normalización del servidor (PermissionCatalog::normalize) para que la
 * persona vea exactamente lo que se guardará:
 * - un solo nivel de alcance por módulo (elegir uno superior incluye los inferiores);
 * - una acción agrega como máximo el alcance MÍNIMO del módulo, nunca el global;
 * - las dependencias (`requires`) se agregan solas y al quitar un permiso se
 *   quitan los que dependen de él;
 * - recibir notificaciones exige al menos "Ver mis notificaciones".
 */
export function useRoleForm(modules: ModuleUi[], initial: string[], opts: { locked: () => boolean; receives: () => boolean }) {
    const byName = new Map<string, { module: ModuleUi; item?: PermissionItem; scope?: ScopeOption }>()
    for (const m of modules) {
        for (const s of m.scope) byName.set(s.name, { module: m, scope: s })
        for (const g of m.groups) for (const p of g.permissions) byName.set(p.name, { module: m, item: p })
    }
    const actionsOf = (m: ModuleUi) => m.groups.flatMap((g) => g.permissions)
    const allNames = modules.flatMap((m) => [...m.scope.map((s) => s.name), ...actionsOf(m).map((p) => p.name)])

    const selected = ref<Set<string>>(new Set(initial.filter((n) => byName.has(n))))
    /** Avisos breves de cambios automáticos (dependencias, alcance mínimo). */
    const notices = reactive<Record<string, string>>({})

    const has = (name: string) => selected.value.has(name)
    const commit = (next: Set<string>) => (selected.value = next)

    /* ---------------- Alcance ---------------- */

    function scopeOf(m: ModuleUi): ScopeKey {
        let best: ScopeKey = 'none'
        for (const s of m.scope) if (has(s.name) && LEVELS.indexOf(s.level) > LEVELS.indexOf(best)) best = s.level
        return best
    }

    function applyScope(next: Set<string>, m: ModuleUi, level: ScopeKey) {
        for (const s of m.scope) next.delete(s.name)
        const opt = m.scope.find((s) => s.level === level)
        if (opt) next.add(opt.name)
    }

    /** Nivel mínimo permitido (p. ej. notificaciones cuando el rol recibe avisos). */
    const minScope = (m: ModuleUi): ScopeKey => (m.key === NOTIF_MODULE && opts.receives() ? 'own' : 'none')

    function setScope(m: ModuleUi, level: ScopeKey) {
        if (opts.locked()) return
        if (LEVELS.indexOf(level) < LEVELS.indexOf(minScope(m))) level = minScope(m)
        const next = new Set(selected.value)
        applyScope(next, m, level)
        delete notices[m.key]
        // "Sin acceso" no deja acciones que necesitan ver registros.
        if (level === 'none') {
            const removed = actionsOf(m).filter((p) => p.scoped && next.has(p.name))
            removed.forEach((p) => removeWithDependents(next, p.name))
            if (removed.length) notices[m.key] = `Se quitaron ${removed.length} acción(es) que necesitan ver registros del módulo.`
        }
        commit(next)
    }

    /* ---------------- Acciones ---------------- */

    function addWithRequirements(next: Set<string>, name: string, trail = new Set<string>()) {
        if (trail.has(name)) return
        trail.add(name)
        next.add(name)
        const info = byName.get(name)
        for (const dep of info?.item?.requires ?? []) addWithRequirements(next, dep, trail)
        if (info?.item?.scoped && info.module.scope.length && !info.module.scope.some((s) => next.has(s.name))) {
            const minimal = info.module.scope[0]!
            next.add(minimal.name)
            notices[info.module.key] = `Se asignó «${minimal.label}» porque la acción necesita ver registros. Puedes ampliarlo si hace falta.`
        }
    }

    function removeWithDependents(next: Set<string>, name: string) {
        next.delete(name)
        for (const [other, info] of byName) {
            if (next.has(other) && info.item?.requires.includes(name)) removeWithDependents(next, other)
        }
    }

    function toggle(name: string) {
        if (opts.locked()) return
        const next = new Set(selected.value)
        if (next.has(name)) removeWithDependents(next, name)
        else addWithRequirements(next, name)
        commit(next)
    }

    /** "Todos" = alcance global + todas las acciones; "Ninguno" = sin acceso. */
    function setModule(m: ModuleUi, on: boolean) {
        if (opts.locked()) return
        const next = new Set(selected.value)
        if (on) {
            const top = m.scope[m.scope.length - 1]
            if (top) applyScope(next, m, top.level)
            actionsOf(m).forEach((p) => addWithRequirements(next, p.name))
            delete notices[m.key]
        } else {
            actionsOf(m).forEach((p) => next.delete(p.name))
            applyScope(next, m, minScope(m))
            // Otros módulos que dependían de estas acciones también se ajustan.
            for (const p of actionsOf(m)) removeWithDependents(next, p.name)
        }
        commit(next)
    }

    // Recibir notificaciones exige al menos "Ver mis notificaciones".
    watch(opts.receives, (receives) => {
        const m = modules.find((x) => x.key === NOTIF_MODULE)
        if (receives && m && scopeOf(m) === 'none') {
            const next = new Set(selected.value)
            applyScope(next, m, 'own')
            commit(next)
        }
    }, { immediate: true })

    /* ---------------- Conteos y advertencias ---------------- */

    const moduleTotal = (m: ModuleUi) => (m.scope.length ? 1 : 0) + actionsOf(m).length
    const moduleCount = (m: ModuleUi) => (scopeOf(m) !== 'none' ? 1 : 0) + actionsOf(m).filter((p) => has(p.name)).length

    const isGlobalScope = (m: ModuleUi) => scopeOf(m) === 'global' && m.scope.length > 1
    const sensitiveSelected = (m: ModuleUi) => actionsOf(m).filter((p) => p.sensitive && has(p.name))
    const moduleWarnings = (m: ModuleUi) => isGlobalScope(m) || sensitiveSelected(m).length > 0

    const totals = computed(() => ({
        selected: modules.reduce((a, m) => a + moduleCount(m), 0),
        total: modules.reduce((a, m) => a + moduleTotal(m), 0),
        modules: modules.filter((m) => moduleCount(m) > 0).length,
    }))

    /* ---------------- Filtros y búsqueda ---------------- */

    const q = ref('')
    const filter = ref<RoleFilter>('todos')

    const visibleModules = computed(() => {
        const term = normalizeText(q.value.trim())
        return modules
            .filter((m) => {
                if (filter.value === 'seleccionados') return moduleCount(m) > 0
                if (filter.value === 'sin_acceso') return moduleCount(m) === 0
                if (filter.value === 'advertencias') return moduleWarnings(m)
                return true
            })
            .map((m) => {
                if (!term || normalizeText(m.label).includes(term)) return { module: m, groups: m.groups, matchScope: true }
                const groups = m.groups
                    .map((g) => ({ ...g, permissions: g.permissions.filter((p) => normalizeText(p.label).includes(term) || normalizeText(p.description).includes(term)) }))
                    .filter((g) => g.permissions.length > 0)
                const matchScope = m.scope.some((s) => normalizeText(s.label).includes(term))
                return { module: m, groups, matchScope }
            })
            .filter((v) => v.matchScope || v.groups.length > 0)
    })

    /* ---------------- Resumen y diferencias ---------------- */

    /** Conjunto efectivo: un nivel de alcance incluye los inferiores del módulo. */
    function effective(set: Set<string>): Set<string> {
        const out = new Set(set)
        for (const m of modules) {
            const top = m.scope.reduce((acc, s, i) => (set.has(s.name) ? i : acc), -1)
            for (let i = 0; i <= top; i++) out.add(m.scope[i]!.name)
        }
        return out
    }

    const original = new Set(initial.filter((n) => byName.has(n)))
    const removed = computed(() => {
        const now = effective(selected.value)
        return [...effective(original)].filter((n) => !now.has(n)).map(describe)
    })
    const added = computed(() => {
        const before = effective(original)
        return [...selected.value].filter((n) => !before.has(n)).map(describe)
    })

    function describe(name: string) {
        const info = byName.get(name)
        return { name, label: info?.item?.label ?? info?.scope?.label ?? 'Permiso', module: info?.module.label ?? '' }
    }

    const summary = computed(() => ({
        modules: modules.filter((m) => moduleCount(m) > 0).map((m) => ({
            label: m.label,
            scope: m.scope.find((s) => s.level === scopeOf(m))?.label ?? null,
            actions: actionsOf(m).filter((p) => has(p.name)).length,
        })),
        globals: modules.filter(isGlobalScope).map((m) => ({ module: m.label, label: m.scope.find((s) => s.level === 'global')!.label })),
        sensitive: modules.flatMap((m) => sensitiveSelected(m).map((p) => ({ module: m.label, label: p.label }))),
    }))

    /** Lista a enviar, en el orden del catálogo. */
    const payload = () => allNames.filter((n) => selected.value.has(n))

    return {
        selected, has, notices, scopeOf, setScope, minScope, toggle, setModule,
        moduleCount, moduleTotal, isGlobalScope, sensitiveSelected, moduleWarnings, totals,
        q, filter, visibleModules, removed, added, summary, payload, allNames,
    }
}
