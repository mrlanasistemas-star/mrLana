<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import SearchableSelect from "@/Components/ui/SearchableSelect.vue";
import DatePickerShadcn from "@/Components/ui/DatePickerShadcn.vue";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import ICON_PDF from "@/img/pdf.png";
import ICON_EXCEL from "@/img/excel.png";
import { toQS, downloadFile } from "@/Utils/exports";
import RequisicionActions from "./partials/RequisicionActions.vue";
import { usePermissions } from "@/Composables/usePermissions";

import type {
    RequisicionesPageProps,
    RequisicionRow,
} from "./Requisiciones.types";
import { useRequisicionesIndex } from "./useRequisicionesIndex";

import {
    Plus,
    Search,
    X,
    Copy,
    ArrowUpDown,
    Building2,
    MapPin,
    User,
    Truck,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    SlidersHorizontal,
    Download,
    FileText,
} from "lucide-vue-next";

const props = defineProps<RequisicionesPageProps>();

const PER_PAGE_OPTIONS: { id: number | "all"; nombre: string }[] = [
    ...[10, 15, 20, 50].map((n) => ({ id: n, nombre: `${n} por página` })),
    { id: "all", nombre: "Todas en una página" },
];

const { can } = usePermissions();

const {
    isColaborador,
    canDelete,
    state,
    rows,
    meta,
    safePagerLinks,
    corporativosActive,
    sucursalesFiltered,
    empleadosFiltered,
    conceptosActive,
    proveedoresActive,
    hasActiveFilters,
    clearFilters,
    selectedCount,
    toggleRow,
    clearSelection,
    destroySelected,
    goTo,
    goCreate,
    destroyRow,
    captureRow,
    requestDeletion,
    money,
    displayName,
    copyText,
} = useRequisicionesIndex(props);

/* ---------- Exportar ---------- */
const exportParams = computed(() => ({
    ...(state as any),
    page: meta.value?.current_page ?? 1,
    perPage: state.perPage,
}));
const exportPdfUrl = computed(() => route("requisiciones.export.pdf") + toQS(exportParams.value));
const exportExcelUrl = computed(() => route("requisiciones.export.excel") + toQS(exportParams.value));

/* ---------- Estatus ---------- */
type StatusKey =
    | "BORRADOR" | "CAPTURADA" | "PAGO_AUTORIZADO" | "PAGO_RECHAZADO" | "PAGADA"
    | "POR_COMPROBAR" | "COMPROBACION_ACEPTADA" | "COMPROBACION_RECHAZADA" | "ELIMINADA";

/**
 * Todo lo visual de un estatus en un solo lugar.
 * - `paso`: etapa alcanzada del flujo (0 a 4); -1 si aún no entra al flujo.
 * - `falla`: etapa que se rechazó, si aplica.
 * - `sigue`: qué falta para avanzar.
 */
/**
 * Clases de la tarjeta (completas, para que Tailwind las detecte):
 * - `card`: borde y un tinte que nace en la esquina superior izquierda y se desvanece.
 * - `foot`: pie (observaciones y acciones) con un velo del mismo tono.
 * - `amount`: el monto total en un tono oscuro del estatus (contraste AA en claro y oscuro).
 * - `dot` hace también de acento lateral.
 */
type StatusInfo = {
    label: string; dot: string; pill: string; bar: string; card: string; foot: string; amount: string;
    paso: number; falla?: number; sigue: string;
};

