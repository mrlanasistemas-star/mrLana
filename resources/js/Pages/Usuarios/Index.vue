<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Eye, Loader2, Pencil, Plus, RotateCcw, Search, UserCheck, UserCog, UserX, Users } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ConfirmDialog } from '@/Components/ui/dialog'
import { usePermissions } from '@/Composables/usePermissions'
import { useFlashSuccess } from '@/Composables/useFlashSuccess'
import type { Paginated } from '@/types/shared'

type Usuario = {
    id: number
    name: string
    email: string
    activo: boolean
    roles: string[]
    empleado_id: number | null
    colaborador: { id: number; nombre: string; puesto: string | null } | null
    created_at: string | null
}

type Estado = 'todos' | 'activos' | 'inactivos'

const props = defineProps<{
    users: Paginated<Usuario>
    roles: { id: number; name: string }[]
    filters: { q: string; estado: Estado; role_id: number | null }
    counts: { total: number; activos: number; inactivos: number }
    can: { registrar: boolean; editar: boolean; desactivar: boolean; reactivar: boolean }
}>()

useFlashSuccess()
const { user: authUser } = usePermissions()

/* ---------- Filtros ---------- */
const f = reactive({ q: props.filters.q ?? '', estado: props.filters.estado ?? 'todos', role_id: props.filters.role_id ?? null })
const loading = ref(false)
let timer: number | undefined

const params = () => ({
    q: f.q.trim() || undefined,
    estado: f.estado !== 'todos' ? f.estado : undefined,
    role_id: f.role_id || undefined,
})

function reload() {
    router.get(route('usuarios.index'), params(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['users', 'filters', 'counts'],
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    })
}

watch(() => f.q, () => {
    window.clearTimeout(timer)
    timer = window.setTimeout(reload, 350)
})
watch(() => [f.estado, f.role_id], reload)

const hayFiltros = computed(() => Boolean(f.q || f.estado !== 'todos' || f.role_id))
function limpiar() {
    Object.assign(f, { q: '', estado: 'todos', role_id: null })
}

const stats = computed(() => [
    { key: 'todos' as const, label: 'Total', value: props.counts.total, icon: Users },
    { key: 'activos' as const, label: 'Activos', value: props.counts.activos, icon: UserCheck },
    { key: 'inactivos' as const, label: 'Inactivos', value: props.counts.inactivos, icon: UserX },
])

/* ---------- Activar / desactivar ---------- */
const isSelf = (u: Usuario) => authUser.value?.id === u.id
const canToggle = (u: Usuario) => (u.activo ? props.can.desactivar && !isSelf(u) : props.can.reactivar)

const confirm = reactive({ open: false, target: null as Usuario | null, loading: false, error: null as string | null })
function askToggle(u: Usuario) {
    Object.assign(confirm, { open: true, target: u, loading: false, error: null })
}
function doToggle() {
    const u = confirm.target
    if (!u) return
    confirm.loading = true
    const name = u.activo ? 'usuarios.deactivate' : 'usuarios.activate'
    router.patch(route(name, u.id), {}, {
        preserveScroll: true,
        onSuccess: () => (confirm.open = false),
        onError: (e: Record<string, string>) => (confirm.error = e.user ?? Object.values(e)[0] ?? "No se pudo completar la acción."),
        onFinish: () => (confirm.loading = false),
    })
}

/* ---------- Paginación ---------- */
const goPage = (url: string | null) =>
    url && router.visit(url, { preserveScroll: true, preserveState: true, onStart: () => (loading.value = true), onFinish: () => (loading.value = false) })
const pageLabel = (l: string) =>
    l.replace('&laquo;', '«').replace('&raquo;', '»').replace(/Previous|pagination\.previous/i, 'Anterior').replace(/Next|pagination\.next/i, 'Siguiente')

const initials = (name: string) =>
    name.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]?.toUpperCase()).join('') || '?'
</script>

