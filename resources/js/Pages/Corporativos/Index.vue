<script setup lang="ts">
    import { computed } from 'vue'
    import { Head } from '@inertiajs/vue3'


    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import AppCheckbox from '@/Components/Checkbox.vue'

    import { Plus, Pencil, Trash2, Search, X, PackageOpen } from 'lucide-vue-next'

    import type { CorporativosProps } from './Corporativos.types'
    import { useCorporativosIndex } from './useCorporativosIndex'
    import { formatDateTime } from '@/Utils/date'

    import ICON_PDF from '@/img/pdf.png'
    import ICON_EXCEL from '@/img/excel.png'
    import { toQS, downloadFile } from '@/Utils/exports'

    import { usePermissions } from '@/Composables/usePermissions'
    const props = defineProps<CorporativosProps>()
    // Solo ayuda visual: el backend vuelve a autorizar cada acción.
    const { can } = usePermissions()

    const {
        state,
        selectedIds,
        headerCheckbox,
        isAllSelected,
        selectedCount,
        headerAriaChecked,
        paginationLinks,
        logoSrc,
        toggleRow,
        toggleAllOnPage,
        clearSelection,
        goTo,
        openCreate,
        openEdit,
        confirmDelete,
        confirmBulkDelete,
        confirmActivate,
    } = useCorporativosIndex(props)

    const exportPdfUrl = computed(() => route('corporativos.export.pdf') + toQS(state))
    const exportExcelUrl = computed(() => route('corporativos.export.excel') + toQS(state))

    type PaginationLink = {
        url: string | null
        label: string
        active?: boolean
    }

    const mobileLinks = computed<PaginationLink[]>(() => {
        const links = (paginationLinks.value ?? []) as unknown as Array<Partial<PaginationLink> | null>
        return links
            .filter((l): l is Partial<PaginationLink> => !!l && typeof l.label === 'string')
            .map((l) => ({
                url: (l.url ?? null) as string | null,
                label: String(l.label ?? ''),
                active: Boolean(l.active),
            }))
    })

    function linkLabelShort(label: string): string {
        const clean = String(label)
            .replace(/&laquo;|&raquo;|&hellip;/g, '')
            .replace(/<[^>]*>/g, '')
            .trim()

        const low = clean.toLowerCase()
        if (low.includes('atrás') || low.includes('anterior')) return '‹ Atrás'
        if (low.includes('siguiente')) return 'Siguiente ›'
        if (/^\d+$/.test(clean)) return clean
        if (clean.length > 6) return clean.slice(0, 6)
        return clean || '…'
    }

    const selectedIdsArray = computed<number[]>({
        get() {
            return Array.from(selectedIds.value)
        },
        set(values: number[]) {
            selectedIds.value = new Set(values)
        },
    })
</script>

