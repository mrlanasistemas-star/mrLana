<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    FileDown,
    FileSpreadsheet,
    KeyRound,
    Loader2,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    ShieldCheck,
    UserMinus,
    UserPlus,
    Users,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import { ConfirmDialog, Dialog, DialogContent, DialogDescription, DialogTitle } from '@/Components/ui/dialog'
import { downloadFile, toQS } from '@/Utils/exports'
import { swalNotify } from '@/lib/swal'
import type { Paginated } from '@/types/shared'

type Colaborador = {
    id: number
    sucursal_id: number
    area_id: number | null
    nombre: string
    apellido_paterno: string
    apellido_materno: string | null
    nombre_completo: string
    email: string | null
    telefono: string | null
    puesto: string | null
    activo: boolean
    sucursal: { id: number; nombre: string; codigo: string | null; corporativo_id: number; corporativo: { id: number; nombre: string } | null } | null
    area: { id: number; nombre: string } | null
    user: { id: number; name: string; email: string; activo: boolean; roles: string[] } | null
}

type Opcion = { id: number; nombre: string; codigo?: string | null; corporativo_id?: number | null; activo?: boolean }

const props = defineProps<{
    colaboradores: Paginated<Colaborador>
    counts: { total: number; con_usuario: number; sin_usuario: number }
    corporativos: Opcion[]
    sucursales: Opcion[]
    areas: Opcion[]
    filters: {
        q: string
        corporativo_id: number | null
        sucursal_id: number | null
        area_id: number | null
        activo: 'all' | '1' | '0'
        acceso: 'all' | 'con' | 'sin'
        per_page: number
    }
    can: {
        registrar: boolean
        editar: boolean
        desactivar: boolean
        reactivar: boolean
        exportar: boolean
        crear_usuario: boolean
        ver_usuario: boolean
    }
}>()

/* ---------- Filtros ---------- */
const f = reactive({ ...props.filters })
let timer: number | undefined

const params = () => ({
    q: f.q || undefined,
    corporativo_id: f.corporativo_id || undefined,
    sucursal_id: f.sucursal_id || undefined,
    area_id: f.area_id || undefined,
    activo: f.activo !== 'all' ? f.activo : undefined,
    acceso: f.acceso !== 'all' ? f.acceso : undefined,
    per_page: f.per_page !== 20 ? f.per_page : undefined,
})

watch(
    () => [f.q, f.corporativo_id, f.sucursal_id, f.area_id, f.activo, f.acceso, f.per_page],
    () => {
        window.clearTimeout(timer)
        timer = window.setTimeout(() => {
            router.get(route('colaboradores.index'), params(), { preserveState: true, preserveScroll: true, replace: true })
        }, 300)
    },
)

watch(() => f.corporativo_id, () => {
    f.sucursal_id = null
    f.area_id = null
})

const sucursalesFiltro = computed(() =>
    props.sucursales.filter((s) => !f.corporativo_id || Number(s.corporativo_id) === Number(f.corporativo_id)),
)
const areasFiltro = computed(() =>
    props.areas.filter((a) => !f.corporativo_id || Number(a.corporativo_id) === Number(f.corporativo_id)),
)

const hayFiltros = computed(() =>
    Boolean(f.q || f.corporativo_id || f.sucursal_id || f.area_id || f.activo !== 'all' || f.acceso !== 'all'),
)
function limpiar() {
    Object.assign(f, { q: '', corporativo_id: null, sucursal_id: null, area_id: null, activo: 'all', acceso: 'all', per_page: 20 })
}

const exportPdf = () => downloadFile(route('colaboradores.export.pdf') + toQS(params()))
const exportExcel = () => downloadFile(route('colaboradores.export.excel') + toQS(params()))

/* ---------- Alta / edición ---------- */
const formOpen = ref(false)
const editing = ref<Colaborador | null>(null)
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const form = reactive({
    corporativo_id: null as number | null,
    sucursal_id: null as number | null,
    area_id: null as number | null,
    nombre: '',
    apellido_paterno: '',
    apellido_materno: '',
    email: '',
    telefono: '',
    puesto: '',
    activo: true,
})

