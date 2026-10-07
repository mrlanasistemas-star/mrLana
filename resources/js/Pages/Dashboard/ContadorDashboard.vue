<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import '@/css/dashboard.css'
import VueApexCharts from 'vue3-apexcharts'
import { useDashboardCharts } from '@/Composables/useDashboardCharts'
import { TrendingUp, DollarSign, FileText, CheckCircle, Clock, AlertCircle, Inbox } from 'lucide-vue-next'
import ICON_PDF from '@/img/pdf.png'
import ICON_EXCEL from '@/img/excel.png'
import { downloadFile } from '@/Utils/exports'

type KPI   = { label: string; value: string | number; hint?: string }
type Point = { name: string; value: number }

type DashboardPayload = {
    userName?: string
    userRole?: string
    exportSegment?: string
    canExport?: boolean
    headline?: string
    subheadline?: string
    kpis?: KPI[]
    activityDaily?: Point[]
    amountsDaily?: Point[]
}

const props = defineProps<{ dashboard?: DashboardPayload }>()

const charts = useDashboardCharts()

// ── Encabezado ───────────────────────────────────────────────────────────────
const headline    = computed(() => props.dashboard?.headline    ?? 'Panel financiero')
const subheadline = computed(() => props.dashboard?.subheadline ?? 'Control de pagos, evidencia y desviación.')

const today = computed(() =>
    new Intl.DateTimeFormat('es-MX', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    }).format(new Date())
)

// ── KPIs ─────────────────────────────────────────────────────────────────────
const kpis = computed<KPI[]>(() => {
    const v = props.dashboard?.kpis
    if (v?.length) return v
    return [
        { label: 'Pagos en cola',   value: '0',     hint: 'Pendientes por liberar.' },
        { label: 'Por comprobar',   value: '0',     hint: 'Esperando evidencia.' },
        { label: 'Monto del mes',   value: '$0.00', hint: 'Total del periodo.' },
        { label: 'Comprobadas',     value: '0',     hint: 'Cerradas con evidencia.' },
    ]
})

// Paleta de gráficas configurada en Configuración.
const kpiAccents  = charts.palette
const kpiIconList = [Clock, AlertCircle, DollarSign, CheckCircle, FileText, TrendingUp]

// ── Placeholders ─────────────────────────────────────────────────────────────
function placeholderDays(n = 14): Point[] {
    const out: Point[] = []
    for (let i = n; i >= 1; i--) out.push({ name: `D-${i}`, value: 0 })
    return out
}

// ── Series reactivas ──────────────────────────────────────────────────────────
const activityDaily = computed<Point[]>(() => {
    const v = props.dashboard?.activityDaily ?? []
    return v.length ? v : placeholderDays(14)
})

const amountsDaily = computed<Point[]>(() => {
    const v = props.dashboard?.amountsDaily ?? []
    return v.length ? v : placeholderDays(14)
})

const hasRealActivity = computed(() => (props.dashboard?.activityDaily?.length ?? 0) > 0)
const hasRealAmounts  = computed(() => (props.dashboard?.amountsDaily?.length  ?? 0) > 0)

// ── Chart: Actividad (área) ───────────────────────────────────────────────────
const activitySeries = computed(() => [{
    name: 'Eventos',
    data: activityDaily.value.map(p => p.value),
}])

const activityChartOptions = computed(() =>
    charts.areaOptions({
        categories: activityDaily.value.map(p => p.name),
        color: charts.seriesColor(0),
        isCurrency: false,
        seriesName: 'Eventos',
    }).value
)

// ── Chart: Montos (área) ──────────────────────────────────────────────────────
const amountsSeries = computed(() => [{
    name: 'Monto',
    data: amountsDaily.value.map(p => p.value),
}])

const amountsChartOptions = computed(() =>
    charts.areaOptions({
        categories: amountsDaily.value.map(p => p.name),
        color: charts.seriesColor(1),
        isCurrency: true,
        seriesName: 'Monto',
    }).value
)

// ── Export ────────────────────────────────────────────────────────────────────
const exporting      = ref<'pdf' | 'excel' | null>(null)
const exportPdfUrl   = computed(() => route('dashboard.export.pdf', { role: props.dashboard?.exportSegment ?? 'colaborador' }))
const exportExcelUrl = computed(() => route('dashboard.export.excel', { role: props.dashboard?.exportSegment ?? 'colaborador' }))

const exportPdf = async () => {
    exporting.value = 'pdf'
    try   { await downloadFile(exportPdfUrl.value) }
    finally { exporting.value = null }
}