const STATUS: Record<StatusKey, StatusInfo> = {
    BORRADOR: { label: "Borrador", dot: "bg-slate-400", bar: "bg-slate-400", paso: -1, sigue: "Falta enviarla",
        pill: "bg-slate-100 border-slate-200 text-slate-700 dark:bg-slate-500/10 dark:border-slate-500/20 dark:text-slate-200",
        card: "border-slate-200 from-slate-100/70 hover:border-slate-300 dark:border-white/10 dark:from-slate-400/[0.06] dark:hover:border-white/20",
        foot: "border-slate-100 bg-slate-50/70 dark:border-white/5 dark:bg-white/[0.02]",
        amount: "text-slate-900 dark:text-zinc-100" },
    CAPTURADA: { label: "Capturada", dot: "bg-sky-500", bar: "bg-sky-500", paso: 0, sigue: "Sigue: autorizar el pago",
        pill: "bg-sky-50 border-sky-200 text-sky-700 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-200",
        card: "border-sky-200/70 from-sky-50 hover:border-sky-300 dark:border-sky-400/15 dark:from-sky-400/[0.07] dark:hover:border-sky-400/30",
        foot: "border-sky-100 bg-sky-50/50 dark:border-sky-400/10 dark:bg-sky-400/[0.04]",
        amount: "text-sky-900 dark:text-sky-100" },
    PAGO_AUTORIZADO: { label: "Pago autorizado", dot: "bg-amber-500", bar: "bg-amber-500", paso: 1, sigue: "Sigue: registrar el pago",
        pill: "bg-amber-50 border-amber-200 text-amber-700 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-200",
        card: "border-amber-200/70 from-amber-50 hover:border-amber-300 dark:border-amber-400/15 dark:from-amber-400/[0.07] dark:hover:border-amber-400/30",
        foot: "border-amber-100 bg-amber-50/50 dark:border-amber-400/10 dark:bg-amber-400/[0.04]",
        amount: "text-amber-900 dark:text-amber-100" },
    PAGO_RECHAZADO: { label: "Pago rechazado", dot: "bg-rose-500", bar: "bg-sky-500", paso: 0, falla: 1, sigue: "El pago fue rechazado",
        pill: "bg-rose-50 border-rose-200 text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-200",
        card: "border-rose-200/70 from-rose-50 hover:border-rose-300 dark:border-rose-400/15 dark:from-rose-400/[0.07] dark:hover:border-rose-400/30",
        foot: "border-rose-100 bg-rose-50/50 dark:border-rose-400/10 dark:bg-rose-400/[0.04]",
        amount: "text-rose-900 dark:text-rose-100" },
    PAGADA: { label: "Pagada", dot: "bg-emerald-500", bar: "bg-emerald-500", paso: 2, sigue: "Sigue: comprobar el gasto",
        pill: "bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-200",
        card: "border-emerald-200/70 from-emerald-50 hover:border-emerald-300 dark:border-emerald-400/15 dark:from-emerald-400/[0.07] dark:hover:border-emerald-400/30",
        foot: "border-emerald-100 bg-emerald-50/50 dark:border-emerald-400/10 dark:bg-emerald-400/[0.04]",
        amount: "text-emerald-900 dark:text-emerald-100" },
    POR_COMPROBAR: { label: "Por comprobar", dot: "bg-violet-500", bar: "bg-violet-500", paso: 3, sigue: "Sigue: revisión de Contabilidad",
        pill: "bg-violet-50 border-violet-200 text-violet-700 dark:bg-violet-500/10 dark:border-violet-500/20 dark:text-violet-200",
        card: "border-violet-200/70 from-violet-50 hover:border-violet-300 dark:border-violet-400/15 dark:from-violet-400/[0.07] dark:hover:border-violet-400/30",
        foot: "border-violet-100 bg-violet-50/50 dark:border-violet-400/10 dark:bg-violet-400/[0.04]",
        amount: "text-violet-900 dark:text-violet-100" },
    COMPROBACION_ACEPTADA: { label: "Comprobada", dot: "bg-teal-500", bar: "bg-teal-500", paso: 4, sigue: "Proceso terminado",
        pill: "bg-teal-50 border-teal-200 text-teal-700 dark:bg-teal-500/10 dark:border-teal-500/20 dark:text-teal-200",
        card: "border-teal-200/70 from-teal-50 hover:border-teal-300 dark:border-teal-400/15 dark:from-teal-400/[0.07] dark:hover:border-teal-400/30",
        foot: "border-teal-100 bg-teal-50/50 dark:border-teal-400/10 dark:bg-teal-400/[0.04]",
        amount: "text-teal-900 dark:text-teal-100" },
    COMPROBACION_RECHAZADA: { label: "Comprobación rechazada", dot: "bg-fuchsia-500", bar: "bg-violet-500", paso: 3, falla: 4, sigue: "Hay que corregir comprobantes",
        pill: "bg-fuchsia-50 border-fuchsia-200 text-fuchsia-700 dark:bg-fuchsia-500/10 dark:border-fuchsia-500/20 dark:text-fuchsia-200",
        card: "border-fuchsia-200/70 from-fuchsia-50 hover:border-fuchsia-300 dark:border-fuchsia-400/15 dark:from-fuchsia-400/[0.07] dark:hover:border-fuchsia-400/30",
        foot: "border-fuchsia-100 bg-fuchsia-50/50 dark:border-fuchsia-400/10 dark:bg-fuchsia-400/[0.04]",
        amount: "text-fuchsia-900 dark:text-fuchsia-100" },
    ELIMINADA: { label: "Eliminada", dot: "bg-zinc-300 dark:bg-zinc-600", bar: "bg-zinc-400", paso: -1, sigue: "",
        pill: "bg-zinc-100 border-zinc-200 text-zinc-600 dark:bg-zinc-500/10 dark:border-zinc-500/20 dark:text-zinc-300",
        card: "border-dashed border-zinc-300 from-zinc-100/80 grayscale-[0.6] dark:border-white/10 dark:from-white/[0.02]",
        foot: "border-zinc-200/70 bg-zinc-100/60 dark:border-white/5 dark:bg-white/[0.01]",
        amount: "text-zinc-500 line-through decoration-zinc-400/70 dark:text-zinc-500" },
};

const PASOS = ["Capturada", "Pago autorizado", "Pagada", "Por comprobar", "Comprobada"];

function st(s: any) {
    return STATUS[String(s || "").toUpperCase() as StatusKey] ?? STATUS.BORRADOR;
}

function pasoClass(s: any, i: number) {
    const info = st(s);
    if (info.falla === i) return "bg-rose-500";
    if (i <= info.paso) return info.bar;
    return "bg-slate-200 dark:bg-white/10";
}

function pasoTexto(s: any) {
    const info = st(s);
    if (info.paso < 0) return info.sigue;
    return `Paso ${info.paso + 1} de ${PASOS.length}. ${info.sigue}`;
}

function rowDisabled(r: RequisicionRow) {
    return String((r as any).status || "").toUpperCase() === "ELIMINADA";
}

/* ---------- Accesos rápidos por estatus ---------- */
const conteos = computed<Record<string, number>>(() => props.conteos ?? {});

const tabs = computed(() => {
    const c = conteos.value;
    const activas = Object.entries(c).reduce((acc, [k, n]) => (k === "ELIMINADA" ? acc : acc + Number(n)), 0);
    const orden: StatusKey[] = [
        "BORRADOR", "CAPTURADA", "PAGO_AUTORIZADO", "PAGADA", "POR_COMPROBAR",
        "COMPROBACION_ACEPTADA", "PAGO_RECHAZADO", "COMPROBACION_RECHAZADA", "ELIMINADA",
    ];
    return [
        { id: "", label: "Todas", dot: "", n: activas },
        // Solo los estatus que tienen requisiciones (y el que esté elegido).
        ...orden
            .filter((k) => Number(c[k] ?? 0) > 0 || state.status === k)
            .map((k) => ({ id: k as string, label: STATUS[k].label, dot: STATUS[k].dot, n: Number(c[k] ?? 0) })),
    ];
});

/* ---------- Filtros ---------- */
const corpPicked = computed(() => Number(state.comprador_corp_id || 0) > 0);

const nameOf = (list: any[], id: any, key = "nombre") =>
    String(list.find((x: any) => String(x.id) === String(id))?.[key] ?? "");

function safeDateShort(v: any) {
    if (!v) return "—";
    // Toma solo YYYY-MM-DD aunque venga como datetime o ISO
    const match = String(v).trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!match) return "—";
    const raw = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    if (Number.isNaN(raw.getTime())) return "—";
    return new Intl.DateTimeFormat("es-MX", { day: "2-digit", month: "short", year: "numeric" }).format(raw);
}

const rango = (from: any, to: any) => {
    if (from && to) return `${safeDateShort(from)} a ${safeDateShort(to)}`;
    return from ? `desde ${safeDateShort(from)}` : `hasta ${safeDateShort(to)}`;
};

