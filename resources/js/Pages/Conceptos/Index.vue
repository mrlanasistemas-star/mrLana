<script setup lang="ts">

    import { computed } from 'vue'
    import { Head } from '@inertiajs/vue3'
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

    import Modal from '@/Components/Modal.vue'
    import SecondaryButton from '@/Components/SecondaryButton.vue'

    import type { ConceptosPageProps, ConceptoRow } from './Conceptos.types'
    import { useConceptosIndex } from './useConceptosIndex'
    import { formatDateTime } from '@/Utils/date'

    // Importamos iconos propios
    import ICON_PDF from '@/img/pdf.png'
    import ICON_EXCEL from '@/img/excel.png'

    // Importamos funciones para exportar archivos
    import { toQS, downloadFile } from '@/Utils/exports'

    import { usePermissions } from '@/Composables/usePermissions'
    const props = defineProps<ConceptosPageProps>()
    // Solo ayuda visual: el backend vuelve a autorizar cada acción.
    const { can } = usePermissions()

    const {
        // filtros + paginación
        state,
        safeLinks,
        goTo,
        hasActiveFilters,
        clearFilters,
        sortLabel,
        toggleSort,

        // bulk
        selectedIds,
        selectedCount,
        isAllSelectedOnPage,
        toggleRow,
        toggleAllOnPage,
        clearSelection,
        destroySelected,

        // modal
        modalOpen,
        isEdit,
        saving,
        form,
        errors,
        canSubmit,
        openCreate,
        openEdit,
        closeModal,
        submit,

        // acciones por fila
        destroyRow,
        confirmActivate,
    } = useConceptosIndex(props)

    const exportPdfUrl = computed(() => route('conceptos.export.pdf') + toQS(state))
    const exportExcelUrl = computed(() => route('conceptos.export.excel') + toQS(state))

    /** Dataset plano */
    const rows = computed<ConceptoRow[]>(() => props.conceptos.data ?? [])

    /** Totales (soporta paginadores distintos) */
    const from = computed(() => (props.conceptos as any)?.from ?? (props.conceptos as any)?.meta?.from ?? 0)
    const to = computed(() => (props.conceptos as any)?.to ?? (props.conceptos as any)?.meta?.to ?? 0)
    const total = computed(() => (props.conceptos as any)?.total ?? (props.conceptos as any)?.meta?.total ?? 0)
    const currentPage = computed(() => (props.conceptos as any)?.current_page ?? (props.conceptos as any)?.meta?.current_page ?? 1)
    const lastPage = computed(() => (props.conceptos as any)?.last_page ?? (props.conceptos as any)?.meta?.last_page ?? 1)

    /** Paginación móvil/tablet: sanitiza labels y evita overflow */
    const mobileLinks = computed(() => {
        const links = (safeLinks.value ?? []) as any[]
        return links.filter((l) => l && typeof l.label === 'string')
    })

    function linkLabelShort(label: string) {
        const t = String(label)
            .replace(/&laquo;|&raquo;|&hellip;/g, '')
            .replace(/<[^>]*>/g, '')
            .trim()

        const low = t.toLowerCase()
        if (low.includes('previous') || low.includes('atrás') || low.includes('anterior')) return 'Atrás'
        if (low.includes('next') || low.includes('siguiente')) return 'Siguiente'
        if (/^\d+$/.test(t)) return t
        if (t.length > 6) return t.slice(0, 6)
        return t || '…'
    }

    /** Estatus pill */
    function statusPill(active: boolean) {
        return active
        ? 'bg-emerald-500/10 text-emerald-200 border-emerald-500/20'
        : 'bg-slate-500/10 text-slate-200 border-white/10'
    }

    /** UI tokens */
    const selectBase =
    'mt-1 w-full rounded-xl px-3 py-2 text-sm border transition focus:outline-none focus:ring-2 ' +
    'border-slate-200 bg-white text-slate-900 focus:ring-slate-200 focus:border-slate-300 ' +
    'dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 dark:focus:ring-white/10'

    const inputBase =
    'mt-1 w-full rounded-xl px-3 py-2 text-sm border transition focus:outline-none focus:ring-2 ' +
    'border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 focus:ring-slate-200 focus:border-slate-300 ' +
    'dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 dark:placeholder:text-neutral-500 dark:focus:ring-white/10'

    /** Micro-animaciones */
    const hoverLift =
    'transition will-change-transform duration-200 ease-out hover:-translate-y-[1px] hover:shadow-md active:translate-y-0 active:scale-[0.99]'