const exportExcel = async () => {
    exporting.value = 'excel'
    try   { await downloadFile(exportExcelUrl.value) }
    finally { exporting.value = null }
}
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <div class="erp-page space-y-6">

            <!-- HEADER EJECUTIVO ───────────────────────────────────────── -->
            <div class="erp-panel p-6">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="erp-badge bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-zinc-300">{{ props.dashboard?.userRole || 'Sin rol' }}</span>
                            <span class="text-xs text-slate-500 capitalize">{{ today }}</span>
                        </div>
                        <h1 class="text-2xl font-black text-slate-900 dark:text-zinc-100">{{ headline }}</h1>
                        <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">{{ subheadline }}</p>
                    </div>
                    <div v-if="props.dashboard?.canExport" class="flex items-center gap-2 flex-shrink-0">
                        <button
                            class="erp-button erp-button-secondary h-11 px-4 text-sm"
                            :disabled="exporting !== null"
                            @click="exportPdf"
                        >
                            <img :src="ICON_PDF" class="h-5 w-5" alt="" />
                            <span>{{ exporting === 'pdf' ? 'Exportando…' : 'PDF' }}</span>
                        </button>
                        <button
                            class="erp-button erp-button-secondary h-11 px-4 text-sm"
                            :disabled="exporting !== null"
                            @click="exportExcel"
                        >
                            <img :src="ICON_EXCEL" class="h-5 w-5" alt="" />
                            <span>{{ exporting === 'excel' ? 'Exportando…' : 'Excel' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- KPIs ───────────────────────────────────────────────────── -->
            <div class="erp-grid-kpis">
                <div
                    v-for="(k, idx) in kpis"
                    :key="k.label"
                    class="erp-kpi-card erp-card-hover"
                    :style="{
                        borderLeftWidth: '4px',
                        borderLeftStyle: 'solid',
                        borderLeftColor: kpiAccents[idx % kpiAccents.length],
                    }"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl flex-shrink-0"
                            :style="{ backgroundColor: kpiAccents[idx % kpiAccents.length] + '1a' }"
                        >
                            <component
                                :is="kpiIconList[idx % kpiIconList.length]"
                                class="h-5 w-5"
                                :style="{ color: kpiAccents[idx % kpiAccents.length] }"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="erp-kpi-label">{{ k.label }}</p>
                            <p class="erp-kpi-value">{{ k.value }}</p>
                            <p v-if="k.hint" class="erp-kpi-hint">{{ k.hint }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GRÁFICAS ────────────────────────────────────────────────── -->
            <div class="erp-grid-charts">

                <!-- 1. Actividad diaria -->
                <div class="erp-chart-card">
                    <div class="erp-chart-header">
                        <p class="erp-chart-title">Actividad diaria</p>
                        <p class="erp-chart-desc">Eventos del flujo (últimos días).</p>
                    </div>
                    <div class="erp-chart-body">
                        <div class="relative">
                            <VueApexCharts
                                :options="activityChartOptions"
                                :series="activitySeries"
                                type="area"
                                height="260"
                                width="100%"
                            />
                            <div
                                v-if="!hasRealActivity"
                                class="absolute inset-0 flex flex-col items-center justify-center bg-white/80 dark:bg-zinc-900/80 rounded-xl"
                            >
                                <div class="erp-empty-state">
                                    <div class="erp-empty-state-icon">
                                        <Inbox class="h-6 w-6" />
                                    </div>
                                    <p class="erp-empty-state-title">Sin datos todavía</p>
                                    <p class="erp-empty-state-desc">Aquí verás el ritmo del flujo (captura, pago, evidencia).</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Montos diarios -->
                <div class="erp-chart-card">
                    <div class="erp-chart-header">
                        <p class="erp-chart-title">Montos diarios</p>
                        <p class="erp-chart-desc">Total diario (últimos días).</p>
                    </div>
                    <div class="erp-chart-body">
                        <div class="relative">
                            <VueApexCharts
                                :options="amountsChartOptions"
                                :series="amountsSeries"
                                type="area"
                                height="260"
                                width="100%"
                            />
                            <div
                                v-if="!hasRealAmounts"
                                class="absolute inset-0 flex flex-col items-center justify-center bg-white/80 dark:bg-zinc-900/80 rounded-xl"
                            >
                                <div class="erp-empty-state">
                                    <div class="erp-empty-state-icon">
                                        <Inbox class="h-6 w-6" />
                                    </div>
                                    <p class="erp-empty-state-title">Sin datos todavía</p>
                                    <p class="erp-empty-state-desc">Aquí se ve la tendencia de montos diarios del periodo.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
