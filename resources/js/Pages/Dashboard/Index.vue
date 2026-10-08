<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import VueApexCharts from 'vue3-apexcharts'
import {
    ArrowDownRight, ArrowUpRight, BarChart3, Building2, CalendarRange, CircleDollarSign, ClipboardCheck, Clock, FileStack,
    Globe2, Loader2, MapPin, Minus, PieChart, Receipt, RotateCcw, Tags, TrendingUp, Truck, UserRound, Wallet,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ChartCard from '@/Components/dashboard/ChartCard.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import DatePickerShadcn from '@/Components/ui/DatePickerShadcn.vue'
import { useDashboardCharts } from '@/Composables/useDashboardCharts'
import { downloadFile, toQS } from '@/Utils/exports'
import ICON_PDF from '@/img/pdf.png'
import ICON_EXCEL from '@/img/excel.png'

type Card = { key: string; label: string; value: number; previous: number | null; delta: number | null; format: 'money' | 'int'; display: string; hint: string }
type Item = { id?: number | null; key?: string; name: string; value: number; count?: number; monto?: number }
type Opcion = { id: number | string; nombre: string; corporativo_id?: number | null }
type DashboardView = 'personal' | 'sucursal' | 'corporativo' | 'general'
type Filters = { preset: string; desde: string; hasta: string; corporativo_id: number | null; sucursal_id: number | null; concepto_id: number | null; status: string | null }

const props = defineProps<{
    dashboard: {
        profile: DashboardView
        view: DashboardView
        views: { value: DashboardView; label: string; description: string }[]
        headline: string
        subheadline: string
        userRole: string
        scopeLabel: string
        canExport: boolean
        filters: Filters
        period: { from: string; to: string; label: string; compare: string }
        cards: Card[]
        trend: { granularity: 'day' | 'month'; points: { key: string; name: string; count: number; monto: number }[] }
        statusMix: Item[]
        byConcepto: Item[]
        bySucursal: Item[]
        byProveedor: Item[]
        monthly: Item[]
        comprobantesMix: Item[]
        options: { presets: { value: string; label: string }[]; estatus: Opcion[]; corporativos: Opcion[]; sucursales: Opcion[]; conceptos: Opcion[] }
    }
}>()

const charts = useDashboardCharts()
const d = computed(() => props.dashboard)
/** Vistas acotadas (personal o sucursal) no comparan sucursales entre sí. */
const isPersonal = computed(() => d.value.view === 'personal' || d.value.view === 'sucursal')
const showCorporativos = computed(() => d.value.options.corporativos.length > 0)
const showSucursales = computed(() => d.value.options.sucursales.length > 0)
const viewIcons: Record<DashboardView, unknown> = { personal: UserRound, sucursal: MapPin, corporativo: Building2, general: Globe2 }

/* ---------- Filtros ---------- */
const f = reactive<Filters>({ ...props.dashboard.filters })
const loading = ref(false)

const params = () => ({
    preset: f.preset !== 'mes' ? f.preset : undefined,
    desde: f.preset === 'rango' ? f.desde : undefined,
    hasta: f.preset === 'rango' ? f.hasta : undefined,
    corporativo_id: f.corporativo_id || undefined,
    sucursal_id: f.sucursal_id || undefined,
    concepto_id: f.concepto_id || undefined,
    status: f.status || undefined,
})

function apply() {
    router.get(route('dashboard'), { vista: d.value.view, ...params() }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['dashboard'],
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    })
}

watch(() => [f.preset, f.corporativo_id, f.sucursal_id, f.concepto_id, f.status], apply)
watch(() => [f.desde, f.hasta], () => f.preset === 'rango' && f.desde && f.hasta && apply())
watch(() => f.corporativo_id, (corp) => {
    const s = props.dashboard.options.sucursales.find((x) => x.id === f.sucursal_id)
    if (corp && s && Number(s.corporativo_id) !== Number(corp)) f.sucursal_id = null
})

const sucursalesFiltro = computed(() =>
    props.dashboard.options.sucursales.filter((s) => !f.corporativo_id || Number(s.corporativo_id) === Number(f.corporativo_id)),
)
const activeCount = computed(() => [f.corporativo_id, f.sucursal_id, f.concepto_id, f.status].filter(Boolean).length)
function reset() {
    Object.assign(f, { preset: 'mes', corporativo_id: null, sucursal_id: null, concepto_id: null, status: null })
}

/* ---------- Cambio de vista (solo entre las autorizadas) ---------- */
function switchView(view: DashboardView) {
    if (view === d.value.view || loading.value) return
    // Corporativo y sucursal no aplican igual en otra vista: se limpian.
    f.corporativo_id = null
    f.sucursal_id = null
    router.get(route('dashboard'), { vista: view, ...params(), corporativo_id: undefined, sucursal_id: undefined }, {
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    })
}

