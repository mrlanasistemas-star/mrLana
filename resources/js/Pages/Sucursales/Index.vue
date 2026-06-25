<script setup lang="ts">
    import { computed } from 'vue'
    import { Head } from '@inertiajs/vue3'


    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import Modal from '@/Components/Modal.vue'
    import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
    import AppInput from '@/Components/ui/AppInput.vue'
    import AppSelect from '@/Components/ui/AppSelect.vue'
    import AppCheckbox from '@/Components/Checkbox.vue'

    import { Plus, Pencil, Trash2, Search, X, PackageOpen } from 'lucide-vue-next'

    import type { SucursalesPageProps } from './Sucursales.types'
    import { useSucursalesIndex } from './useSucursalesIndex'
    import { formatDateTime } from '@/Utils/date'

    import ICON_PDF from '@/img/pdf.png'
    import ICON_EXCEL from '@/img/excel.png'
    import { toQS, downloadFile } from '@/Utils/exports'

    const props = defineProps<SucursalesPageProps>()

    const {
        state,
        corporativosForSelect,
        hasActiveFilters,
        clearFilters,
        sortLabel,
        toggleSort,

        selectedIdsArray,
        selectedCount,
        isAllSelectedOnPage,
        toggleAllOnPage,
        clearSelection,

        paginationLinks,
        mobileLinks,
        linkLabelShort,
        goTo,

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

        confirmDelete,
        confirmBulkDelete,
        confirmActivate,
    } = useSucursalesIndex(props)

    const exportPdfUrl = computed(() => route('sucursales.export.pdf') + toQS(state))
    const exportExcelUrl = computed(() => route('sucursales.export.excel') + toQS(state))

    const pageRows = computed(() => props.sucursales?.data ?? [])

    const from = computed(() => (props.sucursales as any)?.from ?? (props.sucursales as any)?.meta?.from ?? 0)
    const to = computed(() => (props.sucursales as any)?.to ?? (props.sucursales as any)?.meta?.to ?? 0)
    const total = computed(() => (props.sucursales as any)?.total ?? (props.sucursales as any)?.meta?.total ?? 0)

    function corpName(row: any) {
        return row?.corporativo_nombre ?? row?.corporativo?.nombre ?? '—'
    }
</script>