const corporativosActivos = computed(() => props.corporativos.filter((c) => c.activo !== false))
const sucursalesForm = computed(() =>
    props.sucursales.filter((s) => s.activo !== false && (!form.corporativo_id || Number(s.corporativo_id) === Number(form.corporativo_id))),
)
const areasForm = computed(() =>
    props.areas.filter((a) => a.activo !== false && (!form.corporativo_id || Number(a.corporativo_id) === Number(form.corporativo_id))),
)

function openCreate() {
    editing.value = null
    errors.value = {}
    Object.assign(form, {
        corporativo_id: null, sucursal_id: null, area_id: null, nombre: '', apellido_paterno: '', apellido_materno: '',
        email: '', telefono: '', puesto: '', activo: true,
    })
    formOpen.value = true
}

function openEdit(c: Colaborador) {
    editing.value = c
    errors.value = {}
    Object.assign(form, {
        corporativo_id: c.sucursal?.corporativo_id ?? null,
        sucursal_id: c.sucursal_id,
        area_id: c.area_id,
        nombre: c.nombre,
        apellido_paterno: c.apellido_paterno,
        apellido_materno: c.apellido_materno ?? '',
        email: c.email ?? '',
        telefono: c.telefono ?? '',
        puesto: c.puesto ?? '',
        activo: c.activo,
    })
    formOpen.value = true
}

watch(() => form.corporativo_id, (corp, prev) => {
    if (prev === undefined || corp === prev) return
    const suc = props.sucursales.find((s) => s.id === form.sucursal_id)
    if (suc && Number(suc.corporativo_id) !== Number(corp)) form.sucursal_id = null
    const area = props.areas.find((a) => a.id === form.area_id)
    if (area && Number(area.corporativo_id) !== Number(corp)) form.area_id = null
})

function save() {
    saving.value = true
    errors.value = {}
    const payload = { ...form, corporativo_id: undefined }
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false
            swalNotify(editing.value ? 'Colaborador actualizado.' : 'Colaborador registrado.', 'ok')
        },
        onError: (e: Record<string, string>) => (errors.value = e),
        onFinish: () => (saving.value = false),
    }
    if (editing.value) router.put(route('colaboradores.update', editing.value.id), payload, opts)
    else router.post(route('colaboradores.store'), payload, opts)
}

/* ---------- Baja / reactivación ---------- */
const confirm = reactive({ open: false, target: null as Colaborador | null, loading: false })
function askToggle(c: Colaborador) {
    Object.assign(confirm, { open: true, target: c, loading: false })
}
function doToggle() {
    const c = confirm.target
    if (!c) return
    confirm.loading = true
    const opts = { preserveScroll: true, onSuccess: () => (confirm.open = false), onFinish: () => (confirm.loading = false) }
    if (c.activo) router.delete(route('colaboradores.destroy', c.id), opts)
    else router.patch(route('colaboradores.activate', c.id), {}, opts)
}

const goPage = (url: string | null) => url && router.visit(url, { preserveScroll: true, preserveState: true })
const pageLabel = (l: string) => l.replace('&laquo;', '«').replace('&raquo;', '»').replace(/Previous|pagination\.previous/i, 'Anterior').replace(/Next|pagination\.next/i, 'Siguiente')

const inputClass =
    'min-h-[42px] w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 ' +
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/50 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100'
</script>

