<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    Banknote, BadgeCheck, CreditCard, Download, ExternalLink, Eye, FileStack, Hash, Landmark, Loader2, RotateCcw, Search, Upload, Wallet,
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

type Pago = {
    id: number
    beneficiario: string | null
    banco: string | null
    cuenta: string | null
    tipo_pago: string
    tipo_label: string
    monto: number
    fecha_pago: string | null
    referencia: string | null
    archivo_original: string | null
    kind: PreviewItem['kind']
    ext: string
    preview_url: string | null
    download_url: string | null
    user_carga: string | null
    created_at: string | null
    requisicion: { id: number; folio: string; status: string; monto_total: number; solicitante: string | null; concepto: string | null; corporativo: string | null; autorizo: string | null; fecha_autorizacion: string | null } | null
}
type Opcion = { id: number | string; nombre: string }
type Filters = { q: string | null; desde: string | null; hasta: string | null; tipo_pago: string | null; solicitante_id: number | null; user_carga_id: number | null; autorizo_id: number | null; corporativo_id: number | null; requisicion_id: number | null; per_page: number }

const props = defineProps<{
    pagos: Paginated<Pago>
    kpis: { total: number; monto: number; requisiciones: number; transferencias: number; otros: number }
    filters: Filters
    options: { tipos: Opcion[]; solicitantes: Opcion[]; registraron: Opcion[]; autorizaron: Opcion[]; corporativos: Opcion[] }
    can: { exportar: boolean; registrar: boolean }
}>()

const f = reactive<Filters>({ ...props.filters, q: props.filters.q ?? '' })
const loading = ref(false)
let timer: number | undefined

const params = () => ({
    q: f.q?.trim() || undefined,
    desde: f.desde || undefined,
    hasta: f.hasta || undefined,
    tipo_pago: f.tipo_pago || undefined,
    solicitante_id: f.solicitante_id || undefined,
    user_carga_id: f.user_carga_id || undefined,
    autorizo_id: f.autorizo_id || undefined,
    corporativo_id: f.corporativo_id || undefined,
    requisicion_id: f.requisicion_id || undefined,
    per_page: f.per_page !== 24 ? f.per_page : undefined,
})
function apply() {
    router.get(route('pagos.index'), params(), { preserveState: true, preserveScroll: true, replace: true, onStart: () => (loading.value = true), onFinish: () => (loading.value = false) })
}
watch(() => [f.desde, f.hasta, f.tipo_pago, f.solicitante_id, f.user_carga_id, f.autorizo_id, f.corporativo_id, f.requisicion_id, f.per_page], apply)
watch(() => f.q, () => { window.clearTimeout(timer); timer = window.setTimeout(apply, 400) })

const hayFiltros = computed(() => Boolean(f.q || f.desde || f.hasta || f.tipo_pago || f.solicitante_id || f.user_carga_id || f.autorizo_id || f.corporativo_id || f.requisicion_id))
function limpiar() {
    Object.assign(f, { q: '', desde: null, hasta: null, tipo_pago: null, solicitante_id: null, user_carga_id: null, autorizo_id: null, corporativo_id: null, requisicion_id: null })
}

const exporting = ref<'pdf' | 'excel' | null>(null)
async function exportar(kind: 'pdf' | 'excel') {
    exporting.value = kind
    try { await downloadFile(route(`pagos.export.${kind}`) + toQS(params())) } finally { exporting.value = null }
}

const money = (v: number) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v || 0)
const tipoIcon: Record<string, unknown> = { TRANSFERENCIA: Landmark, EFECTIVO: Banknote, TARJETA: CreditCard, CHEQUE: FileStack, OTRO: Wallet }

const kpiCards = computed(() => [
    { label: 'Pagos', value: props.kpis.total.toLocaleString('es-MX'), icon: FileStack },
    { label: 'Monto pagado', value: money(props.kpis.monto), icon: Wallet },
    { label: 'Requisiciones', value: props.kpis.requisiciones.toLocaleString('es-MX'), icon: Hash },
    { label: 'Transferencias', value: money(props.kpis.transferencias), icon: Landmark },
    { label: 'Otros métodos', value: money(props.kpis.otros), icon: Banknote },
])

const previewIndex = ref<number | null>(null)
const previewItems = computed<PreviewItem[]>(() => props.pagos.data.map((p) => ({
    id: p.id,
    kind: p.kind,
    preview_url: p.preview_url,
    download_url: p.download_url,
    archivo_original: p.archivo_original,
    title: `Pago ${money(p.monto)} · ${p.requisicion?.folio ?? ''}`,
    subtitle: [p.beneficiario, p.tipo_label, p.fecha_pago ? formatDateOnlyEsMx(p.fecha_pago) : null].filter(Boolean).join(' · '),
})))

