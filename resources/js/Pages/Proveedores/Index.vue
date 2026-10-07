<script setup lang="ts">
import { usePermissions } from '@/Composables/usePermissions'
    import { Head } from '@inertiajs/vue3'
    import { computed } from 'vue'
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import SecondaryButton from '@/Components/SecondaryButton.vue'
    import SearchSelect from '@/Components/ui/SearchableSelect.vue'
    import type { ProveedoresIndexProps } from './useProveedoresIndex'
    import { useProveedoresIndex } from './useProveedoresIndex'
    import { IconUserPlus } from '@tabler/icons-vue'
    // Importamos iconos propios
    import ICON_PDF from '@/img/pdf.png'
    import ICON_EXCEL from '@/img/excel.png'
    // Importamos funciones para exportar archivos
    import { toQS, downloadFile } from '@/Utils/exports'

    const props = defineProps<ProveedoresIndexProps>()

    const exportPdfUrl = computed(() => route('proveedores.export.pdf') + toQS(state))
    const exportExcelUrl = computed(() => route('proveedores.export.excel') + toQS(state))

    const {
        // ui/rol
        isPrivileged,
        // filtros
        state,
        inputBase,
        hasActiveFilters,
        clearFilters,
        // orden
        sortLabel,
        toggleSort,
        setSort,
        selectBase,
        // rows
        rows,
        // selección
        selectedIds,
        selectedCount,
        selectedActiveCount,
        selectedHasInactive,
        isAllSelectedOnPage,
        toggleRow,
        toggleAllOnPage,
        clearSelection,
        // acciones
        destroySelected,
        confirmDeleteOne,
        activateOne,
        // pager
        pagerLinks,
        goTo,
        // modal
        modalOpen,
        editing,
        form,
        openCreate,
        openEdit,
        closeModal,
        submit,
        onClabeInput,
        // catálogos
        ownerOptions,
    } = useProveedoresIndex(props)
const { can } = usePermissions()

</script>

