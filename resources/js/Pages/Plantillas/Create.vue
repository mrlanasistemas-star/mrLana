<script setup lang="ts">
    import { computed } from 'vue'
    import { Head, usePage } from '@inertiajs/vue3'
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
    import SecondaryButton from '@/Components/SecondaryButton.vue'
    import DatePickerShadcn from '@/Components/ui/DatePickerShadcn.vue'
    import { usePlantillaCreate } from './usePlantillaCreate'
    import type { Catalogos } from '../Requisiciones/Requisiciones.types'

    const page = usePage<any>()
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
        role,
        saving,
        showError,
        fieldError,
    } = usePlantillaCreate(catalogos, plantilla)

    const isEdit = computed(() => !!plantilla)
</script>

<template>
    <Head :title="isEdit ? 'Editar plantilla' : 'Nueva plantilla'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-slate-900 dark:text-zinc-100">
                {{ isEdit ? 'Editar plantilla' : 'Nueva plantilla' }}
            </h2>
        </template>

        <div class="w-full max-w-full min-w-0 px-3 sm:px-6 lg:px-8 py-4 sm:py-6">
            <form class="space-y-6"
            @submit.prevent="isEdit ? update(plantilla.id) : save()">
                <!-- Datos generales -->
                <div class="rounded-3xl border border-slate-200/70
                dark:border-white/10 bg-white dark:bg-neutral-900
                shadow-sm p-5 sm:p-6 space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-neutral-100">Datos generales</h3>

                        <div v-if="saving"
                        class="text-xs font-semibold text-slate-500
                        dark:text-neutral-400">
                            Guardando...
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">
                                Nombre de la plantilla
                            </label>
                            <input v-model="state.nombre" type="text"
                            class="mt-1 w-full rounded-2xl px-3 py-2 text-sm
                            border bg-white border-slate-200
                            focus:outline-none focus:ring-2
                            focus:ring-emerald-500/20
                            dark:border-white/10 dark:bg-neutral-950/40
                            dark:text-neutral-100 transition"
                            placeholder="Ej. Insumos de papelería"/>
                            <p v-if="fieldError('nombre')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                                {{ fieldError('nombre') }}
                            </p>
                        </div>

                        <div>
                            <SearchableSelect v-model="state.corporativo_id"
                            :options="corporativosActive" label="Corporativo"
                            placeholder="Seleccione..."
                            searchPlaceholder="Buscar corporativo..."
                            :allowNull="true" nullLabel="—" rounded="2xl"
                            labelKey="nombre" valueKey="id"/>
                            <p v-if="fieldError('comprador_corp_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                                {{ fieldError('comprador_corp_id') }}
                            </p>
                        </div>

                        <div>
                            <SearchableSelect v-model="state.sucursal_id"
                            :options="sucursalesFiltered" label="Sucursal"
                            placeholder="Seleccione..."
                            searchPlaceholder="Buscar sucursal..."
                            :allowNull="true" nullLabel="—" rounded="2xl"
                            labelKey="nombre" valueKey="id"/>
                            <p v-if="fieldError('sucursal_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                                {{ fieldError('sucursal_id') }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <SearchableSelect v-model="state.solicitante_id"
                            :options="empleadosActive" label="Solicitante"
                            placeholder="Seleccione..."
                            searchPlaceholder="Buscar solicitante..."
                            :allowNull="true" nullLabel="—" rounded="2xl"
                            labelKey="nombre" valueKey="id"
                            :disabled="role === 'COLABORADOR'"/>
                            <p v-if="fieldError('solicitante_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                                {{ fieldError('solicitante_id') }}
                            </p>
                            <p v-if="role === 'COLABORADOR'" class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">
                                Para colaboradores, el solicitante se asigna automáticamente.
                            </p>
                        </div>

                        <div>
                            <SearchableSelect v-model="state.concepto_id"
                            :options="conceptosActive" label="Concepto"
                            placeholder="Seleccione..."
                            searchPlaceholder="Buscar concepto..."
                            :allowNull="true" nullLabel="—" rounded="2xl"
                            labelKey="nombre" valueKey="id"/>
                            <p v-if="fieldError('concepto_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                                {{ fieldError('concepto_id') }}
                            </p>
                        </div>

                        <div>
                            <SearchableSelect v-model="state.proveedor_id"
                            :options="proveedoresList" label="Proveedor"
                            placeholder="Seleccione..."
                            searchPlaceholder="Buscar proveedor..."
                            :allowNull="true" nullLabel="—" rounded="2xl"
                            labelKey="nombre" valueKey="id"/>
                            <p v-if="fieldError('proveedor_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                                {{ fieldError('proveedor_id') }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <DatePickerShadcn v-model="state.fecha_solicitud"
                        label="Fecha esperada de entrega"
                        placeholder="Selecciona fecha"/>
                        <p v-if="fieldError('fecha_solicitud')" class="mt-1 text-xs text-rose-600 dark:text-rose-400 sm:col-span-3">
                            {{ fieldError('fecha_solicitud') }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Observaciones</label>
                        <input v-model="state.observaciones" type="text"
                        class="mt-1 w-full rounded-2xl px-3 py-2 text-sm
                        border bg-white border-slate-200
                        focus:outline-none focus:ring-2 focus:ring-emerald-500/20
                        dark:border-white/10 dark:bg-neutral-950/40
                        dark:text-neutral-100 transition"
                        placeholder="Opcional"/>
                        <p v-if="fieldError('observaciones')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                            {{ fieldError('observaciones') }}
                        </p>
                    </div>
                </div>

                <!-- Items -->
                <div class="rounded-3xl border border-slate-200/70
                dark:border-white/10 bg-white dark:bg-neutral-900
                shadow-sm p-5 sm:p-6 space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-neutral-100">Items de la plantilla</h3>

                        <button type="button" @click="addItem"
                        class="rounded-2xl px-4 py-2 text-sm font-semibold
                        bg-emerald-600 text-white hover:bg-emerald-700
                        dark:bg-emerald-500 dark:hover:bg-emerald-600
                        transition active:scale-[0.99]">
                            Agregar item
                        </button>
                    </div>

                    <div v-if="items.length > 0" class="space-y-3">
                        <div v-for="(item, index) in items" :key="index"
                        class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50
                        dark:bg-neutral-950/40 p-4 grid grid-cols-1
                        sm:grid-cols-12 gap-3 transition">
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold
                                text-slate-500 dark:text-neutral-400">
                                    Cantidad
                                </label>
                                <input v-model.number="item.cantidad"
                                type="number" min="0" step="0.01"
                                class="w-full rounded-xl px-3 py-2 text-sm
                                border border-slate-200 bg-white
                                focus:outline-none focus:ring-2
                                focus:ring-emerald-500/20
                                dark:border-white/10 dark:bg-neutral-900
                                dark:text-neutral-100 transition"/>
                            </div>

                            <div class="sm:col-span-4">
                                <label class="block text-[11px] font-semibold text-slate-500 dark:text-neutral-400">
                                    Descripción
                                </label>
                                <input v-model="item.descripcion" type="text"
                                class="w-full rounded-xl px-3 py-2 text-sm
                                border border-slate-200 bg-white
                                focus:outline-none focus:ring-2
                                focus:ring-emerald-500/20
                                dark:border-white/10 dark:bg-neutral-900
                                dark:text-neutral-100 transition"
                                placeholder="Ej. Hojas tamaño carta"/>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-slate-500 dark:text-neutral-400">
                                    Precio unitario
                                </label>
                                <input v-model.number="item.precio_unitario"
                                type="number" min="0" step="0.01"
                                class="w-full rounded-xl px-3 py-2 text-sm
                                border border-slate-200 bg-white
                                focus:outline-none focus:ring-2
                                focus:ring-emerald-500/20
                                dark:border-white/10 dark:bg-neutral-900
                                dark:text-neutral-100 transition"/>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-slate-500 dark:text-neutral-400">
                                    ¿Genera IVA?
                                </label>
                                <label class="mt-1 inline-flex items-center
                                gap-2 rounded-xl border border-slate-200
                                bg-white px-3 py-2 dark:border-white/10
                                dark:bg-neutral-900 transition
                                hover:bg-slate-50 dark:hover:bg-white/5">
                                    <input v-model="item.genera_iva"
                                    type="checkbox" class="h-4 w-4 rounded
                                    border-slate-300 text-emerald-600
                                    focus:ring-emerald-500"/>
                                    <span class="text-sm font-semibold text-slate-700 dark:text-neutral-200">
                                        {{ item.genera_iva ? 'Sí' : 'No' }}
                                    </span>
                                </label>
                            </div>

                            <div class="sm:col-span-2 flex items-center justify-between sm:justify-end gap-3">
                                <div class="text-right">
                                    <div class="text-[11px] font-semibold text-slate-500 dark:text-neutral-400">
                                        Total
                                    </div>
                                    <div class="text-sm font-extrabold text-slate-900 dark:text-neutral-100">
                                        {{ money(item.total) }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-neutral-400">
                                        Sub: {{ money(item.subtotal) }} · IVA: {{ money(item.iva) }}
                                    </div>
                                </div>

                                <button type="button"
                                @click="removeItem(index)"
                                class="rounded-full p-2 text-rose-600
                                hover:bg-rose-50 dark:hover:bg-rose-500/10
                                transition" aria-label="Quitar item">
                                    ✕
                                </button>
                            </div>

                            <div v-if="fieldError(`detalles.${index}.cantidad`) || fieldError(`detalles.${index}.descripcion`)"
                            class="sm:col-span-12">
                                <p v-if="fieldError(`detalles.${index}.cantidad`)" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                                    {{ fieldError(`detalles.${index}.cantidad`) }}
                                </p>
                                <p v-if="fieldError(`detalles.${index}.descripcion`)" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                                    {{ fieldError(`detalles.${index}.descripcion`) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center text-sm text-slate-500 dark:text-neutral-400">
                        Agrega items para comenzar
                    </div>

                    <p v-if="fieldError('detalles')" class="text-xs text-rose-600 dark:text-rose-400">
                        {{ fieldError('detalles') }}
                    </p>

                    <div class="text-right mt-4">
                        <div class="text-sm text-slate-600 dark:text-neutral-300">
                            Subtotal: <span class="font-bold">{{ money(state.monto_subtotal) }}</span>
                        </div>
                        <div class="text-sm text-slate-600 dark:text-neutral-300">
                            Total: <span class="font-bold">{{ money(state.monto_total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="flex items-center justify-end gap-3">
                    <SecondaryButton type="button"
                    @click="$inertia.visit(route('plantillas.index'))"
                    class="rounded-2xl">
                        Cancelar
                    </SecondaryButton>

                    <button type="submit" :disabled="saving"
                    class="rounded-2xl px-4 py-3 text-sm font-extrabold
                    bg-emerald-600 text-white hover:bg-emerald-700
                    dark:bg-emerald-500 dark:hover:bg-emerald-600
                    transition active:scale-[0.99] disabled:opacity-60
                    disabled:cursor-not-allowed">
                        {{ saving ? 'Guardando...' : (isEdit ? 'Actualizar' : 'Guardar') }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
