<script setup lang="ts">
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import SearchableSelect from "@/Components/ui/SearchableSelect.vue";
import DatePickerShadcn from "@/Components/ui/DatePickerShadcn.vue";
import ICON_PDF from "@/img/pdf.png";
import ICON_EXCEL from "@/img/excel.png";
import { toQS, downloadFile } from "@/Utils/exports";
import Swal from "sweetalert2";
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
    ClipboardList,
    BadgeDollarSign,
    Building2,
    Tags,
    CalendarDays,
} from "lucide-vue-next";

const props = defineProps<RequisicionesPageProps>();

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
    statusOptions,
    inputBase,
    hasActiveFilters,
    clearFilters,
    sortLabel,
    toggleSort,
    selectedCount,
    isAllSelectedOnPage,
    toggleRow,
    toggleAllOnPage,
    clearSelection,
    destroySelected,
    goTo,
    goShow,
    goPay,
    goComprobar,
    goCreate,
    destroyRow,
    captureRow,
    requestDeletion,
    goAjustes,
    money,
    displayName,
    copyText,
} = useRequisicionesIndex(props);

const exportParams = computed(() => ({
    ...(state as any),
    page: meta.value?.current_page ?? 1,
    perPage: state.perPage,
}));

const exportPdfUrl = computed(
    () => route("requisiciones.export.pdf") + toQS(exportParams.value),
);

const exportExcelUrl = computed(
    () => route("requisiciones.export.excel") + toQS(exportParams.value),
);

const pageSummary = computed(() => {
    const from = meta.value?.from ?? null;
    const to = meta.value?.to ?? null;
    const total = meta.value?.total ?? null;
    if (!from || !to || !total) return "";
    return `Mostrando ${from}–${to} de ${total}`;
});

const corpPicked = computed(() => Number(state.comprador_corp_id || 0) > 0);

const totalRows = computed(() => rows.value.length);

const totalMontoPagina = computed(() =>
    rows.value.reduce(
        (acc: number, r: any) => acc + Number(r?.monto_total ?? 0),
        0,
    ),
);

const capturadasCount = computed(
    () =>
        rows.value.filter(
            (r: any) => String(r?.status || "").toUpperCase() === "CAPTURADA",
        ).length,
);

const pendientesCount = computed(
    () =>
        rows.value.filter(
            (r: any) =>
                String(r?.status || "").toUpperCase() === "POR_COMPROBAR",
        ).length,
);

const onPrint = (id: number | string) => {
    Swal.fire({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 1200,
        timerProgressBar: true,
        icon: "info",
        title: "Generando PDF…",
    });
    const url = route("requisiciones.print", { requisicion: id });
    window.open(url, "_blank", "noopener,noreferrer");
};


function rowDisabled(r: RequisicionRow) {
    return String((r as any).status || "").toUpperCase() === "ELIMINADA";
}

function pillText(s: any) {
    const v = String(s || "").toUpperCase();
    if (v === "BORRADOR") return "Borrador";
    if (v === "ELIMINADA") return "Eliminada";
    if (v === "CAPTURADA") return "Capturada";
    if (v === "PAGO_AUTORIZADO") return "Pago autorizado";
    if (v === "PAGO_RECHAZADO") return "Pago rechazado";
    if (v === "PAGADA") return "Pagada";
    if (v === "POR_COMPROBAR") return "Por comprobar";
    if (v === "COMPROBACION_ACEPTADA") return "Comp. aceptada";
    if (v === "COMPROBACION_RECHAZADA") return "Comp. rechazada";
    return v || "—";
}

