<script setup lang="ts">
    /**
     * ============================================================================
     * Areas/Index.vue
     * ----------------------------------------------------------------------------
     * Vista dark/light
     * - Filtros (q, corporativo, estatus, perPage) + sort A-Z/Z-A
     * - Grouped por corporativo (sólo página actual)
     * - Acciones: Editar / Eliminar / Activar (si inactiva)
     * - Regla encadenada: corporativo en baja => NO activar / NO dar de alta en modal
     * ============================================================================
     */

    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import { Head } from '@inertiajs/vue3'
    import { computed } from 'vue'
    import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
    import Modal from '@/Components/Modal.vue'
    import SecondaryButton from '@/Components/SecondaryButton.vue'

    import { Plus, Pencil, Trash2, Search, X, PackageOpen } from 'lucide-vue-next'

    import type { AreasPageProps, AreaRow } from './Areas.types'
    import { useAreasIndex } from './useAreasIndex'

    import ICON_PDF from '@/img/pdf.png'
    import ICON_EXCEL from '@/img/excel.png'
    import { toQS, downloadFile } from '@/Utils/exports'

    const props = defineProps<AreasPageProps>()

    const {
        state,
        safeLinks,
        goTo,
        hasActiveFilters,
        clearFilters,
        sortLabel,
        toggleSort,

        corporativosActive,
        corporativosAll,

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

        destroyRow,
        confirmActivate,

        selectedIds,
        selectedCount,
        isAllSelectedOnPage,
        toggleRow,
        toggleAllOnPage,
        clearSelection,
        destroySelected,
    } = useAreasIndex(props)

    const exportPdfUrl = computed(() => route('areas.export.pdf') + toQS(state))
    const exportExcelUrl = computed(() => route('areas.export.excel') + toQS(state))

    const grouped = computed(() => {
        const map = new Map<string, { key: string; label: string; corporativoId: number | null; rows: AreaRow[] }>()
        for (const r of props.areas.data ?? []) {
            const corpName = r.corporativo?.nombre ?? 'Sin corporativo'
            const corpId = (r.corporativo_id ?? null) as number | null
            const key = `${corpId ?? 'null'}__${corpName}`

            if (!map.has(key)) map.set(key, { key, label: corpName, corporativoId: corpId, rows: [] })
            map.get(key)!.rows.push(r)
        }

        return Array.from(map.values()).sort((a, b) => a.label.localeCompare(b.label, 'es'))
    })

    function corpLabel(row: AreaRow) {
        const c = row.corporativo
        if (!c) return '—'
        return c.codigo ? `${c.nombre} (${c.codigo})` : c.nombre
    }

    function corpIsActive(row: AreaRow) {
        return row?.corporativo?.activo !== false
    }

    const from = computed(() => (props.areas as any)?.from ?? (props.areas as any)?.meta?.from ?? 0)
    const to = computed(() => (props.areas as any)?.to ?? (props.areas as any)?.meta?.to ?? 0)
    const total = computed(() => (props.areas as any)?.total ?? (props.areas as any)?.meta?.total ?? 0)
</script>

