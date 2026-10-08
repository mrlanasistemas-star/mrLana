<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    CheckCircle2, Clock, Download, ExternalLink, Eye, SquareArrowOutUpRight, FileStack, Loader2, MessageSquareText, Receipt, RotateCcw, Search,
    ShieldCheck, Upload, Wallet, XCircle,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import DatePickerShadcn from '@/Components/ui/DatePickerShadcn.vue'
import FileThumb from '@/Components/files/FileThumb.vue'
import FilePreviewDialog, { type PreviewItem } from '@/Components/files/FilePreviewDialog.vue'
import { downloadFile, toQS } from '@/Utils/exports'
import { formatDateOnlyEsMx, formatDateTime } from '@/Utils/date'
import type { Paginated } from '@/types/shared'
import ICON_PDF from '@/img/pdf.png'
import ICON_EXCEL from '@/img/excel.png'

type Comprobante = {
    id: number
    tipo_doc: string
    tipo_label: string
    fecha_emision: string | null
    monto: number
    estatus: 'PENDIENTE' | 'APROBADO' | 'RECHAZADO'
    estatus_label: string
    comentario_revision: string | null
    revisado_at: string | null
    archivo_original: string | null
    kind: PreviewItem['kind']
    ext: string
    preview_url: string | null
    download_url: string | null
    user_carga: string | null
    user_revision: string | null
    created_at: string | null
    requisicion: { id: number; folio: string; status: string; monto_total: number; solicitante: string | null; proveedor: string | null; concepto: string | null; corporativo: string | null } | null
}
type Opcion = { id: number | string; nombre: string }
type Filters = { q: string | null; desde: string | null; hasta: string | null; estatus: string | null; tipo_doc: string | null; solicitante_id: number | null; user_carga_id: number | null; user_revision_id: number | null; corporativo_id: number | null; requisicion_id: number | null; per_page: number }

const props = defineProps<{
    comprobantes: Paginated<Comprobante>
    kpis: { total: number; monto: number; pendientes: number; aprobados: number; rechazados: number }
    filters: Filters
    options: { estatus: Opcion[]; tipos: Opcion[]; solicitantes: Opcion[]; cargaron: Opcion[]; revisaron: Opcion[]; corporativos: Opcion[] }
    can: { exportar: boolean; revisar: boolean }
}>()

/* ---------- Filtros ---------- */
const f = reactive<Filters>({ ...props.filters, q: props.filters.q ?? '' })
const loading = ref(false)
let timer: number | undefined

const params = () => ({
    q: f.q?.trim() || undefined,
    desde: f.desde || undefined,
    hasta: f.hasta || undefined,
    estatus: f.estatus || undefined,
    tipo_doc: f.tipo_doc || undefined,
    solicitante_id: f.solicitante_id || undefined,
    user_carga_id: f.user_carga_id || undefined,
    user_revision_id: f.user_revision_id || undefined,
    corporativo_id: f.corporativo_id || undefined,
    requisicion_id: f.requisicion_id || undefined,
    per_page: f.per_page !== 24 ? f.per_page : undefined,
})
function apply() {
    router.get(route('comprobantes.index'), params(), { preserveState: true, preserveScroll: true, replace: true, onStart: () => (loading.value = true), onFinish: () => (loading.value = false) })
}
watch(() => [f.desde, f.hasta, f.estatus, f.tipo_doc, f.solicitante_id, f.user_carga_id, f.user_revision_id, f.corporativo_id, f.requisicion_id, f.per_page], apply)
watch(() => f.q, () => { window.clearTimeout(timer); timer = window.setTimeout(apply, 400) })

const hayFiltros = computed(() => Boolean(f.q || f.desde || f.hasta || f.estatus || f.tipo_doc || f.solicitante_id || f.user_carga_id || f.user_revision_id || f.corporativo_id || f.requisicion_id))
function limpiar() {
    Object.assign(f, { q: '', desde: null, hasta: null, estatus: null, tipo_doc: null, solicitante_id: null, user_carga_id: null, user_revision_id: null, corporativo_id: null, requisicion_id: null })
}

/* ---------- Exportar con los mismos filtros ---------- */
const exporting = ref<'pdf' | 'excel' | null>(null)
async function exportar(kind: 'pdf' | 'excel') {
    exporting.value = kind
    try { await downloadFile(route(`comprobantes.export.${kind}`) + toQS(params())) } finally { exporting.value = null }
}

