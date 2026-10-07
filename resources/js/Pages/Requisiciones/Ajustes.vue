<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    ArrowLeft,
    BadgeCheck,
    Ban,
    CheckCircle2,
    FileText,
    Loader2,
    PlayCircle,
    Scale,
    Send,
    XCircle,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DatePickerShadcn from '@/Components/ui/DatePickerShadcn.vue'
import { ConfirmDialog } from '@/Components/ui/dialog'
import { formatDateOnlyEsMx, formatDateTime } from '@/Utils/date'
import { usePermissions } from '@/Composables/usePermissions'
import { swalNotify } from '@/lib/swal'

type Tipo = 'INCREMENTO_AUTORIZADO' | 'FALTANTE' | 'DEVOLUCION'
type Sentido = 'A_FAVOR_EMPRESA' | 'A_FAVOR_SOLICITANTE'
type Estatus = 'PENDIENTE' | 'APROBADO' | 'RECHAZADO' | 'APLICADO' | 'CANCELADO'

type Ajuste = {
    id: number
    tipo: Tipo
    sentido: Sentido | ''
    monto: number
    monto_anterior: number
    monto_nuevo: number
    estatus: Estatus
    motivo: string | null
    comentario_revision: string | null
    solicitado_por: string | null
    resuelto_por: string | null
    aplicado_por: string | null
    fecha_registro: string | null
    fecha_resolucion: string | null
    fecha_aplicacion: string | null
    can: { revisar: boolean; aplicar: boolean; cancelar: boolean }
}

const props = defineProps<{
    requisicion: {
        id: number
        folio: string
        status: string
        monto_total: number
        concepto: string | null
        proveedor: string | null
        solicitante: string | null
    }
    ajustes: Ajuste[]
    can: { solicitar: boolean }
    today: string
}>()

const { can } = usePermissions()
const MOTIVO_MAX = 2000

/* ---------- Catálogos de presentación ---------- */
const TIPOS: Array<{ value: Tipo; label: string; help: string; sentido: Sentido }> = [
    { value: 'INCREMENTO_AUTORIZADO', label: 'Incremento autorizado', help: 'El gasto real fue mayor al solicitado.', sentido: 'A_FAVOR_SOLICITANTE' },
    { value: 'FALTANTE', label: 'Faltante', help: 'Falta reembolsar una diferencia al solicitante.', sentido: 'A_FAVOR_SOLICITANTE' },
    { value: 'DEVOLUCION', label: 'Devolución', help: 'Sobró dinero y se regresa a la empresa.', sentido: 'A_FAVOR_EMPRESA' },
]
const tipoLabel = (t: string) => TIPOS.find((x) => x.value === t)?.label ?? t