function statusClass(s: any) {
    const v = String(s || "").toUpperCase();
    const base =
        "inline-flex max-w-full items-center gap-2 rounded-full px-3 py-1.5 text-[11px] font-black border whitespace-nowrap";

    if (v === "BORRADOR") {
        return (
            base +
            " bg-slate-100 border-slate-200 text-slate-700 dark:bg-slate-500/10 dark:border-slate-500/20 dark:text-slate-200"
        );
    }

    if (v === "CAPTURADA") {
        return (
            base +
            " bg-sky-50 border-sky-200 text-sky-700 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-200"
        );
    }

    if (v === "PAGO_AUTORIZADO") {
        return (
            base +
            " bg-amber-50 border-amber-200 text-amber-700 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-200"
        );
    }

    if (v === "PAGO_RECHAZADO") {
        return (
            base +
            " bg-rose-50 border-rose-200 text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-200"
        );
    }

    if (v === "PAGADA") {
        return (
            base +
            " bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-200"
        );
    }

    if (v === "POR_COMPROBAR") {
        return (
            base +
            " bg-violet-50 border-violet-200 text-violet-700 dark:bg-violet-500/10 dark:border-violet-500/20 dark:text-violet-200"
        );
    }

    if (v === "COMPROBACION_ACEPTADA") {
        return (
            base +
            " bg-teal-50 border-teal-200 text-teal-700 dark:bg-teal-500/10 dark:border-teal-500/20 dark:text-teal-200"
        );
    }

    if (v === "COMPROBACION_RECHAZADA") {
        return (
            base +
            " bg-fuchsia-50 border-fuchsia-200 text-fuchsia-700 dark:bg-fuchsia-500/10 dark:border-fuchsia-500/20 dark:text-fuchsia-200"
        );
    }

    if (v === "ELIMINADA") {
        return (
            base +
            " bg-zinc-100 border-zinc-200 text-zinc-700 dark:bg-zinc-500/10 dark:border-zinc-500/20 dark:text-zinc-200"
        );
    }

    return (
        base +
        " bg-slate-100 border-slate-200 text-slate-700 dark:bg-slate-500/10 dark:border-slate-500/20 dark:text-slate-200"
    );
}

function dotClass(s: any) {
    const v = String(s || "").toUpperCase();

    if (v === "BORRADOR") return "bg-slate-400";
    if (v === "CAPTURADA") return "bg-sky-500";
    if (v === "PAGO_AUTORIZADO") return "bg-amber-500";
    if (v === "PAGO_RECHAZADO") return "bg-rose-500";
    if (v === "PAGADA") return "bg-emerald-500";
    if (v === "POR_COMPROBAR") return "bg-violet-500";
    if (v === "COMPROBACION_ACEPTADA") return "bg-teal-500";
    if (v === "COMPROBACION_RECHAZADA") return "bg-fuchsia-500";
    if (v === "ELIMINADA") return "bg-zinc-500";

    return "bg-slate-400";
}

function safeDateShort(v: any) {
    if (!v) return "—";

    const txt = String(v).trim();

    // Toma solo YYYY-MM-DD aunque venga como datetime o ISO
    const match = txt.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!match) return "—";

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);

    const raw = new Date(year, month - 1, day);

    if (Number.isNaN(raw.getTime())) return "—";

    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(raw);
}

function shortText(v: any, max = 90) {
    const txt = String(v || "").trim();
    if (!txt) return "—";
    return txt.length > max ? txt.slice(0, max).trim() + "…" : txt;
}


function statusAccentColor(s: any): string {
    const st = String(s ?? '').toUpperCase()
    if (st === 'PAGADA')               return '#10b981'
    if (st === 'PAGO_AUTORIZADO')      return '#f59e0b'
    if (st === 'CAPTURADA')            return '#0ea5e9'
    if (st === 'BORRADOR')             return '#94a3b8'
    if (st === 'ELIMINADA')            return '#ef4444'
    if (st === 'POR_COMPROBAR')        return '#8b5cf6'
    if (st === 'COMPROBACION_ACEPTADA') return '#14b8a6'
    if (st === 'COMPROBACION_RECHAZADA') return '#d946ef'
    return '#cbd5e1'
}
</script>

