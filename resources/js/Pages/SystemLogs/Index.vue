<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import {
    ArrowRight, ArrowRightLeft, Ban, Eye, FilePen, Globe, History, Loader2, Monitor, Plus, RotateCcw, ScrollText, Search,
    Trash2, UserRound, X,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import DatePickerShadcn from '@/Components/ui/DatePickerShadcn.vue'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/Components/ui/dialog'
import { formatDateTime, formatRelative } from '@/Utils/date'
import type { Paginated } from '@/types/shared'

type Cambio = { antes: unknown; despues: unknown }
type Log = {
    id: number
    accion: string
    accion_label: string
    tabla: string
    modulo: string
    registro_id: number | null
    etiqueta: string | null
    descripcion: string | null
    cambios: Record<string, Cambio> | null
    ip_address: string | null
    user_agent: string | null
    user: { id: number; name: string; email: string } | null
    created_at: string | null
}
type Opcion = { id: string | number; nombre: string; email?: string }
type Filters = { from: string | null; to: string | null; tabla: string | null; registro_id: number | null; accion: string | null; user_id: number | null; ip: string | null; q: string | null; perPage: number }

const props = defineProps<{
    logs: Paginated<Log>
    filters: Filters
    counts: { total: number; by_action: Record<string, number> }
    tablas: Opcion[]
    acciones: Opcion[]
    usuarios: Opcion[]
}>()

/* ---------- Filtros ---------- */
const f = reactive<Filters>({ ...props.filters, q: props.filters.q ?? '', ip: props.filters.ip ?? '' })
const loading = ref(false)
let timer: number | undefined

const params = () => ({
    from: f.from || undefined,
    to: f.to || undefined,
    tabla: f.tabla || undefined,
    registro_id: f.registro_id || undefined,
    accion: f.accion || undefined,
    user_id: f.user_id || undefined,
    ip: f.ip?.trim() || undefined,
    q: f.q?.trim() || undefined,
    perPage: f.perPage !== 30 ? f.perPage : undefined,
})
function apply() {
    router.get(route('systemlogs.index'), params(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    })
}
watch(() => [f.from, f.to, f.tabla, f.registro_id, f.accion, f.user_id, f.perPage], apply)
watch(() => [f.q, f.ip], () => {
    window.clearTimeout(timer)
    timer = window.setTimeout(apply, 400)
})
watch(() => f.tabla, (t, old) => { if (old !== undefined && t !== old) f.registro_id = null })

const hayFiltros = computed(() => Boolean(f.from || f.to || f.tabla || f.registro_id || f.accion || f.user_id || f.ip || f.q))
function limpiar() {
    Object.assign(f, { from: null, to: null, tabla: null, registro_id: null, accion: null, user_id: null, ip: '', q: '' })
}
function rastrear(l: Log) {
    if (!l.registro_id) return
    detail.value = null
    Object.assign(f, { tabla: l.tabla, registro_id: l.registro_id, accion: null, q: '' })
}
const moduloActual = computed(() => props.tablas.find((t) => t.id === f.tabla)?.nombre ?? f.tabla)

/* ---------- Presentación ---------- */
const actionStyle: Record<string, { icon: unknown; cls: string }> = {
    CREACION: { icon: Plus, cls: 'bg-brand-success/10 text-brand-success ring-brand-success/20' },
    ACTUALIZACION: { icon: FilePen, cls: 'bg-brand-accent/10 text-brand-accent ring-brand-accent/20' },
    CAMBIO_ESTATUS: { icon: ArrowRightLeft, cls: 'bg-brand-warning/10 text-brand-warning ring-brand-warning/20' },
    BAJA: { icon: Ban, cls: 'bg-brand-danger/10 text-brand-danger ring-brand-danger/20' },
    ELIMINACION: { icon: Trash2, cls: 'bg-brand-danger/10 text-brand-danger ring-brand-danger/20' },
    REACTIVACION: { icon: RotateCcw, cls: 'bg-brand-success/10 text-brand-success ring-brand-success/20' },
    ACTIVACION: { icon: RotateCcw, cls: 'bg-brand-success/10 text-brand-success ring-brand-success/20' },
}
const style = (a: string) => actionStyle[a] ?? { icon: History, cls: 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-white/5 dark:text-zinc-300 dark:ring-white/10' }

const FIELD_LABELS: Record<string, string> = {
    status: 'Estatus', activo: 'Activo', nombre: 'Nombre', name: 'Nombre', email: 'Correo', folio: 'Folio',
    monto_total: 'Monto total', monto_subtotal: 'Subtotal', fecha_solicitud: 'Fecha de solicitud', fecha_pago: 'Fecha de pago',
    fecha_pago_esperada: 'Fecha esperada de pago', fecha_autorizacion: 'Fecha de autorización', proveedor_id: 'Proveedor',
    concepto_id: 'Concepto', sucursal_id: 'Sucursal', comprador_corp_id: 'Corporativo', solicitante_id: 'Solicitante',
    corporativo_id: 'Corporativo', area_id: 'Área', empleado_id: 'Colaborador', razon_social: 'Razón social', rfc: 'RFC',
    clabe: 'CLABE', banco: 'Banco', observaciones: 'Observaciones', telefono: 'Teléfono', puesto: 'Puesto', rol: 'Rol (legado)',
    apellido_paterno: 'Apellido paterno', apellido_materno: 'Apellido materno', codigo: 'Alias', direccion: 'Dirección',
    tipo_doc: 'Tipo de documento', monto: 'Monto', comentario_revision: 'Comentario de revisión', user_duenio_id: 'Dueño',
}
const fieldLabel = (k: string) => FIELD_LABELS[k] ?? k.replace(/_id$/, '').replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase())
const show = (v: unknown) => {
    if (v === null || v === undefined || v === '') return '—'
    if (typeof v === 'boolean') return v ? 'Sí' : 'No'
    if (typeof v === 'object') return JSON.stringify(v)
    return String(v)
}
const changeList = (l: Log) => Object.entries(l.cambios ?? {})
const initials = (n: string) => n.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]?.toUpperCase()).join('') || '?'
const browser = (ua: string | null) => {
    if (!ua) return null
    const name = /Edg\//.test(ua) ? 'Edge' : /Chrome\//.test(ua) ? 'Chrome' : /Firefox\//.test(ua) ? 'Firefox' : /Safari\//.test(ua) ? 'Safari' : 'Navegador'
    const os = /Windows/.test(ua) ? 'Windows' : /Android/.test(ua) ? 'Android' : /iPhone|iPad/.test(ua) ? 'iOS' : /Mac OS/.test(ua) ? 'macOS' : /Linux/.test(ua) ? 'Linux' : ''
    return [name, os].filter(Boolean).join(' · ')
}