<template>
    <Head title="Colaboradores" />

    <AuthenticatedLayout>
        <template #header>Colaboradores</template>

        <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <!-- Encabezado y conteos -->
            <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">Colaboradores</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400">Personas de la organización. Una persona puede existir sin cuenta de acceso.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="can.exportar" type="button" class="btn-secondary" @click="exportPdf"><FileDown class="h-4 w-4" aria-hidden="true" /> PDF</button>
                    <button v-if="can.exportar" type="button" class="btn-secondary" @click="exportExcel"><FileSpreadsheet class="h-4 w-4" aria-hidden="true" /> Excel</button>
                    <button v-if="can.registrar" type="button" class="btn-primary" @click="openCreate"><Plus class="h-4 w-4" aria-hidden="true" /> Registrar colaborador</button>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <button
                    v-for="card in [
                        { key: 'all', label: 'Total', value: counts.total, icon: Users },
                        { key: 'con', label: 'Con acceso', value: counts.con_usuario, icon: ShieldCheck },
                        { key: 'sin', label: 'Sin acceso', value: counts.sin_usuario, icon: UserMinus },
                    ] as const"
                    :key="card.key"
                    type="button"
                    class="flex items-center gap-3 rounded-2xl border p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60"
                    :class="f.acceso === card.key
                        ? 'border-brand-primary/50 bg-brand-primary/[0.06] dark:bg-brand-primary/10'
                        : 'border-slate-200/70 bg-white hover:bg-slate-50 dark:border-white/10 dark:bg-zinc-900/70 dark:hover:bg-white/5'"
                    :aria-pressed="f.acceso === card.key"
                    @click="f.acceso = card.key"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-zinc-200">
                        <component :is="card.icon" class="h-5 w-5" aria-hidden="true" />
                    </span>
                    <span>
                        <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ card.label }}</span>
                        <span class="block text-2xl font-black tabular-nums text-slate-900 dark:text-zinc-100">{{ card.value }}</span>
                    </span>
                </button>
            </div>

            <!-- Filtros -->
            <section class="rounded-3xl border border-slate-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-zinc-900/70 sm:p-5" aria-label="Filtros">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-12">
                    <div class="xl:col-span-4">
                        <label for="colab-q" class="text-xs font-semibold text-slate-600 dark:text-zinc-300">Buscar</label>
                        <div class="relative mt-1">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input id="colab-q" v-model="f.q" type="search" placeholder="Nombre, correo, puesto…" :class="[inputClass, 'pl-9']" />
                        </div>
                    </div>
                    <div class="xl:col-span-3">
                        <SearchableSelect v-model="f.corporativo_id" :options="corporativos" label="Corporativo" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" />
                    </div>
                    <div class="xl:col-span-3">
                        <SearchableSelect v-model="f.sucursal_id" :options="sucursalesFiltro" label="Sucursal" placeholder="Todas" :allow-null="true" null-label="Todas" rounded="xl" />
                    </div>
                    <div class="xl:col-span-2">
                        <SearchableSelect v-model="f.area_id" :options="areasFiltro" label="Área" placeholder="Todas" :allow-null="true" null-label="Todas" rounded="xl" />
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400">Estado:</span>
                    <button
                        v-for="opt in ([['all', 'Todos'], ['1', 'Activos'], ['0', 'Inactivos']] as const)"
                        :key="opt[0]"
                        type="button"
                        class="chip"
                        :class="f.activo === opt[0] ? 'chip-on' : ''"
                        :aria-pressed="f.activo === opt[0]"
                        @click="f.activo = opt[0]"
                    >
                        {{ opt[1] }}
                    </button>
                    <span class="ml-2 text-xs font-semibold text-slate-500 dark:text-zinc-400">Acceso:</span>
                    <button
                        v-for="opt in ([['all', 'Todos'], ['con', 'Con usuario'], ['sin', 'Sin usuario']] as const)"
                        :key="opt[0]"
                        type="button"
                        class="chip"
                        :class="f.acceso === opt[0] ? 'chip-on' : ''"
                        :aria-pressed="f.acceso === opt[0]"
                        @click="f.acceso = opt[0]"
                    >
                        {{ opt[1] }}
                    </button>
                    <button v-if="hayFiltros" type="button" class="ml-auto inline-flex min-h-[36px] items-center gap-1.5 text-xs font-semibold text-slate-600 underline-offset-2 hover:underline dark:text-zinc-300" @click="limpiar">
                        <RotateCcw class="h-3.5 w-3.5" aria-hidden="true" /> Limpiar filtros
                    </button>
                </div>
            </section>

            <!-- Listado -->
            <section class="overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-sm dark:border-white/10 dark:bg-zinc-900/70">
                <p v-if="colaboradores.data.length === 0" class="p-10 text-center text-sm text-slate-500 dark:text-zinc-400">
                    No hay colaboradores con los filtros actuales.
                </p>

                <div v-else class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Listado de colaboradores</caption>
                        <thead>
                            <tr class="border-b border-slate-100 text-left text-[11px] uppercase tracking-wider text-slate-500 dark:border-white/5 dark:text-zinc-400">
                                <th scope="col" class="px-5 py-3 font-semibold">Colaborador</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Organización</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Acceso al sistema</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Estado</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            <tr v-for="c in colaboradores.data" :key="c.id" class="align-top" :class="c.activo ? '' : 'opacity-70'">
                                <td class="max-w-[18rem] px-5 py-3.5">
                                    <p class="break-words font-semibold text-slate-900 dark:text-zinc-100">{{ c.nombre_completo }}</p>
                                    <p class="break-words text-xs text-slate-500 dark:text-zinc-400">{{ c.puesto || 'Sin puesto' }}<template v-if="c.email"> · <span class="[overflow-wrap:anywhere]">{{ c.email }}</span></template></p>
                                </td>
                                <td class="px-3 py-3.5 text-xs text-slate-600 dark:text-zinc-300">
                                    <p class="font-semibold">{{ c.sucursal?.corporativo?.nombre || '—' }}</p>
                                    <p>{{ c.sucursal?.nombre || '—' }}<template v-if="c.area"> · {{ c.area.nombre }}</template></p>
                                </td>
                                <td class="px-3 py-3.5">
                                    <template v-if="c.user">
                                        <span class="badge badge-info"><ShieldCheck class="h-3.5 w-3.5" aria-hidden="true" /> Con acceso</span>
                                        <p class="mt-1 break-words text-xs text-slate-600 [overflow-wrap:anywhere] dark:text-zinc-300">{{ c.user.email }}</p>
                                        <p class="text-xs text-slate-500 dark:text-zinc-400">{{ c.user.roles.join(', ') || 'Sin rol' }} · {{ c.user.activo ? 'cuenta activa' : 'cuenta inactiva' }}</p>
                                    </template>
                                    <span v-else class="badge badge-muted">Sin acceso</span>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="badge" :class="c.activo ? 'badge-ok' : 'badge-muted'">{{ c.activo ? 'Activo' : 'Inactivo' }}</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <Link v-if="c.user && can.ver_usuario" :href="route('usuarios.edit', c.user.id)" class="btn-sm"><KeyRound class="h-4 w-4" aria-hidden="true" /> Administrar acceso</Link>
                                        <Link v-else-if="!c.user && can.crear_usuario && c.activo" :href="route('usuarios.create', { colaborador: c.id })" class="btn-sm"><UserPlus class="h-4 w-4" aria-hidden="true" /> Crear usuario</Link>
                                        <button v-if="can.editar" type="button" class="btn-sm" @click="openEdit(c)"><Pencil class="h-4 w-4" aria-hidden="true" /> Editar</button>
                                        <button v-if="c.activo && can.desactivar" type="button" class="btn-sm btn-sm-danger" @click="askToggle(c)">Dar de baja</button>
                                        <button v-else-if="!c.activo && can.reactivar" type="button" class="btn-sm" @click="askToggle(c)">Reactivar</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul v-if="colaboradores.data.length" class="divide-y divide-slate-100 dark:divide-white/5 lg:hidden">
                    <li v-for="c in colaboradores.data" :key="c.id" class="space-y-3 p-4" :class="c.activo ? '' : 'opacity-75'">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words font-semibold text-slate-900 dark:text-zinc-100">{{ c.nombre_completo }}</p>
                                <p class="break-words text-xs text-slate-500 dark:text-zinc-400">{{ c.puesto || 'Sin puesto' }}</p>
                            </div>
                            <span class="badge shrink-0" :class="c.activo ? 'badge-ok' : 'badge-muted'">{{ c.activo ? 'Activo' : 'Inactivo' }}</span>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-zinc-300">{{ c.sucursal?.corporativo?.nombre || '—' }} · {{ c.sucursal?.nombre || '—' }}<template v-if="c.area"> · {{ c.area.nombre }}</template></p>
                        <div>
                            <span v-if="c.user" class="badge badge-info"><ShieldCheck class="h-3.5 w-3.5" aria-hidden="true" /> Con acceso · {{ c.user.roles.join(', ') || 'Sin rol' }}</span>
                            <span v-else class="badge badge-muted">Sin acceso</span>
                            <p v-if="c.user" class="mt-1 break-words text-xs text-slate-500 [overflow-wrap:anywhere]">{{ c.user.email }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Link v-if="c.user && can.ver_usuario" :href="route('usuarios.edit', c.user.id)" class="btn-sm"><KeyRound class="h-4 w-4" aria-hidden="true" /> Administrar acceso</Link>
                            <Link v-else-if="!c.user && can.crear_usuario && c.activo" :href="route('usuarios.create', { colaborador: c.id })" class="btn-sm"><UserPlus class="h-4 w-4" aria-hidden="true" /> Crear usuario</Link>
                            <button v-if="can.editar" type="button" class="btn-sm" @click="openEdit(c)"><Pencil class="h-4 w-4" aria-hidden="true" /> Editar</button>
                            <button v-if="c.activo && can.desactivar" type="button" class="btn-sm btn-sm-danger" @click="askToggle(c)">Dar de baja</button>
                            <button v-else-if="!c.activo && can.reactivar" type="button" class="btn-sm" @click="askToggle(c)">Reactivar</button>
                        </div>
                    </li>
                </ul>

                <nav v-if="colaboradores.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 dark:border-white/5" aria-label="Paginación">
                    <p class="text-xs text-slate-500 dark:text-zinc-400">Mostrando {{ colaboradores.from }}–{{ colaboradores.to }} de {{ colaboradores.total }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="(l, i) in colaboradores.links"
                            :key="i"
                            type="button"
                            class="min-h-[36px] min-w-[36px] rounded-xl px-3 text-xs font-semibold transition disabled:opacity-40"
                            :class="l.active ? 'bg-slate-900 text-white dark:bg-zinc-300 dark:text-zinc-900' : 'border border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5'"
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

        <!-- Modal alta / edición -->
        <Dialog v-model:open="formOpen">
            <DialogContent class="max-w-2xl">
                <div class="pr-8">
                    <DialogTitle class="text-lg font-bold text-slate-900 dark:text-zinc-100">{{ editing ? 'Editar colaborador' : 'Registrar colaborador' }}</DialogTitle>
                    <DialogDescription class="text-sm text-slate-500 dark:text-zinc-400">
                        Datos laborales. La cuenta de acceso se administra por separado en Usuarios.
                    </DialogDescription>
                </div>
                <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
                    <div>
                        <label for="col-nombre" class="field-label">Nombre *</label>
                        <input id="col-nombre" v-model="form.nombre" maxlength="120" :class="inputClass" />
                        <p v-if="errors.nombre" class="field-error" role="alert">{{ errors.nombre }}</p>
                    </div>
                    <div>
                        <label for="col-ap" class="field-label">Apellido paterno *</label>
                        <input id="col-ap" v-model="form.apellido_paterno" maxlength="120" :class="inputClass" />
                        <p v-if="errors.apellido_paterno" class="field-error" role="alert">{{ errors.apellido_paterno }}</p>
                    </div>
                    <div>
                        <label for="col-am" class="field-label">Apellido materno</label>
                        <input id="col-am" v-model="form.apellido_materno" maxlength="120" :class="inputClass" />
                    </div>
                    <div>
                        <label for="col-puesto" class="field-label">Puesto</label>
                        <input id="col-puesto" v-model="form.puesto" maxlength="120" :class="inputClass" />
                    </div>
                    <div>
                        <label for="col-email" class="field-label">Correo</label>
                        <input id="col-email" v-model="form.email" type="email" maxlength="150" autocomplete="off" :class="inputClass" />
                        <p v-if="errors.email" class="field-error" role="alert">{{ errors.email }}</p>
                    </div>
                    <div>
                        <label for="col-tel" class="field-label">Teléfono</label>
                        <input id="col-tel" v-model="form.telefono" type="tel" maxlength="30" :class="inputClass" />
                    </div>
                    <div>
                        <SearchableSelect v-model="form.corporativo_id" :options="corporativosActivos" label="Corporativo" placeholder="Seleccione…" rounded="xl" />
                        <p v-if="errors.corporativo_id" class="field-error" role="alert">{{ errors.corporativo_id }}</p>
                    </div>
                    <div>
                        <SearchableSelect v-model="form.sucursal_id" :options="sucursalesForm" label="Sucursal *" placeholder="Seleccione…" rounded="xl" :error="errors.sucursal_id" />
                        <p v-if="errors.sucursal_id" class="field-error" role="alert">{{ errors.sucursal_id }}</p>
                    </div>
                    <div>
                        <SearchableSelect v-model="form.area_id" :options="areasForm" label="Área" placeholder="Sin área" :allow-null="true" null-label="Sin área" rounded="xl" />
                        <p v-if="errors.area_id" class="field-error" role="alert">{{ errors.area_id }}</p>
                    </div>
                    <label class="flex min-h-[42px] items-center gap-2 self-end text-sm font-semibold text-slate-700 dark:text-zinc-200">
                        <input v-model="form.activo" type="checkbox" class="h-5 w-5 rounded border-slate-300" /> Colaborador activo
                    </label>

                    <div class="flex flex-col-reverse gap-2 sm:col-span-2 sm:flex-row sm:justify-end">
                        <button type="button" class="btn-secondary justify-center" :disabled="saving" @click="formOpen = false">Cancelar</button>
                        <button type="submit" class="btn-primary justify-center" :disabled="saving">
                            <Loader2 v-if="saving" class="h-4 w-4 animate-spin" aria-hidden="true" />
                            {{ editing ? 'Guardar cambios' : 'Registrar' }}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="confirm.open"
            :title="confirm.target?.activo ? 'Dar de baja colaborador' : 'Reactivar colaborador'"
            :description="confirm.target?.activo
                ? `${confirm.target?.nombre_completo} quedará inactivo. Si tiene cuenta de acceso, se administra por separado en Usuarios.`
                : `${confirm.target?.nombre_completo ?? ''} volverá a estar activo.`"
            :confirm-label="confirm.target?.activo ? 'Dar de baja' : 'Reactivar'"
            :tone="confirm.target?.activo ? 'danger' : 'success'"
            :loading="confirm.loading"
            @confirm="doToggle"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.btn-primary {
    @apply inline-flex min-h-[42px] items-center gap-2 rounded-xl bg-brand-button px-4 text-sm font-semibold text-brand-button-fg shadow-sm transition
        hover:bg-brand-button/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-slate-400 disabled:opacity-60;
}
.btn-secondary {
    @apply inline-flex min-h-[42px] items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition
        hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60 disabled:opacity-60
        dark:border-white/10 dark:bg-white/5 dark:text-zinc-200 dark:hover:bg-white/10;
}
.btn-sm {
    @apply inline-flex min-h-[36px] items-center gap-1.5 rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition
        hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60 dark:border-white/10 dark:text-zinc-200 dark:hover:bg-white/10;
}
.btn-sm-danger {
    @apply border-rose-200 text-rose-700 hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10;
}
.chip {
    @apply inline-flex min-h-[34px] items-center rounded-full bg-slate-100 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-200
        focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60 dark:bg-white/5 dark:text-zinc-300 dark:hover:bg-white/10;
}
.chip-on {
    @apply bg-slate-900 text-white hover:bg-slate-900 dark:bg-zinc-300 dark:text-zinc-900;
}
.badge {
    @apply inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1;
}
.badge-ok { @apply bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/30; }
.badge-info { @apply bg-sky-50 text-sky-800 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-500/30; }
.badge-muted { @apply bg-slate-100 text-slate-600 ring-slate-200 dark:bg-white/5 dark:text-zinc-400 dark:ring-white/10; }
.field-label { @apply mb-1 block text-xs font-semibold text-slate-700 dark:text-zinc-300; }
.field-error { @apply mt-1 text-xs text-rose-600 dark:text-rose-400; }
</style>