</script>

<template>
    <Head title="Conceptos" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-slate-900 dark:text-zinc-100">
                Conceptos
            </h2>
        </template>

        <div class="w-full max-w-full min-w-0 overflow-x-hidden">
            <div class="w-full max-w-full min-w-0 px-3 sm:px-6 lg:px-8 py-4 sm:py-6 overflow-x-hidden">

                <!-- Header -->
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between
                rounded-2xl border border-slate-200/70 dark:border-white/10
                bg-white dark:bg-neutral-900 shadow-sm px-4 py-4
                w-full max-w-full min-w-0">
                    <div class="min-w-0">
                        <h1 class="text-base font-bold text-slate-900 dark:text-neutral-100 truncate">
                            Catálogo de conceptos de requisición
                        </h1>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 min-w-0 w-full sm:w-auto">
                        <!-- Bulk bar compacta -->
                        <div v-if="selectedCount > 0 && can('conceptos.desactivar')"
                        class="flex flex-wrap items-center gap-2
                        rounded-2xl border border-slate-200/70 dark:border-white/10
                        bg-slate-50 dark:bg-neutral-950/40 px-3 py-2
                        min-w-0 max-w-full overflow-hidden">
                            <div class="text-xs text-slate-700 dark:text-neutral-200 truncate">
                                Seleccionados: <span class="font-extrabold">{{ selectedCount }}</span>
                            </div>

                            <button type="button"
                            @click="clearSelection"
                            class="rounded-xl px-3 py-1.5 text-xs font-semibold
                            bg-white text-slate-800 border border-slate-200 hover:bg-slate-50
                            dark:bg-neutral-900 dark:text-neutral-100 dark:border-white/10 dark:hover:bg-neutral-950/40
                            focus:outline-none focus:ring-2 focus:ring-slate-200 dark:focus:ring-white/10
                            transition active:scale-[0.98]">
                                Limpiar
                            </button>

                            <button type="button"
                            @click="destroySelected"
                            class="rounded-xl px-3 py-1.5 text-xs font-semibold
                            bg-white text-rose-700 border border-rose-200 hover:bg-rose-50
                            dark:bg-neutral-900 dark:text-rose-300 dark:border-rose-500/20 dark:hover:bg-rose-500/10
                            focus:outline-none focus:ring-2 focus:ring-rose-200 dark:focus:ring-rose-500/20
                            transition active:scale-[0.98]"
                            title="Baja lógica masiva (activo=false)">
                                Eliminar
                            </button>
                        </div>

                        <button v-if="can('conceptos.registrar')" type="button"
                        @click="openCreate"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold
                        bg-slate-900 text-white hover:bg-slate-800
                        dark:bg-zinc-300 dark:text-neutral-900 dark:hover:bg-zinc-200
                        Focus:outline-none focus:ring-2 focus:ring-slate-300 dark:focus:ring-white/20
                        transition active:scale-[0.98]
                        w-full sm:w-auto">
                            Nuevo
                        </button>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="mb-4 grid grid-cols-1 lg:grid-cols-12 gap-3
                rounded-2xl border border-slate-200/70 dark:border-white/10
                bg-white dark:bg-neutral-900 shadow-sm p-4
                w-full max-w-full min-w-0 overflow-x-hidden">
                    <div class="lg:col-span-4 min-w-0">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Búsqueda</label>
                        <input v-model="state.q" type="text" placeholder="Concepto..." :class="inputBase" />
                    </div>

                    <div class="lg:col-span-2 min-w-0">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Estatus</label>
                        <!-- usa 'all' | '1' | '0' (y default '1' desde el composable) -->
                        <select v-model="state.activo" :class="selectBase">
                        <option value="all">Todos</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                        </select>
                    </div>

                    <div class="lg:col-span-2 min-w-0">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Por página</label>
                        <select v-model="state.perPage" :class="selectBase">
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>

                    <div class="lg:col-span-4 min-w-0 flex flex-wrap items-end gap-x-6 gap-y-2 ml-2">
                        <!-- PDF -->
                        <button v-if="can('conceptos.exportar')" type="button" @click="downloadFile(exportPdfUrl)" class="group flex flex-col items-center gap-1 py-2 ...">
                            <img :src="ICON_PDF" alt="PDF" class="h-6 w-6 transition-transform group-hover:scale-125"/>
                            <span class="relative text-[11px] leading-none ...">Descargar</span>
                        </button>

                        <!-- EXCEL -->
                        <button v-if="can('conceptos.exportar')" type="button" @click="downloadFile(exportExcelUrl)" class="group flex flex-col items-center gap-1 py-2 ...">
                            <img :src="ICON_EXCEL" alt="EXCEL" class="h-6 w-6 transition-transform group-hover:scale-125"/>
                            <span class="relative text-[11px] leading-none ...">Descargar</span>
                        </button>

                        <button type="button"
                            @click="toggleSort"
                            class="rounded-xl px-3 py-2 text-xs font-extrabold border
                            border-slate-200 bg-white text-slate-700 hover:bg-slate-50
                            dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 dark:hover:bg-white/5
                            transition active:scale-[0.98]"
                            :title="`Ordenar ${sortLabel}`">
                                Orden: {{ sortLabel }}
                        </button>

                        <SecondaryButton v-if="hasActiveFilters"
                            type="button"
                            @click="clearFilters"
                            :disabled="!hasActiveFilters"
                            class="rounded-xl disabled:opacity-50 shrink-0"
                            title="Restablece filtros (default: Activos)">
                            Limpiar
                        </SecondaryButton>
                    </div>

                </div>

                <!-- TABLA DESKTOP -->
                <div class="hidden xl:block overflow-hidden rounded-2xl border border-slate-200/70 dark:border-white/10
                bg-white dark:bg-neutral-900 shadow-sm w-full max-w-full min-w-0">
                    <div class="w-full max-w-full min-w-0 overflow-x-auto">
                        <table class="w-full min-w-[980px] text-sm">
                        <thead class="bg-slate-50 dark:bg-neutral-950/60">
                            <tr class="text-left text-slate-600 dark:text-neutral-300">
                                <th class="px-4 py-3 font-semibold w-[46px]">
                                    <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 dark:border-white/10 bg-white dark:bg-neutral-900"
                                    :checked="isAllSelectedOnPage"
                                    @change="toggleAllOnPage(($event.target as HTMLInputElement).checked)"
                                    />
                                </th>

                                <th class="px-4 py-3 font-semibold">Concepto</th>
                                <th class="px-4 py-3 font-semibold">Estatus</th>
                                <th class="px-4 py-3 font-semibold">Fecha de registro</th>
                                <th class="px-4 py-3 font-semibold">Fecha de actualización</th>
                                <th class="px-4 py-3 font-semibold text-right">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="border-t border-slate-200/70 dark:border-white/10
                                    hover:bg-slate-50/70 dark:hover:bg-neutral-950/40 transition"
                            >
                            <td class="px-4 py-3 align-middle">
                                <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 dark:border-white/10 bg-white dark:bg-neutral-900"
                                :checked="selectedIds.has(row.id)"
                                @change="toggleRow(row.id, ($event.target as HTMLInputElement).checked)"
                                />
                            </td>

                            <td class="px-4 py-3 min-w-0">
                                <div class="font-semibold text-slate-900 dark:text-neutral-100 truncate max-w-[520px]">
                                {{ row.nombre }}
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <span
                                class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold border
                                        bg-slate-100 text-slate-700 border-slate-200
                                        dark:bg-white/5 dark:text-neutral-200 dark:border-white/10"
                                >
                                <span class="h-1.5 w-1.5 rounded-full" :class="row.activo ? 'bg-emerald-500/80' : 'bg-slate-400/80'" />
                                {{ row.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>

                            <td class="px-4 py-3 min-w-0">
                                <div class="font-semibold text-slate-900 dark:text-neutral-100 truncate max-w-[520px]">
                                {{ formatDateTime(row.created_at) }}
                                </div>
                            </td>

                            <td class="px-4 py-3 min-w-0">
                                <div class="font-semibold text-slate-900 dark:text-neutral-100 truncate max-w-[520px]">
                                {{ formatDateTime(row.updated_at) }}
                                </div>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <div class="inline-flex gap-2">
                                <button
                                    v-if="can('conceptos.editar')" type="button"
                                    @click="openEdit(row)"
                                    class="rounded-xl px-3 py-2 text-xs font-extrabold
                                        border border-slate-200 bg-white text-slate-700 hover:bg-slate-50
                                        dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 dark:hover:bg-white/5
                                        transition active:scale-[0.98]"
                                >
                                    Editar
                                </button>

                                <!-- Activo => baja lógica -->
                                <button
                                    v-if="row.activo && can('conceptos.desactivar')"
                                    type="button"
                                    @click="destroyRow(row)"
                                    class="rounded-xl px-3 py-2 text-xs font-extrabold
                                        bg-white text-rose-700 border border-rose-200 hover:bg-rose-50
                                        dark:bg-neutral-900 dark:text-rose-300 dark:border-rose-500/20 dark:hover:bg-rose-500/10
                                        transition active:scale-[0.98]"
                                >
                                    Eliminar
                                </button>

                                <!-- Inactivo => activar -->
                                <button
                                    v-else-if="!row.activo && can('conceptos.reactivar')"
                                    type="button"
                                    @click="confirmActivate(row)"
                                    class="rounded-xl px-3 py-2 text-xs font-extrabold
                                        bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100
                                        dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20 dark:hover:bg-emerald-500/15
                                        transition active:scale-[0.98]"
                                >
                                    Activar
                                </button>
                                </div>
                            </td>
                            </tr>

                            <tr v-if="rows.length === 0">
                            <td colspan="4" class="px-4 py-12 text-center text-slate-500 dark:text-neutral-400">
                                No hay conceptos con los filtros actuales.
                            </td>
                            </tr>
                        </tbody>
                        </table>
                    </div>

                    <!-- Paginación desktop -->
                    <div
                        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3
                            border-t border-slate-200/70 dark:border-white/10
                            px-4 py-3 bg-white dark:bg-neutral-900
                            w-full max-w-full min-w-0 overflow-x-hidden"
                    >
                        <div class="text-xs text-slate-600 dark:text-neutral-300">
                        Página <span class="font-semibold">{{ currentPage }}</span> de
                        <span class="font-semibold">{{ lastPage }}</span>
                        </div>

                        <nav class="flex flex-wrap gap-2 max-w-full">
                        <button
                            v-for="(link, i) in safeLinks"
                            :key="i"
                            type="button"
                            @click="goTo(link.url)"
                            :disabled="!link.url"
                            class="rounded-xl px-3 py-1.5 text-sm font-semibold border transition
                                border-slate-200 bg-white text-slate-800 hover:bg-slate-50
                                disabled:opacity-50 disabled:cursor-not-allowed
                                dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 dark:hover:bg-white/5"
                            :class="link.active ? 'ring-2 ring-slate-300 dark:ring-white/10' : ''"
                        >
                            {{ link.label }}
                        </button>
                        </nav>
                    </div>
                </div>

                <!-- CARDS MÓVIL/TABLET -->
                <div class="xl:hidden grid gap-3 w-full max-w-full min-w-0 overflow-x-hidden">
                <div
                    v-for="row in rows"
                    :key="row.id"
                    class="w-full max-w-full min-w-0 overflow-hidden
                        rounded-2xl border border-slate-200/70 dark:border-white/10
                        bg-white dark:bg-neutral-900 shadow-sm p-4"
                    :class="hoverLift"
                >
                    <div class="flex items-start justify-between gap-3 min-w-0">
                    <div class="flex items-start gap-3 min-w-0">
                        <input
                        type="checkbox"
                        class="mt-1 h-4 w-4 rounded border-slate-300 dark:border-white/10 bg-white dark:bg-neutral-900 shrink-0"
                        :checked="selectedIds.has(row.id)"
                        @change="toggleRow(row.id, ($event.target as HTMLInputElement).checked)"
                        />

                        <div class="min-w-0">
                            <div class="font-semibold text-slate-900 dark:text-neutral-100 truncate">
                                {{ row.nombre }}
                            </div>
                            <div class="text-xs text-slate-900 dark:text-neutral-100 truncate">
                                Fecha de registro: {{ formatDateTime(row.created_at) }}
                            </div>
                            <div class="text-xs text-slate-900 dark:text-neutral-100 truncate">
                                Fecha de edición: {{ formatDateTime(row.updated_at) }}
                            </div>
                        </div>
                    </div>

                    <span class="shrink-0 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold border
                        bg-slate-100 text-slate-700 border-slate-200
                        dark:bg-white/5 dark:text-neutral-200 dark:border-white/10">
                        <span class="h-1.5 w-1.5 rounded-full"
                        :class="row.activo ? 'bg-emerald-500/80' : 'bg-slate-400/80'"/>
                            {{ row.activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 min-w-0">
                    <button
                        v-if="can('conceptos.editar')" type="button"
                        @click="openEdit(row)"
                        class="rounded-xl px-3 py-2 text-xs font-extrabold
                            border border-slate-200 bg-white text-slate-700 hover:bg-slate-50
                            dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 dark:hover:bg-white/5
                            transition active:scale-[0.98]"
                    >
                        Editar
                    </button>

                    <!-- Activo => baja lógica / Inactivo => activar -->
                    <button
                        v-if="row.activo && can('conceptos.desactivar')"
                        type="button"
                        @click="destroyRow(row)"
                        class="rounded-xl px-3 py-2 text-xs font-extrabold
                            bg-white text-rose-700 border border-rose-200 hover:bg-rose-50
                            dark:bg-neutral-900 dark:text-rose-300 dark:border-rose-500/20 dark:hover:bg-rose-500/10
                            transition active:scale-[0.98]"
                    >
                        Eliminar
                    </button>

                    <button
                        v-else-if="!row.activo && can('conceptos.reactivar')"
                        type="button"
                        @click="confirmActivate(row)"
                        class="rounded-xl px-3 py-2 text-xs font-extrabold
                            bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100
                            dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20 dark:hover:bg-emerald-500/15
                            transition active:scale-[0.98]"
                    >
                        Activar
                    </button>
                    </div>
                </div>

                <div
                    v-if="rows.length === 0"
                    class="rounded-2xl border border-slate-200/70 dark:border-white/10
                        bg-white dark:bg-neutral-900 shadow-sm p-6 text-center
                        text-slate-500 dark:text-neutral-400"
                >
                    No hay conceptos con los filtros actuales.
                </div>

                <!-- paginación mobile -->
                <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-white dark:bg-neutral-900 shadow-sm p-4 overflow-hidden">
                    <div class="flex items-center justify-between mb-3 gap-2 min-w-0">
                    <div class="text-xs text-slate-600 dark:text-neutral-300 min-w-0 truncate">
                        <span class="font-semibold">{{ from }}</span> -
                        <span class="font-semibold">{{ to }}</span> /
                        <span class="font-semibold">{{ total }}</span>
                    </div>

                    <button
                        type="button"
                        class="text-xs font-semibold text-slate-700 hover:text-slate-900
                            dark:text-neutral-300 dark:hover:text-white shrink-0"
                        @click="toggleAllOnPage(!isAllSelectedOnPage)"
                    >
                        {{ isAllSelectedOnPage ? 'Quitar' : 'Seleccionar' }}
                    </button>
                    </div>

                    <div class="flex flex-wrap gap-2 max-w-full">
                    <button
                        v-for="(l, idx) in mobileLinks"
                        :key="idx"
                        type="button"
                        @click="goTo(l.url)"
                        :disabled="!l.url"
                        class="px-3 py-2 text-xs font-semibold rounded-xl border
                            disabled:opacity-50 disabled:cursor-not-allowed transition active:scale-[0.98]"
                        :class="
                        l.active
                            ? 'bg-slate-900 text-white border-slate-900 dark:bg-zinc-300 dark:text-neutral-900 dark:border-neutral-100'
                            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50 dark:bg-neutral-900 dark:text-neutral-200 dark:border-white/10 dark:hover:bg-neutral-950/40'
                        "
                    >
                        {{ linkLabelShort(l.label) }}
                    </button>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <!-- Modal Crear/Editar -->
        <Modal :show="modalOpen" maxWidth="2xl" @close="closeModal">
            <div class="rounded-3xl border border-slate-200/60 dark:border-white/10 bg-white dark:bg-neutral-900 shadow-2xl">
                <div class="p-6 sm:p-7">
                    <div class="flex items-start justify-between gap-4 min-w-0">
                        <div class="min-w-0">
                            <h3 class="text-xl font-extrabold text-slate-900 dark:text-neutral-100">
                                {{ isEdit ? 'Editar concepto' : 'Nuevo concepto' }}
                            </h3>
                        </div>

                        <button type="button"
                        class="rounded-full px-4 py-2 text-sm font-semibold
                        border border-slate-200 bg-white hover:bg-rose-500 hover:text-white
                        dark:border-white/10 dark:bg-white/10 dark:hover:bg-rose-500 dark:text-neutral-100
                        focus:outline-none focus:ring-2 focus:ring-slate-200 dark:focus:ring-white/10
                        transition active:scale-[0.98] shrink-0"
                        @click="closeModal" aria-label="Cerrar"
                        title="Cerrar">X
                        </button>
                    </div>

                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 min-w-0">
                        <div class="sm:col-span-2 min-w-0">
                            <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">
                                Nombre *
                            </label>
                            <input v-model="form.nombre" type="text" placeholder="Ej. Gasolina" :class="inputBase" />
                            <p v-if="errors.nombre" class="mt-1 text-xs text-rose-500 break-words">
                                {{ errors.nombre }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-7 flex flex-col sm:flex-row gap-3 sm:justify-end min-w-0">
                        <SecondaryButton class="rounded-2xl" @click="closeModal">Cancelar</SecondaryButton>

                        <button type="button" @click="submit"
                        :disabled="!canSubmit"
                        class="rounded-2xl px-6 py-3 text-sm font-extrabold tracking-wide
                        bg-slate-900 text-white hover:bg-slate-800
                        dark:bg-zinc-300 dark:text-neutral-900 dark:hover:bg-zinc-200
                        disabled:opacity-50 disabled:cursor-not-allowed
                        focus:outline-none focus:ring-2 focus:ring-slate-300 dark:focus:ring-white/20
                        transition active:scale-[0.98]">
                        {{ saving ? 'Guardando...' : (isEdit ? 'Actualizar' : 'Crear') }}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
    :global(html.dark select option) {
    background: #0a0a0a;
    color: #f5f5f5;
    }
</style>