const ESTATUS: Record<Estatus, { label: string; cls: string }> = {
    PENDIENTE: { label: 'Pendiente', cls: 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30' },
    APROBADO: { label: 'Aprobado', cls: 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/30' },
    APLICADO: { label: 'Aplicado', cls: 'bg-sky-50 text-sky-800 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-500/30' },
    RECHAZADO: { label: 'Rechazado', cls: 'bg-rose-50 text-rose-800 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-500/30' },
    CANCELADO: { label: 'Cancelado', cls: 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-white/5 dark:text-zinc-400 dark:ring-white/10' },
}

const money = (v: unknown) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v ?? 0))
const impacto = (a: Pick<Ajuste, 'sentido' | 'monto'>) => (a.sentido === 'A_FAVOR_EMPRESA' ? -1 : 1) * Number(a.monto ?? 0)

/* ---------- Formulario ---------- */
const form = reactive({
    tipo: 'INCREMENTO_AUTORIZADO' as Tipo,
    monto: '' as string | number,
    fecha: props.today,
    motivo: '',
})
const formErrors = ref<Record<string, string>>({})
const submitting = ref(false)
const touched = ref(false)

const sentidoActual = computed<Sentido>(() => TIPOS.find((t) => t.value === form.tipo)?.sentido ?? 'A_FAVOR_SOLICITANTE')
const montoNumero = computed(() => Number(form.monto) || 0)
const nuevoEstimado = computed(() => Math.max(0, props.requisicion.monto_total + (sentidoActual.value === 'A_FAVOR_EMPRESA' ? -1 : 1) * montoNumero.value))

const clientErrors = computed(() => {
    const e: Record<string, string> = {}
    if (!(montoNumero.value > 0)) e.monto = 'El monto debe ser mayor a 0.'
    if (form.motivo.trim().length < 3) e.motivo = 'Describe el motivo del ajuste.'
    if (form.motivo.length > MOTIVO_MAX) e.motivo = 'El motivo no debe exceder 2,000 caracteres.'
    return e
})
const errorFor = (k: string) => formErrors.value[k] ?? (touched.value ? clientErrors.value[k] : undefined)

function submit() {
    touched.value = true
    if (Object.keys(clientErrors.value).length) return
    submitting.value = true
    formErrors.value = {}
    router.post(route('requisiciones.ajustes.store', props.requisicion.id), {
        tipo: form.tipo,
        sentido: sentidoActual.value,
        monto: montoNumero.value,
        fecha: form.fecha,
        motivo: form.motivo.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            form.monto = ''
            form.motivo = ''
            touched.value = false
            swalNotify('Ajuste enviado a revisión.', 'ok')
        },
        onError: (e: Record<string, string>) => (formErrors.value = e),
        onFinish: () => (submitting.value = false),
    })
}

/* ---------- Historial ---------- */
const filtro = ref<'TODOS' | Estatus>('TODOS')
const filtrados = computed(() => (filtro.value === 'TODOS' ? props.ajustes : props.ajustes.filter((a) => a.estatus === filtro.value)))
const conteo = (e: Estatus) => props.ajustes.filter((a) => a.estatus === e).length
const pendientes = computed(() => conteo('PENDIENTE'))

const expandidos = ref<Set<number>>(new Set())
const MOTIVO_RESUMEN = 180
const esLargo = (a: Ajuste) => (a.motivo?.length ?? 0) > MOTIVO_RESUMEN || (a.motivo ?? '').split('\n').length > 3
const toggleMotivo = (id: number) => {
    const s = new Set(expandidos.value)
    s.has(id) ? s.delete(id) : s.add(id)
    expandidos.value = s
}

/* ---------- Acciones con confirmación ---------- */
type Accion = 'APROBAR' | 'RECHAZAR' | 'APLICAR' | 'CANCELAR'
const dialog = reactive({ open: false, accion: null as Accion | null, ajuste: null as Ajuste | null, loading: false, error: null as string | null })

const dialogCopy = computed(() => {
    const a = dialog.ajuste
    const m = a ? money(a.monto) : ''
    switch (dialog.accion) {
        case 'APROBAR': return { title: 'Aprobar ajuste', text: `Se aprobará el ajuste por ${m}. Después podrá aplicarse al monto.`, confirm: 'Aprobar', tone: 'success' as const }
        case 'RECHAZAR': return { title: 'Rechazar ajuste', text: 'El solicitante verá el motivo del rechazo.', confirm: 'Rechazar', tone: 'danger' as const }
        case 'APLICAR': return { title: 'Aplicar ajuste', text: `El monto de la requisición pasará de ${money(props.requisicion.monto_total)} a ${money(Math.max(0, props.requisicion.monto_total + (a ? impacto(a) : 0)))}.`, confirm: 'Aplicar al monto', tone: 'default' as const }
        case 'CANCELAR': return { title: 'Cancelar ajuste', text: 'La solicitud quedará cancelada y ya no podrá revisarse.', confirm: 'Cancelar ajuste', tone: 'danger' as const }
        default: return { title: '', text: '', confirm: '', tone: 'default' as const }
    }
})

function abrir(accion: Accion, ajuste: Ajuste) {
    Object.assign(dialog, { open: true, accion, ajuste, loading: false, error: null })
}