<template>
    <Head title="Áreas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4 w-full min-w-0">
                <div>
                    <h2 class="text-xl font-black text-slate-900 dark:text-zinc-100">Áreas</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">Administra áreas por corporativo</p>
                </div>
                <button type="button" @click="openCreate" class="erp-button erp-button-primary h-11 px-5">
                    <Plus class="h-4 w-4" /> Nueva área
                </button>
            </div>
        </template>

        <div class="erp-page space-y-4">

            <!-- Bulk actions -->
            <div v-if="selectedCount > 0" class="erp-panel p-3 flex flex-wrap items-center gap-3">
                <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">
                    {{ selectedCount }} seleccionada(s)
                </span>
                <button type="button" @click="clearSelection" class="erp-button erp-button-secondary h-9 px-3 text-xs">
                    Limpiar
                </button>
                <button type="button" @click="destroySelected" class="erp-button erp-button-danger h-9 px-3 text-xs">
                    <Trash2 class="h-3.5 w-3.5" /> Eliminar seleccionadas
                </button>
            </div>

            <!-- Toolbar / Filtros -->
            <div class="erp-panel p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="relative flex-1 min-w-[180px]">
                        <Search class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input v-model="state.q" class="erp-input pl-10 h-10"
                            placeholder="Nombre del área..." />
                    </div>

                    <div class="min-w-[200px] flex-1">
                        <SearchableSelect v-model="state.corporativo_id"
                            :options="corporativosActive" label-key="nombre" value-key="id"
                            :nullable="true" null-label="Todos" placeholder="Todos"
                            class="w-full" />
                    </div>

                    <select v-model="state.activo" class="erp-select h-10 w-auto min-w-[130px]">
                        <option value="all">Todos</option>
                        <option value="1">Activas</option>
                        <option value="0">Inactivas</option>
                    </select>

                    <select v-model="state.perPage" class="erp-select h-10 w-auto min-w-[100px]">
                        <option :value="10">10 / pág</option>
                        <option :value="15">15 / pág</option>
                        <option :value="25">25 / pág</option>
                        <option :value="50">50 / pág</option>
                        <option :value="100">100 / pág</option>
                    </select>

                    <button type="button" @click="toggleSort"
                        class="erp-button erp-button-secondary h-10 px-3 text-xs whitespace-nowrap">
                        Orden: {{ sortLabel }}
                    </button>

                    <button type="button" @click="downloadFile(exportPdfUrl)"
                        class="erp-icon-button" title="Exportar PDF">
                        <img :src="ICON_PDF" alt="PDF" class="h-5 w-5" />
                    </button>

                    <button type="button" @click="downloadFile(exportExcelUrl)"
                        class="erp-icon-button" title="Exportar Excel">
                        <img :src="ICON_EXCEL" alt="Excel" class="h-5 w-5" />
                    </button>

                    <button v-if="hasActiveFilters" @click="clearFilters"
                        class="erp-button erp-button-secondary h-10 px-3 text-xs gap-1.5">
                        <X class="h-3.5 w-3.5" /> Limpiar
                    </button>
                </div>
            </div>

            <!-- Mobile cards (xl:hidden) -->
            <div class="xl:hidden space-y-3">
                <template v-for="g in grouped" :key="g.key">
                    <!-- Encabezado de grupo -->
                    <div class="erp-panel px-4 py-3 flex items-center justify-between">
                        <div class="font-black text-slate-900 dark:text-zinc-100">{{ g.label }}</div>
                        <div class="text-xs text-slate-500 dark:text-zinc-500">{{ g.rows.length }} área(s)</div>
                    </div>

                    <div v-for="row in g.rows" :key="row.id" class="erp-card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0">
                                <input type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-slate-300 dark:border-white/10 bg-white dark:bg-neutral-900 shrink-0"
                                    :checked="selectedIds.has(row.id)"
                                    @change="toggleRow(row.id, ($event.target as HTMLInputElement).checked)" />
                                <div class="min-w-0">
                                    <div class="font-semibold text-slate-900 dark:text-zinc-100 truncate">{{ row.nombre }}</div>
                                    <div class="text-xs text-slate-500 dark:text-zinc-500 truncate">{{ corpLabel(row) }}</div>
                                </div>
                            </div>
                            <span v-if="row.activo"
                                class="erp-badge bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/20 shrink-0">
                                Activo
                            </span>
                            <span v-else
                                class="erp-badge bg-slate-50 text-slate-600 border border-slate-200 dark:bg-white/8 dark:text-zinc-400 shrink-0">
                                Inactivo
                            </span>
                        </div>

                        <div class="flex gap-2 mt-3 pt-3 border-t border-slate-100 dark:border-white/8">
                            <button type="button" @click="openEdit(row)"
                                class="erp-button erp-button-secondary h-9 text-xs flex-1">
                                <Pencil class="h-3.5 w-3.5" /> Editar
                            </button>
                            <button v-if="row.activo" type="button" @click="destroyRow(row)"
                                class="erp-button erp-button-danger h-9 text-xs">
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                            <button v-else-if="!corpIsActive(row)" type="button" disabled
                                class="erp-button erp-button-secondary h-9 text-xs opacity-50 cursor-not-allowed">
                                Corp. en baja
                            </button>
                            <button v-else type="button" @click="confirmActivate(row)"
                                class="erp-button h-9 text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20">
                                Activar
                            </button>
                        </div>
                    </div>
                </template>

                <div v-if="(props.areas.data ?? []).length === 0" class="erp-panel erp-empty-state">
                    <div class="erp-empty-state-icon"><PackageOpen class="h-6 w-6" /></div>
                    <p class="erp-empty-state-title">Sin registros</p>
                    <p class="erp-empty-state-desc">No se encontraron áreas con los filtros actuales.</p>
                </div>
            </div>

            <!-- Desktop tabla (hidden xl:block) -->
            <div class="hidden xl:block erp-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="erp-table min-w-[700px]">
                        <thead>
                            <tr>
                                <th class="w-[46px]">
                                    <input type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 dark:border-white/10 bg-white dark:bg-neutral-900"
                                        :checked="isAllSelectedOnPage"
                                        @change="toggleAllOnPage(($event.target as HTMLInputElement).checked)" />
                                </th>
                                <th>Área</th>
                                <th>Corporativo</th>
                                <th>Estatus</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <template v-for="g in grouped" :key="g.key">
                                <!-- Fila de encabezado de grupo -->
                                <tr>
                                    <td colspan="5" class="bg-slate-50 dark:bg-white/3 px-4 py-2">
                                        <div class="flex items-center justify-between">
                                            <div class="text-sm font-black text-slate-900 dark:text-zinc-100">
                                                {{ g.label }}
                                            </div>
                                            <div class="text-xs text-slate-500 dark:text-zinc-500">
                                                {{ g.rows.length }} área(s)
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-for="row in g.rows" :key="row.id" class="group">
                                    <td class="align-middle">
                                        <input type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300 dark:border-white/10 bg-white dark:bg-neutral-900"
                                            :checked="selectedIds.has(row.id)"
                                            @change="toggleRow(row.id, ($event.target as HTMLInputElement).checked)" />
                                    </td>

                                    <td>
                                        <div class="font-semibold text-slate-900 dark:text-zinc-100 truncate">
                                            {{ row.nombre }}
                                        </div>
                                    </td>

                                    <td>{{ corpLabel(row) }}</td>

                                    <td>
                                        <span v-if="row.activo"
                                            class="erp-badge bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/20">
                                            Activo
                                        </span>
                                        <span v-else
                                            class="erp-badge bg-slate-50 text-slate-600 border border-slate-200 dark:bg-white/8 dark:text-zinc-400">
                                            Inactivo
                                        </span>
                                    </td>

                                    <td>
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" @click="openEdit(row)"
                                                class="erp-icon-button" title="Editar">
                                                <Pencil class="h-4 w-4" />
                                            </button>
                                            <button v-if="row.activo" type="button" @click="destroyRow(row)"
                                                class="erp-icon-button text-rose-500 dark:text-rose-400 hover:text-rose-700 hover:bg-rose-50 hover:border-rose-200 dark:hover:text-rose-300 dark:hover:bg-rose-500/15 dark:hover:border-rose-500/30"
                                                title="Eliminar">
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                            <button v-else-if="!corpIsActive(row)" type="button" disabled
                                                class="erp-icon-button opacity-50 cursor-not-allowed"
                                                title="No se puede activar (corporativo en baja)">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            </button>
                                            <button v-else type="button" @click="confirmActivate(row)"
                                                class="erp-icon-button text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 hover:border-emerald-200 dark:hover:text-emerald-300 dark:hover:bg-emerald-500/15 dark:hover:border-emerald-500/30"
                                                title="Activar">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div v-if="(props.areas.data ?? []).length === 0" class="erp-empty-state">
                    <div class="erp-empty-state-icon"><PackageOpen class="h-6 w-6" /></div>
                    <p class="erp-empty-state-title">Sin registros</p>
                    <p class="erp-empty-state-desc">No se encontraron áreas con los filtros actuales.</p>
                </div>

                <div v-if="(props.areas.data ?? []).length > 0"
                    class="px-4 py-3 border-t border-slate-100 dark:border-white/8 text-xs text-slate-500 dark:text-zinc-500">
                    Mostrando {{ from }} – {{ to }} de {{ total }} registros
                </div>
            </div>

            <!-- Paginación -->
            <div class="erp-pager">
                <button v-for="(link, i) in safeLinks" :key="i"
                    :disabled="!link.url"
                    @click="goTo(link.url)"
                    class="erp-pager-btn"
                    :class="link.active ? 'active' : ''">
                    {{ link.label }}
                </button>
            </div>

        </div>

        <!-- Modal Create/Edit -->
        <Modal :show="modalOpen" maxWidth="3xl" @close="closeModal">
            <div class="p-5 sm:p-7">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <h3 class="text-xl font-black text-slate-900 dark:text-zinc-100">
                        {{ isEdit ? 'Editar área' : 'Nueva área' }}
                    </h3>
                    <button type="button" @click="closeModal"
                        class="erp-button erp-button-secondary h-9 px-3 text-xs">
                        Cerrar
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <SearchableSelect v-model="form.corporativo_id"
                            :options="corporativosActive" label="Corporativo"
                            label-key="nombre" value-key="id"
                            :nullable="true" null-label="Sin corporativo"
                            placeholder="Busca y selecciona el corporativo..."
                            class="w-full" />
                        <p v-if="errors.corporativo_id" class="erp-error">{{ errors.corporativo_id }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="erp-label">Nombre *</label>
                        <input v-model="form.nombre" type="text" placeholder="Ej. Recursos Humanos"
                            class="erp-input" />
                        <p v-if="errors.nombre" class="erp-error">{{ errors.nombre }}</p>
                    </div>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row gap-3 sm:justify-end">
                    <SecondaryButton class="rounded-2xl" @click="closeModal">Cancelar</SecondaryButton>
                    <button type="button" @click="submit" :disabled="!canSubmit"
                        class="erp-button erp-button-primary h-10 px-6">
                        {{ saving ? 'Guardando...' : (isEdit ? 'Actualizar' : 'Crear') }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
