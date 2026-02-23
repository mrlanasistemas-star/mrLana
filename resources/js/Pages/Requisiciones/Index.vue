<script setup lang="ts">
    import { computed } from 'vue'
    import { Head, router } from '@inertiajs/vue3'
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
    import DatePickerShadcn from '@/Components/ui/DatePickerShadcn.vue'
    import ICON_PDF from '@/img/pdf.png'
    import ICON_EXCEL from '@/img/excel.png'
    import { toQS, downloadFile } from '@/Utils/exports'
    import Swal from 'sweetalert2'
    declare const route: any
    import type { RequisicionesPageProps, RequisicionRow } from './Requisiciones.types'
    import { useRequisicionesIndex } from './useRequisicionesIndex'

    import {
        Plus,
        Search,
        X,
        Copy,
        ArrowUpDown,
        Banknote,
        FileText,
        Printer,
        Trash2,
    } from 'lucide-vue-next'

    const props = defineProps<RequisicionesPageProps>()

    const {
        role,
        isColaborador,
        canDelete,
        canPay,
        canComprobar,
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
        goCreate,
        destroyRow,
        fmtDateLong,
        money,
        displayName,
        copyText,
    } = useRequisicionesIndex(props)

    const exportPdfUrl = computed(() => route('requisiciones.export.pdf') + toQS(state as any))
    const exportExcelUrl = computed(() => route('requisiciones.export.excel') + toQS(state as any))

    const pageSummary = computed(() => {
        const from = meta.value?.from ?? null
        const to = meta.value?.to ?? null
        const total = meta.value?.total ?? null
        if (!from || !to || !total) return ''
        return `Mostrando ${from}–${to} de ${total}`
    })

    const corpPicked = computed(() => Number(state.comprador_corp_id || 0) > 0)

    const onPrint = (id: number | string) => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1200,
            timerProgressBar: true,
            icon: 'info',
            title: 'Generando PDF…',
        })
        const url = route('requisiciones.print', { requisicion: id })
        window.open(url, '_blank', 'noopener,noreferrer')
    }

    const onPay = (id: number | string) => router.visit(route('requisiciones.pagar', { requisicion: id }))
    const onComprobar = (id: number | string) => router.visit(route('requisiciones.comprobar', { requisicion: id }))

    function rowDisabled(r: RequisicionRow) {
        return String((r as any).status || '').toUpperCase() === 'ELIMINADA'
    }

    function pillText(s: any) {
        const v = String(s || '').toUpperCase()
        if (v === 'BORRADOR') return 'Borrador'
        if (v === 'ELIMINADA') return 'Eliminada'
        if (v === 'CAPTURADA') return 'Capturada'
        if (v === 'PAGO_AUTORIZADO') return 'Pago autorizado'
        if (v === 'PAGO_RECHAZADO') return 'Pago rechazado'
        if (v === 'PAGADA') return 'Pagada'
        if (v === 'POR_COMPROBAR') return 'Por comprobar'
        if (v === 'COMPROBACION_ACEPTADA') return 'Comprobación aceptada'
        if (v === 'COMPROBACION_RECHAZADA') return 'Comprobación rechazada'
        return v || '—'
    }

    function statusClass(s: any) {
        const v = String(s || '').toUpperCase()
        const base =
            'inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black border backdrop-blur'
        if (v === 'CAPTURADA') return base + ' bg-teal-500/10 border-teal-500/25 text-teal-900 dark:text-teal-100'
        if (v === 'PAGO_AUTORIZADO') return base + ' bg-amber-500/10 border-amber-500/25 text-amber-900 dark:text-amber-100'
        if (v === 'PAGO_RECHAZADO') return base + ' bg-rose-500/10 border-rose-500/25 text-rose-900 dark:text-rose-100'
        if (v === 'PAGADA' || v === 'COMPROBACION_ACEPTADA')
            return base + ' bg-emerald-500/10 border-emerald-500/25 text-emerald-900 dark:text-emerald-100'
        if (v === 'POR_COMPROBAR') return base + ' bg-violet-500/10 border-violet-500/25 text-violet-900 dark:text-violet-100'
        if (v === 'COMPROBACION_RECHAZADA')
            return base + ' bg-rose-500/10 border-rose-500/25 text-rose-900 dark:text-rose-100'
        if (v === 'ELIMINADA')
            return base + ' bg-zinc-500/10 border-zinc-500/25 text-zinc-900 dark:text-zinc-100'
        return base + ' bg-slate-500/10 border-slate-500/25 text-slate-900 dark:text-zinc-100'
    }

    function dotClass(s: any) {
        const v = String(s || '').toUpperCase()
        if (v === 'CAPTURADA') return 'bg-teal-500'
        if (v === 'PAGO_AUTORIZADO') return 'bg-amber-500'
        if (v === 'PAGO_RECHAZADO') return 'bg-rose-500'
        if (v === 'PAGADA' || v === 'COMPROBACION_ACEPTADA') return 'bg-emerald-500'
        if (v === 'POR_COMPROBAR') return 'bg-violet-500'
        if (v === 'COMPROBACION_RECHAZADA') return 'bg-rose-500'
        if (v === 'ELIMINADA') return 'bg-zinc-500'
        return 'bg-slate-500'
    }

    function safeDateAny(v: any) {
        if (!v) return '—'
        const txt = String(v)
        if (/^\d{4}-\d{2}-\d{2}$/.test(txt)) return txt
        return fmtDateLong(txt)
    }

    const scrollHide = 'overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden'