/* ---------- Presentación ---------- */
const money = (v: number) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v || 0)
const status = {
    PENDIENTE: { icon: Clock, cls: 'bg-brand-warning/15 text-brand-warning ring-brand-warning/30' },
    APROBADO: { icon: CheckCircle2, cls: 'bg-brand-success/15 text-brand-success ring-brand-success/30' },
    RECHAZADO: { icon: XCircle, cls: 'bg-brand-danger/15 text-brand-danger ring-brand-danger/30' },
} as const

const kpiCards = computed(() => [
    { key: null, label: 'Comprobantes', value: props.kpis.total.toLocaleString('es-MX'), icon: FileStack, tone: 'bg-brand-accent/10 text-brand-accent' },
    { key: null, label: 'Monto comprobado', value: money(props.kpis.monto), icon: Wallet, tone: 'bg-brand-accent/10 text-brand-accent' },
    { key: 'PENDIENTE', label: 'Pendientes', value: props.kpis.pendientes.toLocaleString('es-MX'), icon: Clock, tone: 'bg-brand-warning/10 text-brand-warning' },
    { key: 'APROBADO', label: 'Aprobados', value: props.kpis.aprobados.toLocaleString('es-MX'), icon: CheckCircle2, tone: 'bg-brand-success/10 text-brand-success' },
    { key: 'RECHAZADO', label: 'Rechazados', value: props.kpis.rechazados.toLocaleString('es-MX'), icon: XCircle, tone: 'bg-brand-danger/10 text-brand-danger' },
])

/* ---------- Visor ---------- */
const previewIndex = ref<number | null>(null)
const previewItems = computed<PreviewItem[]>(() => props.comprobantes.data.map((c) => ({
    id: c.id,
    kind: c.kind,
    preview_url: c.preview_url,
    download_url: c.download_url,
    archivo_original: c.archivo_original,
    title: `${c.tipo_label} · ${money(c.monto)} · ${c.requisicion?.folio ?? ''}`,
    subtitle: [c.requisicion?.proveedor, c.estatus_label, c.archivo_original].filter(Boolean).join(' · '),
})))

const perPageOptions = [12, 24, 48, 96].map((n) => ({ id: n, nombre: `${n} por página` }))
const goPage = (url: string | null) => url && router.visit(url, { preserveScroll: true, preserveState: true, onStart: () => (loading.value = true), onFinish: () => (loading.value = false) })
const pageLabel = (l: string) => l.replace('&laquo;', '«').replace('&raquo;', '»').replace(/Previous|pagination\.previous/i, 'Anterior').replace(/Next|pagination\.next/i, 'Siguiente')
</script>