/* ---------- Exportar exactamente la vista y los filtros actuales ---------- */
const exporting = ref<'pdf' | 'excel' | null>(null)
async function exportar(kind: 'pdf' | 'excel') {
    exporting.value = kind
    try {
        await downloadFile(route(`dashboard.export.${kind}`, { vista: d.value.view }) + toQS(params()))
    } finally {
        exporting.value = null
    }
}

/* ---------- KPIs ---------- */
const cardIcons: Record<string, unknown> = { monto: CircleDollarSign, count: FileStack, avg: Receipt, paid: Wallet, pending: Clock, toCheck: ClipboardCheck }
const deltaTone = (c: Card) => (c.delta === null || c.delta === 0 ? 'flat' : c.delta > 0 ? 'up' : 'down')

/* ---------- Gráficas ---------- */
const sum = (items: Item[]) => items.reduce((a, b) => a + (b.value || 0), 0)
const trendCats = computed(() => d.value.trend.points.map((p) => p.name))
const trendLabel = computed(() => (d.value.trend.granularity === 'month' ? 'por mes' : 'por día'))

const montoSeries = computed(() => [{ name: 'Monto', data: d.value.trend.points.map((p) => p.monto) }])
const montoOpts = computed(() => charts.areaOptions({ categories: trendCats.value, color: charts.seriesColor(0), isCurrency: true, seriesName: 'Monto' }).value)
const countSeries = computed(() => [{ name: 'Requisiciones', data: d.value.trend.points.map((p) => p.count) }])
const countOpts = computed(() => charts.barOptions({ categories: trendCats.value, color: charts.seriesColor(1), seriesName: 'Requisiciones' }).value)

/** Clic en un punto de la gráfica → índice del elemento. */
const onPoint = (fn: (index: number) => void) =>
    (_e: MouseEvent, _chart?: unknown, cfg?: { dataPointIndex?: number }) => fn(cfg?.dataPointIndex ?? -1)

/** Barras horizontales; al hacer clic en una barra se filtra por ese elemento. */
function hbar(items: Item[], color: string, onPick?: (item: Item) => void) {
    const base = charts.hbarOptions({ categories: items.map((i) => i.name), color, isCurrency: true, seriesName: 'Monto', height: Math.max(220, items.length * 38) }).value
    return {
        ...base,
        chart: {
            ...base.chart,
            events: onPick ? { dataPointSelection: onPoint((i) => { const it = items[i]; if (it?.id) onPick(it) }) } : {},
        },
        states: { active: { filter: { type: 'none' as const } } },
    }
}
const conceptoOpts = computed(() => hbar(d.value.byConcepto, charts.seriesColor(2), (it) => (f.concepto_id = it.id ?? null)))
const sucursalOpts = computed(() => hbar(d.value.bySucursal, charts.seriesColor(3), (it) => (f.sucursal_id = it.id ?? null)))
const proveedorOpts = computed(() => hbar(d.value.byProveedor, charts.seriesColor(4)))
const toSeries = (items: Item[]) => [{ name: 'Monto', data: items.map((i) => i.value) }]

const statusItems = computed(() => d.value.statusMix.filter((s) => s.value > 0))
const statusOpts = computed(() => {
    const base = charts.donutOptions({ labels: statusItems.value.map((s) => s.name), total: sum(statusItems.value), totalLabel: 'Requisiciones' }).value
    return {
        ...base,
        chart: {
            ...base.chart,
            events: { dataPointSelection: onPoint((i) => { const it = statusItems.value[i]; if (it?.key) f.status = it.key }) },
        },
    }
})
const compItems = computed(() => d.value.comprobantesMix.filter((s) => s.value > 0))
const compOpts = computed(() => charts.donutOptions({ labels: compItems.value.map((s) => s.name), total: sum(compItems.value), totalLabel: 'Comprobantes' }).value)

const monthlySeries = computed(() => toSeries(d.value.monthly))
const monthlyOpts = computed(() => charts.barOptions({ categories: d.value.monthly.map((m) => m.name), color: charts.seriesColor(0), isCurrency: true, seriesName: 'Monto' }).value)