<template>
    <Head title="Proveedores" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3 min-w-0">
                <h2 class="text-xl font-semibold leading-tight text-slate-900 dark:text-zinc-100 truncate">
                Proveedores
                </h2>
            </div>
        </template>

        <div class="w-full max-w-full min-w-0 px-3 sm:px-6 lg:px-8 py-4 sm:py-6">
            <!-- HERO -->
            <div class="mb-4 rounded-3xl border border-slate-200/70
            dark:border-white/10 bg-white/90 dark:bg-neutral-900/80
            backdrop-blur shadow-sm px-4 sm:px-6 py-4 sm:py-5 flex flex-col
            sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-neutral-100 truncate">
                        Control y gestión de proveedores
                    </div>
                </div>

                <button v-if="can('proveedores.registrar')" type="button" @click="openCreate"
                class="inline-flex items-center justify-center gap-2
                rounded-2xl px-5 py-2.5 text-sm font-semibold
                bg-emerald-600 text-white hover:bg-emerald-700
                dark:bg-emerald-500 dark:hover:bg-emerald-600
                shadow-sm hover:shadow transition active:scale-[0.98]
                w-full sm:w-auto">
                    <IconUserPlus :size="22" stroke-width="1.5" />
                    Nuevo proveedor
                </button>
            </div>

            <!-- FILTROS -->
            <div class="mb-4 rounded-3xl border border-slate-200/70
            dark:border-white/10 bg-white/90 dark:bg-neutral-900/80
            backdrop-blur shadow-sm p-4 sm:p-5 relative z-[50]">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                    <!-- USUARIO DUEÑO (solo admin/conta) -->
                    <div v-if="isPrivileged" class="lg:col-span-4 min-w-0">
                        <SearchSelect v-model="state.user_duenio_id" :options="ownerOptions"
                        label="Usuario dueño" placeholder="Todos"
                        search-placeholder="Buscar usuario..." label-key="nombre"
                        secondary-key="email" value-key="id" :allow-null="true"
                        null-label="Todos" rounded="2xl" z-index-class="z-[80]"/>
                    </div>

                    <!-- ESTATUS (solo admin/conta) -->
                    <div v-if="isPrivileged" class="lg:col-span-3 min-w-0">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Estatus</label>
                        <select v-model="state.status"
                        class="w-full rounded-2xl mt-1" :class="selectBase">
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                            <option value="">Todos</option>
                        </select>
                    </div>

                    <!-- PER PAGE -->
                    <div class="lg:col-span-2 min-w-0">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Por página</label>
                        <select v-model.number="state.perPage" class="w-full rounded-2xl mt-1" :class="selectBase">
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                            <option :value="50">50</option>
                        </select>
                    </div>

                    <!-- BUSCAR -->
                    <div class="lg:col-span-3 min-w-0">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Buscar</label>
                        <input v-model="state.q" type="text"
                        placeholder="Razón social, RFC, banco o CLABE..."
                        class="w-full rounded-2xl mt-1" :class="inputBase"/>
                    </div>

                    <!-- ACCIONES -->
                    <div class="lg:col-span-12 flex flex-wrap items-center gap-3 pt-2">
                        <!-- UN SOLO control de orden -->
                        <button type="button" @click="toggleSort"
                        class="inline-flex items-center justify-center
                        rounded-2xl px-4 py-2 text-xs font-semibold
                        bg-slate-100 text-slate-800 hover:bg-slate-200
                        dark:bg-white/10 dark:text-neutral-100
                        dark:hover:bg-white/15 transition"
                        title="Cambiar orden">
                            Orden: {{ sortLabel }}
                        </button>

                        <button type="button" @click="setSort('razon_social')"
                        class="inline-flex items-center justify-center
                        rounded-2xl px-4 py-2 text-xs font-bold border
                        border-slate-200 bg-white text-slate-900 hover:bg-slate-50
                        dark:border-white/10 dark:bg-white/10 dark:text-neutral-100
                        dark:hover:bg-white/15 transition active:scale-[0.98]">
                            Nombre
                        </button>

                        <SecondaryButton v-if="hasActiveFilters" type="button"
                        @click="clearFilters" class="rounded-2xl">
                            Limpiar filtros
                        </SecondaryButton>

                        <!-- PDF -->
                        <button v-if="can('proveedores.exportar')" type="button" @click="downloadFile(exportPdfUrl)" class="group flex flex-col items-center gap-1 py-2 ...">
                            <img :src="ICON_PDF" alt="PDF" class="h-6 w-6 transition-transform group-hover:scale-125"/>
                            <span class="relative text-[11px] leading-none ...">Descargar</span>
                        </button>

                        <!-- EXCEL -->
                        <button v-if="can('proveedores.exportar')" type="button" @click="downloadFile(exportExcelUrl)" class="group flex flex-col items-center gap-1 py-2 ...">
                            <img :src="ICON_EXCEL" alt="EXCEL" class="h-6 w-6 transition-transform group-hover:scale-125"/>
                            <span class="relative text-[11px] leading-none ...">Descargar</span>
                        </button>

                        <div class="ml-auto flex flex-wrap items-center gap-2">
                            <button v-if="selectedCount" type="button" @click="clearSelection"
                            class="inline-flex items-center justify-center rounded-2xl
                            px-4 py-2 text-xs font-bold border border-slate-200
                            bg-white text-slate-900 hover:bg-slate-50
                            dark:border-white/10 dark:bg-white/10
                            dark:text-neutral-100 dark:hover:bg-white/15
                            transition active:scale-[0.98]">
                                Limpiar selección ({{ selectedCount }})
                            </button>

                            <!-- Solo aparece cuando hay seleccionados ACTIVO y ninguno INACTIVO -->
                            <button v-if="can('proveedores.desactivar') && selectedActiveCount > 0 && !selectedHasInactive"
                            type="button" @click="destroySelected"
                            class="inline-flex items-center justify-center rounded-2xl
                            px-4 py-2 text-xs font-bold border border-rose-200 bg-rose-50
                            text-rose-700 hover:bg-rose-100
                            dark:border-rose-500/20 dark:bg-rose-500/10
                            dark:text-rose-200 dark:hover:bg-rose-500/15
                            transition active:scale-[0.98]">
                                Eliminar seleccionados
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABLA (lg+) -->
            <div class="hidden lg:block rounded-3xl border border-slate-200/70
            dark:border-white/10 bg-white/90 dark:bg-neutral-900/80
            backdrop-blur shadow-sm overflow-hidden">
                <table class="w-full table-auto text-sm">
                    <thead class="bg-slate-50 dark:bg-neutral-950/60">
                        <tr class="text-left text-slate-600 dark:text-neutral-300">
                            <th class="px-4 py-3 font-semibold w-12">
                                <input type="checkbox" class="h-4 w-4 rounded
                                border-slate-300 dark:border-white/10 bg-white
                                dark:bg-neutral-900" :checked="isAllSelectedOnPage"
                                @change="toggleAllOnPage(($event.target as HTMLInputElement).checked)"/>
                            </th>
                            <th class="px-4 py-3 font-semibold">Razón social</th>
                            <th class="px-4 py-3 font-semibold">RFC</th>
                            <th class="px-4 py-3 font-semibold">CLABE</th>
                            <th class="px-4 py-3 font-semibold">Banco</th>
                            <th class="px-4 py-3 font-semibold">Estatus</th>
                            <th class="px-4 py-3 font-semibold text-right">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="row in rows" :key="row.id"
                        class="border-t border-slate-200/70 dark:border-white/10
                        hover:bg-slate-50/70 dark:hover:bg-neutral-950/40 transition">
                            <td class="px-4 py-3 align-middle">
                                <input type="checkbox" class="h-4 w-4 rounded
                                border-slate-300 dark:border-white/10 bg-white
                                dark:bg-neutral-900" :checked="selectedIds.has(row.id)"
                                @change="toggleRow(row.id, ($event.target as HTMLInputElement).checked)"/>
                            </td>

                            <td class="px-4 py-3 min-w-0">
                                <div class="font-extrabold text-slate-900 dark:text-neutral-100 truncate">
                                    {{ row.razon_social }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-neutral-400 truncate">
                                    ID: {{ row.id }} · {{ row.created_at ? String(row.created_at).slice(0, 10) : '—' }}
                                </div>
                            </td>

                            <td class="px-4 py-3 text-slate-800 dark:text-neutral-100">
                                {{ row.rfc || '—' }}
                            </td>

                            <td class="px-4 py-3 text-slate-800 dark:text-neutral-100">
                                {{ row.clabe || '—' }}
                            </td>

                            <td class="px-4 py-3 text-slate-800 dark:text-neutral-100">
                                {{ row.banco || '—' }}
                            </td>

                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-2 rounded-full px-3
                                py-1 text-xs font-semibold border"
                                :class="row.status === 'INACTIVO'
                                ? 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200'">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-40"></span>
                                    <span class="truncate">{{ row.status }}</span>
                                </span>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <div class="inline-flex gap-2">
                                    <button v-if="can('proveedores.editar')" type="button" @click="openEdit(row)"
                                    class="btn border border-slate-200 bg-slate-50
                                    text-slate-900 hover:bg-slate-100
                                    dark:border-white/10 dark:bg-white/10
                                    dark:text-neutral-100 dark:hover:bg-white/15">
                                        Editar
                                    </button>

                                    <!-- Reactivar si está INACTIVO -->
                                    <button v-if="row.status === 'INACTIVO' && can('proveedores.reactivar')" type="button"
                                    @click="activateOne(row.id)"
                                    class="btn border border-emerald-200 bg-emerald-50
                                    text-emerald-700 hover:bg-emerald-100
                                    dark:border-emerald-500/20 dark:bg-emerald-500/10
                                    dark:text-emerald-200 dark:hover:bg-emerald-500/15">
                                        Reactivar
                                    </button>

                                    <!-- Eliminar solo si está ACTIVO -->
                                    <button v-else-if="row.status !== 'INACTIVO' && can('proveedores.desactivar')" type="button"
                                    @click="confirmDeleteOne(row.id)"
                                    class="btn border border-rose-200 bg-rose-50
                                    text-rose-700 hover:bg-rose-100
                                    dark:border-rose-500/20 dark:bg-rose-500/10
                                    dark:text-rose-200 dark:hover:bg-rose-500/15">
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="rows.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-slate-500 dark:text-neutral-400">
                                No hay proveedores con los filtros actuales.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- PAGINACIÓN -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between
                gap-3 border-t border-slate-200/70 dark:border-white/10
                px-4 py-3 bg-white/90 dark:bg-neutral-900/80">
                    <div class="text-xs text-slate-600 dark:text-neutral-300">
                        Mostrando {{ props.rows.from ?? 0 }} a {{ props.rows.to ?? 0 }} de {{ props.rows.total ?? 0 }}
                    </div>

                    <nav class="flex flex-wrap gap-2 max-w-full">
                        <button v-for="(link, i) in pagerLinks" :key="`${i}-${link.label}`"
                        type="button" @click="link.url ? goTo(link.url) : null"
                        :disabled="!link.url" class="rounded-2xl px-3 py-1.5 text-sm
                        font-semibold border transition border-slate-200 bg-white
                        text-slate-800 hover:bg-slate-50 hover:-translate-y-[1px]
                        disabled:opacity-50 disabled:cursor-not-allowed
                        dark:border-white/10 dark:bg-neutral-950/40
                        dark:text-neutral-100 dark:hover:bg-neutral-950/60"
                        :class="link.active ? 'ring-2 ring-slate-300 dark:ring-white/10' : ''"
                        v-html="link.label"/>
                    </nav>
                </div>
            </div>

            <!-- CARDS (mobile/tablet) -->
            <div class="lg:hidden grid min-w-0 grid-cols-1 gap-3">
                <transition-group name="list" tag="div" class="grid min-w-0 grid-cols-1 gap-3">
                    <div v-for="row in rows" :key="row.id"
                    class="min-w-0 rounded-3xl border border-slate-200/70 dark:border-white/10
                    bg-white/90 dark:bg-neutral-900/80 backdrop-blur shadow-sm p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0">
                                <input type="checkbox" class="mt-1 h-4 w-4 rounded
                                border-slate-300 dark:border-white/10
                                bg-white dark:bg-neutral-900 shrink-0"
                                :checked="selectedIds.has(row.id)"
                                @change="toggleRow(row.id, ($event.target as HTMLInputElement).checked)"/>

                                <div class="min-w-0">
                                    <div class="font-extrabold text-slate-900 dark:text-neutral-100 truncate">
                                        {{ row.razon_social }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-slate-500 dark:text-neutral-400 truncate">
                                        RFC: {{ row.rfc || '—' }} · Banco: {{ row.banco || '—' }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-slate-500 dark:text-neutral-400 truncate">
                                        CLABE: {{ row.clabe || '—' }}
                                    </div>
                                </div>
                            </div>

                            <span class="inline-flex items-center gap-2 rounded-full px-3
                            py-1 text-[11px] font-semibold border shrink-0"
                            :class="row.status === 'INACTIVO'
                            ? 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200'">
                                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-40"></span>
                                <span class="truncate">{{ row.status }}</span>
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button v-if="can('proveedores.editar')" type="button" @click="openEdit(row)"
                            class="btn border border-slate-200 bg-slate-50 text-slate-900
                            hover:bg-slate-100 dark:border-white/10 dark:bg-white/10
                            dark:text-neutral-100 dark:hover:bg-white/15">
                                Editar
                            </button>

                            <button v-if="row.status === 'INACTIVO' && can('proveedores.reactivar')" type="button"
                            @click="activateOne(row.id)" class="btn border
                            border-emerald-200 bg-emerald-50 text-emerald-700
                            hover:bg-emerald-100 dark:border-emerald-500/20
                            dark:bg-emerald-500/10 dark:text-emerald-200
                            dark:hover:bg-emerald-500/15">
                                Reactivar
                            </button>

                            <button v-else-if="row.status !== 'INACTIVO' && can('proveedores.desactivar')" type="button" @click="confirmDeleteOne(row.id)"
                            class="btn border border-rose-200 bg-rose-50 text-rose-700
                            hover:bg-rose-100 dark:border-rose-500/20 dark:bg-rose-500/10
                            dark:text-rose-200 dark:hover:bg-rose-500/15">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </transition-group>

                <!-- paginación cards -->
                <div class="rounded-3xl border border-slate-200/70 dark:border-white/10
                bg-white/90 dark:bg-neutral-900/80 backdrop-blur shadow-sm px-4 py-3">
                    <nav class="flex flex-wrap gap-2 max-w-full justify-center">
                        <button v-for="(link, i) in pagerLinks" :key="`m-${i}-${link.label}`"
                        type="button" @click="link.url ? goTo(link.url) : null"
                        :disabled="!link.url" class="rounded-2xl px-3 py-1.5 text-sm
                        font-semibold border transition border-slate-200 bg-white
                        text-slate-800 hover:bg-slate-50 hover:-translate-y-[1px]
                        disabled:opacity-50 disabled:cursor-not-allowed
                        dark:border-white/10 dark:bg-neutral-950/40
                        dark:text-neutral-100 dark:hover:bg-neutral-950/60"
                        :class="link.active ? 'ring-2 ring-slate-300 dark:ring-white/10' : ''"
                        v-html="link.label"/>
                    </nav>
                </div>
            </div>

            <!-- MODAL -->
            <div v-if="modalOpen" class="fixed inset-0 z-[12000]">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeModal"></div>

                <div class="absolute inset-0 flex items-center justify-center p-4">
                    <div class="w-full max-w-2xl rounded-3xl border border-slate-200/70
                    dark:border-white/10 bg-white dark:bg-neutral-900 shadow-2xl
                    overflow-hidden">
                        <div class="px-6 py-5 border-b border-slate-200/70 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <div class="text-lg font-black text-slate-900 dark:text-neutral-100">
                                    {{ editing ? 'Editar proveedor' : 'Nuevo proveedor' }}
                                </div>
                                <button class="rounded-2xl px-3 py-2 text-sm font-semibold
                                bg-slate-100 hover:bg-slate-200
                                dark:bg-white/10 dark:hover:bg-white/15
                                text-slate-800 dark:text-neutral-100"
                                @click="closeModal">
                                    Cerrar
                                </button>
                            </div>
                        </div>

                        <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Razón social</label>
                                <input v-model="form.razon_social" type="text" class="w-full rounded-2xl mt-1" :class="inputBase" />
                                <div v-if="form.errors.razon_social" class="mt-1 text-xs text-rose-600">
                                    {{ form.errors.razon_social }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">RFC</label>
                                <input v-model="form.rfc" type="text" class="w-full rounded-2xl mt-1" :class="inputBase" />
                                <div v-if="form.errors.rfc" class="mt-1 text-xs text-rose-600">{{ form.errors.rfc }}</div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Banco</label>
                                <input v-model="form.banco" type="text" class="w-full rounded-2xl mt-1" :class="inputBase" />
                                <div v-if="form.errors.banco" class="mt-1 text-xs text-rose-600">{{ form.errors.banco }}</div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">CLABE interbancaria</label>
                                <input :value="form.clabe" @input="onClabeInput"
                                inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                                placeholder="Solo números" maxlength="18"
                                class="w-full rounded-2xl mt-1" :class="inputBase"/>
                                <div class="mt-1 text-xs text-slate-500 dark:text-neutral-400">
                                    Solo dígitos (sin letras) para evitar CLABEs inválidas.
                                </div>
                                <div v-if="form.errors.clabe" class="mt-1 text-xs text-rose-600">{{ form.errors.clabe }}</div>
                            </div>
                        </div>

                        <div class="px-6 py-5 border-t border-slate-200/70 dark:border-white/10 flex items-center justify-end gap-2">
                            <SecondaryButton @click="closeModal">Cancelar</SecondaryButton>
                            <button type="button" class="rounded-2xl px-4 py-2 font-semibold
                            bg-slate-900 text-white hover:bg-slate-800
                            dark:bg-zinc-300 dark:text-neutral-900
                            dark:hover:bg-zinc-200 transition active:scale-[0.98]"
                            :disabled="form.processing" @click="submit">
                                {{ editing ? 'Guardar cambios' : 'Crear proveedor' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
    :global(html),
    :global(body) {
        max-width: 100%;
        overflow-x: hidden;
    }

    :global(html.dark select option) {
        background: #0a0a0a;
        color: #f5f5f5;
    }

    .list-enter-active,
    .list-leave-active {
        transition: all 180ms ease;
    }
    .list-enter-from {
        opacity: 0;
        transform: translateY(6px);
    }
    .list-leave-to {
        opacity: 0;
        transform: translateY(-6px);
    }

    .btn {
        border-radius: 1rem;
        padding: 0.6rem 0.9rem;
        font-size: 0.75rem;
        font-weight: 900;
        transition: transform 150ms ease, filter 150ms ease, box-shadow 150ms ease;
    }
    .btn:hover {
        transform: translateY(-1px);
        filter: brightness(0.99);
    }
    .btn:active {
        transform: scale(0.98);
    }
</style>