<template>
    <Head title="Corporativos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4 w-full min-w-0">
                <div>
                    <h2 class="text-xl font-black text-slate-900 dark:text-zinc-100">Corporativos</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">Administra todos tus corporativos</p>
                </div>
                <button v-if="can('corporativos.registrar')" type="button" @click="openCreate" class="erp-button erp-button-primary h-11 px-5">
                    <Plus class="h-4 w-4" /> Nuevo
                </button>
            </div>
        </template>

        <div class="erp-page space-y-4">

            <!-- Bulk actions -->
            <div v-if="selectedCount > 0 && can('corporativos.desactivar')" class="erp-panel p-3 flex flex-wrap items-center gap-3">
                <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">
                    {{ selectedCount }} seleccionado(s)
                </span>
                <button type="button" @click="clearSelection" class="erp-button erp-button-secondary h-9 px-3 text-xs">
                    Limpiar
                </button>
                <button type="button" @click="confirmBulkDelete" class="erp-button erp-button-danger h-9 px-3 text-xs">
                    <Trash2 class="h-3.5 w-3.5" /> Eliminar seleccionados
                </button>
            </div>

            <!-- Toolbar / Filtros -->
            <div class="erp-panel p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="relative flex-1 min-w-[200px]">
                        <Search class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input v-model="state.q" class="erp-input pl-10 h-10"
                            placeholder="Buscar por nombre, RFC, email, teléfono o alias..." />
                    </div>

                    <select v-model="state.activo" class="erp-select h-10 w-auto min-w-[130px]">
                        <option value="all">Todos</option>
                        <option value="1">Activos</option>
                        <option value="0">Eliminados</option>
                    </select>

                    <select v-model="state.perPage" class="erp-select h-10 w-auto min-w-[100px]">
                        <option :value="10">10 / pág</option>
                        <option :value="25">25 / pág</option>
                        <option :value="50">50 / pág</option>
                        <option :value="100">100 / pág</option>
                    </select>

                    <button v-if="can('corporativos.exportar')" type="button" @click="downloadFile(exportPdfUrl)"
                        class="erp-icon-button" title="Exportar PDF">
                        <img :src="ICON_PDF" alt="PDF" class="h-5 w-5" />
                    </button>

                    <button v-if="can('corporativos.exportar')" type="button" @click="downloadFile(exportExcelUrl)"
                        class="erp-icon-button" title="Exportar Excel">
                        <img :src="ICON_EXCEL" alt="Excel" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Mobile cards (xl:hidden) -->
            <div class="xl:hidden space-y-3">
                <div v-for="row in corporativos.data" :key="row.id" class="erp-card p-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="pt-0.5 shrink-0">
                            <AppCheckbox
                                v-model:checked="selectedIdsArray"
                                :value="row.id"
                                :label="`Seleccionar corporativo ${row.nombre}`"
                            />
                        </div>

                        <div class="h-11 w-11 rounded-xl border border-slate-200/70 dark:border-white/10 overflow-hidden
                            bg-slate-50 dark:bg-neutral-950 grid place-items-center shrink-0">
                            <img v-if="row.logo_path" :src="logoSrc(row.logo_path)!"
                                class="h-full w-full object-contain" alt="logo" loading="lazy" />
                            <span v-else class="text-[10px] font-black text-slate-500 dark:text-neutral-400">
                                {{ row.nombre?.slice(0, 2)?.toUpperCase() }}
                            </span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2 min-w-0">
                                <div class="font-bold text-slate-900 dark:text-zinc-100 truncate">{{ row.nombre }}</div>
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
                                <div><span class="font-semibold">RFC:</span> {{ row.rfc ?? '—' }}</div>
                                <div><span class="font-semibold">Alias:</span> {{ row.codigo ?? '—' }}</div>
                                <div><span class="font-semibold">Email:</span> {{ row.email ?? '—' }}</div>
                                <div><span class="font-semibold">Teléfono:</span> {{ row.telefono ?? '—' }}</div>
                                <div><span class="font-semibold">Registro:</span> {{ formatDateTime(row.created_at) }}</div>
                                <div><span class="font-semibold">Actualización:</span> {{ formatDateTime(row.updated_at) }}</div>
                            </div>

                            <div class="flex gap-2 mt-3 pt-3 border-t border-slate-100 dark:border-white/8">
                                <button v-if="can('corporativos.editar')" type="button" @click="openEdit(row)"
                                    class="erp-button erp-button-secondary h-9 text-xs flex-1">
                                    <Pencil class="h-3.5 w-3.5" /> Editar
                                </button>
                                <button v-if="row.activo && can('corporativos.desactivar')" type="button" @click="confirmDelete(row)"
                                    class="erp-button erp-button-danger h-9 text-xs">
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                                <button v-else-if="!row.activo && can('corporativos.reactivar')" type="button" @click="confirmActivate(row)"
                                    class="erp-button h-9 text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20">
                                    Activar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!corporativos.data?.length" class="erp-panel erp-empty-state">
                    <div class="erp-empty-state-icon"><PackageOpen class="h-6 w-6" /></div>
                    <p class="erp-empty-state-title">Sin registros</p>
                    <p class="erp-empty-state-desc">No se encontraron corporativos con los filtros actuales.</p>
                </div>
            </div>

            <!-- Desktop tabla (hidden xl:block) -->
            <div class="hidden xl:block erp-panel overflow-hidden">
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th class="w-[46px]">
                                <AppCheckbox
                                    :checked="isAllSelected"
                                    @update:checked="(v) => toggleAllOnPage(!!v)"
                                />
                            </th>
                            <th>Corporativo</th>
                            <th>Alias</th>
                            <th>RFC</th>
                            <th>Contacto</th>
                            <th>Dirección</th>
                            <th>Estatus</th>
                            <th>Registro</th>
                            <th>Actualización</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="row in corporativos.data" :key="row.id" class="group">
                            <td class="align-middle">
                                <AppCheckbox
                                    v-model:checked="selectedIdsArray"
                                    :value="row.id"
                                    :label="`Seleccionar corporativo ${row.nombre}`"
                                />
                            </td>

                            <td>
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="h-9 w-9 rounded-xl border border-slate-200/70 dark:border-white/10 overflow-hidden
                                        bg-slate-50 dark:bg-neutral-950 grid place-items-center shrink-0">
                                        <img v-if="row.logo_path" :src="logoSrc(row.logo_path)!"
                                            class="h-full w-full object-contain" alt="logo" loading="lazy" />
                                        <span v-else class="text-[9px] font-black text-slate-500 dark:text-neutral-400">
                                            {{ row.nombre?.slice(0, 2)?.toUpperCase() }}
                                        </span>
                                    </div>
                                    <div class="font-semibold text-slate-900 dark:text-zinc-100 truncate max-w-[180px]">
                                        {{ row.nombre }}
                                    </div>
                                </div>
                            </td>

                            <td>{{ row.codigo ?? '—' }}</td>
                            <td>{{ row.rfc ?? '—' }}</td>

                            <td>
                                <div>{{ row.email ?? '—' }}</div>
                                <div class="text-xs text-slate-500 dark:text-zinc-500">{{ row.telefono ?? '—' }}</div>
                            </td>

                            <td class="max-w-[200px] truncate">{{ row.direccion ?? '—' }}</td>

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
                                    <button v-if="can('corporativos.editar')" type="button" @click="openEdit(row)"
                                        class="erp-icon-button" title="Editar">
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button v-if="row.activo && can('corporativos.desactivar')" type="button" @click="confirmDelete(row)"
                                        class="erp-icon-button text-rose-500 dark:text-rose-400 hover:text-rose-700 hover:bg-rose-50 hover:border-rose-200 dark:hover:text-rose-300 dark:hover:bg-rose-500/15 dark:hover:border-rose-500/30"
                                        title="Eliminar">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                    <button v-else-if="!row.activo && can('corporativos.reactivar')" type="button" @click="confirmActivate(row)"
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

                <div v-if="!corporativos.data?.length" class="erp-empty-state">
                    <div class="erp-empty-state-icon"><PackageOpen class="h-6 w-6" /></div>
                    <p class="erp-empty-state-title">Sin registros</p>
                    <p class="erp-empty-state-desc">No se encontraron corporativos con los filtros actuales.</p>
                </div>

                <div v-if="corporativos.data?.length"
                    class="px-4 py-3 border-t border-slate-100 dark:border-white/8 text-xs text-slate-500 dark:text-zinc-500">
                    Mostrando {{ corporativos.meta.from ?? 0 }} – {{ corporativos.meta.to ?? 0 }}
                    de {{ corporativos.meta.total }} registros
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
    </AuthenticatedLayout>
</template>
