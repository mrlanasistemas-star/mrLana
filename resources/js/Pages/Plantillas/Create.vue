<script setup lang="ts">
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import DatePickerShadcn from '@/Components/ui/DatePickerShadcn.vue'
import { usePlantillaCreate } from './usePlantillaCreate'
import type { Catalogos } from '../Requisiciones/Requisiciones.types'
import {
    Plus,
    Trash2,
    FileText,
    Building2,
    Users,
    Tags,
    Truck,
    FilePlus2,
} from 'lucide-vue-next'

const page      = usePage<any>()
const catalogos = (page.props as any)?.catalogos as Catalogos
const plantilla = (page.props as any)?.plantilla ?? null

const {
    state,
    items,
    corporativosActive,
    sucursalesFiltered,
    empleadosActive,
    conceptosActive,
    proveedoresList,
    addItem,
    removeItem,
    save,
    update,
    money,
    solicitanteFijo,
    saving,
    fieldError,
} = usePlantillaCreate(catalogos, plantilla)

const isEdit = computed(() => !!plantilla)
</script>

<template>
    <Head :title="isEdit ? 'Editar plantilla' : 'Nueva plantilla'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="min-w-0">
                <h2 class="text-xl font-black text-slate-900 dark:text-zinc-100 truncate">
                    {{ isEdit ? 'Editar plantilla' : 'Nueva plantilla' }}
                </h2>
                <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                    {{ isEdit ? 'Modifica los datos de la plantilla' : 'Crea una nueva plantilla reutilizable' }}
                </p>
            </div>
        </template>

        <div class="erp-page">
            <form class="space-y-6" @submit.prevent="isEdit ? update(plantilla.id) : save()">

                <!-- ── Sección 1: Información general ── -->
                <div class="erp-form-section">
                    <div class="erp-form-section-header">
                        <div class="erp-form-section-icon">
                            <FileText class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="erp-form-section-title">Información general</div>
                            <div class="erp-form-section-desc">Nombre y estado de la plantilla</div>
                        </div>
                    </div>
                    <div class="erp-form-section-body">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="erp-label">Nombre *</label>
                                <input
                                    v-model="state.nombre"
                                    type="text"
                                    placeholder="Ej. Insumos de papelería"
                                    class="erp-input"
                                />
                                <p v-if="fieldError('nombre')" class="erp-error">
                                    {{ fieldError('nombre') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Sección 2: Corporativo y Sucursal ── -->
                <div class="erp-form-section">
                    <div class="erp-form-section-header">
                        <div class="erp-form-section-icon">
                            <Building2 class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="erp-form-section-title">Corporativo y Sucursal</div>
                            <div class="erp-form-section-desc">Asignación de unidad de negocio</div>
                        </div>
                    </div>
                    <div class="erp-form-section-body">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <SearchableSelect
                                    v-model="state.corporativo_id"
                                    :options="corporativosActive"
                                    label="Corporativo"
                                    placeholder="Seleccione..."
                                    searchPlaceholder="Buscar corporativo..."
                                    :allowNull="true"
                                    nullLabel="—"
                                    rounded="2xl"
                                    labelKey="nombre"
                                    valueKey="id"
                                    :button-class="solicitanteFijo ? 'pointer-events-none cursor-not-allowed opacity-50' : ''"
                                />
                                <p v-if="fieldError('comprador_corp_id')" class="erp-error">
                                    {{ fieldError('comprador_corp_id') }}
                                </p>
                            </div>

                            <div>
                                <SearchableSelect
                                    v-model="state.sucursal_id"
                                    :options="sucursalesFiltered"
                                    label="Sucursal"
                                    placeholder="Seleccione..."
                                    searchPlaceholder="Buscar sucursal..."
                                    :allowNull="true"
                                    nullLabel="—"
                                    rounded="2xl"
                                    labelKey="nombre"
                                    valueKey="id"
                                    :button-class="solicitanteFijo ? 'pointer-events-none cursor-not-allowed opacity-50' : ''"
                                />
                                <p v-if="fieldError('sucursal_id')" class="erp-error">
                                    {{ fieldError('sucursal_id') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Sección 3: Solicitante, Concepto y Proveedor ── -->
                <div class="erp-form-section">
                    <div class="erp-form-section-header">
                        <div class="erp-form-section-icon">
                            <Users class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="erp-form-section-title">Solicitante, Concepto y Proveedor</div>
                            <div class="erp-form-section-desc">Personas y categorías relacionadas a la plantilla</div>
                        </div>
                    </div>
                    <div class="erp-form-section-body">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <SearchableSelect
                                    v-model="state.solicitante_id"
                                    :options="empleadosActive"
                                    label="Solicitante"
                                    placeholder="Seleccione..."
                                    searchPlaceholder="Buscar solicitante..."
                                    :allowNull="true"
                                    nullLabel="—"
                                    rounded="2xl"
                                    labelKey="nombre"
                                    valueKey="id"
                                    :button-class="solicitanteFijo ? 'pointer-events-none cursor-not-allowed opacity-50' : ''"
                                />
                                <p v-if="solicitanteFijo" class="erp-error" style="color: var(--erp-muted);">
                                    Para colaboradores, el solicitante se asigna automáticamente.
                                </p>
                            </div>

                            <div>
                                <SearchableSelect
                                    v-model="state.concepto_id"
                                    :options="conceptosActive"
                                    label="Concepto"
                                    placeholder="Seleccione..."
                                    searchPlaceholder="Buscar concepto..."
                                    :allowNull="true"
                                    nullLabel="—"
                                    rounded="2xl"
                                    labelKey="nombre"
                                    valueKey="id"
                                />
                                <p v-if="fieldError('concepto_id')" class="erp-error">
                                    {{ fieldError('concepto_id') }}
                                </p>
                            </div>

                            <div>
                                <SearchableSelect
                                    v-model="state.proveedor_id"
                                    :options="proveedoresList"
                                    label="Proveedor"
                                    placeholder="Seleccione..."
                                    searchPlaceholder="Buscar proveedor..."
                                    :allowNull="true"
                                    nullLabel="—"
                                    rounded="2xl"
                                    labelKey="nombre"
                                    valueKey="id"
                                />
                                <p v-if="fieldError('proveedor_id')" class="erp-error">
                                    {{ fieldError('proveedor_id') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Sección 4: Fecha y Observaciones ── -->
                <div class="erp-form-section">
                    <div class="erp-form-section-header">
                        <div class="erp-form-section-icon">
                            <FileText class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="erp-form-section-title">Fecha y Observaciones</div>
                            <div class="erp-form-section-desc">Información adicional de la plantilla</div>
                        </div>
                    </div>
                    <div class="erp-form-section-body">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <DatePickerShadcn
                                    v-model="state.fecha_solicitud"
                                    label="Fecha esperada de entrega"
                                    placeholder="Selecciona fecha"
                                />
                                <p v-if="fieldError('fecha_solicitud')" class="erp-error">
                                    {{ fieldError('fecha_solicitud') }}
                                </p>
                            </div>

                            <div>
                                <DatePickerShadcn
                                    v-model="state.fecha_pago_esperada"
                                    label="Fecha esperada de pago (opcional)"
                                    placeholder="Sin definir"
                                    :min-value="state.fecha_solicitud || null"
                                    clearable
                                />
                                <p v-if="fieldError('fecha_pago_esperada')" class="erp-error">
                                    {{ fieldError('fecha_pago_esperada') }}
                                </p>
                            </div>

                            <div>
                                <label class="erp-label">Observaciones</label>
                                <input
                                    v-model="state.observaciones"
                                    type="text"
                                    placeholder="Opcional"
                                    class="erp-input"
                                />
                                <p v-if="fieldError('observaciones')" class="erp-error">
                                    {{ fieldError('observaciones') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Sección 5: Partidas ── -->
                <div class="erp-form-section">
                    <div class="erp-form-section-header">
                        <div class="erp-form-section-icon">
                            <FilePlus2 class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="erp-form-section-title">Partidas</div>
                            <div class="erp-form-section-desc">Artículos o servicios que componen la plantilla</div>
                        </div>
                        <button
                            type="button"
                            @click="addItem"
                            class="erp-button erp-button-primary ml-auto"
                        >
                            <Plus class="h-4 w-4" />
                            Agregar partida
                        </button>
                    </div>

                    <div class="erp-form-section-body space-y-3">
                        <!-- Empty state de partidas -->
                        <div v-if="items.length === 0" class="erp-empty-state py-8">
                            <div class="erp-empty-state-icon">
                                <FilePlus2 class="h-6 w-6" />
                            </div>
                            <p class="erp-empty-state-title">Sin partidas</p>
                            <p class="erp-empty-state-desc">
                                Agrega partidas para comenzar a construir la plantilla.
                            </p>
                        </div>

                        <!-- Lista de partidas -->
                        <div
                            v-for="(item, index) in items"
                            :key="index"
                            class="erp-card p-4 space-y-3"
                        >
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <!-- Descripción -->
                                <div class="col-span-2 sm:col-span-2">
                                    <label class="erp-label">Descripción</label>
                                    <input
                                        v-model="item.descripcion"
                                        type="text"
                                        placeholder="Ej. Hojas tamaño carta"
                                        class="erp-input"
                                    />
                                    <p v-if="fieldError(`detalles.${index}.descripcion`)" class="erp-error">
                                        {{ fieldError(`detalles.${index}.descripcion`) }}
                                    </p>
                                </div>

                                <!-- Cantidad -->
                                <div>
                                    <label class="erp-label">Cantidad</label>
                                    <input
                                        v-model.number="item.cantidad"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="erp-input"
                                    />
                                    <p v-if="fieldError(`detalles.${index}.cantidad`)" class="erp-error">
                                        {{ fieldError(`detalles.${index}.cantidad`) }}
                                    </p>
                                </div>

                                <!-- Precio unitario -->
                                <div>
                                    <label class="erp-label">Precio unitario</label>
                                    <input
                                        v-model.number="item.precio_unitario"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="erp-input"
                                    />
                                </div>

                                <!-- ¿Genera IVA? -->
                                <div class="col-span-2 sm:col-span-2 flex items-end">
                                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                        <input
                                            v-model="item.genera_iva"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                        />
                                        <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">
                                            {{ item.genera_iva ? 'Genera IVA (16%)' : 'Sin IVA' }}
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <!-- Total de la partida + botón quitar -->
                            <div class="flex justify-between items-center pt-2 border-t border-slate-100 dark:border-white/[0.06]">
                                <div class="text-sm text-slate-500 dark:text-zinc-400 space-x-4">
                                    <span>
                                        Subtotal:
                                        <strong class="text-slate-700 dark:text-zinc-200">{{ money(item.subtotal) }}</strong>
                                    </span>
                                    <span>
                                        IVA:
                                        <strong class="text-slate-700 dark:text-zinc-200">{{ money(item.iva) }}</strong>
                                    </span>
                                    <span>
                                        Total:
                                        <strong class="text-slate-900 dark:text-zinc-100">{{ money(item.total) }}</strong>
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    @click="removeItem(index)"
                                    class="erp-icon-button text-rose-500 border-rose-200 dark:border-rose-500/25 hover:bg-rose-50 dark:hover:bg-rose-500/10"
                                    aria-label="Quitar partida"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Error global de detalles -->
                        <p v-if="fieldError('detalles')" class="erp-error">
                            {{ fieldError('detalles') }}
                        </p>
                    </div>
                </div>

                <!-- ── Panel de resumen sticky ── -->
                <div class="erp-panel p-5 sticky bottom-4 z-10 border-t-2 border-slate-200 dark:border-white/10">
                    <div class="flex justify-between items-center flex-wrap gap-4">
                        <div class="flex flex-wrap gap-6 text-sm text-slate-600 dark:text-zinc-300">
                            <span>
                                Subtotal:
                                <strong class="text-slate-900 dark:text-zinc-100">{{ money(state.monto_subtotal) }}</strong>
                            </span>
                            <span>
                                IVA:
                                <strong class="text-slate-900 dark:text-zinc-100">
                                    {{ money(state.monto_total - state.monto_subtotal) }}
                                </strong>
                            </span>
                            <span class="text-lg font-black text-slate-900 dark:text-zinc-100">
                                Total: {{ money(state.monto_total) }}
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="erp-button erp-button-secondary h-10"
                                @click="$inertia.visit(route('plantillas.index'))"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="saving"
                                class="erp-button erp-button-primary h-11 px-5"
                            >
                                {{ saving ? 'Guardando...' : (isEdit ? 'Actualizar plantilla' : 'Guardar plantilla') }}
                            </button>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </AuthenticatedLayout>
</template>