<template>
    <Head title="Comprobantes" />

    <AuthenticatedLayout>
        <template #header>Comprobantes</template>

        <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-accent/10 text-brand-accent">
                        <Receipt class="h-5 w-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">Comprobantes</h2>
                        <p class="text-sm text-slate-500 dark:text-zinc-400">Facturas, tickets y notas cargados en las requisiciones, con su revisión.</p>
                    </div>
                </div>
                <div v-if="can.exportar" class="flex flex-wrap gap-2">
                    <button type="button" class="ui-btn-secondary" :disabled="exporting !== null" @click="exportar('pdf')">
                        <Loader2 v-if="exporting === 'pdf'" class="h-4 w-4 animate-spin" aria-hidden="true" /><img v-else :src="ICON_PDF" class="h-5 w-5" alt="" /> PDF
                    </button>
                    <button type="button" class="ui-btn-secondary" :disabled="exporting !== null" @click="exportar('excel')">
                        <Loader2 v-if="exporting === 'excel'" class="h-4 w-4 animate-spin" aria-hidden="true" /><img v-else :src="ICON_EXCEL" class="h-5 w-5" alt="" /> Excel
                    </button>
                </div>
            </section>

            <!-- Indicadores -->
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                <button
                    v-for="k in kpiCards"
                    :key="k.label"
                    type="button"
                    class="ui-card flex flex-col items-start gap-2 p-4 text-left transition min-[420px]:flex-row min-[420px]:items-center min-[420px]:gap-3 hover:border-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 dark:hover:border-white/15"
                    :class="k.key && f.estatus === k.key ? 'ring-2 ring-brand-accent/40' : ''"
                    :disabled="!k.key"
                    :aria-pressed="k.key ? f.estatus === k.key : undefined"
                    @click="k.key && (f.estatus = f.estatus === k.key ? null : k.key)"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="k.tone"><component :is="k.icon" class="h-5 w-5" aria-hidden="true" /></span>
                    <span class="min-w-0">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ k.label }}</span>
                        <span class="block break-words text-base font-black leading-tight tabular-nums sm:text-lg text-slate-900 dark:text-zinc-100">{{ k.value }}</span>
                    </span>
                </button>
            </div>

            <!-- Filtros -->
            <section data-tour="comprobantes-filtros" class="ui-card space-y-3 p-4 sm:p-5" aria-label="Filtros">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-12">
                    <div class="sm:col-span-2 xl:col-span-4">
                        <label for="cmp-q" class="ui-label">Buscar</label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input id="cmp-q" v-model="f.q" type="search" class="ui-input pl-9" placeholder="Folio, proveedor, archivo o comentario…" autocomplete="off" />
                        </div>
                    </div>
                    <div class="xl:col-span-2"><DatePickerShadcn v-model="f.desde" label="Desde" :max-value="f.hasta" clearable /></div>
                    <div class="xl:col-span-2"><DatePickerShadcn v-model="f.hasta" label="Hasta" :min-value="f.desde" clearable /></div>
                    <div class="xl:col-span-2"><SearchableSelect v-model="f.estatus" :options="options.estatus" label="Estatus" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" /></div>
                    <div class="xl:col-span-2"><SearchableSelect v-model="f.tipo_doc" :options="options.tipos" label="Tipo" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" /></div>
                    <div class="xl:col-span-3"><SearchableSelect v-model="f.solicitante_id" :options="options.solicitantes" label="Solicitó" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" /></div>
                    <div class="xl:col-span-3"><SearchableSelect v-model="f.user_carga_id" :options="options.cargaron" label="Cargó" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" /></div>
                    <div class="xl:col-span-2"><SearchableSelect v-model="f.user_revision_id" :options="options.revisaron" label="Revisó (aprobó/rechazó)" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" /></div>
                    <div class="xl:col-span-2"><SearchableSelect v-model="f.corporativo_id" :options="options.corporativos" label="Corporativo" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" /></div>
                    <div class="xl:col-span-2"><SearchableSelect v-model="f.per_page" :options="perPageOptions" label="Mostrar" rounded="xl" /></div>
                </div>
                <div v-if="hayFiltros || loading" class="flex items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs dark:border-white/[0.06]">
                    <span v-if="loading" class="inline-flex items-center gap-1.5 text-slate-500 dark:text-zinc-400" role="status"><Loader2 class="h-3.5 w-3.5 animate-spin" aria-hidden="true" /> Cargando…</span>
                    <span v-else class="text-slate-500 dark:text-zinc-400">{{ comprobantes.total.toLocaleString('es-MX') }} resultado(s)</span>
                    <button v-if="hayFiltros" type="button" class="inline-flex min-h-[36px] items-center gap-1.5 font-semibold text-slate-600 underline-offset-2 hover:underline dark:text-zinc-300" @click="limpiar">
                        <RotateCcw class="h-3.5 w-3.5" aria-hidden="true" /> Limpiar filtros
                    </button>
                </div>
            </section>

            <!-- Tarjetas -->
            <div v-if="comprobantes.data.length === 0" class="ui-card flex flex-col items-center gap-3 px-6 py-16 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-zinc-500"><Receipt class="h-7 w-7" aria-hidden="true" /></span>
                <p class="text-base font-semibold text-slate-700 dark:text-zinc-200">{{ hayFiltros ? 'Ningún comprobante coincide con los filtros.' : 'Aún no se han cargado comprobantes.' }}</p>
                <p class="max-w-sm text-sm text-slate-500 dark:text-zinc-400">Los comprobantes se cargan desde la pantalla «Comprobar» de cada requisición pagada.</p>
                <button v-if="hayFiltros" type="button" class="ui-btn-secondary" @click="limpiar">Limpiar filtros</button>
            </div>

            <ul data-tour="comprobantes-lista" v-else class="grid grid-cols-1 gap-4 transition-opacity sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4" :class="loading ? 'opacity-60' : ''">
                <li v-for="(c, i) in comprobantes.data" :key="c.id" class="ui-card group flex min-w-0 flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg motion-reduce:transform-none">
                    <FileThumb :kind="c.kind" :url="c.preview_url" :name="c.archivo_original" :ext="c.ext" @open="previewIndex = i">
                        <span class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 backdrop-blur" :class="status[c.estatus].cls">
                            <component :is="status[c.estatus].icon" class="h-3.5 w-3.5" aria-hidden="true" /> {{ c.estatus_label }}
                        </span>
                        <span class="absolute right-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-bold text-slate-700 shadow-sm dark:bg-zinc-900/90 dark:text-zinc-200">{{ c.tipo_label }}</span>
                    </FileThumb>

                    <div class="flex min-w-0 flex-1 flex-col gap-3 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xl font-black tabular-nums tracking-tight text-slate-900 dark:text-zinc-100">{{ money(c.monto) }}</p>
                                <p class="text-xs text-slate-500 dark:text-zinc-400">{{ c.fecha_emision ? formatDateOnlyEsMx(c.fecha_emision) : `Cargado ${formatDateTime(c.created_at)}` }}</p>
                            </div>
                            <Link v-if="c.requisicion" :href="route('requisiciones.comprobar', c.requisicion.id)" class="ui-badge ui-badge-accent shrink-0 hover:underline">{{ c.requisicion.folio }}</Link>
                        </div>

                        <dl class="space-y-1 text-xs">
                            <div v-if="c.requisicion?.proveedor" class="flex gap-1.5"><dt class="sr-only">Proveedor</dt><dd class="min-w-0 break-words font-semibold text-slate-700 dark:text-zinc-200">{{ c.requisicion.proveedor }}</dd></div>
                            <div class="flex gap-1.5 text-slate-500 dark:text-zinc-400"><dt class="shrink-0">Solicitó:</dt><dd class="min-w-0 break-words text-slate-700 dark:text-zinc-300">{{ c.requisicion?.solicitante ?? '—' }}</dd></div>
                            <div class="flex items-center gap-1.5 text-slate-500 dark:text-zinc-400"><Upload class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><dt class="sr-only">Cargó</dt><dd class="min-w-0 break-words">{{ c.user_carga ?? '—' }}</dd></div>
                            <div class="flex items-center gap-1.5 text-slate-500 dark:text-zinc-400"><ShieldCheck class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><dt class="sr-only">Revisó</dt><dd class="min-w-0 break-words">{{ c.user_revision ?? 'Sin revisar' }}<template v-if="c.revisado_at"> · {{ formatDateTime(c.revisado_at) }}</template></dd></div>
                        </dl>

                        <p v-if="c.comentario_revision" class="flex gap-1.5 rounded-xl bg-slate-50 p-2.5 text-xs text-slate-600 dark:bg-white/[0.04] dark:text-zinc-300">
                            <MessageSquareText class="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                            <span class="line-clamp-3 break-words [overflow-wrap:anywhere]">{{ c.comentario_revision }}</span>
                        </p>

                        <div class="mt-auto flex flex-wrap gap-1.5 border-t border-slate-100 pt-3 dark:border-white/[0.06]">
                            <button type="button" class="ui-btn-sm" :disabled="c.kind === 'none'" title="Ver en grande en esta pantalla" @click="previewIndex = i"><Eye class="h-3.5 w-3.5" aria-hidden="true" /> Abrir</button>
                            <a v-if="c.preview_url" :href="c.preview_url" target="_blank" rel="noopener" class="ui-btn-sm" title="Abrir en otra pestaña"><SquareArrowOutUpRight class="h-3.5 w-3.5" aria-hidden="true" /> Otra pestaña</a>
                            <a v-if="c.download_url" :href="c.download_url" class="ui-btn-sm"><Download class="h-3.5 w-3.5" aria-hidden="true" /> Descargar</a>
                            <Link v-if="c.requisicion" :href="route('requisiciones.comprobar', c.requisicion.id)" class="ui-btn-sm ml-auto">
                                {{ can.revisar && c.estatus === 'PENDIENTE' ? 'Revisar' : 'Requisición' }} <ExternalLink class="h-3.5 w-3.5" aria-hidden="true" />
                            </Link>
                        </div>
                    </div>
                </li>
            </ul>

            <nav v-if="comprobantes.last_page > 1" class="ui-card flex flex-wrap items-center justify-between gap-3 px-4 py-3" aria-label="Paginación">
                <p class="text-xs text-slate-500 dark:text-zinc-400">Mostrando {{ comprobantes.from }}–{{ comprobantes.to }} de {{ comprobantes.total.toLocaleString('es-MX') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="(l, i) in comprobantes.links"
                        :key="i"
                        type="button"
                        class="min-h-[38px] min-w-[38px] rounded-xl px-3 text-xs font-semibold transition disabled:opacity-40"
                        :class="l.active ? 'bg-brand-primary text-brand-primary-fg dark:bg-brand-primary/20 dark:text-zinc-50' : 'border border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5'"
                        :disabled="!l.url"
                        :aria-current="l.active ? 'page' : undefined"
                        @click="goPage(l.url)"
                    >{{ pageLabel(l.label) }}</button>
                </div>
            </nav>
        </div>

        <FilePreviewDialog v-model:index="previewIndex" :items="previewItems" />
    </AuthenticatedLayout>
</template>