const detail = ref<Log | null>(null)
const detailOpen = computed({ get: () => detail.value !== null, set: (v) => { if (!v) detail.value = null } })

const perPageOptions = [15, 30, 50, 100].map((n) => ({ id: n, nombre: `${n} por página` }))
const goPage = (url: string | null) => url && router.visit(url, { preserveScroll: true, preserveState: true, onStart: () => (loading.value = true), onFinish: () => (loading.value = false) })
const pageLabel = (l: string) =>
    l.replace('&laquo;', '«').replace('&raquo;', '»').replace(/Previous|pagination\.previous/i, 'Anterior').replace(/Next|pagination\.next/i, 'Siguiente')
</script>

<template>
    <Head title="Bitácora" />

    <AuthenticatedLayout>
        <template #header>Bitácora</template>

        <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <section class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-accent/10 text-brand-accent">
                        <ScrollText class="h-5 w-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">Bitácora del sistema</h2>
                        <p class="text-sm text-slate-500 dark:text-zinc-400">Quién hizo qué, sobre qué registro, cuándo y desde dónde. {{ counts.total.toLocaleString('es-MX') }} evento(s) con los filtros actuales.</p>
                    </div>
                </div>
            </section>

            <!-- Historial de un registro -->
            <div v-if="f.registro_id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-brand-accent/30 bg-brand-accent/[0.06] px-4 py-3">
                <p class="flex items-center gap-2 text-sm text-slate-700 dark:text-zinc-200">
                    <History class="h-4 w-4 text-brand-accent" aria-hidden="true" />
                    Historial completo de <strong class="font-semibold">{{ moduloActual }} #{{ f.registro_id }}</strong>
                </p>
                <button type="button" class="ui-btn-sm" @click="f.registro_id = null">
                    <X class="h-3.5 w-3.5" aria-hidden="true" /> Quitar
                </button>
            </div>

            <!-- Filtros -->
            <section class="ui-card space-y-4 p-4 sm:p-5" aria-label="Filtros">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-12">
                    <div class="sm:col-span-2 xl:col-span-4">
                        <label for="log-q" class="ui-label">Buscar</label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input id="log-q" v-model="f.q" type="search" class="ui-input pl-9" placeholder="Folio, nombre, descripción o ID…" autocomplete="off" />
                        </div>
                    </div>
                    <div class="xl:col-span-2"><DatePickerShadcn v-model="f.from" label="Desde" :max-value="f.to" clearable /></div>
                    <div class="xl:col-span-2"><DatePickerShadcn v-model="f.to" label="Hasta" :min-value="f.from" clearable /></div>
                    <div class="xl:col-span-2">
                        <SearchableSelect v-model="f.tabla" :options="tablas" label="Módulo" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" />
                    </div>
                    <div class="xl:col-span-2">
                        <SearchableSelect v-model="f.user_id" :options="usuarios" secondary-key="email" label="Usuario" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" />
                    </div>
                    <div class="xl:col-span-2">
                        <label for="log-ip" class="ui-label">IP</label>
                        <div class="relative">
                            <Globe class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input id="log-ip" v-model="f.ip" class="ui-input pl-9" placeholder="Ej. 187.190" autocomplete="off" />
                        </div>
                    </div>
                    <div class="xl:col-span-2">
                        <SearchableSelect v-model="f.perPage" :options="perPageOptions" label="Mostrar" rounded="xl" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-white/[0.06]" role="group" aria-label="Acción">
                    <button type="button" class="ui-chip" :class="!f.accion ? 'ui-chip-on' : ''" :aria-pressed="!f.accion" @click="f.accion = null">
                        Todas <span class="tabular-nums opacity-70">{{ counts.total }}</span>
                    </button>
                    <button
                        v-for="a in acciones"
                        :key="a.id"
                        type="button"
                        class="ui-chip"
                        :class="f.accion === a.id ? 'ui-chip-on' : ''"
                        :aria-pressed="f.accion === a.id"
                        @click="f.accion = String(a.id)"
                    >
                        <component :is="style(String(a.id)).icon" class="h-3.5 w-3.5" aria-hidden="true" />
                        {{ a.nombre }}
                        <span class="tabular-nums opacity-70">{{ (counts.by_action[a.id] ?? 0) + (a.id === 'BAJA' ? counts.by_action.ELIMINACION ?? 0 : 0) + (a.id === 'REACTIVACION' ? counts.by_action.ACTIVACION ?? 0 : 0) }}</span>
                    </button>
                    <span v-if="loading" class="inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-zinc-400" role="status">
                        <Loader2 class="h-3.5 w-3.5 animate-spin" aria-hidden="true" /> Cargando…
                    </span>
                    <button v-if="hayFiltros" type="button" class="ml-auto inline-flex min-h-[36px] items-center gap-1.5 text-xs font-semibold text-slate-600 underline-offset-2 hover:underline dark:text-zinc-300" @click="limpiar">
                        <RotateCcw class="h-3.5 w-3.5" aria-hidden="true" /> Limpiar filtros
                    </button>
                </div>
            </section>

            <!-- Eventos -->
            <section class="ui-card overflow-hidden transition-opacity" :class="loading ? 'opacity-60' : ''" :aria-busy="loading">
                <div v-if="logs.data.length === 0" class="flex flex-col items-center gap-3 px-6 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-zinc-500">
                        <ScrollText class="h-7 w-7" aria-hidden="true" />
                    </span>
                    <p class="text-base font-semibold text-slate-700 dark:text-zinc-200">No hay eventos con estos filtros.</p>
                    <button v-if="hayFiltros" type="button" class="ui-btn-secondary" @click="limpiar">Limpiar filtros</button>
                </div>

                <ul v-else class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                    <li v-for="l in logs.data" :key="l.id" class="group flex gap-4 px-4 py-4 transition-colors hover:bg-slate-50 sm:px-5 dark:hover:bg-white/[0.03]">
                        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset" :class="style(l.accion).cls">
                            <component :is="style(l.accion).icon" class="h-[18px] w-[18px]" aria-hidden="true" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                                <p class="min-w-0 text-sm text-slate-900 dark:text-zinc-100">
                                    <span class="font-bold">{{ l.accion_label }}</span>
                                    <span class="text-slate-400"> · </span>
                                    <span class="font-medium">{{ l.modulo }}</span>
                                    <span v-if="l.etiqueta || l.registro_id" class="break-words [overflow-wrap:anywhere]">
                                        — {{ l.etiqueta ?? '' }} <span class="text-xs text-slate-500 dark:text-zinc-400">#{{ l.registro_id }}</span>
                                    </span>
                                </p>
                                <time :datetime="l.created_at ?? undefined" :title="formatDateTime(l.created_at)" class="shrink-0 text-xs tabular-nums text-slate-500 dark:text-zinc-400">
                                    {{ formatRelative(l.created_at) }} · {{ formatDateTime(l.created_at) }}
                                </time>
                            </div>

                            <!-- Resumen de cambios -->
                            <div v-if="changeList(l).length" class="mt-2 flex flex-wrap gap-1.5">
                                <span
                                    v-for="[campo, c] in changeList(l).slice(0, 4)"
                                    :key="campo"
                                    class="inline-flex max-w-full items-center gap-1.5 rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-600 dark:bg-white/[0.05] dark:text-zinc-300"
                                >
                                    <span class="font-semibold">{{ fieldLabel(campo) }}:</span>
                                    <span class="max-w-[10rem] truncate text-slate-400 line-through dark:text-zinc-500">{{ show(c.antes) }}</span>
                                    <ArrowRight class="h-3 w-3 shrink-0" aria-hidden="true" />
                                    <span class="max-w-[12rem] truncate font-medium text-slate-800 dark:text-zinc-100">{{ show(c.despues) }}</span>
                                </span>
                                <span v-if="changeList(l).length > 4" class="self-center text-xs text-slate-500 dark:text-zinc-400">+{{ changeList(l).length - 4 }} más</span>
                            </div>
                            <p v-else-if="l.descripcion" class="mt-1 line-clamp-2 whitespace-pre-line break-words text-xs text-slate-500 [overflow-wrap:anywhere] dark:text-zinc-400">{{ l.descripcion }}</p>

                            <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-500 dark:text-zinc-400">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-primary/10 text-[9px] font-black text-brand-primary dark:bg-white/10 dark:text-zinc-200">{{ l.user ? initials(l.user.name) : '—' }}</span>
                                    <span class="font-semibold text-slate-700 dark:text-zinc-200">{{ l.user?.name ?? 'Sistema / proceso automático' }}</span>
                                </span>
                                <span v-if="l.ip_address" class="inline-flex items-center gap-1"><Globe class="h-3.5 w-3.5" aria-hidden="true" /> {{ l.ip_address }}</span>
                                <span v-if="browser(l.user_agent)" class="hidden items-center gap-1 sm:inline-flex"><Monitor class="h-3.5 w-3.5" aria-hidden="true" /> {{ browser(l.user_agent) }}</span>
                                <span class="ml-auto flex gap-1.5">
                                    <button type="button" class="ui-btn-sm min-h-[32px]" @click="detail = l"><Eye class="h-3.5 w-3.5" aria-hidden="true" /> Detalle</button>
                                    <button v-if="l.registro_id && !(f.registro_id === l.registro_id && f.tabla === l.tabla)" type="button" class="ui-btn-sm min-h-[32px]" @click="rastrear(l)">
                                        <History class="h-3.5 w-3.5" aria-hidden="true" /> Historial
                                    </button>
                                </span>
                            </div>
                        </div>
                    </li>
                </ul>

                <nav v-if="logs.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 dark:border-white/[0.06]" aria-label="Paginación">
                    <p class="text-xs text-slate-500 dark:text-zinc-400">Mostrando {{ logs.from }}–{{ logs.to }} de {{ logs.total.toLocaleString('es-MX') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="(link, i) in logs.links"
                            :key="i"
                            type="button"
                            class="min-h-[38px] min-w-[38px] rounded-xl px-3 text-xs font-semibold transition disabled:opacity-40"
                            :class="link.active ? 'bg-brand-primary text-brand-primary-fg dark:bg-brand-primary/20 dark:text-zinc-50' : 'border border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5'"
                            :disabled="!link.url"
                            :aria-current="link.active ? 'page' : undefined"
                            @click="goPage(link.url)"
                        >
                            {{ pageLabel(link.label) }}
                        </button>
                    </div>
                </nav>
            </section>
        </div>

        <!-- Detalle -->
        <Dialog v-model:open="detailOpen">
            <DialogContent class="max-w-2xl">
                <template v-if="detail">
                    <div class="flex items-start gap-3 pr-8">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset" :class="style(detail.accion).cls">
                            <component :is="style(detail.accion).icon" class="h-[18px] w-[18px]" aria-hidden="true" />
                        </span>
                        <div class="min-w-0">
                            <DialogTitle class="text-base font-bold text-slate-900 dark:text-zinc-100">{{ detail.accion_label }} · {{ detail.modulo }}</DialogTitle>
                            <DialogDescription class="break-words text-sm text-slate-500 [overflow-wrap:anywhere] dark:text-zinc-400">
                                {{ detail.etiqueta ?? 'Registro' }} #{{ detail.registro_id }} · {{ formatDateTime(detail.created_at) }}
                            </DialogDescription>
                        </div>
                    </div>

                    <dl class="grid grid-cols-1 gap-3 rounded-2xl bg-slate-50 p-3 text-sm dark:bg-white/[0.04] sm:grid-cols-3">
                        <div class="min-w-0"><dt class="text-xs text-slate-500 dark:text-zinc-400">Usuario</dt><dd class="flex items-center gap-1.5 font-semibold text-slate-800 dark:text-zinc-100"><UserRound class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><span class="truncate">{{ detail.user?.name ?? 'Sistema' }}</span></dd><dd v-if="detail.user" class="truncate text-xs text-slate-500">{{ detail.user.email }}</dd></div>
                        <div class="min-w-0"><dt class="text-xs text-slate-500 dark:text-zinc-400">IP</dt><dd class="font-semibold text-slate-800 dark:text-zinc-100">{{ detail.ip_address ?? '—' }}</dd></div>
                        <div class="min-w-0"><dt class="text-xs text-slate-500 dark:text-zinc-400">Navegador</dt><dd class="font-semibold text-slate-800 dark:text-zinc-100">{{ browser(detail.user_agent) ?? '—' }}</dd></div>
                    </dl>

                    <div v-if="changeList(detail).length" class="overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10">
                        <table class="w-full table-fixed text-sm">
                            <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500 dark:bg-white/[0.04] dark:text-zinc-400">
                                <tr><th class="w-1/4 px-3 py-2 font-semibold">Campo</th><th class="px-3 py-2 font-semibold">Antes</th><th class="px-3 py-2 font-semibold">Después</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                                <tr v-for="[campo, c] in changeList(detail)" :key="campo" class="align-top">
                                    <td class="px-3 py-2 font-semibold text-slate-700 dark:text-zinc-200">{{ fieldLabel(campo) }}</td>
                                    <td class="break-words px-3 py-2 text-brand-danger [overflow-wrap:anywhere]">{{ show(c.antes) }}</td>
                                    <td class="break-words px-3 py-2 text-brand-success [overflow-wrap:anywhere]">{{ show(c.despues) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <pre v-else-if="detail.descripcion" class="max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-2xl bg-slate-50 p-3 font-sans text-sm text-slate-700 dark:bg-white/[0.04] dark:text-zinc-200">{{ detail.descripcion }}</pre>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button v-if="detail.registro_id" type="button" class="ui-btn-secondary" @click="rastrear(detail)">
                            <History class="h-4 w-4" aria-hidden="true" /> Ver historial del registro
                        </button>
                        <button type="button" class="ui-btn-primary" @click="detail = null">Cerrar</button>
                    </div>
                </template>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