/** Filtros aplicados, cada uno con su forma de quitarlo. */
const chips = computed(() => {
    const out: { key: string; label: string; clear: () => void }[] = [];
    if (state.comprador_corp_id)
        out.push({ key: "corp", label: `Corporativo: ${nameOf(corporativosActive.value, state.comprador_corp_id)}`, clear: () => (state.comprador_corp_id = "") });
    if (state.sucursal_id)
        out.push({ key: "suc", label: `Sucursal: ${nameOf(sucursalesFiltered.value, state.sucursal_id)}`, clear: () => (state.sucursal_id = "") });
    if (!isColaborador.value && state.solicitante_id)
        out.push({ key: "sol", label: `Solicitante: ${nameOf(empleadosFiltered.value, state.solicitante_id)}`, clear: () => (state.solicitante_id = "") });
    if (state.concepto_id)
        out.push({ key: "con", label: `Concepto: ${nameOf(conceptosActive.value, state.concepto_id)}`, clear: () => (state.concepto_id = "") });
    if (state.proveedor_id)
        out.push({ key: "prov", label: `Proveedor: ${nameOf(proveedoresActive.value, state.proveedor_id, "razon_social")}`, clear: () => (state.proveedor_id = "") });
    if (state.fecha_registro_from || state.fecha_registro_to)
        out.push({
            key: "freg",
            label: `Registro: ${rango(state.fecha_registro_from, state.fecha_registro_to)}`,
            clear: () => { state.fecha_registro_from = ""; state.fecha_registro_to = ""; state.fecha_from = ""; state.fecha_to = ""; },
        });
    if (state.fecha_pago_from || state.fecha_pago_to)
        out.push({
            key: "fpago",
            label: `Fecha de pago: ${rango(state.fecha_pago_from, state.fecha_pago_to)}`,
            clear: () => { state.fecha_pago_from = ""; state.fecha_pago_to = ""; },
        });
    return out;
});

// El panel abre solo si ya hay filtros aplicados al llegar.
const filtersOpen = ref(chips.value.length > 0);

const SORT_OPTIONS: { dir: "desc" | "asc"; nombre: string; corto: string }[] = [
    { dir: "desc", nombre: "Más recientes primero", corto: "Recientes" },
    { dir: "asc", nombre: "Más antiguas primero", corto: "Antiguas" },
];
const sortActual = computed(() => SORT_OPTIONS.find((o) => o.dir === state.dir) ?? SORT_OPTIONS[0]);
const perPageTexto = computed(() => PER_PAGE_OPTIONS.find((o) => o.id === state.perPage)?.nombre ?? "Por página");
const perPageCorto = computed(() => (state.perPage === "all" ? "Todas" : `${state.perPage} por página`));

/* ---------- Paginación ---------- */
const total = computed(() => Number(meta.value?.total ?? rows.value.length));

const pageSummary = computed(() => {
    const from = meta.value?.from ?? null;
    const to = meta.value?.to ?? null;
    if (!from || !to || !total.value) return "";
    return `${from} a ${to} de ${total.value}`;
});

/**
 * Laravel entrega [anterior, ...páginas, siguiente]. Los extremos se pintan
 * como flechas, así que su texto (que llega con entidades como &laquo;) no se usa.
 */
const pager = computed(() => {
    const links = safePagerLinks.value as any[];
    if (links.length < 3) return null;
    return {
        prev: links[0],
        next: links[links.length - 1],
        pages: links.slice(1, -1).map((l) => ({ ...l, text: /^\d+$/.test(l.cleanLabel) ? l.cleanLabel : "…" })),
    };
});
const currentPage = computed(() => Number(meta.value?.current_page ?? 1));
const lastPage = computed(() => Number(meta.value?.last_page ?? 1));

/* ---------- Render progresivo ----------
 * Con "Todas en una página" pueden llegar cientos de filas; pintar todas las
 * tarjetas a la vez congela la página. Se pintan por tandas: un centinela al
 * final pide la siguiente al acercarse (y queda un botón de respaldo).
 * Los totales y conteos siguen usando `rows` completo.
 */
const TANDA = 40;
const visibles = ref(TANDA);
const rowsVisibles = computed(() => rows.value.slice(0, visibles.value));
const faltanPorPintar = computed(() => Math.max(0, rows.value.length - visibles.value));

function mostrarMas() {
    visibles.value = Math.min(rows.value.length, visibles.value + TANDA);
}

// Reinicia al cambiar filtros, orden, tamaño de página o página (no al borrar una fila).
watch(
    () => [JSON.stringify(state), meta.value?.current_page],
    () => { visibles.value = TANDA; },
);

const centinela = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

function reobservar() {
    // Volver a observar dispara de nuevo la revisión: si el centinela sigue a la vista
    // tras pintar una tanda (pantallas altas), se pide otra.
    if (!observer || !centinela.value) return;
    observer.unobserve(centinela.value);
    observer.observe(centinela.value);
}

onMounted(() => {
    if (typeof IntersectionObserver === "undefined") return;
    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((e) => e.isIntersecting) && faltanPorPintar.value > 0) {
                mostrarMas();
                nextTick(reobservar);
            }
        },
        { rootMargin: "800px 0px" },
    );
    if (centinela.value) observer.observe(centinela.value);
});

watch(centinela, (el, prev) => {
    if (!observer) return;
    if (prev) observer.unobserve(prev);
    if (el) observer.observe(el);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
});

const totalMontoPagina = computed(() =>
    rows.value.reduce((acc: number, r: any) => acc + Number(r?.monto_total ?? 0), 0),
);

/* ---------- Clases compartidas ---------- */
const toolBtn =
    "inline-flex h-11 shrink-0 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-sm sm:gap-2 sm:px-3.5 font-semibold text-slate-700 " +
    "hover:border-slate-300 hover:bg-slate-50 transition active:scale-[0.98] " +
    "focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30 motion-reduce:transform-none " +
    "dark:border-white/10 dark:bg-white/5 dark:text-zinc-200 dark:hover:bg-white/10";