const perPageOptions = [12, 24, 48, 96].map((n) => ({ id: n, nombre: `${n} por página` }))
const goPage = (url: string | null) => url && router.visit(url, { preserveScroll: true, preserveState: true, onStart: () => (loading.value = true), onFinish: () => (loading.value = false) })
const pageLabel = (l: string) => l.replace('&laquo;', '«').replace('&raquo;', '»').replace(/Previous|pagination\.previous/i, 'Anterior').replace(/Next|pagination\.next/i, 'Siguiente')
</script>

<template>
    <Head title="Pagos" />

    <AuthenticatedLayout>
        <template #header>Pagos</template>

        <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-accent/10 text-brand-accent"><Banknote class="h-5 w-5" aria-hidden="true" /></span>
                    <div class="min-w-0">
                        <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">Pagos</h2>
                        <p class="text-sm text-slate-500 dark:text-zinc-400">Pagos registrados en las requisiciones, con su comprobante, quién autorizó y quién registró.</p>
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

            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                <div v-for="k in kpiCards" :key="k.label" class="ui-card flex items-center gap-3 p-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-accent/10 text-brand-accent"><component :is="k.icon" class="h-5 w-5" aria-hidden="true" /></span>
                    <span class="min-w-0">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ k.label }}</span>
                        <span class="block truncate text-lg font-black tabular-nums text-slate-900 dark:text-zinc-100">{{ k.value }}</span>
                    </span>
                </div>
            </div>

            <section class="ui-card space-y-3 p-4 sm:p-5" aria-label="Filtros">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-12">
                    <div class="sm:col-span-2 xl:col-span-4">
                        <label for="pag-q" class="ui-label">Buscar</label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input id="pag-q" v-model="f.q" type="search" class="ui-input pl-9" placeholder="Folio, beneficiario, referencia o archivo…" autocomplete="off" />
                        </div>
                    </div>
                    <div class="xl:col-span-2"><DatePickerShadcn v-model="f.desde" label="Pagado desde" :max-value="f.hasta" clearable /></div>
                    <div class="xl:col-span-2"><DatePickerShadcn v-model="f.hasta" label="Pagado hasta" :min-value="f.desde" clearable /></div>
                    <div class="xl:col-span-2"><SearchableSelect v-model="f.tipo_pago" :options="options.tipos" label="Tipo de pago" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" /></div>
                    <div class="xl:col-span-2"><SearchableSelect v-model="f.corporativo_id" :options="options.corporativos" label="Corporativo" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" /></div>
                    <div class="xl:col-span-3"><SearchableSelect v-model="f.solicitante_id" :options="options.solicitantes" label="Solicitó" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" /></div>
                    <div class="xl:col-span-3"><SearchableSelect v-model="f.autorizo_id" :options="options.autorizaron" label="Autorizó" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" /></div>
                    <div class="xl:col-span-3"><SearchableSelect v-model="f.user_carga_id" :options="options.registraron" label="Registró el pago" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" /></div>
                    <div class="xl:col-span-3"><SearchableSelect v-model="f.per_page" :options="perPageOptions" label="Mostrar" rounded="xl" /></div>
                </div>
                <div v-if="hayFiltros || loading" class="flex items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs dark:border-white/[0.06]">
                    <span v-if="loading" class="inline-flex items-center gap-1.5 text-slate-500 dark:text-zinc-400" role="status"><Loader2 class="h-3.5 w-3.5 animate-spin" aria-hidden="true" /> Cargando…</span>
                    <span v-else class="text-slate-500 dark:text-zinc-400">{{ pagos.total.toLocaleString('es-MX') }} resultado(s)</span>
                    <button v-if="hayFiltros" type="button" class="inline-flex min-h-[36px] items-center gap-1.5 font-semibold text-slate-600 underline-offset-2 hover:underline dark:text-zinc-300" @click="limpiar">
                        <RotateCcw class="h-3.5 w-3.5" aria-hidden="true" /> Limpiar filtros
                    </button>
                </div>
            </section>

            <div v-if="pagos.data.length === 0" class="ui-card flex flex-col items-center gap-3 px-6 py-16 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-zinc-500"><Banknote class="h-7 w-7" aria-hidden="true" /></span>
                <p class="text-base font-semibold text-slate-700 dark:text-zinc-200">{{ hayFiltros ? 'Ningún pago coincide con los filtros.' : 'Aún no hay pagos registrados.' }}</p>
                <p class="max-w-sm text-sm text-slate-500 dark:text-zinc-400">Los pagos se registran desde la pantalla «Pagos» de cada requisición autorizada.</p>
                <button v-if="hayFiltros" type="button" class="ui-btn-secondary" @click="limpiar">Limpiar filtros</button>
            </div>

            <ul v-else class="grid grid-cols-1 gap-4 transition-opacity sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4" :class="loading ? 'opacity-60' : ''">
                <li v-for="(p, i) in pagos.data" :key="p.id" class="ui-card group flex min-w-0 flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg motion-reduce:transform-none">
                    <FileThumb :kind="p.kind" :url="p.preview_url" :name="p.archivo_original" :ext="p.ext" @open="previewIndex = i">
                        <span class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-bold text-slate-700 shadow-sm dark:bg-zinc-900/90 dark:text-zinc-200">
                            <component :is="tipoIcon[p.tipo_pago] ?? Wallet" class="h-3.5 w-3.5" aria-hidden="true" /> {{ p.tipo_label }}
                        </span>
                    </FileThumb>

                    <div class="flex min-w-0 flex-1 flex-col gap-3 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xl font-black tabular-nums tracking-tight text-slate-900 dark:text-zinc-100">{{ money(p.monto) }}</p>
                                <p class="text-xs text-slate-500 dark:text-zinc-400">Pagado el {{ p.fecha_pago ? formatDateOnlyEsMx(p.fecha_pago) : '—' }}</p>
                            </div>
                            <Link v-if="p.requisicion" :href="route('requisiciones.pagar', p.requisicion.id)" class="ui-badge ui-badge-accent shrink-0 hover:underline">{{ p.requisicion.folio }}</Link>
                        </div>

                        <dl class="space-y-1 text-xs">
                            <div class="min-w-0"><dt class="sr-only">Beneficiario</dt><dd class="break-words font-semibold text-slate-700 dark:text-zinc-200">{{ p.beneficiario ?? '—' }}</dd></div>
                            <div v-if="p.banco || p.cuenta" class="flex items-center gap-1.5 text-slate-500 dark:text-zinc-400"><Landmark class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><dd class="truncate">{{ [p.banco, p.cuenta].filter(Boolean).join(' · ') }}</dd></div>
                            <div class="flex gap-1.5 text-slate-500 dark:text-zinc-400"><dt class="shrink-0">Solicitó:</dt><dd class="min-w-0 truncate text-slate-700 dark:text-zinc-300">{{ p.requisicion?.solicitante ?? '—' }}</dd></div>
                            <div class="flex items-center gap-1.5 text-slate-500 dark:text-zinc-400">
                                <BadgeCheck class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><dt class="sr-only">Autorizó</dt>
                                <dd class="min-w-0 truncate">Autorizó: {{ p.requisicion?.autorizo ?? 'No registrado' }}<template v-if="p.requisicion?.fecha_autorizacion"> · {{ formatDateTime(p.requisicion.fecha_autorizacion) }}</template></dd>
                            </div>
                            <div class="flex items-center gap-1.5 text-slate-500 dark:text-zinc-400"><Upload class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><dt class="sr-only">Registró</dt><dd class="min-w-0 truncate">Registró: {{ p.user_carga ?? '—' }}</dd></div>
                            <div v-if="p.referencia" class="flex items-center gap-1.5 text-slate-500 dark:text-zinc-400"><Hash class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><dd class="min-w-0 break-words [overflow-wrap:anywhere]">{{ p.referencia }}</dd></div>
                        </dl>

                        <div class="mt-auto flex flex-wrap gap-1.5 border-t border-slate-100 pt-3 dark:border-white/[0.06]">
                            <button type="button" class="ui-btn-sm" :disabled="p.kind === 'none'" @click="previewIndex = i"><Eye class="h-3.5 w-3.5" aria-hidden="true" /> Ver</button>
                            <a v-if="p.download_url" :href="p.download_url" class="ui-btn-sm"><Download class="h-3.5 w-3.5" aria-hidden="true" /> Descargar</a>
                            <Link v-if="p.requisicion" :href="route('requisiciones.pagar', p.requisicion.id)" class="ui-btn-sm ml-auto">Requisición <ExternalLink class="h-3.5 w-3.5" aria-hidden="true" /></Link>
                        </div>
                    </div>
                </li>
            </ul>

            <nav v-if="pagos.last_page > 1" class="ui-card flex flex-wrap items-center justify-between gap-3 px-4 py-3" aria-label="Paginación">
                <p class="text-xs text-slate-500 dark:text-zinc-400">Mostrando {{ pagos.from }}–{{ pagos.to }} de {{ pagos.total.toLocaleString('es-MX') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="(l, i) in pagos.links"
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