<template>
    <Head title="Requisiciones" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3 min-w-0">
                <div class="min-w-0">
                    <h2
                        class="text-xl font-black text-slate-900 dark:text-zinc-100 truncate"
                    >
                        Requisiciones
                    </h2>
                    <p
                        class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400"
                    >
                        Panel de seguimiento y control
                    </p>
                </div>

                <button
                    v-if="can('requisiciones.registrar')"
                    type="button"
                    aria-label="Nueva requisición"
                    @click="goCreate"
                    class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-black bg-emerald-600 text-white hover:bg-emerald-700 hover:shadow-md hover:-translate-y-[1px] active:scale-[0.99] transition dark:bg-emerald-500 dark:hover:bg-emerald-600"
                >
                    <Plus class="h-4 w-4" />
                    <span class="hidden sm:inline">Nueva</span>
                </button>
            </div>
        </template>

        <div
            class="w-full max-w-full min-w-0 px-3 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-4"
        >
            <!-- RESUMEN -->
            <div
                class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/90 dark:bg-neutral-900/60 backdrop-blur p-4 sm:p-5 shadow-sm"
            >
                <div
                    class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3"
                >
                    <div
                        class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 p-4"
                    >
                        <div
                            class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wide text-slate-500 dark:text-zinc-400"
                        >
                            <ClipboardList class="h-4 w-4" />
                            Registros visibles
                        </div>
                        <div
                            class="mt-2 text-2xl font-black text-slate-900 dark:text-zinc-100"
                        >
                            {{ totalRows }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-zinc-400">
                            {{ pageSummary || "Página actual" }}
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 p-4"
                    >
                        <div
                            class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wide text-slate-500 dark:text-zinc-400"
                        >
                            <BadgeDollarSign class="h-4 w-4" />
                            Total página
                        </div>
                        <div
                            class="mt-2 text-xl font-black text-slate-900 dark:text-zinc-100"
                        >
                            {{ money(totalMontoPagina) }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-zinc-400">
                            Suma del listado actual
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 p-4"
                    >
                        <div
                            class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wide text-slate-500 dark:text-zinc-400"
                        >
                            <CalendarDays class="h-4 w-4" />
                            Capturadas
                        </div>
                        <div
                            class="mt-2 text-2xl font-black text-slate-900 dark:text-zinc-100"
                        >
                            {{ capturadasCount }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-zinc-400">
                            En esta página
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 p-4"
                    >
                        <div
                            class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wide text-slate-500 dark:text-zinc-400"
                        >
                            <Tags class="h-4 w-4" />
                            Por comprobar
                        </div>
                        <div
                            class="mt-2 text-2xl font-black text-slate-900 dark:text-zinc-100"
                        >
                            {{ pendientesCount }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-zinc-400">
                            Pendientes de comprobación
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button v-if="can('requisiciones.exportar')"
                        type="button"
                        @click="downloadFile(exportExcelUrl)"
                        class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black border border-slate-200 bg-white hover:bg-slate-50 hover:shadow-sm active:scale-[0.99] transition dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                        title="Excel"
                    >
                        <img :src="ICON_EXCEL" class="h-5 w-5" alt="Excel" />
                        Excel
                    </button>

                    <button v-if="can('requisiciones.exportar')"
                        type="button"
                        @click="downloadFile(exportPdfUrl)"
                        class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black border border-slate-200 bg-white hover:bg-slate-50 hover:shadow-sm active:scale-[0.99] transition dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                        title="PDF"
                    >
                        <img :src="ICON_PDF" class="h-5 w-5" alt="PDF" />
                        PDF
                    </button>

                    <button
                        type="button"
                        @click="toggleSort"
                        class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black bg-slate-100 hover:bg-slate-200 hover:shadow-sm active:scale-[0.99] transition dark:bg-white/10 dark:hover:bg-white/15 dark:text-zinc-100"
                    >
                        <ArrowUpDown class="h-4 w-4" />
                        <span class="hidden sm:inline">Orden:</span>
                        {{ sortLabel }}
                    </button>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        @click="clearFilters"
                        class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black border border-slate-200 bg-white hover:bg-slate-50 hover:shadow-sm active:scale-[0.99] transition dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                    >
                        Limpiar
                    </button>

                    <template v-if="canDelete && selectedCount > 0">
                        <button
                            type="button"
                            @click="destroySelected"
                            class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black bg-rose-600 text-white hover:bg-rose-700 hover:shadow-sm active:scale-[0.99] transition"
                        >
                            Eliminar ({{ selectedCount }})
                        </button>

                        <button
                            type="button"
                            @click="clearSelection"
                            class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black border border-slate-200 bg-white hover:bg-slate-50 hover:shadow-sm active:scale-[0.99] transition dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                        >
                            Quitar selección
                        </button>
                    </template>
                </div>
            </div>

            <!-- FILTROS -->
            <div
                class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/90 dark:bg-neutral-900/60 backdrop-blur p-4 sm:p-5 shadow-sm"
            >
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                    <div class="lg:col-span-5 min-w-0">
                        <label
                            class="block text-xs font-black text-slate-600 dark:text-zinc-300"
                            >Buscar</label
                        >
                        <div class="relative mt-1">
                            <Search
                                class="h-4 w-4 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 pointer-events-none"
                            />
                            <input
                                v-model="state.q"
                                type="text"
                                placeholder="Folio, proveedor, concepto, sucursal, observación..."
                                :class="inputBase"
                                class="pl-11"
                            />
                            <button
                                v-if="state.q"
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex items-center justify-center h-8 w-8 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                                @click="state.q = ''"
                                title="Limpiar"
                            >
                                <X
                                    class="h-4 w-4 text-slate-700 dark:text-zinc-200"
                                />
                            </button>
                        </div>
                    </div>

                    <div class="lg:col-span-3 min-w-0">
                        <label
                            class="block text-xs font-black text-slate-600 dark:text-zinc-300"
                            >Estatus</label
                        >
                        <select
                            v-model="state.status"
                            :class="inputBase"
                            class="mt-1"
                        >
                            <option
                                v-for="s in statusOptions"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.nombre }}
                            </option>
                        </select>
                    </div>

                    <div class="lg:col-span-4 min-w-0">
                        <label
                            class="block text-xs font-black text-slate-600 dark:text-zinc-300"
                        >
                            Fecha de pago de la requisición
                        </label>
                        <div class="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <DatePickerShadcn
                                v-model="state.fecha_pago_from"
                                placeholder="Desde"
                            />
                            <DatePickerShadcn
                                v-model="state.fecha_pago_to"
                                placeholder="Hasta"
                            />
                        </div>
                    </div>
                </div>

                <details class="mt-3">
                    <summary
                        class="cursor-pointer select-none text-xs font-black text-slate-700 dark:text-zinc-200 rounded-2xl px-3 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/15 inline-flex items-center gap-2"
                    >
                        Más filtros
                        <span
                            class="text-[11px] font-extrabold text-slate-500 dark:text-zinc-400"
                        >
                            (corporativo, sucursal, solicitante, proveedor,
                            concepto)
                        </span>
                    </summary>

                    <div class="pt-3 grid grid-cols-1 lg:grid-cols-12 gap-3">
                        <div class="lg:col-span-3 min-w-0 relative z-[999999]">
                            <SearchableSelect
                                v-model="state.comprador_corp_id"
                                :options="corporativosActive"
                                label="Corporativo"
                                placeholder="Todos"
                                searchPlaceholder="Buscar corporativo..."
                                :allowNull="true"
                                nullLabel="Todos"
                                rounded="2xl"
                                zIndexClass="z-[200000]"
                                labelKey="nombre"
                                valueKey="id"
                            />
                        </div>

                        <div class="lg:col-span-3 min-w-0 relative z-[999997]">
                            <template v-if="corpPicked">
                                <SearchableSelect
                                    v-model="state.sucursal_id"
                                    :options="sucursalesFiltered"
                                    label="Sucursal"
                                    placeholder="Todas"
                                    searchPlaceholder="Buscar sucursal..."
                                    :allowNull="true"
                                    nullLabel="Todas"
                                    rounded="2xl"
                                    zIndexClass="z-[200000]"
                                    labelKey="nombre"
                                    secondaryKey="codigo"
                                    valueKey="id"
                                />
                            </template>
                            <template v-else>
                                <label
                                    class="block text-xs font-black text-slate-600 dark:text-zinc-300"
                                    >Sucursal</label
                                >
                                <div
                                    class="mt-1 rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 px-4 py-3"
                                >
                                    <div
                                        class="text-sm font-black text-slate-900 dark:text-zinc-100"
                                    >
                                        Todas
                                    </div>
                                    <div
                                        class="text-[12px] font-semibold text-slate-500 dark:text-zinc-400"
                                    >
                                        Elige corporativo.
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="lg:col-span-3 min-w-0 relative z-[999995]">
                            <template v-if="!isColaborador">
                                <SearchableSelect
                                    v-model="state.solicitante_id"
                                    :options="empleadosFiltered"
                                    label="Solicitante"
                                    placeholder="Todos"
                                    searchPlaceholder="Buscar empleado..."
                                    :allowNull="true"
                                    nullLabel="Todos"
                                    rounded="2xl"
                                    zIndexClass="z-[200000]"
                                    labelKey="nombre"
                                    secondaryKey="puesto"
                                    valueKey="id"
                                />
                            </template>
                            <template v-else>
                                <label
                                    class="block text-xs font-black text-slate-600 dark:text-zinc-300"
                                    >Solicitante</label
                                >
                                <div
                                    class="mt-1 rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 px-4 py-3"
                                >
                                    <div
                                        class="text-sm font-black text-slate-900 dark:text-zinc-100"
                                    >
                                        Mis requisiciones
                                    </div>
                                    <div
                                        class="text-[12px] font-semibold text-slate-500 dark:text-zinc-400"
                                    >
                                        Solo se muestran las tuyas
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="lg:col-span-3 min-w-0">
                            <label
                                class="block text-xs font-black text-slate-600 dark:text-zinc-300"
                                >Por página</label
                            >
                            <select
                                v-model="state.perPage"
                                :class="inputBase"
                                class="mt-1"
                            >
                                <option :value="10">10</option>
                                <option :value="15">15</option>
                                <option :value="20">20</option>
                                <option :value="50">50</option>
                                <option value="all">Todos</option>
                            </select>
                        </div>

                        <div class="lg:col-span-6 min-w-0 relative z-[999994]">
                            <SearchableSelect
                                v-model="state.concepto_id"
                                :options="conceptosActive"
                                label="Concepto"
                                placeholder="Todos"
                                searchPlaceholder="Buscar concepto..."
                                :allowNull="true"
                                nullLabel="Todos"
                                rounded="2xl"
                                zIndexClass="z-[200000]"
                                labelKey="nombre"
                                valueKey="id"
                            />
                        </div>

                        <div class="lg:col-span-6 min-w-0 relative z-[999992]">
                            <SearchableSelect
                                v-model="state.proveedor_id"
                                :options="proveedoresActive"
                                label="Proveedor"
                                placeholder="Todos"
                                searchPlaceholder="Buscar proveedor..."
                                :allowNull="true"
                                nullLabel="Todos"
                                rounded="2xl"
                                zIndexClass="z-[200000]"
                                labelKey="razon_social"
                                valueKey="id"
                            />
                        </div>

                        <div class="lg:col-span-12 min-w-0">
                            <label
                                class="block text-xs font-black text-slate-600 dark:text-zinc-300"
                            >
                                Fecha de registro
                            </label>
                            <div class="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <DatePickerShadcn
                                    v-model="state.fecha_registro_from"
                                    placeholder="Desde"
                                />
                                <DatePickerShadcn
                                    v-model="state.fecha_registro_to"
                                    placeholder="Hasta"
                                />
                            </div>
                        </div>
                    </div>
                </details>
            </div>

            <!-- DESKTOP -->
            <div class="hidden xl:block space-y-2">
                <div
                    v-for="r in rows"
                    :key="r.id"
                    class="group border border-l-4 border-slate-200/80 dark:border-white/10 rounded-2xl
                           bg-white dark:bg-neutral-900/90
                           shadow-sm hover:shadow-lg hover:-translate-y-0.5
                           transition-all duration-200
                           hover:border-slate-300/80 dark:hover:border-white/20"
                    :style="{ borderLeftColor: statusAccentColor((r as any).status) }"
                    :class="rowDisabled(r) ? 'opacity-50 pointer-events-none' : ''"
                >
                    <!-- Fila principal -->
                    <div class="flex items-center gap-0 pl-4 pr-3 py-3.5">
                        <!-- Checkbox -->
                        <div class="shrink-0 pr-3">
                            <input
                                type="checkbox"
                                :disabled="rowDisabled(r)"
                                @change="toggleRow(r.id, ($event.target as HTMLInputElement).checked)"
                                class="h-4 w-4 rounded border-slate-300 dark:border-white/20 accent-slate-900 dark:accent-white cursor-pointer"
                            />
                        </div>

                        <!-- Folio + Status badge -->
                        <div class="shrink-0 w-[172px] pr-4">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span
                                    class="font-black text-[13px] tracking-tight text-slate-900 dark:text-zinc-100 truncate leading-tight"
                                    :title="(r as any).folio"
                                >{{ (r as any).folio }}</span>
                                <button
                                    type="button"
                                    class="shrink-0 inline-flex items-center justify-center h-5 w-5 rounded-md
                                           border border-slate-200 bg-transparent hover:bg-slate-100
                                           active:scale-95 transition-all
                                           dark:border-white/10 dark:hover:bg-white/10"
                                    title="Copiar folio"
                                    @click.stop="copyText((r as any).folio)"
                                >
                                    <Copy class="h-2.5 w-2.5 text-slate-400 dark:text-zinc-400" />
                                </button>
                            </div>
                            <div class="mt-1.5">
                                <span :class="statusClass((r as any).status)">
                                    <span class="h-1.5 w-1.5 rounded-full shrink-0" :class="dotClass((r as any).status)"></span>
                                    <span class="text-[10px] truncate">{{ pillText((r as any).status) }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Separador -->
                        <div class="self-stretch w-px bg-slate-100 dark:bg-white/8 shrink-0 mr-4"></div>

                        <!-- Centro: Concepto + 2 columnas info -->
                        <div class="flex-1 min-w-0 mr-4">
                            <div
                                class="font-black text-sm text-slate-900 dark:text-zinc-100 truncate leading-tight"
                                :title="displayName((r as any).concepto)"
                            >{{ displayName((r as any).concepto) }}</div>
                            <div class="mt-1.5 grid grid-cols-2 gap-x-4 gap-y-1">
                                <div class="min-w-0">
                                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Corporativo</div>
                                    <div class="text-[11px] font-semibold text-slate-600 dark:text-zinc-300 truncate">{{ displayName((r as any).comprador) }}</div>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Solicitante</div>
                                    <div class="text-[11px] font-semibold text-slate-600 dark:text-zinc-300 truncate">{{ displayName((r as any).solicitante) }}</div>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Sucursal</div>
                                    <div class="text-[11px] font-semibold text-slate-600 dark:text-zinc-300 truncate">{{ displayName((r as any).sucursal) }}</div>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Proveedor</div>
                                    <div class="text-[11px] font-semibold text-slate-600 dark:text-zinc-300 truncate">{{ displayName((r as any).proveedor) }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Separador -->
                        <div class="self-stretch w-px bg-slate-100 dark:bg-white/8 shrink-0 mr-4"></div>

                        <!-- Fechas — 3 filas -->
                        <div class="shrink-0 w-[172px] space-y-1.5 mr-4">
                            <div class="flex items-baseline gap-2">
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 shrink-0 w-[58px]">Registro</span>
                                <span class="text-[11px] font-semibold text-slate-600 dark:text-zinc-300 tabular-nums">
                                    {{ safeDateShort((r as any).fecha_registro_ymd ?? (r as any).created_at) }}
                                </span>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 shrink-0 w-[58px]">Solicitud</span>
                                <span class="text-[11px] font-semibold text-slate-600 dark:text-zinc-300 tabular-nums">
                                    {{ safeDateShort((r as any).fecha_solicitud_ymd ?? (r as any).fecha_solicitud) }}
                                </span>
                            </div>
                            <div v-if="(r as any).fecha_pago_esperada" class="flex items-baseline gap-2">
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 shrink-0 w-[58px]">Esperada</span>
                                <span class="text-[11px] font-semibold text-slate-600 dark:text-zinc-300 tabular-nums">
                                    {{ safeDateShort((r as any).fecha_pago_esperada) }}
                                </span>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 shrink-0 w-[58px]">Pago</span>
                                <span
                                    class="text-[11px] font-semibold tabular-nums"
                                    :class="(r as any).fecha_pago_ymd ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-300 dark:text-zinc-600'"
                                >{{ safeDateShort((r as any).fecha_pago_ymd) }}</span>
                            </div>
                        </div>

                        <!-- Separador -->
                        <div class="self-stretch w-px bg-slate-100 dark:bg-white/8 shrink-0 mr-3"></div>

                        <!-- Monto + Acciones -->
                        <div class="shrink-0 w-[200px] flex flex-col items-end gap-2.5">
                            <div class="text-right">
                                <div class="text-lg font-black text-slate-900 dark:text-zinc-100 tabular-nums leading-none whitespace-nowrap">
                                    {{ money((r as any).monto_total) }}
                                </div>
                                <div class="mt-0.5 text-[10px] text-slate-400 dark:text-zinc-500 tabular-nums">
                                    Subtotal {{ money((r as any).monto_subtotal) }}
                                </div>
                            </div>

                            <RequisicionActions
                                :row="r"
                                @show="goShow(r.id)"
                                @pay="goPay(r.id)"
                                @comprobar="goComprobar(r.id)"
                                @ajustes="goAjustes(r.id)"
                                @print="onPrint(r.id)"
                                @capture="captureRow(r.id)"
                                @request-delete="requestDeletion(r)"
                                @delete="destroyRow(r)"
                            />
                        </div>
                    </div>

                    <div v-if="(r as any).eliminacion_pendiente" class="px-5 pb-2 -mt-1">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                            Eliminación solicitada · pendiente de Contabilidad
                        </span>
                    </div>

                    <!-- Observaciones — solo si existen -->
                    <div v-if="(r as any).observaciones" class="px-5 pb-3 -mt-1">
                        <div class="rounded-xl bg-slate-50/80 dark:bg-white/5 border border-slate-100/80 dark:border-white/5 px-3 py-2 flex items-start gap-2">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 shrink-0 mt-0.5">Observaciones</span>
                            <span class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed">{{ shortText((r as any).observaciones, 280) }}</span>
                        </div>
                    </div>
                </div>

                <div
                    v-if="rows.length === 0"
                    class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/60 p-10 text-center"
                >
                    <div class="text-sm font-semibold text-slate-400 dark:text-zinc-500">
                        No hay requisiciones con los filtros actuales.
                    </div>
                </div>

                <div
                    class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/90 dark:bg-neutral-900/60 backdrop-blur px-4 sm:px-6 py-4 flex items-center justify-between gap-3"
                >
                    <div
                        class="text-sm font-semibold text-slate-600 dark:text-zinc-300"
                    >
                        {{ pageSummary }}
                    </div>

                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        <button
                            v-for="l in safePagerLinks"
                            :key="l.label + String(l.url)"
                            type="button"
                            @click="goTo(l.url)"
                            :disabled="!l.url"
                            class="inline-flex items-center justify-center h-9 min-w-9 px-3 rounded-2xl text-xs font-black border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99] disabled:opacity-40 disabled:pointer-events-none transition dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                            :class="
                                l.active
                                    ? 'bg-slate-900 text-white border-slate-900 dark:bg-white/15 dark:text-zinc-100 dark:border-white/20'
                                    : ''
                            "
                        >
                            {{ (l as any).uiLabel }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- MOBILE / TABLET -->
            <div class="xl:hidden space-y-2.5">
                <div
                    v-for="r in rows"
                    :key="r.id"
                    class="border border-l-4 border-slate-200/80 dark:border-white/10 rounded-2xl
                           bg-white dark:bg-neutral-900/90 p-4 shadow-sm"
                    :style="{ borderLeftColor: statusAccentColor((r as any).status) }"
                    :class="rowDisabled(r) ? 'opacity-60 pointer-events-none' : ''"
                >
                    <!-- Header: folio + checkbox -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="font-black text-[14px] tracking-tight text-slate-900 dark:text-zinc-100 truncate">
                                    {{ (r as any).folio }}
                                </span>
                                <button
                                    type="button"
                                    class="shrink-0 inline-flex items-center justify-center h-6 w-6 rounded-lg border border-slate-200 bg-transparent hover:bg-slate-100 active:scale-95 transition-all dark:border-white/10 dark:hover:bg-white/10"
                                    title="Copiar folio"
                                    @click.stop="copyText((r as any).folio)"
                                >
                                    <Copy class="h-3 w-3 text-slate-400 dark:text-zinc-400" />
                                </button>
                            </div>

                            <div class="mt-2">
                                <span :class="statusClass((r as any).status)">
                                    <span class="h-2 w-2 rounded-full shrink-0" :class="dotClass((r as any).status)"></span>
                                    <span class="text-[11px]">{{ pillText((r as any).status) }}</span>
                                </span>
                            </div>

                            <div class="mt-1.5 text-[11px] text-slate-500 dark:text-zinc-400 truncate font-semibold">
                                {{ displayName((r as any).concepto) }}
                            </div>
                        </div>

                        <div class="flex items-start gap-2 shrink-0">
                            <!-- Monto prominente en mobile -->
                            <div class="text-right">
                                <div class="text-base font-black text-slate-900 dark:text-zinc-100 tabular-nums">{{ money((r as any).monto_total) }}</div>
                                <div class="text-[10px] text-slate-400 tabular-nums">Subtotal {{ money((r as any).monto_subtotal) }}</div>
                            </div>
                            <input
                                type="checkbox"
                                :disabled="rowDisabled(r)"
                                @change="toggleRow(r.id, ($event.target as HTMLInputElement).checked)"
                                class="h-4 w-4 rounded border-slate-300 dark:border-white/20 mt-0.5"
                            />
                        </div>
                    </div>

                    <!-- Info grid mobile -->
                    <div class="mt-3 space-y-2">
                        <!-- Fechas en 3 filas -->
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div class="rounded-xl border border-slate-100/80 dark:border-white/8 bg-slate-50/60 dark:bg-white/5 px-2.5 py-2">
                                <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Registro</div>
                                <div class="mt-0.5 text-[11px] font-semibold text-slate-700 dark:text-zinc-200 tabular-nums">
                                    {{ safeDateShort((r as any).fecha_registro_ymd ?? (r as any).created_at) }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-100/80 dark:border-white/8 bg-slate-50/60 dark:bg-white/5 px-2.5 py-2">
                                <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Solicitud</div>
                                <div class="mt-0.5 text-[11px] font-semibold text-slate-700 dark:text-zinc-200 tabular-nums">
                                    {{ safeDateShort((r as any).fecha_solicitud_ymd ?? (r as any).fecha_solicitud) }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-100/80 dark:border-white/8 bg-slate-50/60 dark:bg-white/5 px-2.5 py-2">
                                <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Pago esperado</div>
                                <div class="mt-0.5 text-[11px] font-semibold text-slate-700 dark:text-zinc-200 tabular-nums">
                                    {{ (r as any).fecha_pago_esperada ? safeDateShort((r as any).fecha_pago_esperada) : 'Sin definir' }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-100/80 dark:border-white/8 bg-slate-50/60 dark:bg-white/5 px-2.5 py-2">
                                <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Pago</div>
                                <div class="mt-0.5 text-[11px] font-semibold tabular-nums"
                                    :class="(r as any).fecha_pago_ymd ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-300 dark:text-zinc-600'">
                                    {{ safeDateShort((r as any).fecha_pago_ymd) }}
                                </div>
                            </div>
                        </div>

                        <!-- Proveedor + Solicitante -->
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl border border-slate-100/80 dark:border-white/8 bg-slate-50/60 dark:bg-white/5 px-2.5 py-2">
                                <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Proveedor</div>
                                <div class="mt-0.5 text-[11px] font-semibold text-slate-700 dark:text-zinc-200 break-words">
                                    {{ displayName((r as any).proveedor) }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-100/80 dark:border-white/8 bg-slate-50/60 dark:bg-white/5 px-2.5 py-2">
                                <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Solicitante</div>
                                <div class="mt-0.5 text-[11px] font-semibold text-slate-700 dark:text-zinc-200 break-words">
                                    {{ displayName((r as any).solicitante) }}
                                </div>
                            </div>
                        </div>

                        <!-- Corp + Suc -->
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl border border-slate-100/80 dark:border-white/8 bg-slate-50/60 dark:bg-white/5 px-2.5 py-2">
                                <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Corporativo</div>
                                <div class="mt-0.5 text-[11px] font-semibold text-slate-700 dark:text-zinc-200 break-words">
                                    {{ displayName((r as any).comprador) }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-100/80 dark:border-white/8 bg-slate-50/60 dark:bg-white/5 px-2.5 py-2">
                                <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Sucursal</div>
                                <div class="mt-0.5 text-[11px] font-semibold text-slate-700 dark:text-zinc-200 break-words">
                                    {{ displayName((r as any).sucursal) }}
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones (solo si existen) -->
                        <div v-if="(r as any).observaciones" class="rounded-xl border border-slate-100/80 dark:border-white/8 bg-slate-50/60 dark:bg-white/5 px-2.5 py-2">
                            <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Observaciones</div>
                            <div class="mt-0.5 text-[11px] text-slate-600 dark:text-zinc-300 break-words">
                                {{ shortText((r as any).observaciones, 160) }}
                            </div>
                        </div>
                    </div>

                    <div v-if="(r as any).eliminacion_pendiente" class="mt-3">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                            Eliminación solicitada · pendiente
                        </span>
                    </div>

                    <div class="mt-4 border-t border-slate-100 pt-3 dark:border-white/5">
                        <RequisicionActions
                            :row="r"
                            size="md"
                            @show="goShow(r.id)"
                            @pay="goPay(r.id)"
                            @comprobar="goComprobar(r.id)"
                            @ajustes="goAjustes(r.id)"
                            @print="onPrint(r.id)"
                            @capture="captureRow(r.id)"
                            @request-delete="requestDeletion(r)"
                            @delete="destroyRow(r)"
                        />
                    </div>
                </div>

                <div
                    v-if="rows.length === 0"
                    class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/60 backdrop-blur p-6 text-center"
                >
                    <div
                        class="text-sm font-semibold text-slate-500 dark:text-zinc-400"
                    >
                        No hay requisiciones con los filtros actuales.
                    </div>
                </div>

                <div
                    v-if="safePagerLinks.length"
                    class="flex items-center justify-center gap-2 flex-wrap py-2"
                >
                    <button
                        v-for="l in safePagerLinks"
                        :key="l.label + String(l.url)"
                        type="button"
                        @click="goTo(l.url)"
                        :disabled="!l.url"
                        class="inline-flex items-center justify-center h-9 min-w-9 px-3 rounded-2xl text-xs font-black border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99] disabled:opacity-40 disabled:pointer-events-none dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                        :class="
                            l.active
                                ? 'bg-slate-900 text-white border-slate-900 dark:bg-white/15 dark:text-zinc-100 dark:border-white/20'
                                : ''
                        "
                    >
                        {{ (l as any).uiLabel }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