const menuItem = "flex min-h-10 cursor-pointer items-center gap-2.5 rounded-xl px-3 text-sm font-semibold text-slate-800 dark:text-zinc-100";
const filterLabel = "block truncate text-xs font-semibold text-slate-600 dark:text-zinc-300";
const pageBtn =
    "inline-flex h-10 min-w-10 items-center justify-center rounded-xl border px-3 text-sm font-semibold tabular-nums transition " +
    "focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30 disabled:pointer-events-none disabled:opacity-40";
const pageBtnIdle =
    "border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-zinc-200 dark:hover:bg-white/10";
</script>

<template>
    <Head title="Requisiciones" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3 min-w-0">
                <div class="flex min-w-0 items-baseline gap-2.5">
                    <h2 class="text-xl font-black text-slate-900 dark:text-zinc-100 truncate">Requisiciones</h2>
                    <span
                        v-if="total"
                        class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold tabular-nums text-slate-600 dark:bg-white/10 dark:text-zinc-300"
                        :title="`${total} con los filtros actuales`"
                    >
                        {{ total }}
                    </span>
                </div>

                <button
                    v-if="can('requisiciones.registrar')"
                    data-tour="requisiciones-nueva"
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-button px-4 text-sm font-black text-brand-button-fg shadow-sm
                           hover:bg-brand-button/90 active:scale-[0.99] transition
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 focus-visible:ring-offset-2"
                    aria-label="Nueva requisición"
                    @click="goCreate"
                >
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    <span class="hidden sm:inline">Nueva requisición</span>
                    <span class="sm:hidden">Nueva</span>
                </button>
            </div>
        </template>

        <div class="w-full max-w-full min-w-0 px-3 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-4">
            <!-- Estatus: accesos rápidos con conteo -->
            <nav
                aria-label="Filtrar por estatus"
                class="-mx-3 snap-x snap-mandatory overflow-x-auto overscroll-x-contain scroll-px-3 -my-1 px-3 py-1 [scrollbar-width:none] sm:m-0 sm:snap-none sm:overflow-visible sm:p-0 [&::-webkit-scrollbar]:hidden"
            >
                <ul class="flex w-max gap-2 sm:w-auto sm:flex-wrap">
                    <li v-for="t in tabs" :key="t.id || 'todas'" class="snap-start">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-full border px-3.5 text-sm font-semibold whitespace-nowrap transition motion-reduce:transition-none
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 focus-visible:ring-offset-1 dark:focus-visible:ring-offset-neutral-950"
                            :class="state.status === t.id
                                ? 'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-900'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-zinc-200 dark:hover:bg-white/10'"
                            :aria-pressed="state.status === t.id"
                            @click="state.status = t.id"
                        >
                            <span v-if="t.dot" class="h-2 w-2 rounded-full" :class="t.dot" aria-hidden="true" />
                            {{ t.label }}
                            <span
                                class="rounded-full px-1.5 text-xs font-bold tabular-nums"
                                :class="state.status === t.id
                                    ? 'bg-white/20 dark:bg-slate-900/10'
                                    : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-zinc-300'"
                            >{{ t.n }}</span>
                        </button>
                    </li>
                </ul>
            </nav>

            <!-- Barra: buscar + filtros + orden + exportar -->
            <div
                data-tour="requisiciones-filtros"
                class="rounded-2xl border border-slate-200/80 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-neutral-900/70"
            >
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
                    <div class="relative min-w-0 lg:flex-1">
                        <label for="req-buscar" class="sr-only">Buscar requisiciones</label>
                        <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-zinc-500" aria-hidden="true" />
                        <input
                            id="req-buscar"
                            v-model="state.q"
                            type="search"
                            placeholder="Buscar por folio, proveedor, concepto o sucursal"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-10 text-sm text-slate-900 placeholder:text-slate-400
                                   focus:border-brand-primary/60 focus:outline-none focus:ring-2 focus:ring-brand-primary/20
                                   dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 dark:placeholder:text-neutral-500
                                   text-ellipsis [&::-webkit-search-cancel-button]:hidden"
                        />
                        <button
                            v-if="state.q"
                            type="button"
                            class="absolute right-0.5 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 dark:text-zinc-300 dark:hover:bg-white/10"
                            aria-label="Borrar búsqueda"
                            @click="state.q = ''"
                        >
                            <X class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>

                    <div class="flex min-w-0 items-center gap-2">
                    <button
                        type="button"
                        :class="[toolBtn, 'min-w-0 flex-1 basis-0 justify-center sm:flex-none sm:basis-auto', filtersOpen || chips.length ? 'border-brand-primary/50 text-brand-primary dark:text-zinc-50' : '']"
                        :aria-expanded="filtersOpen"
                        aria-controls="req-filtros"
                        @click="filtersOpen = !filtersOpen"
                    >
                        <SlidersHorizontal class="h-4 w-4 shrink-0" aria-hidden="true" />
                        Filtros
                        <span
                            v-if="chips.length"
                            class="rounded-full bg-brand-primary px-1.5 text-xs font-bold tabular-nums text-brand-primary-fg"
                        >{{ chips.length }}</span>
                        <ChevronDown class="hidden h-4 w-4 shrink-0 opacity-60 transition-transform motion-reduce:transition-none sm:block" :class="filtersOpen ? 'rotate-180' : ''" aria-hidden="true" />
                    </button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button type="button" :class="[toolBtn, 'min-w-0 flex-1 basis-0 justify-center sm:flex-none sm:basis-auto']" :aria-label="`Orden actual: ${sortActual.nombre}. Cambiar orden`">
                                <ArrowUpDown class="h-4 w-4 shrink-0" aria-hidden="true" />
                                <span class="hidden text-slate-500 sm:inline dark:text-zinc-400">Orden:</span>
                                <span class="hidden truncate xl:inline">{{ sortActual.nombre }}</span>
                                <span class="truncate xl:hidden">{{ sortActual.corto }}</span>
                                <ChevronDown class="hidden h-4 w-4 shrink-0 opacity-60 sm:block" aria-hidden="true" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="min-w-60 border-slate-200/80 bg-white dark:border-white/10 dark:bg-neutral-950">
                            <div class="px-3 pb-1 pt-1.5 text-xs font-semibold text-slate-500 dark:text-zinc-400">Por fecha de registro</div>
                            <DropdownMenuItem
                                v-for="o in SORT_OPTIONS"
                                :key="o.dir"
                                :class="[menuItem, 'justify-between', state.dir === o.dir ? 'bg-brand-tint text-brand-primary dark:bg-brand-primary/15 dark:text-zinc-50' : '']"
                                @select="state.dir = o.dir"
                            >
                                {{ o.nombre }}
                                <Check v-if="state.dir === o.dir" class="h-4 w-4" aria-hidden="true" />
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu v-if="can('requisiciones.exportar')">
                        <DropdownMenuTrigger as-child>
                            <button
                                data-tour="requisiciones-exportar"
                                type="button"
                                :class="[toolBtn, 'w-11 justify-center !px-0 sm:w-auto sm:!px-3.5']"
                                aria-label="Exportar a Excel o PDF"
                                title="Exportar"
                            >
                                <Download class="h-4 w-4 shrink-0" aria-hidden="true" />
                                <span class="hidden sm:inline">Exportar</span>
                                <ChevronDown class="hidden h-4 w-4 opacity-60 sm:block" aria-hidden="true" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="min-w-52 border-slate-200/80 bg-white dark:border-white/10 dark:bg-neutral-950">
                            <DropdownMenuItem :class="menuItem" @select="downloadFile(exportExcelUrl)">
                                <img :src="ICON_EXCEL" class="h-5 w-5" alt="" />
                                Descargar Excel
                            </DropdownMenuItem>
                            <DropdownMenuItem :class="menuItem" @select="downloadFile(exportPdfUrl)">
                                <img :src="ICON_PDF" class="h-5 w-5" alt="" />
                                Descargar PDF
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    </div>
                </div>

                <!-- Panel de filtros -->
                <div v-show="filtersOpen" id="req-filtros" class="mt-3 border-t border-slate-200/70 pt-3 dark:border-white/10">
                    <div class="grid grid-cols-1 gap-x-3 gap-y-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="min-w-0">
                            <label for="f-corp" :class="filterLabel">Corporativo</label>
                            <div class="mt-1">
                                <SearchableSelect
                                    id="f-corp"
                                    v-model="state.comprador_corp_id"
                                    :options="corporativosActive"
                                    placeholder="Todos"
                                    searchPlaceholder="Buscar corporativo..."
                                    :allowNull="true"
                                    nullLabel="Todos"
                                    :compact="true"
                                    zIndexClass="z-[200000]"
                                    labelKey="nombre"
                                    valueKey="id"
                                />
                            </div>
                        </div>

                        <div class="min-w-0">
                            <label for="f-suc" :class="filterLabel">Sucursal</label>
                            <div class="mt-1">
                                <SearchableSelect
                                    v-if="corpPicked"
                                    id="f-suc"
                                    v-model="state.sucursal_id"
                                    :options="sucursalesFiltered"
                                    placeholder="Todas"
                                    searchPlaceholder="Buscar sucursal..."
                                    :allowNull="true"
                                    nullLabel="Todas"
                                    :compact="true"
                                    zIndexClass="z-[200000]"
                                    labelKey="nombre"
                                    secondaryKey="codigo"
                                    valueKey="id"
                                />
                                <div
                                    v-else
                                    class="flex min-h-[42px] items-center rounded-xl border border-dashed border-slate-200 px-4 text-sm text-slate-500 dark:border-white/10 dark:text-zinc-400"
                                >
                                    Elige primero un corporativo
                                </div>
                            </div>
                        </div>

                        <div v-if="!isColaborador" class="min-w-0">
                            <label for="f-sol" :class="filterLabel">Solicitante</label>
                            <div class="mt-1">
                                <SearchableSelect
                                    id="f-sol"
                                    v-model="state.solicitante_id"
                                    :options="empleadosFiltered"
                                    placeholder="Todos"
                                    searchPlaceholder="Buscar colaborador..."
                                    :allowNull="true"
                                    nullLabel="Todos"
                                    :compact="true"
                                    zIndexClass="z-[200000]"
                                    labelKey="nombre"
                                    secondaryKey="puesto"
                                    valueKey="id"
                                />
                            </div>
                        </div>

                        <div class="min-w-0">
                            <label for="f-con" :class="filterLabel">Concepto</label>
                            <div class="mt-1">
                                <SearchableSelect
                                    id="f-con"
                                    v-model="state.concepto_id"
                                    :options="conceptosActive"
                                    placeholder="Todos"
                                    searchPlaceholder="Buscar concepto..."
                                    :allowNull="true"
                                    nullLabel="Todos"
                                    :compact="true"
                                    zIndexClass="z-[200000]"
                                    labelKey="nombre"
                                    valueKey="id"
                                />
                            </div>
                        </div>

                        <div class="min-w-0" :class="isColaborador ? '' : 'sm:col-span-2 xl:col-span-2'">
                            <label for="f-prov" :class="filterLabel">Proveedor</label>
                            <div class="mt-1">
                                <SearchableSelect
                                    id="f-prov"
                                    v-model="state.proveedor_id"
                                    :options="proveedoresActive"
                                    placeholder="Todos"
                                    searchPlaceholder="Buscar proveedor..."
                                    :allowNull="true"
                                    nullLabel="Todos"
                                    :compact="true"
                                    zIndexClass="z-[200000]"
                                    labelKey="razon_social"
                                    valueKey="id"
                                />
                            </div>
                        </div>

                        <fieldset class="min-w-0">
                            <legend :class="filterLabel">Fecha de registro</legend>
                            <div class="grid grid-cols-2 gap-2">
                                <DatePickerShadcn v-model="state.fecha_registro_from" placeholder="Desde" :max-value="state.fecha_registro_to || null" clearable />
                                <DatePickerShadcn v-model="state.fecha_registro_to" placeholder="Hasta" :min-value="state.fecha_registro_from || null" clearable />
                            </div>
                        </fieldset>

                        <fieldset class="min-w-0">
                            <legend :class="filterLabel">Fecha de pago</legend>
                            <div class="grid grid-cols-2 gap-2">
                                <DatePickerShadcn v-model="state.fecha_pago_from" placeholder="Desde" :max-value="state.fecha_pago_to || null" clearable />
                                <DatePickerShadcn v-model="state.fecha_pago_to" placeholder="Hasta" :min-value="state.fecha_pago_from || null" clearable />
                            </div>
                        </fieldset>
                    </div>
                </div>

                <!-- Filtros aplicados -->
                <div v-if="chips.length || hasActiveFilters" class="mt-3 flex flex-wrap items-center gap-2">
                    <button
                        v-for="c in chips"
                        :key="c.key"
                        type="button"
                        class="group inline-flex h-10 max-w-full items-center gap-1.5 rounded-full border border-brand-primary/25 bg-brand-tint pl-3 pr-2.5 text-xs font-semibold text-brand-primary
                               hover:border-brand-primary/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 sm:h-8 sm:pr-2
                               dark:bg-brand-primary/15 dark:text-zinc-50"
                        :aria-label="`Quitar filtro ${c.label}`"
                        @click="c.clear()"
                    >
                        <span class="truncate">{{ c.label }}</span>
                        <X class="h-3.5 w-3.5 shrink-0 opacity-60 group-hover:opacity-100" aria-hidden="true" />
                    </button>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex h-10 items-center rounded-full px-3 text-xs font-bold text-slate-600 underline-offset-4 hover:underline
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 sm:h-8 dark:text-zinc-300"
                        @click="clearFilters"
                    >
                        Limpiar todo
                    </button>
                </div>
            </div>

            <!-- Selección múltiple -->
            <div
                v-if="canDelete && selectedCount > 0"
                class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 dark:border-rose-500/25 dark:bg-rose-500/10"
                role="status"
            >
                <span class="text-sm font-semibold text-rose-900 dark:text-rose-100">
                    {{ selectedCount === 1 ? '1 requisición seleccionada' : `${selectedCount} requisiciones seleccionadas` }}
                </span>
                <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:items-center">
                    <button type="button" :class="toolBtn" class="!h-10 justify-center" @click="clearSelection">Quitar selección</button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-xl bg-rose-600 px-4 text-sm font-black text-white hover:bg-rose-700 transition active:scale-[0.98]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 motion-reduce:transform-none dark:focus-visible:ring-offset-neutral-950"
                        @click="destroySelected"
                    >
                        <span class="sm:hidden">Eliminar</span>
                        <span class="hidden sm:inline">Eliminar seleccionadas</span>
                    </button>
                </div>
            </div>

            <!-- "Todas" llegó en bloques por ser demasiadas -->
            <div
                v-if="props.pagination?.all_truncated"
                role="status"
                class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900
                       dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-100"
            >
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-500" aria-hidden="true" />
                <p>
                    Son {{ total.toLocaleString('es-MX') }} requisiciones: para que cargue rápido se muestran de
                    {{ (props.pagination?.all_limit ?? 1000).toLocaleString('es-MX') }} en {{ (props.pagination?.all_limit ?? 1000).toLocaleString('es-MX') }}.
                    Usa la paginación de abajo o agrega filtros para acotar.
                </p>
            </div>

            <!-- Resumen del listado -->
            <div v-if="rows.length" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-1 text-sm text-slate-600 dark:text-zinc-300">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span>Mostrando <span class="font-bold tabular-nums text-slate-900 dark:text-zinc-100">{{ pageSummary || rows.length }}</span></span>
                    <span class="h-4 w-px bg-slate-300 dark:bg-white/15" aria-hidden="true" />
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="-my-1 inline-flex h-10 items-center gap-1.5 rounded-xl px-2.5 font-semibold text-slate-700 hover:bg-slate-100
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 dark:text-zinc-200 dark:hover:bg-white/10"
                                :aria-label="`Mostrando ${perPageTexto}. Cambiar`"
                            >
                                {{ perPageCorto }}
                                <ChevronDown class="h-4 w-4 opacity-60" aria-hidden="true" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="min-w-52 border-slate-200/80 bg-white dark:border-white/10 dark:bg-neutral-950">
                            <DropdownMenuItem
                                v-for="o in PER_PAGE_OPTIONS"
                                :key="o.id"
                                :class="[menuItem, 'justify-between', state.perPage === o.id ? 'bg-brand-tint text-brand-primary dark:bg-brand-primary/15 dark:text-zinc-50' : '']"
                                @select="state.perPage = o.id as any"
                            >
                                {{ o.nombre }}
                                <Check v-if="state.perPage === o.id" class="h-4 w-4" aria-hidden="true" />
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <span>
                    Suma de esta página:
                    <span class="font-bold tabular-nums text-slate-900 dark:text-zinc-100">{{ money(totalMontoPagina) }}</span>
                </span>
            </div>

            <!-- Listado -->
            <div data-tour="requisiciones-lista" class="space-y-3">
                <article
                    v-for="r in rowsVisibles"
                    :key="r.id"
                    class="relative overflow-hidden rounded-2xl border [contain-intrinsic-size:auto_15rem] [content-visibility:auto] bg-white bg-gradient-to-br via-white via-40% to-white shadow-sm
                           transition-[box-shadow,border-color] hover:shadow-md motion-reduce:transition-none
                           dark:bg-neutral-900 dark:via-neutral-900/95 dark:to-neutral-900/95"
                    :class="[st((r as any).status).card, rowDisabled(r) ? 'opacity-80 shadow-none hover:shadow-none' : '']"
                    :aria-label="`Requisición ${(r as any).folio}, ${st((r as any).status).label}`"
                >
                    <!-- Acento del estatus -->
                    <span class="pointer-events-none absolute inset-y-0 left-0 w-1" :class="st((r as any).status).dot" aria-hidden="true" />

                    <div
                        class="grid grid-cols-1 gap-4 p-4 pl-5 sm:pl-6
                               md:grid-cols-[minmax(0,1fr)_auto] md:gap-x-6
                               xl:grid-cols-[minmax(0,1fr)_17rem_11rem] xl:items-center"
                    >
                        <!-- Qué es -->
                        <div class="flex min-w-0 items-start gap-2 md:col-span-2 xl:col-span-1">
                            <label
                                v-if="canDelete"
                                class="-ml-2.5 -mt-2.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-primary/40"
                                :class="rowDisabled(r) ? 'cursor-not-allowed' : 'cursor-pointer hover:bg-slate-900/5 dark:hover:bg-white/5'"
                            >
                                <input
                                    type="checkbox"
                                    :disabled="rowDisabled(r)"
                                    class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-slate-900 focus:outline-none disabled:cursor-not-allowed dark:border-white/20 dark:accent-white"
                                    :aria-label="`Seleccionar ${(r as any).folio}`"
                                    @change="toggleRow(r.id, ($event.target as HTMLInputElement).checked)"
                                />
                            </label>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                                    <Link
                                        :href="route('requisiciones.show', r.id)"
                                        class="rounded text-sm font-bold tabular-nums text-slate-500 underline-offset-4 hover:text-brand-primary hover:underline
                                               focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 dark:text-zinc-400"
                                        :title="`Abrir ${(r as any).folio}`"
                                    >{{ (r as any).folio }}</Link>
                                    <button
                                        type="button"
                                        class="-my-1.5 -ml-1.5 inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-900/5 hover:text-slate-700
                                               focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 dark:hover:bg-white/10 dark:hover:text-zinc-200"
                                        :aria-label="`Copiar folio ${(r as any).folio}`"
                                        title="Copiar folio"
                                        @click.stop="copyText((r as any).folio)"
                                    >
                                        <Copy class="h-3.5 w-3.5" aria-hidden="true" />
                                    </button>
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold whitespace-nowrap" :class="st((r as any).status).pill">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="st((r as any).status).dot" aria-hidden="true" />
                                        {{ st((r as any).status).label }}
                                    </span>
                                    <span
                                        v-if="(r as any).eliminacion_pendiente"
                                        class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                                    >
                                        Eliminación solicitada
                                    </span>
                                </div>

                                <h3 class="mt-1.5 text-base font-black leading-snug text-slate-900 break-words dark:text-zinc-100">
                                    {{ displayName((r as any).concepto) }}
                                </h3>

                                <dl class="mt-2 grid grid-cols-1 gap-x-5 gap-y-1.5 text-sm text-slate-600 sm:grid-cols-2 dark:text-zinc-300">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <dt><Truck class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" /><span class="sr-only">Proveedor</span></dt>
                                        <dd class="truncate" :title="`Proveedor: ${displayName((r as any).proveedor)}`">{{ displayName((r as any).proveedor) }}</dd>
                                    </div>
                                    <div class="flex min-w-0 items-center gap-2">
                                        <dt><User class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" /><span class="sr-only">Solicitante</span></dt>
                                        <dd class="truncate" :title="`Solicitante: ${displayName((r as any).solicitante)}`">{{ displayName((r as any).solicitante) }}</dd>
                                    </div>
                                    <div class="flex min-w-0 items-center gap-2">
                                        <dt><Building2 class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" /><span class="sr-only">Corporativo</span></dt>
                                        <dd class="truncate" :title="`Corporativo: ${displayName((r as any).comprador)}`">{{ displayName((r as any).comprador) }}</dd>
                                    </div>
                                    <div class="flex min-w-0 items-center gap-2">
                                        <dt><MapPin class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" /><span class="sr-only">Sucursal</span></dt>
                                        <dd class="truncate" :title="`Sucursal: ${displayName((r as any).sucursal)}`">{{ displayName((r as any).sucursal) }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- En qué va -->
                        <div class="min-w-0 md:max-w-sm xl:max-w-none">
                            <template v-if="!rowDisabled(r)">
                                <div
                                    class="flex gap-1"
                                    role="img"
                                    :aria-label="`Avance: ${st((r as any).status).label}. ${pasoTexto((r as any).status)}`"
                                >
                                    <span
                                        v-for="(p, i) in PASOS"
                                        :key="p"
                                        class="h-1.5 flex-1 rounded-full"
                                        :class="pasoClass((r as any).status, i)"
                                        :title="p"
                                    />
                                </div>
                                <p class="mt-1.5 text-xs font-semibold text-slate-600 dark:text-zinc-300">{{ pasoTexto((r as any).status) }}</p>
                            </template>

                            <dl class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-zinc-400">
                                <div class="flex gap-1">
                                    <dt>Registro</dt>
                                    <dd class="font-semibold tabular-nums text-slate-700 dark:text-zinc-200">{{ safeDateShort((r as any).fecha_registro_ymd ?? (r as any).created_at) }}</dd>
                                </div>
                                <div class="flex gap-1">
                                    <dt>Solicitud</dt>
                                    <dd class="font-semibold tabular-nums text-slate-700 dark:text-zinc-200">{{ safeDateShort((r as any).fecha_solicitud_ymd ?? (r as any).fecha_solicitud) }}</dd>
                                </div>
                                <div v-if="(r as any).fecha_pago_ymd" class="flex gap-1">
                                    <dt>Pago</dt>
                                    <dd class="font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">{{ safeDateShort((r as any).fecha_pago_ymd) }}</dd>
                                </div>
                                <div v-else-if="(r as any).fecha_pago_esperada" class="flex gap-1">
                                    <dt>Pago esperado</dt>
                                    <dd class="font-semibold tabular-nums text-slate-700 dark:text-zinc-200">{{ safeDateShort((r as any).fecha_pago_esperada) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Cuánto -->
                        <div
                            class="flex min-w-0 items-baseline justify-between gap-3 border-t border-dashed border-slate-200/90 pt-3
                                   md:block md:self-start md:border-0 md:pt-0 md:text-right xl:self-center dark:border-white/10"
                        >
                            <div class="order-2 text-xl font-black tabular-nums leading-none whitespace-nowrap md:order-none" :class="st((r as any).status).amount">
                                <span class="sr-only">Total </span>{{ money((r as any).monto_total) }}
                            </div>
                            <div class="order-1 min-w-0 truncate text-xs tabular-nums text-slate-500 md:order-none md:mt-1.5 dark:text-zinc-400">
                                Subtotal {{ money((r as any).monto_subtotal) }}
                            </div>
                        </div>
                    </div>

                    <!-- Registro de la eliminación: quién, cuándo, si tenía pagos y por qué -->
                    <div
                        v-if="rowDisabled(r) && (r as any).eliminacion"
                        class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-slate-200 bg-white/70 px-4 py-2.5 pl-5 text-xs text-slate-600 sm:pl-6
                               dark:border-white/10 dark:bg-white/[0.03] dark:text-zinc-300"
                    >
                        <span
                            class="inline-flex items-center rounded-full border px-2.5 py-1 font-bold"
                            :class="(r as any).eliminacion.pagado > 0
                                ? 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200'
                                : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-zinc-200'"
                        >
                            <template v-if="(r as any).eliminacion.pagado === null">Pagos al eliminar: sin registro</template>
                            <template v-else-if="(r as any).eliminacion.pagado > 0">Tenía pagos por {{ money((r as any).eliminacion.pagado) }}</template>
                            <template v-else>Sin pagos registrados</template>
                        </span>
                        <span v-if="(r as any).eliminacion.por">
                            Eliminó o autorizó: <span class="font-semibold text-slate-900 dark:text-zinc-100">{{ (r as any).eliminacion.por }}</span>
                        </span>
                        <span v-if="(r as any).eliminacion.fecha" class="tabular-nums">{{ safeDateShort((r as any).eliminacion.fecha) }}</span>
                        <span v-if="(r as any).eliminacion.motivo" class="min-w-0 basis-full break-words line-clamp-2" :title="(r as any).eliminacion.motivo">
                            <span class="font-semibold text-slate-500 dark:text-zinc-400">Motivo:</span> {{ (r as any).eliminacion.motivo }}
                        </span>
                    </div>

                    <!-- Observaciones + acciones -->
                    <div
                        class="flex flex-col gap-3 border-t px-4 py-3 pl-5 sm:pl-6 xl:flex-row xl:items-center xl:justify-between xl:py-2.5"
                        :class="st((r as any).status).foot"
                    >
                        <p
                            v-if="(r as any).observaciones"
                            class="min-w-0 flex-1 text-xs leading-relaxed text-slate-600 line-clamp-2 break-words dark:text-zinc-300"
                            :title="(r as any).observaciones"
                        >
                            <span class="font-semibold text-slate-500 dark:text-zinc-400">Observaciones:</span>
                            {{ (r as any).observaciones }}
                        </p>
                        <span v-else class="hidden flex-1 xl:block" />

                        <RequisicionActions
                            class="xl:justify-end"
                            :row="r"
                            @capture="captureRow(r.id)"
                            @request-delete="requestDeletion(r)"
                            @delete="destroyRow(r)"
                        />
                    </div>
                </article>

                <!-- Más tarjetas (render progresivo) -->
                <div v-if="faltanPorPintar > 0" ref="centinela" class="flex flex-col items-center gap-2 py-4">
                    <button type="button" :class="toolBtn" @click="mostrarMas">
                        Mostrar {{ Math.min(TANDA, faltanPorPintar) }} más
                    </button>
                    <span class="text-xs text-slate-500 dark:text-zinc-400" aria-live="polite">
                        Faltan {{ faltanPorPintar }} de {{ rows.length }} en esta página
                    </span>
                </div>

                <!-- Sin resultados -->
                <div
                    v-if="rows.length === 0"
                    class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center dark:border-white/15 dark:bg-neutral-900/60"
                >
                    <FileText class="mx-auto h-10 w-10 text-slate-300 dark:text-zinc-600" aria-hidden="true" />
                    <p class="mt-3 text-base font-black text-slate-900 dark:text-zinc-100">
                        {{ hasActiveFilters ? 'Ninguna requisición coincide con estos filtros' : 'Todavía no hay requisiciones' }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                        {{ hasActiveFilters ? 'Prueba con otra búsqueda o quita algún filtro.' : 'Cuando registres una, aparecerá aquí con su avance.' }}
                    </p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <button v-if="hasActiveFilters" type="button" :class="toolBtn" @click="clearFilters">Limpiar filtros</button>
                        <button
                            v-else-if="can('requisiciones.registrar')"
                            type="button"
                            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-button px-4 text-sm font-black text-brand-button-fg hover:bg-brand-button/90"
                            @click="goCreate"
                        >
                            <Plus class="h-4 w-4" aria-hidden="true" />
                            Nueva requisición
                        </button>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            <nav
                v-if="pager && rows.length"
                aria-label="Paginación"
                class="flex flex-col gap-3 pt-1 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
            >
                <span class="hidden text-sm text-slate-600 sm:inline dark:text-zinc-300">{{ pageSummary }}</span>

                <div class="flex items-center gap-1.5 sm:flex-wrap">
                    <button type="button" :class="[pageBtn, pageBtnIdle, 'flex-1 gap-1 sm:flex-none']" :disabled="!pager.prev.url" aria-label="Página anterior" @click="goTo(pager.prev.url)">
                        <ChevronLeft class="h-4 w-4" aria-hidden="true" />
                        Anterior
                    </button>

                    <!-- Móvil: solo la página actual -->
                    <span class="px-2 text-center text-sm font-semibold tabular-nums text-slate-700 sm:hidden dark:text-zinc-200" aria-current="page">
                        {{ currentPage }} <span class="font-normal text-slate-500 dark:text-zinc-400">de {{ lastPage }}</span>
                    </span>

                    <button
                        v-for="(l, i) in pager.pages"
                        :key="`${l.text}-${i}`"
                        type="button"
                        class="max-sm:hidden"
                        :class="[pageBtn, l.active ? 'border-brand-primary bg-brand-primary text-brand-primary-fg' : pageBtnIdle]"
                        :disabled="!l.url"
                        :aria-current="l.active ? 'page' : undefined"
                        :aria-label="l.text === '…' ? undefined : `Página ${l.text}`"
                        @click="goTo(l.url)"
                    >
                        {{ l.text }}
                    </button>

                    <button type="button" :class="[pageBtn, pageBtnIdle, 'flex-1 gap-1 sm:flex-none']" :disabled="!pager.next.url" aria-label="Página siguiente" @click="goTo(pager.next.url)">
                        Siguiente
                        <ChevronRight class="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>
            </nav>
        </div>
    </AuthenticatedLayout>
</template>