<template>
    <Head title="Usuarios" />

    <AuthenticatedLayout>
        <template #header>Usuarios</template>

        <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">Usuarios</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400">
                        Cuentas que pueden iniciar sesión. Cada cuenta tiene un rol y puede vincularse con un colaborador.
                    </p>
                </div>
                <Link v-if="can.registrar" :href="route('usuarios.create')" class="ui-btn-primary self-start lg:self-auto">
                    <Plus class="h-4 w-4" aria-hidden="true" /> Registrar usuario
                </Link>
            </section>

            <!-- Indicadores (también filtran por estado) -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <button
                    v-for="card in stats"
                    :key="card.key"
                    type="button"
                    class="flex items-center gap-3 rounded-2xl border p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50"
                    :class="f.estado === card.key
                        ? 'border-brand-primary/40 bg-brand-primary/[0.06] dark:bg-brand-primary/10'
                        : 'border-slate-200/70 bg-white hover:bg-slate-50 dark:border-white/10 dark:bg-zinc-900/70 dark:hover:bg-white/5'"
                    :aria-pressed="f.estado === card.key"
                    @click="f.estado = card.key"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-zinc-200">
                        <component :is="card.icon" class="h-5 w-5" aria-hidden="true" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ card.label }}</span>
                        <span class="block text-2xl font-black tabular-nums text-slate-900 dark:text-zinc-100">{{ card.value }}</span>
                    </span>
                </button>
            </div>

            <!-- Filtros -->
            <section class="ui-card p-4 sm:p-5" aria-label="Filtros">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <div class="md:col-span-7">
                        <label for="usr-q" class="ui-label">Buscar</label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input id="usr-q" v-model="f.q" type="search" placeholder="Nombre, correo o colaborador…" class="ui-input pl-9" autocomplete="off" />
                        </div>
                    </div>
                    <div class="md:col-span-5">
                        <label for="usr-rol" class="ui-label">Rol</label>
                        <select id="usr-rol" v-model="f.role_id" class="ui-input">
                            <option :value="null">Todos los roles</option>
                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400">Estado:</span>
                    <button
                        v-for="opt in ([['todos', 'Todos'], ['activos', 'Activos'], ['inactivos', 'Inactivos']] as const)"
                        :key="opt[0]"
                        type="button"
                        class="ui-chip"
                        :class="f.estado === opt[0] ? 'ui-chip-on' : ''"
                        :aria-pressed="f.estado === opt[0]"
                        @click="f.estado = opt[0]"
                    >
                        {{ opt[1] }}
                    </button>
                    <span v-if="loading" class="inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-zinc-400" role="status">
                        <Loader2 class="h-3.5 w-3.5 animate-spin" aria-hidden="true" /> Cargando…
                    </span>
                    <button
                        v-if="hayFiltros"
                        type="button"
                        class="ml-auto inline-flex min-h-[36px] items-center gap-1.5 text-xs font-semibold text-slate-600 underline-offset-2 hover:underline dark:text-zinc-300"
                        @click="limpiar"
                    >
                        <RotateCcw class="h-3.5 w-3.5" aria-hidden="true" /> Limpiar filtros
                    </button>
                </div>
            </section>

            <!-- Listado -->
            <section class="ui-card overflow-hidden transition-opacity" :class="loading ? 'opacity-60' : ''" :aria-busy="loading">
                <div v-if="users.data.length === 0" class="flex flex-col items-center gap-3 p-10 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-zinc-400">
                        <UserCog class="h-6 w-6" aria-hidden="true" />
                    </span>
                    <p class="text-sm font-semibold text-slate-700 dark:text-zinc-200">
                        {{ hayFiltros ? 'Ningún usuario coincide con los filtros.' : 'Aún no hay usuarios registrados.' }}
                    </p>
                    <button v-if="hayFiltros" type="button" class="ui-btn-secondary" @click="limpiar">Limpiar filtros</button>
                    <Link v-else-if="can.registrar" :href="route('usuarios.create')" class="ui-btn-primary">
                        <Plus class="h-4 w-4" aria-hidden="true" /> Registrar usuario
                    </Link>
                </div>

                <!-- Escritorio -->
                <div v-else class="hidden lg:block">
                    <table class="w-full table-fixed text-sm">
                        <caption class="sr-only">Listado de usuarios</caption>
                        <thead>
                            <tr class="border-b border-slate-100 text-left text-[11px] uppercase tracking-wider text-slate-500 dark:border-white/5 dark:text-zinc-400">
                                <th scope="col" class="w-[32%] px-5 py-3 font-semibold">Usuario</th>
                                <th scope="col" class="w-[16%] px-3 py-3 font-semibold">Rol</th>
                                <th scope="col" class="w-[22%] px-3 py-3 font-semibold">Colaborador</th>
                                <th scope="col" class="w-[10%] px-3 py-3 font-semibold">Estado</th>
                                <th scope="col" class="w-[20%] px-5 py-3 text-right font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            <tr v-for="u in users.data" :key="u.id" class="align-top" :class="u.activo ? '' : 'opacity-70'">
                                <td class="px-5 py-3.5">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-xs font-black text-brand-primary" aria-hidden="true">
                                            {{ initials(u.name) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="break-words font-semibold text-slate-900 dark:text-zinc-100">
                                                {{ u.name }}
                                                <span v-if="isSelf(u)" class="ui-badge ui-badge-accent ml-1 align-middle">Tú</span>
                                            </p>
                                            <p class="text-xs text-slate-500 [overflow-wrap:anywhere] dark:text-zinc-400">{{ u.email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span v-if="u.roles.length" class="ui-badge ui-badge-muted break-words">{{ u.roles.join(', ') }}</span>
                                    <span v-else class="ui-badge ui-badge-warn">Sin rol</span>
                                </td>
                                <td class="px-3 py-3.5 text-xs text-slate-600 dark:text-zinc-300">
                                    <template v-if="u.colaborador">
                                        <p class="break-words font-semibold">{{ u.colaborador.nombre }}</p>
                                        <p class="break-words text-slate-500 dark:text-zinc-400">{{ u.colaborador.puesto || 'Sin puesto' }}</p>
                                    </template>
                                    <span v-else class="text-slate-400 dark:text-zinc-500">Sin vincular</span>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="ui-badge" :class="u.activo ? 'ui-badge-ok' : 'ui-badge-muted'">{{ u.activo ? 'Activo' : 'Inactivo' }}</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <Link :href="route('usuarios.edit', u.id)" class="ui-btn-sm">
                                            <component :is="can.editar ? Pencil : Eye" class="h-4 w-4" aria-hidden="true" />
                                            {{ can.editar ? 'Editar' : 'Ver' }}
                                        </Link>
                                        <button
                                            v-if="canToggle(u)"
                                            type="button"
                                            class="ui-btn-sm"
                                            :class="u.activo ? 'ui-btn-sm-danger' : ''"
                                            @click="askToggle(u)"
                                        >
                                            {{ u.activo ? 'Desactivar' : 'Reactivar' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Móvil / tableta -->
                <ul v-if="users.data.length" class="divide-y divide-slate-100 dark:divide-white/5 lg:hidden">
                    <li v-for="u in users.data" :key="u.id" class="space-y-3 p-4" :class="u.activo ? '' : 'opacity-75'">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-xs font-black text-brand-primary" aria-hidden="true">
                                    {{ initials(u.name) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="break-words font-semibold text-slate-900 dark:text-zinc-100">
                                        {{ u.name }} <span v-if="isSelf(u)" class="ui-badge ui-badge-accent align-middle">Tú</span>
                                    </p>
                                    <p class="text-xs text-slate-500 [overflow-wrap:anywhere] dark:text-zinc-400">{{ u.email }}</p>
                                </div>
                            </div>
                            <span class="ui-badge shrink-0" :class="u.activo ? 'ui-badge-ok' : 'ui-badge-muted'">{{ u.activo ? 'Activo' : 'Inactivo' }}</span>
                        </div>
                        <dl class="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                            <div class="min-w-0">
                                <dt class="font-semibold text-slate-500 dark:text-zinc-400">Rol</dt>
                                <dd class="break-words text-slate-800 dark:text-zinc-200">{{ u.roles.join(', ') || 'Sin rol' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="font-semibold text-slate-500 dark:text-zinc-400">Colaborador</dt>
                                <dd class="break-words text-slate-800 dark:text-zinc-200">{{ u.colaborador?.nombre || 'Sin vincular' }}</dd>
                            </div>
                        </dl>
                        <div class="flex flex-wrap gap-2">
                            <Link :href="route('usuarios.edit', u.id)" class="ui-btn-sm">
                                <component :is="can.editar ? Pencil : Eye" class="h-4 w-4" aria-hidden="true" />
                                {{ can.editar ? 'Editar' : 'Ver' }}
                            </Link>
                            <button v-if="canToggle(u)" type="button" class="ui-btn-sm" :class="u.activo ? 'ui-btn-sm-danger' : ''" @click="askToggle(u)">
                                {{ u.activo ? 'Desactivar' : 'Reactivar' }}
                            </button>
                        </div>
                    </li>
                </ul>

                <nav v-if="users.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 dark:border-white/5" aria-label="Paginación">
                    <p class="text-xs text-slate-500 dark:text-zinc-400">Mostrando {{ users.from }}–{{ users.to }} de {{ users.total }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="(l, i) in users.links"
                            :key="i"
                            type="button"
                            class="min-h-[38px] min-w-[38px] rounded-xl px-3 text-xs font-semibold transition disabled:opacity-40"
                            :class="l.active ? 'bg-brand-primary text-brand-primary-fg' : 'border border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5'"
                            :disabled="!l.url"
                            :aria-current="l.active ? 'page' : undefined"
                            @click="goPage(l.url)"
                        >
                            {{ pageLabel(l.label) }}
                        </button>
                    </div>
                </nav>
            </section>
        </div>

        <ConfirmDialog
            v-model:open="confirm.open"
            :title="confirm.target?.activo ? 'Desactivar cuenta' : 'Reactivar cuenta'"
            :description="confirm.target?.activo
                ? `${confirm.target?.name} ya no podrá iniciar sesión y se cerrarán sus sesiones activas. Sus registros se conservan.`
                : `${confirm.target?.name ?? ''} podrá volver a iniciar sesión con su contraseña actual.`"
            :confirm-label="confirm.target?.activo ? 'Desactivar' : 'Reactivar'"
            :tone="confirm.target?.activo ? 'danger' : 'success'"
            :loading="confirm.loading"
            :error="confirm.error"
            @confirm="doToggle"
        />
    </AuthenticatedLayout>
</template>