</script>

<template>
    <Head title="Requisiciones" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3 min-w-0">
                <h2 class="text-xl font-black text-slate-900 dark:text-zinc-100 truncate">Requisiciones</h2>

                <button type="button" @click="goCreate"
                class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-black
                bg-emerald-600 text-white hover:bg-emerald-700 active:scale-[0.99]
                dark:bg-emerald-500 dark:hover:bg-emerald-600">
                    <Plus class="h-4 w-4" />
                    <span class="hidden sm:inline">Nueva</span>
                </button>
            </div>
        </template>

        <div class="w-full max-w-full min-w-0 px-3 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-4">
            <!-- RESUMEN + ACCIONES -->
            <div class="rounded-3xl border border-slate-200/70 dark:border-white/10
            bg-white/85 dark:bg-neutral-900/60 backdrop-blur p-4 sm:p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        <button type="button" @click="downloadFile(exportExcelUrl)"
                        class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black
                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15" title="Excel">
                            <img :src="ICON_EXCEL" class="h-5 w-5" alt="Excel" />
                            Excel
                        </button>

                        <button type="button" @click="downloadFile(exportPdfUrl)"
                        class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black
                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15" title="PDF">
                            <img :src="ICON_PDF" class="h-5 w-5" alt="PDF" />
                            PDF
                        </button>

                        <button type="button" @click="toggleSort"
                        class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black
                        bg-slate-100 hover:bg-slate-200 active:scale-[0.99]
                        dark:bg-white/10 dark:hover:bg-white/15 dark:text-zinc-100">
                            <ArrowUpDown class="h-4 w-4" />
                            <span class="hidden sm:inline">Orden:</span> {{ sortLabel }}
                        </button>

                        <button v-if="hasActiveFilters" type="button" @click="clearFilters"
                        class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black
                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15">
                            Limpiar
                        </button>

                        <template v-if="canDelete && selectedCount > 0">
                            <button type="button" @click="destroySelected"
                            class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black
                            bg-rose-600 text-white hover:bg-rose-700 active:scale-[0.99]">
                                Eliminar ({{ selectedCount }})
                            </button>

                            <button type="button" @click="clearSelection"
                            class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black
                            border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                            dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15">
                                Quitar selección
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- FILTROS (compacto, y “Más filtros” colapsable en mobile) -->
            <div class="rounded-3xl border border-slate-200/70 dark:border-white/10
            bg-white/85 dark:bg-neutral-900/60 backdrop-blur p-4 sm:p-5">
                <!-- Primera fila: lo indispensable -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                    <div class="lg:col-span-5 min-w-0">
                        <label class="block text-xs font-black text-slate-600 dark:text-zinc-300">Buscar</label>
                        <div class="relative mt-1">
                            <Search class="h-4 w-4 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input v-model="state.q" type="text"
                            placeholder="Folio, proveedor, concepto..."
                            :class="inputBase" class="pl-11" />
                            <button v-if="state.q" type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex items-center justify-center
                            h-8 w-8 rounded-xl border border-slate-200 bg-white hover:bg-slate-50
                            dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                            @click="state.q = ''" title="Limpiar">
                                <X class="h-4 w-4 text-slate-700 dark:text-zinc-200" />
                            </button>
                        </div>
                    </div>

                    <div class="lg:col-span-3 min-w-0">
                        <label class="block text-xs font-black text-slate-600 dark:text-zinc-300">Estatus</label>
                        <select v-model="state.status" :class="inputBase" class="mt-1">
                            <option v-for="s in statusOptions" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                        </select>
                    </div>

                    <div class="lg:col-span-4 min-w-0">
                        <label class="block text-xs font-black text-slate-600 dark:text-zinc-300">
                            Fecha de registro (rango)
                        </label>
                        <div class="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <DatePickerShadcn v-model="state.fecha_from" placeholder="Desde" />
                            <DatePickerShadcn v-model="state.fecha_to" placeholder="Hasta" />
                        </div>
                    </div>
                </div>

                <!-- Más filtros colapsables -->
                <details class="mt-3">
                    <summary class="cursor-pointer select-none text-xs font-black text-slate-700 dark:text-zinc-200
                    rounded-2xl px-3 py-2 bg-slate-100 hover:bg-slate-200
                    dark:bg-white/10 dark:hover:bg-white/15 inline-flex items-center gap-2">
                        Más filtros
                        <span class="text-[11px] font-extrabold text-slate-500 dark:text-zinc-400">
                        (corporativo, sucursal, solicitante, proveedor, concepto)
                        </span>
                    </summary>

                    <div class="pt-3 grid grid-cols-1 lg:grid-cols-12 gap-3">
                        <div class="lg:col-span-3 min-w-0 relative z-[999999]">
                            <SearchableSelect v-model="state.comprador_corp_id" :options="corporativosActive"
                            label="Corporativo" placeholder="Todos" searchPlaceholder="Buscar corporativo..."
                            :allowNull="true" nullLabel="Todos" rounded="2xl" zIndexClass="z-[200000]"
                            labelKey="nombre" valueKey="id"/>
                        </div>

                        <div class="lg:col-span-3 min-w-0 relative z-[999997]">
                            <template v-if="corpPicked">
                                <SearchableSelect v-model="state.sucursal_id" :options="sucursalesFiltered"
                                label="Sucursal" placeholder="Todas" searchPlaceholder="Buscar sucursal..."
                                :allowNull="true" nullLabel="Todas" rounded="2xl" zIndexClass="z-[200000]"
                                labelKey="nombre" secondaryKey="codigo" valueKey="id" />
                            </template>
                            <template v-else>
                                <label class="block text-xs font-black text-slate-600 dark:text-zinc-300">Sucursal</label>
                                <div class="mt-1 rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 px-4 py-3">
                                    <div class="text-sm font-black text-slate-900 dark:text-zinc-100">Todas</div>
                                    <div class="text-[12px] font-semibold text-slate-500 dark:text-zinc-400">
                                        Elige corporativo.
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="lg:col-span-3 min-w-0 relative z-[999995]">
                            <template v-if="!isColaborador">
                                <SearchableSelect v-model="state.solicitante_id" :options="empleadosFiltered"
                                label="Solicitante" placeholder="Todos" searchPlaceholder="Buscar empleado..."
                                :allowNull="true" nullLabel="Todos" rounded="2xl" zIndexClass="z-[200000]"
                                labelKey="nombre" secondaryKey="puesto" valueKey="id"/>
                            </template>
                            <template v-else>
                                <label class="block text-xs font-black text-slate-600 dark:text-zinc-300">Solicitante</label>
                                <div class="mt-1 rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 px-4 py-3">
                                    <div class="text-sm font-black text-slate-900 dark:text-zinc-100">Mis requisiciones</div>
                                    <div class="text-[12px] font-semibold text-slate-500 dark:text-zinc-400">Rol: {{ role }}</div>
                                </div>
                            </template>
                        </div>

                        <div class="lg:col-span-3 min-w-0">
                            <label class="block text-xs font-black text-slate-600 dark:text-zinc-300">Por página</label>
                            <select v-model="state.perPage" :class="inputBase" class="mt-1">
                                <option :value="10">10</option>
                                <option :value="15">15</option>
                                <option :value="20">20</option>
                                <option :value="50">50</option>
                            </select>
                        </div>

                        <div class="lg:col-span-6 min-w-0 relative z-[999994]">
                            <SearchableSelect v-model="state.concepto_id" :options="conceptosActive"
                            label="Concepto" placeholder="Todos" searchPlaceholder="Buscar concepto..."
                            :allowNull="true" nullLabel="Todos" rounded="2xl" zIndexClass="z-[200000]"
                            labelKey="nombre" valueKey="id"/>
                        </div>

                        <div class="lg:col-span-6 min-w-0 relative z-[999992]">
                            <SearchableSelect v-model="state.proveedor_id" :options="proveedoresActive"
                            label="Proveedor" placeholder="Todos" searchPlaceholder="Buscar proveedor..."
                            :allowNull="true" nullLabel="Todos" rounded="2xl" zIndexClass="z-[200000]"
                            labelKey="razon_social" valueKey="id" />
                        </div>
                    </div>
                </details>
            </div>

            <!-- DESKTOP: TABLA (sin ID, con Folio) -->
            <div class="hidden xl:block rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/60 backdrop-blur overflow-hidden">
                <div :class="scrollHide">
                    <table class="w-full min-w-[1050px]">
                        <thead class="bg-slate-50/80 dark:bg-neutral-950/40">
                            <tr class="text-left text-[12px] font-black text-slate-600 dark:text-zinc-300">
                                <th class="px-4 py-3 w-[48px]">
                                    <input type="checkbox" :checked="isAllSelectedOnPage"
                                    @change="toggleAllOnPage(($event.target as HTMLInputElement).checked)"
                                    class="h-4 w-4 rounded border-slate-300 dark:border-white/20"/>
                                </th>
                                <th class="px-4 py-3 w-[190px]">Estatus</th>
                                <th class="px-4 py-3 w-[220px]">Folio</th>
                                <th class="px-4 py-3 w-[170px]">Captura</th>
                                <th class="px-4 py-3 w-[170px]">Entrega</th>
                                <th class="px-4 py-3 w-[140px]">Pago</th>
                                <th class="px-4 py-3 w-[160px] text-right">Monto</th>
                                <th class="px-4 py-3 hidden xl:table-cell w-[260px]">Proveedor</th>
                                <th class="px-4 py-3 hidden 2xl:table-cell w-[260px]">Concepto</th>
                                <th class="px-4 py-3 w-[180px] text-right">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="r in rows" :key="r.id"
                            class="border-t border-slate-200/70 dark:border-white/10 hover:bg-slate-50/70 dark:hover:bg-white/5 transition"
                            :class="rowDisabled(r) ? 'opacity-60' : ''">
                                <td class="px-4 py-3 align-top">
                                    <input type="checkbox" :disabled="rowDisabled(r)"
                                    @change="toggleRow(r.id, ($event.target as HTMLInputElement).checked)"
                                    class="h-4 w-4 rounded border-slate-300 dark:border-white/20"/>
                                </td>

                                <td class="px-4 py-3 align-top">
                                    <div :class="statusClass((r as any).status)">
                                        <span class="h-2.5 w-2.5 rounded-full" :class="dotClass((r as any).status)"></span>
                                        <span class="truncate">{{ pillText((r as any).status) }}</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 align-top">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="font-black text-slate-900 dark:text-zinc-100 truncate" :title="(r as any).folio">
                                            {{ (r as any).folio }}
                                        </div>
                                        <button type="button"
                                        class="inline-flex items-center justify-center h-9 w-9 rounded-2xl
                                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                                        title="Copiar folio" @click="copyText((r as any).folio)">
                                            <Copy class="h-4 w-4 text-slate-700 dark:text-zinc-200" />
                                        </button>
                                    </div>
                                    <div class="text-[11px] font-semibold text-slate-500 dark:text-zinc-400 mt-1">
                                        {{ displayName((r as any).solicitante) }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 align-top text-sm font-semibold text-slate-800 dark:text-zinc-100">
                                    {{ fmtDateLong((r as any).created_at) }}
                                </td>

                                <td class="px-4 py-3 align-top text-sm font-semibold text-slate-800 dark:text-zinc-100">
                                    {{ fmtDateLong((r as any).fecha_solicitud) }}
                                </td>

                                <td class="px-4 py-3 align-top text-sm font-semibold text-slate-800 dark:text-zinc-100">
                                    {{ safeDateAny((r as any).fecha_pago) }}
                                </td>

                                <td class="px-4 py-3 align-top text-right">
                                    <div class="font-black text-slate-900 dark:text-zinc-100">
                                        {{ money((r as any).monto_total) }}
                                    </div>
                                    <div class="text-[11px] font-semibold text-slate-500 dark:text-zinc-400">
                                        Sub: {{ money((r as any).monto_subtotal) }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 align-top hidden xl:table-cell">
                                    <div class="text-sm font-semibold text-slate-800 dark:text-zinc-100 truncate" :title="displayName((r as any).proveedor)">
                                        {{ displayName((r as any).proveedor) }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 align-top hidden 2xl:table-cell">
                                    <div class="text-sm font-semibold text-slate-800 dark:text-zinc-100 truncate" :title="displayName((r as any).concepto)">
                                        {{ displayName((r as any).concepto) }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 align-top">
                                    <div class="flex items-center justify-end gap-2">
                                        <button class="inline-flex items-center justify-center h-9 w-9 rounded-2xl
                                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                                        title="Ver" @click="goShow(r.id)">
                                            <Search class="h-4 w-4 text-slate-700 dark:text-zinc-200" />
                                        </button>

                                        <button class="inline-flex items-center justify-center h-9 w-9 rounded-2xl
                                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                                        disabled:opacity-40 disabled:pointer-events-none
                                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                                        title="Pagar" :disabled="!canPay || rowDisabled(r)" @click="onPay(r.id)">
                                            <Banknote class="h-4 w-4 text-slate-700 dark:text-zinc-200" />
                                        </button>

                                        <button class="inline-flex items-center justify-center h-9 w-9 rounded-2xl
                                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                                        disabled:opacity-40 disabled:pointer-events-none
                                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                                        title="Comprobar" :disabled="!canComprobar || rowDisabled(r)" @click="onComprobar(r.id)">
                                            <FileText class="h-4 w-4 text-slate-700 dark:text-zinc-200" />
                                        </button>

                                        <button class="inline-flex items-center justify-center h-9 w-9 rounded-2xl
                                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                                        disabled:opacity-40 disabled:pointer-events-none
                                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                                        title="Imprimir" :disabled="rowDisabled(r)" @click="onPrint(r.id)">
                                            <Printer class="h-4 w-4 text-slate-700 dark:text-zinc-200" />
                                        </button>

                                        <button v-if="canDelete"
                                        class="inline-flex items-center justify-center h-9 w-9 rounded-2xl
                                        border border-rose-500/25 bg-rose-500/10 hover:bg-rose-500/15 active:scale-[0.99]
                                        disabled:opacity-40 disabled:pointer-events-none"
                                        title="Eliminar" :disabled="rowDisabled(r)" @click="destroyRow(r)">
                                            <Trash2 class="h-4 w-4 text-rose-700" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="rows.length === 0">
                                <td colspan="10" class="px-6 py-10 text-center text-sm font-semibold text-slate-500 dark:text-zinc-400">
                                    No hay requisiciones con los filtros actuales.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-4 sm:px-6 py-4 border-t border-slate-200/70 dark:border-white/10 flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-slate-600 dark:text-zinc-300">
                        {{ pageSummary }}
                    </div>

                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        <button v-for="l in safePagerLinks" :key="l.label + String(l.url)"
                        type="button" @click="goTo(l.url)" :disabled="!l.url"
                        class="inline-flex items-center justify-center h-9 min-w-9 px-3 rounded-2xl text-xs font-black
                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                        disabled:opacity-40 disabled:pointer-events-none
                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                        :class="l.active ? 'bg-slate-900 text-white border-slate-900 dark:bg-white/15 dark:text-zinc-100 dark:border-white/20' : ''">
                            {{ (l as any).uiLabel }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- MOBILE/TABLET: CARDS -->
            <div class="xl:hidden space-y-3">
                <div v-for="r in rows" :key="r.id"
                class="rounded-3xl border border-slate-200/70 dark:border-white/10
                bg-white/85 dark:bg-neutral-900/60 backdrop-blur p-4"
                :class="rowDisabled(r) ? 'opacity-60' : ''">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="font-black text-slate-900 dark:text-zinc-100 truncate">
                                    {{ (r as any).folio }}
                                </div>
                                <button type="button"
                                class="inline-flex items-center justify-center h-9 w-9 rounded-2xl
                                border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                                dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                                title="Copiar folio" @click="copyText((r as any).folio)">
                                    <Copy class="h-4 w-4 text-slate-700 dark:text-zinc-200" />
                                </button>
                            </div>

                            <div class="mt-2">
                                <div :class="statusClass((r as any).status)">
                                    <span class="h-2.5 w-2.5 rounded-full" :class="dotClass((r as any).status)"></span>
                                    {{ pillText((r as any).status) }}
                                </div>
                            </div>

                            <div class="mt-2 text-xs font-semibold text-slate-500 dark:text-zinc-400 truncate">
                                {{ displayName((r as any).solicitante) }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="checkbox" :disabled="rowDisabled(r)"
                            @change="toggleRow(r.id, ($event.target as HTMLInputElement).checked)"
                            class="h-4 w-4 rounded border-slate-300 dark:border-white/20"/>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 p-3">
                            <div class="text-[11px] font-black text-slate-600 dark:text-zinc-300">Captura</div>
                            <div class="text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                {{ fmtDateLong((r as any).created_at) }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 p-3">
                            <div class="text-[11px] font-black text-slate-600 dark:text-zinc-300">Entrega</div>
                            <div class="text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                {{ fmtDateLong((r as any).fecha_solicitud) }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 p-3">
                            <div class="text-[11px] font-black text-slate-600 dark:text-zinc-300">Pago</div>
                            <div class="text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                {{ safeDateAny((r as any).fecha_pago) }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 p-3">
                            <div class="text-[11px] font-black text-slate-600 dark:text-zinc-300">Monto</div>
                            <div class="text-sm font-black text-slate-900 dark:text-zinc-100">
                                {{ money((r as any).monto_total) }}
                            </div>
                        </div>

                        <div class="col-span-2 rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 p-3">
                            <div class="text-[11px] font-black text-slate-600 dark:text-zinc-300">Proveedor</div>
                            <div class="text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                {{ displayName((r as any).proveedor) }}
                            </div>
                        </div>

                        <div class="col-span-2 rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 p-3">
                            <div class="text-[11px] font-black text-slate-600 dark:text-zinc-300">Concepto</div>
                            <div class="text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                {{ displayName((r as any).concepto) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button class="inline-flex items-center justify-center h-10 w-10 rounded-2xl
                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                        title="Ver" @click="goShow(r.id)">
                            <Search class="h-4 w-4 text-slate-700 dark:text-zinc-200" />
                        </button>

                        <button class="inline-flex items-center justify-center h-10 w-10 rounded-2xl
                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                        disabled:opacity-40 disabled:pointer-events-none
                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                        title="Pagar" :disabled="!canPay || rowDisabled(r)" @click="onPay(r.id)">
                            <Banknote class="h-4 w-4 text-slate-700 dark:text-zinc-200" />
                        </button>

                        <button class="inline-flex items-center justify-center h-10 w-10 rounded-2xl
                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                        disabled:opacity-40 disabled:pointer-events-none
                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                        title="Comprobar" :disabled="!canComprobar || rowDisabled(r)"
                        @click="onComprobar(r.id)">
                            <FileText class="h-4 w-4 text-slate-700 dark:text-zinc-200" />
                        </button>

                        <button class="inline-flex items-center justify-center h-10 w-10 rounded-2xl
                        border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                        disabled:opacity-40 disabled:pointer-events-none
                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                        title="Imprimir" :disabled="rowDisabled(r)"
                        @click="onPrint(r.id)">
                            <Printer class="h-4 w-4 text-slate-700 dark:text-zinc-200" />
                        </button>

                        <button v-if="canDelete" class="inline-flex items-center justify-center h-10 w-10 rounded-2xl
                        border border-rose-500/25 bg-rose-500/10 hover:bg-rose-500/15 active:scale-[0.99]
                        disabled:opacity-40 disabled:pointer-events-none"
                        title="Eliminar" :disabled="rowDisabled(r)" @click="destroyRow(r)">
                            <Trash2 class="h-4 w-4 text-rose-700" />
                        </button>
                    </div>
                </div>

                <div v-if="rows.length === 0"
                class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/60 backdrop-blur p-6 text-center">
                    <div class="text-sm font-semibold text-slate-500 dark:text-zinc-400">
                        No hay requisiciones con los filtros actuales.
                    </div>
                </div>

                <div v-if="safePagerLinks.length" class="flex items-center justify-center gap-2 flex-wrap py-2">
                    <button v-for="l in safePagerLinks" :key="l.label + String(l.url)" type="button"
                    @click="goTo(l.url)" :disabled="!l.url"
                    class="inline-flex items-center justify-center h-9 min-w-9 px-3 rounded-2xl text-xs font-black
                    border border-slate-200 bg-white hover:bg-slate-50 active:scale-[0.99]
                    disabled:opacity-40 disabled:pointer-events-none
                    dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                    :class="l.active ? 'bg-slate-900 text-white border-slate-900 dark:bg-white/15 dark:text-zinc-100 dark:border-white/20' : ''">
                        {{ (l as any).uiLabel }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