const chipCls = (on: boolean) => (on ? 'ui-chip ui-chip-on' : 'ui-chip')
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>Dashboard</template>

        <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <!-- Selector de vista: solo las que el rol permite (la inicial es la más amplia) -->
            <nav
                v-if="d.views.length > 1"
                class="flex w-full max-w-full gap-1 overflow-x-auto rounded-2xl border border-slate-200/80 bg-white p-1 shadow-sm [scrollbar-width:none] dark:border-white/10 dark:bg-neutral-900"
                aria-label="Vista del dashboard"
                data-tour="dashboard-vistas"
            >
                <button
                    v-for="v in d.views"
                    :key="v.value"
                    type="button"
                    class="group inline-flex min-h-[42px] shrink-0 items-center gap-2 rounded-xl px-3.5 text-sm font-semibold transition-all duration-150
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 sm:flex-1 sm:justify-center"
                    :class="d.view === v.value
                        ? 'bg-brand-primary text-brand-primary-fg shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-300 dark:hover:bg-white/10'"
                    :aria-pressed="d.view === v.value"
                    :title="v.description"
                    @click="switchView(v.value)"
                >
                    <component :is="viewIcons[v.value]" class="h-4 w-4 shrink-0 transition-transform duration-150 group-hover:scale-110 motion-reduce:transform-none" aria-hidden="true" />
                    {{ v.label }}
                </button>
            </nav>

            <!-- Encabezado -->
            <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between" data-tour="dashboard-encabezado">
                <div class="min-w-0">
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <span class="ui-badge ui-badge-muted">{{ d.userRole || 'Sin rol' }}</span>
                        <span class="ui-badge ui-badge-accent">{{ d.scopeLabel }}</span>
                    </div>
                    <h2 class="text-2xl font-black tracking-tight text-slate-900 dark:text-zinc-100">{{ d.headline }}</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400">
                        {{ d.subheadline }} <span class="font-semibold text-slate-700 dark:text-zinc-200">{{ d.period.label }}: {{ d.period.from }} – {{ d.period.to }}</span>
                    </p>
                </div>
                <div v-if="d.canExport" class="flex flex-wrap gap-2" data-tour="dashboard-exportar">
                    <button type="button" class="ui-btn-secondary" :disabled="exporting !== null" @click="exportar('pdf')">
                        <Loader2 v-if="exporting === 'pdf'" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        <img v-else :src="ICON_PDF" class="h-5 w-5" alt="" /> PDF
                    </button>
                    <button type="button" class="ui-btn-secondary" :disabled="exporting !== null" @click="exportar('excel')">
                        <Loader2 v-if="exporting === 'excel'" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        <img v-else :src="ICON_EXCEL" class="h-5 w-5" alt="" /> Excel
                    </button>
                </div>
            </section>

            <!-- Filtros -->
            <section class="ui-card space-y-4 p-4 sm:p-5" aria-label="Filtros del dashboard" data-tour="dashboard-filtros">
                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Periodo">
                    <CalendarRange class="mr-1 h-4 w-4 text-slate-400" aria-hidden="true" />
                    <button
                        v-for="p in d.options.presets"
                        :key="p.value"
                        type="button"
                        :class="chipCls(f.preset === p.value)"
                        :aria-pressed="f.preset === p.value"
                        @click="f.preset = p.value"
                    >
                        {{ p.label }}
                    </button>
                    <span v-if="loading" class="ml-1 inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-zinc-400" role="status">
                        <Loader2 class="h-3.5 w-3.5 animate-spin" aria-hidden="true" /> Actualizando…
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-12">
                    <template v-if="f.preset === 'rango'">
                        <div class="xl:col-span-2"><DatePickerShadcn v-model="f.desde" label="Desde" :max-value="f.hasta" /></div>
                        <div class="xl:col-span-2"><DatePickerShadcn v-model="f.hasta" label="Hasta" :min-value="f.desde" /></div>
                    </template>
                    <div v-if="showCorporativos" :class="f.preset === 'rango' ? 'xl:col-span-2' : 'xl:col-span-3'">
                        <SearchableSelect v-model="f.corporativo_id" :options="d.options.corporativos" label="Corporativo" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" />
                    </div>
                    <div v-if="showSucursales" :class="f.preset === 'rango' ? 'xl:col-span-2' : 'xl:col-span-3'">
                        <SearchableSelect v-model="f.sucursal_id" :options="sucursalesFiltro" label="Sucursal" placeholder="Todas" :allow-null="true" null-label="Todas" rounded="xl" />
                    </div>
                    <div :class="f.preset === 'rango' ? 'xl:col-span-2' : 'xl:col-span-3'">
                        <SearchableSelect v-model="f.concepto_id" :options="d.options.conceptos" label="Concepto" placeholder="Todos" :allow-null="true" null-label="Todos" rounded="xl" />
                    </div>
                    <div :class="f.preset === 'rango' ? 'xl:col-span-2' : 'xl:col-span-3'">
                        <SearchableSelect v-model="f.status" :options="d.options.estatus" label="Estatus" placeholder="Todos (sin eliminadas)" :allow-null="true" null-label="Todos (sin eliminadas)" rounded="xl" />
                    </div>
                </div>

                <div v-if="activeCount || f.preset !== 'mes'" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-white/[0.06] dark:text-zinc-400">
                    <span>Comparado con {{ d.period.compare }} · Haz clic en una barra o segmento para filtrar.</span>
                    <button type="button" class="inline-flex min-h-[36px] items-center gap-1.5 font-semibold text-slate-600 underline-offset-2 hover:underline dark:text-zinc-300" @click="reset">
                        <RotateCcw class="h-3.5 w-3.5" aria-hidden="true" /> Restablecer filtros
                    </button>
                </div>
            </section>

            <!-- KPIs -->
            <section class="grid grid-cols-1 gap-3 transition-opacity sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6" :class="loading ? 'opacity-60' : ''" aria-label="Indicadores" data-tour="dashboard-indicadores">
                <article v-for="c in d.cards" :key="c.key" class="ui-card group p-4 transition hover:border-slate-300 dark:hover:border-white/15">
                    <div class="flex items-start justify-between gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-accent/10 text-brand-accent">
                            <component :is="cardIcons[c.key]" class="h-[18px] w-[18px]" aria-hidden="true" />
                        </span>
                        <span
                            v-if="c.delta !== null"
                            class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums"
                            :class="{
                                up: 'bg-brand-success/10 text-brand-success',
                                down: 'bg-brand-danger/10 text-brand-danger',
                                flat: 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-zinc-400',
                            }[deltaTone(c)]"
                            :title="`Periodo anterior: ${c.format === 'money' ? charts.fmtMXN(c.previous ?? 0) : c.previous}`"
                        >
                            <component :is="{ up: ArrowUpRight, down: ArrowDownRight, flat: Minus }[deltaTone(c)]" class="h-3 w-3" aria-hidden="true" />
                            {{ Math.abs(c.delta) }}%
                        </span>
                    </div>
                    <p class="mt-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ c.label }}</p>
                    <p class="mt-0.5 break-words text-2xl font-black tabular-nums tracking-tight text-slate-900 dark:text-zinc-100">{{ c.display }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ c.hint }}</p>
                </article>
            </section>

            <!-- Gráficas -->
            <div class="grid grid-cols-1 gap-4 transition-opacity xl:grid-cols-2" :class="loading ? 'opacity-60' : ''">
                <ChartCard title="Gasto en el tiempo" :description="`Monto solicitado ${trendLabel}`" :icon="TrendingUp" :empty="!d.trend.points.some((p) => p.monto > 0)">
                    <VueApexCharts type="area" height="280" width="100%" :options="montoOpts" :series="montoSeries" />
                </ChartCard>
                <ChartCard title="Requisiciones en el tiempo" :description="`Cantidad registrada ${trendLabel}`" :icon="BarChart3" :empty="!d.trend.points.some((p) => p.count > 0)">
                    <VueApexCharts type="bar" height="280" width="100%" :options="countOpts" :series="countSeries" />
                </ChartCard>

                <ChartCard title="Gasto por concepto" description="Top por monto · clic para filtrar" :icon="Tags" :empty="!d.byConcepto.length">
                    <VueApexCharts type="bar" width="100%" :height="Math.max(220, d.byConcepto.length * 38)" :options="conceptoOpts" :series="toSeries(d.byConcepto)" />
                </ChartCard>
                <ChartCard title="Requisiciones por estatus" description="Del periodo, incluye eliminadas · clic para filtrar" :icon="PieChart" :empty="!statusItems.length">
                    <VueApexCharts type="donut" height="300" width="100%" :options="statusOpts" :series="statusItems.map((s) => s.value)" />
                </ChartCard>

                <ChartCard v-if="!isPersonal" title="Gasto por sucursal" description="Top por monto · clic para filtrar" :icon="Building2" :empty="!d.bySucursal.length">
                    <VueApexCharts type="bar" width="100%" :height="Math.max(220, d.bySucursal.length * 38)" :options="sucursalOpts" :series="toSeries(d.bySucursal)" />
                </ChartCard>
                <ChartCard title="Principales proveedores" description="Top por monto en el periodo" :icon="Truck" :empty="!d.byProveedor.length">
                    <VueApexCharts type="bar" width="100%" :height="Math.max(220, d.byProveedor.length * 38)" :options="proveedorOpts" :series="toSeries(d.byProveedor)" />
                </ChartCard>

                <ChartCard title="Últimos 12 meses" description="Monto mensual con los filtros actuales (sin periodo)" :icon="CalendarRange" :empty="!d.monthly.some((m) => m.value > 0)" :class="isPersonal ? '' : 'xl:col-span-1'">
                    <VueApexCharts type="bar" height="280" width="100%" :options="monthlyOpts" :series="monthlySeries" />
                </ChartCard>
                <ChartCard title="Comprobantes" description="Por tipo de documento en el periodo" :icon="Receipt" :empty="!compItems.length" empty-text="No se han cargado comprobantes en este periodo.">
                    <VueApexCharts type="donut" height="300" width="100%" :options="compOpts" :series="compItems.map((s) => s.value)" />
                </ChartCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