<template>
    <Head title="Sucursales" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4 w-full min-w-0">
                <div>
                    <h2 class="text-xl font-black text-slate-900 dark:text-zinc-100">Sucursales</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">Administra tus sucursales por corporativo</p>
                </div>
                <button type="button" @click="openCreate" class="erp-button erp-button-primary h-11 px-5">
                    <Plus class="h-4 w-4" /> Nueva
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
                <button type="button" @click="confirmBulkDelete" class="erp-button erp-button-danger h-9 px-3 text-xs">
                    <Trash2 class="h-3.5 w-3.5" /> Eliminar seleccionadas
                </button>
            </div>

            <!-- Toolbar / Filtros -->
            <div class="erp-panel p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="relative flex-1 min-w-[180px]">
                        <Search class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input v-model="state.q" class="erp-input pl-10 h-10"
                            placeholder="Buscar por nombre, alias, ciudad, estado..." />
                    </div>

                    <div class="min-w-[200px] flex-1">
                        <SearchableSelect v-model="state.corporativo_id"
                            :options="corporativosForSelect" label-key="nombre" value-key="id"
                            :nullable="true" null-label="Todos" placeholder="Todos"
                            class="w-full" />
                    </div>

                    <select v-model="state.activo" class="erp-select h-10 w-auto min-w-[130px]">
                        <option value="all">Todos</option>
                        <option value="1">Activos</option>
                        <option value="0">Eliminados</option>
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
                <div v-for="row in pageRows" :key="row.id" class="erp-card p-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="pt-0.5 shrink-0">
                            <AppCheckbox v-model:checked="selectedIdsArray"
                                :value="row.id"
                                :label="`Seleccionar sucursal ${row.nombre}`" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2 min-w-0">
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900 dark:text-zinc-100 truncate">{{ row.nombre }}</div>
                                    <div class="text-xs text-slate-500 dark:text-zinc-500 truncate">{{ corpName(row) }}</div>
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

                            <div class="mt-2 space-y-1 text-xs text-slate-600 dark:text-zinc-400">
                                <div><span class="font-semibold">Alias:</span> {{ row.codigo ?? '—' }}</div>
                                <div><span class="font-semibold">Ciudad:</span> {{ row.ciudad ?? '—' }}</div>
                                <div><span class="font-semibold">Estado:</span> {{ row.estado ?? '—' }}</div>
                                <div><span class="font-semibold">Dirección:</span> {{ row.direccion ?? '—' }}</div>
                                <div><span class="font-semibold">Registro:</span> {{ formatDateTime(row.created_at) }}</div>
                                <div><span class="font-semibold">Actualización:</span> {{ formatDateTime(row.updated_at) }}</div>
                            </div>

                            <div class="flex gap-2 mt-3 pt-3 border-t border-slate-100 dark:border-white/8">
                                <button type="button" @click="openEdit(row)"
                                    class="erp-button erp-button-secondary h-9 text-xs flex-1">
                                    <Pencil class="h-3.5 w-3.5" /> Editar
                                </button>
                                <button v-if="row.activo" type="button" @click="confirmDelete(row)"
                                    class="erp-button erp-button-danger h-9 text-xs">
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                                <button v-else-if="row.corporativo_activo === false" type="button" disabled
                                    class="erp-button erp-button-secondary h-9 text-xs opacity-50 cursor-not-allowed">
                                    Corp. en baja
                                </button>
                                <button v-else type="button" @click="confirmActivate(row)"
                                    class="erp-button h-9 text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20">
                                    Activar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!pageRows.length" class="erp-panel erp-empty-state">
                    <div class="erp-empty-state-icon"><PackageOpen class="h-6 w-6" /></div>
                    <p class="erp-empty-state-title">Sin registros</p>
                    <p class="erp-empty-state-desc">No se encontraron sucursales con los filtros actuales.</p>
                </div>
            </div>

            <!-- Desktop tabla (hidden xl:block) -->
            <div class="hidden xl:block erp-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="erp-table min-w-[980px]">
                        <thead>
                            <tr>
                                <th class="w-[46px]">
                                    <AppCheckbox :checked="isAllSelectedOnPage"
                                        @update:checked="(v) => toggleAllOnPage(!!v)"
                                        :label="'Seleccionar todas en la página'" />
                                </th>
                                <th>Sucursal</th>
                                <th>Alias</th>
                                <th>Corporativo</th>
                                <th>Ciudad / Estado</th>
                                <th>Dirección</th>
                                <th>Estatus</th>
                                <th>Registro</th>
                                <th>Actualización</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="row in pageRows" :key="row.id" class="group">
                                <td class="align-middle">
                                    <AppCheckbox v-model:checked="selectedIdsArray"
                                        :value="row.id"
                                        :label="`Seleccionar sucursal ${row.nombre}`" />
                                </td>

                                <td>
                                    <div class="font-semibold text-slate-900 dark:text-zinc-100">{{ row.nombre }}</div>
                                </td>

                                <td>{{ row.codigo ?? '—' }}</td>
                                <td>{{ corpName(row) }}</td>

                                <td>
                                    <div>{{ row.ciudad ?? '—' }}</div>
                                    <div class="text-xs text-slate-500 dark:text-zinc-500">{{ row.estado ?? '—' }}</div>
                                </td>

                                <td class="max-w-[180px] truncate">{{ row.direccion ?? '—' }}</td>

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

                                <td class="text-xs text-slate-600 dark:text-zinc-400 whitespace-nowrap">
                                    {{ formatDateTime(row.created_at) }}
                                </td>
                                <td class="text-xs text-slate-600 dark:text-zinc-400 whitespace-nowrap">
                                    {{ formatDateTime(row.updated_at) }}
                                </td>

                                <td>
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" @click="openEdit(row)"
                                            class="erp-icon-button" title="Editar">
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button v-if="row.activo" type="button" @click="confirmDelete(row)"
                                            class="erp-icon-button text-rose-500 dark:text-rose-400 hover:text-rose-700 hover:bg-rose-50 hover:border-rose-200 dark:hover:text-rose-300 dark:hover:bg-rose-500/15 dark:hover:border-rose-500/30"
                                            title="Eliminar">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                        <button v-else-if="row.corporativo_activo === false" type="button" disabled
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
                        </tbody>
                    </table>
                </div>

                <div v-if="!pageRows.length" class="erp-empty-state">
                    <div class="erp-empty-state-icon"><PackageOpen class="h-6 w-6" /></div>
                    <p class="erp-empty-state-title">Sin registros</p>
                    <p class="erp-empty-state-desc">No se encontraron sucursales con los filtros actuales.</p>
                </div>

                <div v-if="pageRows.length"
                    class="px-4 py-3 border-t border-slate-100 dark:border-white/8 text-xs text-slate-500 dark:text-zinc-500">
                    Mostrando {{ from }} – {{ to }} de {{ total }} registros
                </div>
            </div>

            <!-- Paginación -->
            <div class="erp-pager">
                <button v-for="(l, idx) in mobileLinks" :key="idx"
                    :disabled="!l.url"
                    @click="goTo(l.url)"
                    class="erp-pager-btn"
                    :class="l.active ? 'active' : ''">
                    {{ linkLabelShort(l.label) }}
                </button>
            </div>

        </div>

        <!-- Modal Create/Edit -->
        <Modal :show="modalOpen" @close="closeModal">
            <div class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3 mb-5">
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-zinc-100">
                            {{ isEdit ? 'Editar sucursal' : 'Nueva sucursal' }}
                        </h3>
                    </div>
                    <button type="button" @click="closeModal"
                        class="erp-button erp-button-secondary h-9 px-3 text-xs">
                        Cerrar
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <SearchableSelect
                            v-model="form.corporativo_id"
                            :options="corporativosForSelect"
                            label="Corporativo"
                            label-key="nombre"
                            value-key="id"
                            :nullable="false"
                            placeholder="Selecciona corporativo..."
                            class="w-full"
                            :error="errors.corporativo_id ?? null" />
                    </div>

                    <div class="sm:col-span-2">
                        <AppInput v-model="form.nombre" label="Nombre" placeholder="Ej. Sucursal Centro" />
                        <div v-if="errors.nombre" class="erp-error">{{ errors.nombre }}</div>
                    </div>

                    <AppInput v-model="form.codigo" label="Alias" placeholder="Ej. CUM" />
                    <AppInput v-model="form.ciudad" label="Ciudad" placeholder="Ej. Cuernavaca" />
                    <AppInput v-model="form.estado" label="Estado" placeholder="Ej. Morelos" />

                    <div class="sm:col-span-2">
                        <AppInput v-model="form.direccion" label="Dirección" placeholder="Opcional" />
                    </div>
                </div>

                <div class="mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2">
                    <button type="button" @click="closeModal"
                        class="erp-button erp-button-secondary h-10 px-4">
                        Cancelar
                    </button>
                    <button type="button" @click="submit" :disabled="!canSubmit"
                        class="erp-button erp-button-primary h-10 px-5">
                        {{ saving ? 'Guardando...' : (isEdit ? 'Actualizar' : 'Guardar') }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