function confirmar(comentario: string) {
    const a = dialog.ajuste
    if (!a || !dialog.accion) return
    dialog.loading = true
    const opts = {
        preserveScroll: true,
        onSuccess: () => (dialog.open = false),
        onError: (e: Record<string, string>) => (dialog.error = Object.values(e)[0] ?? 'No se pudo completar la acción.'),
        onFinish: () => (dialog.loading = false),
    }
    if (dialog.accion === 'APROBAR' || dialog.accion === 'RECHAZAR') {
        router.patch(route('requisiciones.ajustes.review', a.id), { accion: dialog.accion, comentario_revision: comentario || null }, opts)
    } else if (dialog.accion === 'APLICAR') {
        router.post(route('requisiciones.ajustes.apply', a.id), {}, opts)
    } else {
        router.post(route('requisiciones.ajustes.cancel', a.id), {}, opts)
    }
}

const fechaCorta = (v: string | null) => (v ? formatDateTime(v) : '—')
</script>

<template>
    <Head :title="`Ajustes · ${requisicion.folio}`" />

    <AuthenticatedLayout>
        <template #header>Ajustes de monto</template>

        <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
            <!-- Cabecera -->
            <section class="overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-sm dark:border-white/10 dark:bg-zinc-900/70">
                <div class="flex flex-col gap-4 p-4 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-start gap-3">
                        <Link
                            :href="route('requisiciones.show', requisicion.id)"
                            class="inline-flex h-11 shrink-0 items-center gap-2 rounded-2xl border border-slate-200 px-3 text-sm font-semibold text-slate-700
                                   transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60
                                   dark:border-white/10 dark:text-zinc-200 dark:hover:bg-white/5"
                        >
                            <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                            <span class="hidden sm:inline">Volver a la requisición</span>
                            <span class="sm:hidden">Volver</span>
                        </Link>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="break-all text-xl font-black tracking-tight text-slate-900 dark:text-zinc-100">{{ requisicion.folio }}</h2>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:bg-white/10 dark:text-zinc-300">
                                    {{ requisicion.status.replace(/_/g, ' ').toLowerCase().replace(/^./, (c) => c.toUpperCase()) }}
                                </span>
                                <span v-if="pendientes" class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">
                                    {{ pendientes }} pendiente{{ pendientes === 1 ? '' : 's' }}
                                </span>
                            </div>
                            <p class="mt-1 break-words text-sm text-slate-500 [overflow-wrap:anywhere] dark:text-zinc-400">
                                {{ [requisicion.concepto, requisicion.proveedor, requisicion.solicitante].filter(Boolean).join(' · ') || '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-end gap-4 lg:justify-end">
                        <div class="text-left lg:text-right">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Monto actual</p>
                            <p class="text-2xl font-black tabular-nums text-slate-900 dark:text-zinc-100 sm:text-3xl">{{ money(requisicion.monto_total) }}</p>
                        </div>
                        <Link
                            v-if="can('comprobaciones.ver')"
                            :href="route('requisiciones.comprobar', requisicion.id)"
                            class="inline-flex h-11 items-center gap-2 rounded-2xl border border-slate-200 px-3 text-sm font-semibold text-slate-700
                                   transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60
                                   dark:border-white/10 dark:text-zinc-200 dark:hover:bg-white/5"
                        >
                            <FileText class="h-4 w-4" aria-hidden="true" /> Comprobaciones
                        </Link>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-5" :class="props.can.solicitar ? 'xl:grid-cols-[minmax(340px,420px)_minmax(0,1fr)]' : ''">
                <!-- Formulario -->
                <section
                    v-if="props.can.solicitar"
                    class="h-fit rounded-3xl border border-slate-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-zinc-900/70 sm:p-6 xl:sticky xl:top-24"
                    aria-labelledby="nuevo-ajuste"
                >
                    <h3 id="nuevo-ajuste" class="flex items-center gap-2 text-base font-extrabold text-slate-900 dark:text-zinc-100">
                        <Scale class="h-5 w-5" aria-hidden="true" /> Solicitar ajuste
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Contabilidad revisará la solicitud antes de modificar el monto.</p>

                    <form class="mt-5 space-y-4" novalidate @submit.prevent="submit">
                        <fieldset>
                            <legend class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Tipo de ajuste</legend>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3 xl:grid-cols-1">
                                <label
                                    v-for="t in TIPOS"
                                    :key="t.value"
                                    class="flex cursor-pointer items-start gap-3 rounded-2xl border p-3 transition focus-within:ring-2 focus-within:ring-slate-400/60"
                                    :class="form.tipo === t.value
                                        ? 'border-brand-primary/60 bg-brand-primary/[0.06] dark:bg-brand-primary/10'
                                        : 'border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5'"
                                >
                                    <input v-model="form.tipo" type="radio" name="tipo" :value="t.value" class="mt-0.5 h-4 w-4 shrink-0 border-slate-300 text-slate-900 focus:ring-0" />
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold leading-snug text-slate-900 dark:text-zinc-100">{{ t.label }}</span>
                                        <span class="block text-xs leading-snug text-slate-500 dark:text-zinc-400">{{ t.help }}</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <div>
                                <label for="ajuste-monto" class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Monto</label>
                                <div class="relative mt-1">
                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">$</span>
                                    <input
                                        id="ajuste-monto"
                                        v-model="form.monto"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        inputmode="decimal"
                                        :aria-invalid="errorFor('monto') ? 'true' : undefined"
                                        class="min-h-[44px] w-full rounded-2xl border border-slate-200 bg-white pl-7 pr-3 text-sm tabular-nums text-slate-900
                                               focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/50 dark:border-white/10 dark:bg-zinc-950/60 dark:text-zinc-100"
                                    />
                                </div>
                                <p v-if="errorFor('monto')" class="mt-1 text-xs text-rose-600 dark:text-rose-400" role="alert">{{ errorFor('monto') }}</p>
                            </div>
                            <DatePickerShadcn id="ajuste-fecha" v-model="form.fecha" label="Fecha" />
                        </div>

                        <div>
                            <label for="ajuste-motivo" class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Motivo</label>
                            <textarea
                                id="ajuste-motivo"
                                v-model="form.motivo"
                                rows="5"
                                :maxlength="MOTIVO_MAX"
                                placeholder="Explica qué cambió y por qué es necesario el ajuste…"
                                :aria-invalid="errorFor('motivo') ? 'true' : undefined"
                                class="mt-1 w-full resize-y rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/50 dark:border-white/10 dark:bg-zinc-950/60 dark:text-zinc-100"
                            />
                            <div class="mt-1 flex justify-between gap-2 text-xs">
                                <span class="text-rose-600 dark:text-rose-400" role="alert">{{ errorFor('motivo') }}</span>
                                <span class="tabular-nums text-slate-400">{{ form.motivo.length.toLocaleString('es-MX') }}/2,000</span>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-3 text-sm dark:bg-white/5">
                            <div class="flex justify-between gap-2"><span class="text-slate-500 dark:text-zinc-400">Monto actual</span><span class="tabular-nums font-semibold">{{ money(requisicion.monto_total) }}</span></div>
                            <div class="flex justify-between gap-2"><span class="text-slate-500 dark:text-zinc-400">{{ sentidoActual === 'A_FAVOR_EMPRESA' ? 'Disminuye' : 'Aumenta' }}</span><span class="tabular-nums font-semibold" :class="sentidoActual === 'A_FAVOR_EMPRESA' ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400'">{{ sentidoActual === 'A_FAVOR_EMPRESA' ? '−' : '+' }}{{ money(montoNumero) }}</span></div>
                            <div class="mt-1 flex justify-between gap-2 border-t border-slate-200 pt-1 font-bold dark:border-white/10"><span>Nuevo monto estimado</span><span class="tabular-nums">{{ money(nuevoEstimado) }}</span></div>
                        </div>

                        <p v-if="formErrors.tipo || formErrors.sentido || formErrors.fecha" class="text-xs text-rose-600" role="alert">
                            {{ formErrors.tipo || formErrors.sentido || formErrors.fecha }}
                        </p>

                        <button
                            type="submit"
                            :disabled="submitting"
                            class="inline-flex min-h-[46px] w-full items-center justify-center gap-2 rounded-2xl bg-brand-button px-4 text-sm font-bold text-brand-button-fg
                                   shadow-sm transition hover:bg-brand-button/90 active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2
                                   focus-visible:ring-slate-400 disabled:opacity-60 motion-reduce:transform-none dark:focus-visible:ring-offset-zinc-900"
                        >
                            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" aria-hidden="true" />
                            <Send v-else class="h-4 w-4" aria-hidden="true" />
                            Enviar a revisión
                        </button>
                    </form>
                </section>

                <!-- Historial -->
                <section class="min-w-0 rounded-3xl border border-slate-200/70 bg-white shadow-sm dark:border-white/10 dark:bg-zinc-900/70" aria-labelledby="historial-ajustes">
                    <div class="flex flex-col gap-3 border-b border-slate-100 p-4 dark:border-white/5 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <h3 id="historial-ajustes" class="text-base font-extrabold text-slate-900 dark:text-zinc-100">Historial de ajustes</h3>
                        <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 [scrollbar-width:thin]" role="tablist" aria-label="Filtrar por estatus">
                            <button
                                v-for="opt in (['TODOS', 'PENDIENTE', 'APROBADO', 'APLICADO', 'RECHAZADO', 'CANCELADO'] as const)"
                                :key="opt"
                                type="button"
                                role="tab"
                                :aria-selected="filtro === opt"
                                class="inline-flex min-h-[36px] shrink-0 items-center gap-1.5 rounded-full px-3 text-xs font-semibold transition
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60"
                                :class="filtro === opt ? 'bg-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-zinc-300 dark:hover:bg-white/10'"
                                @click="filtro = opt"
                            >
                                {{ opt === 'TODOS' ? 'Todos' : ESTATUS[opt].label }}
                                <span class="tabular-nums opacity-70">{{ opt === 'TODOS' ? ajustes.length : conteo(opt) }}</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="!props.can.solicitar && requisicion.status !== 'ELIMINADA'" class="mx-4 mt-4 rounded-2xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-white/5 dark:text-zinc-300 sm:mx-5">
                        No puedes solicitar ajustes para esta requisición en su estado actual o con tus permisos.
                    </div>

                    <p v-if="filtrados.length === 0" class="p-10 text-center text-sm text-slate-500 dark:text-zinc-400">
                        {{ ajustes.length === 0 ? 'Aún no hay ajustes para esta requisición.' : 'No hay ajustes con este estatus.' }}
                    </p>

                    <!-- Escritorio: tabla -->
                    <div v-else class="hidden lg:block">
                        <table class="w-full table-fixed text-sm">
                            <caption class="sr-only">Ajustes de la requisición {{ requisicion.folio }}</caption>
                            <thead>
                                <tr class="border-b border-slate-100 text-left text-[11px] uppercase tracking-wider text-slate-500 dark:border-white/5 dark:text-zinc-400">
                                    <th scope="col" class="w-[16%] px-5 py-3 font-semibold">Tipo</th>
                                    <th scope="col" class="w-[14%] px-3 py-3 text-right font-semibold">Impacto</th>
                                    <th scope="col" class="px-3 py-3 font-semibold">Motivo y revisión</th>
                                    <th scope="col" class="w-[24%] px-3 py-3 font-semibold">Auditoría</th>
                                    <th scope="col" class="w-[150px] px-5 py-3 text-right font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                <tr v-for="a in filtrados" :key="a.id" class="align-top">
                                    <td class="px-5 py-4">
                                        <p class="break-words font-semibold text-slate-900 dark:text-zinc-100">{{ tipoLabel(a.tipo) }}</p>
                                        <span class="mt-1.5 inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold ring-1" :class="ESTATUS[a.estatus].cls">{{ ESTATUS[a.estatus].label }}</span>
                                    </td>
                                    <td class="px-3 py-4 text-right tabular-nums">
                                        <p class="font-bold" :class="impacto(a) < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400'">{{ impacto(a) < 0 ? '−' : '+' }}{{ money(a.monto) }}</p>
                                        <p v-if="a.estatus === 'APLICADO'" class="mt-1 text-[11px] text-slate-500 dark:text-zinc-400">{{ money(a.monto_anterior) }} → {{ money(a.monto_nuevo) }}</p>
                                    </td>
                                    <td class="min-w-0 px-3 py-4">
                                        <p
                                            class="whitespace-pre-wrap break-words text-slate-700 [overflow-wrap:anywhere] dark:text-zinc-200"
                                            :class="esLargo(a) && !expandidos.has(a.id) ? 'line-clamp-3' : ''"
                                        >{{ a.motivo || '—' }}</p>
                                        <button
                                            v-if="esLargo(a)"
                                            type="button"
                                            class="mt-1 text-xs font-semibold text-slate-600 underline underline-offset-2 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60 dark:text-zinc-300"
                                            :aria-expanded="expandidos.has(a.id)"
                                            @click="toggleMotivo(a.id)"
                                        >
                                            {{ expandidos.has(a.id) ? 'Ver menos' : 'Ver completo' }}
                                        </button>
                                        <p v-if="a.comentario_revision" class="mt-2 whitespace-pre-wrap break-words rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600 [overflow-wrap:anywhere] dark:bg-white/5 dark:text-zinc-300">
                                            <span class="font-semibold">Revisión:</span> {{ a.comentario_revision }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-4 text-xs text-slate-600 dark:text-zinc-300">
                                        <dl class="space-y-1.5">
                                            <div><dt class="inline font-semibold">Solicitó:</dt> <dd class="inline break-words">{{ a.solicitado_por || '—' }}</dd><div class="text-slate-400">{{ formatDateOnlyEsMx(a.fecha_registro) }}</div></div>
                                            <div v-if="a.resuelto_por"><dt class="inline font-semibold">{{ a.estatus === 'RECHAZADO' ? 'Rechazó' : a.estatus === 'CANCELADO' ? 'Canceló' : 'Autorizó' }}:</dt> <dd class="inline break-words">{{ a.resuelto_por }}</dd><div class="text-slate-400">{{ fechaCorta(a.fecha_resolucion) }}</div></div>
                                            <div v-if="a.aplicado_por"><dt class="inline font-semibold">Aplicó:</dt> <dd class="inline break-words">{{ a.aplicado_por }}</dd><div class="text-slate-400">{{ fechaCorta(a.fecha_aplicacion) }}</div></div>
                                        </dl>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-col items-end gap-1.5">
                                            <button v-if="a.can.revisar" type="button" class="action-btn bg-emerald-600 text-white hover:bg-emerald-700" @click="abrir('APROBAR', a)"><CheckCircle2 class="h-4 w-4" aria-hidden="true" /> Aprobar</button>
                                            <button v-if="a.can.revisar" type="button" class="action-btn border border-rose-200 text-rose-700 hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10" @click="abrir('RECHAZAR', a)"><XCircle class="h-4 w-4" aria-hidden="true" /> Rechazar</button>
                                            <button v-if="a.can.aplicar" type="button" class="action-btn bg-brand-button text-brand-button-fg hover:bg-brand-button/90" @click="abrir('APLICAR', a)"><PlayCircle class="h-4 w-4" aria-hidden="true" /> Aplicar</button>
                                            <button v-if="a.can.cancelar" type="button" class="action-btn border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5" @click="abrir('CANCELAR', a)"><Ban class="h-4 w-4" aria-hidden="true" /> Cancelar</button>
                                            <span v-if="!a.can.revisar && !a.can.aplicar && !a.can.cancelar" class="inline-flex items-center gap-1 text-xs text-slate-400"><BadgeCheck class="h-3.5 w-3.5" aria-hidden="true" /> Sin acciones</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Móvil y tableta: tarjetas -->
                    <ul v-if="filtrados.length" class="divide-y divide-slate-100 dark:divide-white/5 lg:hidden">
                        <li v-for="a in filtrados" :key="a.id" class="space-y-3 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="break-words font-semibold text-slate-900 dark:text-zinc-100">{{ tipoLabel(a.tipo) }}</p>
                                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold ring-1" :class="ESTATUS[a.estatus].cls">{{ ESTATUS[a.estatus].label }}</span>
                                </div>
                                <p class="shrink-0 text-right font-bold tabular-nums" :class="impacto(a) < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400'">
                                    {{ impacto(a) < 0 ? '−' : '+' }}{{ money(a.monto) }}
                                </p>
                            </div>
                            <div>
                                <p class="whitespace-pre-wrap break-words text-sm text-slate-700 [overflow-wrap:anywhere] dark:text-zinc-200" :class="esLargo(a) && !expandidos.has(a.id) ? 'line-clamp-4' : ''">{{ a.motivo || '—' }}</p>
                                <button v-if="esLargo(a)" type="button" class="mt-1 min-h-[32px] text-xs font-semibold underline underline-offset-2" :aria-expanded="expandidos.has(a.id)" @click="toggleMotivo(a.id)">
                                    {{ expandidos.has(a.id) ? 'Ver menos' : 'Ver completo' }}
                                </button>
                            </div>
                            <p v-if="a.comentario_revision" class="whitespace-pre-wrap break-words rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600 [overflow-wrap:anywhere] dark:bg-white/5 dark:text-zinc-300">
                                <span class="font-semibold">Revisión:</span> {{ a.comentario_revision }}
                            </p>
                            <div class="space-y-0.5 text-xs text-slate-500 dark:text-zinc-400">
                                <p>Solicitó {{ a.solicitado_por || '—' }} · {{ formatDateOnlyEsMx(a.fecha_registro) }}</p>
                                <p v-if="a.resuelto_por">{{ a.estatus === 'RECHAZADO' ? 'Rechazó' : a.estatus === 'CANCELADO' ? 'Canceló' : 'Autorizó' }} {{ a.resuelto_por }} · {{ fechaCorta(a.fecha_resolucion) }}</p>
                                <p v-if="a.aplicado_por">Aplicó {{ a.aplicado_por }} · {{ fechaCorta(a.fecha_aplicacion) }}</p>
                            </div>
                            <div v-if="a.can.revisar || a.can.aplicar || a.can.cancelar" class="grid grid-cols-2 gap-2">
                                <button v-if="a.can.revisar" type="button" class="action-btn justify-center bg-emerald-600 text-white" @click="abrir('APROBAR', a)"><CheckCircle2 class="h-4 w-4" aria-hidden="true" /> Aprobar</button>
                                <button v-if="a.can.revisar" type="button" class="action-btn justify-center border border-rose-200 text-rose-700 dark:border-rose-500/30 dark:text-rose-300" @click="abrir('RECHAZAR', a)"><XCircle class="h-4 w-4" aria-hidden="true" /> Rechazar</button>
                                <button v-if="a.can.aplicar" type="button" class="action-btn col-span-2 justify-center bg-brand-button text-brand-button-fg" @click="abrir('APLICAR', a)"><PlayCircle class="h-4 w-4" aria-hidden="true" /> Aplicar al monto</button>
                                <button v-if="a.can.cancelar" type="button" class="action-btn col-span-2 justify-center border border-slate-200 text-slate-600 dark:border-white/10 dark:text-zinc-300" @click="abrir('CANCELAR', a)"><Ban class="h-4 w-4" aria-hidden="true" /> Cancelar solicitud</button>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="dialog.open"
            :title="dialogCopy.title"
            :description="dialogCopy.text"
            :confirm-label="dialogCopy.confirm"
            :tone="dialogCopy.tone"
            :loading="dialog.loading"
            :error="dialog.error"
            :with-comment="dialog.accion === 'APROBAR' || dialog.accion === 'RECHAZAR'"
            :comment-label="dialog.accion === 'RECHAZAR' ? 'Motivo del rechazo' : 'Comentario (opcional)'"
            :comment-required="dialog.accion === 'RECHAZAR'"
            @confirm="confirmar"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.action-btn {
    @apply inline-flex min-h-[38px] items-center gap-1.5 rounded-xl px-3 text-xs font-bold transition
        focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60 active:scale-[0.98] motion-reduce:transform-none;
}
</style>
