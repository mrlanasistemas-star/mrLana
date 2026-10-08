<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import type { PlantillasPageProps } from './Plantillas.types'
import { usePlantillasIndex } from './usePlantillasIndex'
import {
    Plus,
    Pencil,
    Trash2,
    Search,
    X,
    FileText,
    Building2,
    Users,
    Tags,
    Truck,
    RefreshCw,
    ArrowUpDown,
    FilePlus2,
} from 'lucide-vue-next'

const props = defineProps<PlantillasPageProps>()

const {
    state,
    rows,
    pagerLinks,
    sortLabel,
    toggleSort,
    goCreatePlantilla,
    destroyRow,
    reactivateRow,
    goToUrl,
    money,
} = usePlantillasIndex(props)

const currentPage = props.plantillas?.meta?.current_page ?? 1
const lastPage    = props.plantillas?.meta?.last_page    ?? 1
const totalRows   = props.plantillas?.meta?.total        ?? 0
</script>

<template>
    <Head title="Plantillas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4 w-full min-w-0">
                <div class="min-w-0">
                    <h2 class="text-xl font-black text-slate-900 dark:text-zinc-100 truncate">
                        Plantillas
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                        Base reutilizable de requisiciones frecuentes
                    </p>
                </div>
                <button data-tour="plantillas-nueva" v-if="props.can?.registrar" @click="goCreatePlantilla" class="erp-button erp-button-primary h-11 px-5 flex-shrink-0">
                    <Plus class="h-4 w-4" />
                    Nueva plantilla
                </button>
            </div>
        </template>

        <div class="erp-page space-y-4">

            <!-- ── Toolbar de filtros ── -->
            <div class="erp-panel p-4">
                <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center flex-wrap">

                    <!-- Buscador -->
                    <div class="relative flex-1 min-w-[14rem]">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 dark:text-zinc-500 pointer-events-none" />
                        <input
                            v-model="state.q"
                            type="text"
                            placeholder="Buscar por nombre u observaciones..."
                            class="erp-input h-10 pl-10"
                        />
                    </div>

                    <!-- Filtro Estatus -->
                    <div class="w-full sm:w-52">
                        <select v-model="state.status" class="erp-select h-10">
                            <option value="">Todos los estatus</option>
                            <option value="BORRADOR">Borrador</option>
                            <option value="ELIMINADA">Eliminada</option>
                        </select>
                    </div>

                    <!-- Orden A-Z / Z-A -->
                    <button
                        type="button"
                        @click="toggleSort"
                        class="erp-button erp-button-secondary h-10 gap-1.5 flex-shrink-0"
                    >
                        <ArrowUpDown class="h-4 w-4" />
                        {{ sortLabel }}
                    </button>

                    <!-- Limpiar filtros -->
                    <button
                        v-if="state.q || state.status"
                        type="button"
                        @click="state.q = ''; state.status = ''"
                        class="erp-icon-button flex-shrink-0"
                        title="Limpiar filtros"
                    >
                        <X class="h-4 w-4" />
                    </button>

                    <!-- Contador de resultados -->
                    <span class="text-sm text-slate-500 dark:text-zinc-400 flex-shrink-0 whitespace-nowrap ml-auto">
                        {{ totalRows }} resultado{{ totalRows !== 1 ? 's' : '' }}
                    </span>
                </div>
            </div>

            <!-- ── Empty state ── -->
            <div v-if="!rows.length" class="erp-panel">
                <div class="erp-empty-state">
                    <div class="erp-empty-state-icon">
                        <FileText class="h-6 w-6" />
                    </div>
                    <p class="erp-empty-state-title">Sin plantillas</p>
                    <p class="erp-empty-state-desc">
                        Crea una plantilla para agilizar tus requisiciones frecuentes.
                    </p>
                    <button v-if="props.can?.registrar" @click="goCreatePlantilla" class="erp-button erp-button-primary mt-2">
                        <Plus class="h-4 w-4" />
                        Nueva plantilla
                    </button>
                </div>
            </div>

            <!-- ── Grid de cards ── -->
            <div data-tour="plantillas-lista" v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <div
                    v-for="row in rows"
                    :key="row.id"
                    class="erp-card erp-card-hover p-5 flex flex-col gap-3"
                >
                    <!-- Encabezado: nombre + estatus -->
                    <div class="flex items-start justify-between gap-3 min-w-0">
                        <p class="font-black text-slate-900 dark:text-zinc-100 text-base leading-tight truncate min-w-0">
                            {{ row.nombre }}
                        </p>
                        <span
                            class="erp-badge flex-shrink-0"
                            :class="row.status === 'BORRADOR'
                                ? 'bg-zinc-100 text-zinc-600 border-zinc-200 dark:bg-zinc-800/50 dark:text-zinc-300 dark:border-zinc-700'
                                : 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/25'"
                        >
                            {{ row.status }}
                        </span>
                    </div>

                    <!-- Monto total -->
                    <div
                        v-if="Number(row.monto_total) > 0"
                        class="text-2xl font-black text-emerald-600 dark:text-emerald-400 leading-none"
                    >
                        {{ money(row.monto_total) }}
                    </div>

                    <!-- Datos de la plantilla -->
                    <div class="space-y-1.5 text-sm text-slate-600 dark:text-zinc-400 flex-1">
                        <div v-if="row.sucursal" class="flex items-center gap-2 min-w-0">
                            <Building2 class="h-3.5 w-3.5 flex-shrink-0 text-slate-400 dark:text-zinc-500" />
                            <span class="font-semibold text-slate-500 dark:text-zinc-500 flex-shrink-0">Sucursal:</span>
                            <span class="truncate">{{ row.sucursal.nombre }}</span>
                        </div>
                        <div v-if="row.solicitante" class="flex items-center gap-2 min-w-0">
                            <Users class="h-3.5 w-3.5 flex-shrink-0 text-slate-400 dark:text-zinc-500" />
                            <span class="font-semibold text-slate-500 dark:text-zinc-500 flex-shrink-0">Solicitante:</span>
                            <span class="truncate">{{ row.solicitante.nombre }}</span>
                        </div>
                        <div v-if="row.proveedor" class="flex items-center gap-2 min-w-0">
                            <Truck class="h-3.5 w-3.5 flex-shrink-0 text-slate-400 dark:text-zinc-500" />
                            <span class="font-semibold text-slate-500 dark:text-zinc-500 flex-shrink-0">Proveedor:</span>
                            <span class="truncate">{{ row.proveedor.nombre }}</span>
                        </div>
                        <div v-if="row.concepto" class="flex items-center gap-2 min-w-0">
                            <Tags class="h-3.5 w-3.5 flex-shrink-0 text-slate-400 dark:text-zinc-500" />
                            <span class="font-semibold text-slate-500 dark:text-zinc-500 flex-shrink-0">Concepto:</span>
                            <span class="truncate">{{ row.concepto.nombre }}</span>
                        </div>
                        <div v-if="row.observaciones" class="flex items-start gap-2 min-w-0">
                            <FileText class="h-3.5 w-3.5 flex-shrink-0 text-slate-400 dark:text-zinc-500 mt-0.5" />
                            <span class="font-semibold text-slate-500 dark:text-zinc-500 flex-shrink-0">Observaciones:</span>
                            <span class="line-clamp-2 min-w-0">
                                {{ row.observaciones.length > 80
                                    ? row.observaciones.slice(0, 80) + '…'
                                    : row.observaciones }}
                            </span>
                        </div>
                    </div>

                    <!-- ── Acciones al pie ── -->
                    <div class="mt-auto pt-3 border-t border-slate-100 dark:border-white/[0.08] flex flex-wrap gap-2">
                        <a
                            v-if="props.can?.usar && row.status !== 'ELIMINADA'"
                            :href="route('requisiciones.registrar', { plantilla: row.id })"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="erp-button h-9 px-3 text-xs flex-1 min-w-[7rem] justify-center
                                   bg-emerald-600 text-white border-emerald-600
                                   hover:bg-emerald-700 hover:border-emerald-700
                                   dark:bg-emerald-500 dark:border-emerald-500 dark:hover:bg-emerald-600"
                        >
                            <FilePlus2 class="h-3.5 w-3.5 flex-shrink-0" />
                            Crear requisición
                        </a>

                        <a
                            v-if="(row as any).can?.editar ?? props.can?.editar"
                            :href="route('plantillas.edit', { plantilla: row.id })"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="erp-button erp-button-secondary h-9 px-3 text-xs flex-1 min-w-[5rem] justify-center"
                        >
                            <Pencil class="h-3.5 w-3.5 flex-shrink-0" />
                            Editar
                        </a>

                        <button
                            v-if="((row as any).can?.eliminar ?? props.can?.eliminar) && row.status !== 'ELIMINADA'"
                            type="button"
                            @click="destroyRow(row)"
                            class="erp-button erp-button-danger h-9 px-3 text-xs flex-1 min-w-[5rem] justify-center"
                        >
                            <Trash2 class="h-3.5 w-3.5 flex-shrink-0" />
                            Eliminar
                        </button>

                        <button
                            v-else-if="(row as any).can?.eliminar ?? props.can?.eliminar"
                            type="button"
                            @click="reactivateRow(row)"
                            class="erp-button h-9 px-3 text-xs flex-1 min-w-[5rem] justify-center
                                   bg-emerald-600 text-white border-emerald-600
                                   hover:bg-emerald-700 hover:border-emerald-700
                                   dark:bg-emerald-500 dark:border-emerald-500 dark:hover:bg-emerald-600"
                        >
                            <RefreshCw class="h-3.5 w-3.5 flex-shrink-0" />
                            Reactivar
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Paginación ── -->
            <div v-if="pagerLinks.length > 3" class="erp-pager">
                <span class="text-xs text-slate-500 dark:text-zinc-400 mr-2">
                    Página {{ currentPage }} de {{ lastPage }}
                </span>
                <button
                    v-for="(link, i) in pagerLinks"
                    :key="`${i}-${link.cleanLabel}`"
                    type="button"
                    class="erp-pager-btn"
                    :class="{ active: link.active }"
                    :disabled="!link.url"
                    @click="goToUrl(link.url)"
                    v-html="link.label"
                />
            </div>

        </div>
    </AuthenticatedLayout>
</template>
