<!-- resources/js/Pages/Requisiciones/Create.vue -->
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { Loader2, Plus, Save, Send, Trash2 } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import DatePickerShadcn from '@/Components/ui/DatePickerShadcn.vue'
import { usePermissions } from '@/Composables/usePermissions'

import { useRequisicionCreate } from './useRequisicionCreate'
import type { Catalogos } from './Requisiciones.types'

const props = defineProps<{
  catalogos: Catalogos
  plantilla?: Record<string, any> | null
  today: string
}>()

const { can } = usePermissions()

const {
  state,
  items,
  corporativosActive,
  sucursalesFiltered,
  empleadosActive,
  conceptosActive,
  proveedoresList,
  selectedProveedor,
  minFechaEsperada,
  addItem,
  removeItem,
  saveDraft,
  sendRequi,
  money,
  solicitanteFijo,
  saving,
  errorFor,
} = useRequisicionCreate(props.catalogos, props.plantilla ?? null, props.today)

const lockedClass = 'pointer-events-none cursor-not-allowed opacity-60'
const inputClass =
  'w-full min-h-[42px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 ' +
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 dark:border-white/10 dark:bg-neutral-900 dark:text-neutral-100'
</script>

<template>
  <Head title="Nueva requisición" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <span>Nueva requisición</span>
        <span v-if="saving" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 dark:text-neutral-400">
          <Loader2 class="h-3.5 w-3.5 animate-spin" aria-hidden="true" /> Procesando…
        </span>
      </div>
    </template>

    <div class="w-full min-w-0 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
      <form class="space-y-6" novalidate @submit.prevent>
        <section class="space-y-4 rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900 sm:p-6" aria-labelledby="datos-generales">
          <h3 id="datos-generales" class="text-base font-extrabold text-slate-900 dark:text-neutral-100">Datos generales</h3>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
              <SearchableSelect
                id="comprador"
                v-model="state.corporativo_id"
                :options="corporativosActive"
                label="Comprador"
                placeholder="Seleccione…"
                search-placeholder="Buscar corporativo…"
                :allow-null="false"
                rounded="2xl"
                label-key="nombre"
                value-key="id"
                :button-class="solicitanteFijo ? lockedClass : ''"
              />
              <p v-if="errorFor('comprador_corp_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400" role="alert">{{ errorFor('comprador_corp_id') }}</p>
            </div>

            <div>
              <SearchableSelect
                id="sucursal"
                v-model="state.sucursal_id"
                :options="sucursalesFiltered"
                label="Sucursal"
                placeholder="Seleccione…"
                search-placeholder="Buscar sucursal…"
                :allow-null="false"
                rounded="2xl"
                label-key="nombre"
                value-key="id"
                :button-class="solicitanteFijo ? lockedClass : ''"
              />
              <p v-if="errorFor('sucursal_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400" role="alert">{{ errorFor('sucursal_id') }}</p>
              <p v-else-if="!state.corporativo_id" class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">
                Primero elige un corporativo para ver sus sucursales.
              </p>
            </div>

            <div>
              <SearchableSelect
                id="solicitante"
                v-model="state.solicitante_id"
                :options="empleadosActive"
                label="Solicitante"
                placeholder="Seleccione…"
                search-placeholder="Buscar solicitante…"
                :allow-null="false"
                rounded="2xl"
                label-key="nombre"
                value-key="id"
                :button-class="solicitanteFijo ? lockedClass : ''"
              />
              <p v-if="errorFor('solicitante_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400" role="alert">{{ errorFor('solicitante_id') }}</p>
              <p v-else-if="solicitanteFijo" class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">
                El solicitante es el colaborador vinculado a tu cuenta.
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
              <SearchableSelect
                id="concepto"
                v-model="state.concepto_id"
                :options="conceptosActive"
                label="Concepto"
                placeholder="Seleccione…"
                search-placeholder="Buscar concepto…"
                :allow-null="false"
                rounded="2xl"
                label-key="nombre"
                value-key="id"
              />
              <p v-if="errorFor('concepto_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400" role="alert">{{ errorFor('concepto_id') }}</p>
            </div>

            <div>
              <SearchableSelect
                id="proveedor"
                v-model="state.proveedor_id"
                :options="proveedoresList"
                label="Proveedor *"
                placeholder="Selecciona un proveedor activo…"
                search-placeholder="Buscar proveedor…"
                :allow-null="false"
                rounded="2xl"
                label-key="razon_social"
                secondary-key="rfc"
                value-key="id"
                :error="errorFor('proveedor_id')"
              />
              <p v-if="errorFor('proveedor_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400" role="alert">{{ errorFor('proveedor_id') }}</p>
              <p v-else-if="proveedoresList.length === 0" class="mt-1 text-[11px] text-amber-700 dark:text-amber-300">
                No tienes proveedores activos.
                <Link v-if="can('proveedores.registrar')" :href="route('proveedores.index')" class="font-semibold underline underline-offset-2">Registrar proveedor</Link>
              </p>
            </div>

            <div>
              <DatePickerShadcn
                id="fecha-solicitud"
                v-model="state.fecha_solicitud"
                label="Fecha de solicitud"
                placeholder="Selecciona fecha"
                :min-value="today"
                :invalid="!!errorFor('fecha_solicitud')"
              />
              <p v-if="errorFor('fecha_solicitud')" class="mt-1 text-xs text-rose-600 dark:text-rose-400" role="alert">{{ errorFor('fecha_solicitud') }}</p>
              <p v-else class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">Hoy o una fecha futura.</p>
            </div>

            <div>
              <DatePickerShadcn
                id="fecha-pago-esperada"
                v-model="state.fecha_pago_esperada"
                label="Fecha esperada de pago (opcional)"
                placeholder="Sin definir"
                :min-value="minFechaEsperada"
                clearable
                :invalid="!!errorFor('fecha_pago_esperada')"
              />
              <p v-if="errorFor('fecha_pago_esperada')" class="mt-1 text-xs text-rose-600 dark:text-rose-400" role="alert">{{ errorFor('fecha_pago_esperada') }}</p>
              <p v-else class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">Cuándo necesitas el pago. La autorización real la registra Contabilidad.</p>
            </div>
          </div>

          <div>
            <label for="observaciones" class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Observaciones</label>
            <textarea id="observaciones" v-model="state.observaciones" rows="2" maxlength="5000" :class="[inputClass, 'mt-1 rounded-2xl']" />
          </div>

          <div v-if="selectedProveedor" class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4 dark:border-white/10 dark:bg-neutral-950/40">
            <p class="text-sm font-extrabold text-slate-900 dark:text-neutral-100">Datos del proveedor</p>
            <dl class="mt-2 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
              <div class="min-w-0"><dt class="inline text-slate-500 dark:text-neutral-400">Razón social: </dt><dd class="inline break-words font-semibold">{{ selectedProveedor.razon_social }}</dd></div>
              <div class="min-w-0"><dt class="inline text-slate-500 dark:text-neutral-400">RFC: </dt><dd class="inline font-semibold">{{ selectedProveedor.rfc || '—' }}</dd></div>
              <div class="min-w-0"><dt class="inline text-slate-500 dark:text-neutral-400">CLABE: </dt><dd class="inline break-all font-semibold">{{ selectedProveedor.clabe || '—' }}</dd></div>
              <div class="min-w-0"><dt class="inline text-slate-500 dark:text-neutral-400">Banco: </dt><dd class="inline font-semibold">{{ selectedProveedor.banco || '—' }}</dd></div>
            </dl>
          </div>
        </section>

        <section class="space-y-4 rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900 sm:p-6" aria-labelledby="items-titulo">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 id="items-titulo" class="text-base font-extrabold text-slate-900 dark:text-neutral-100">Items de la requisición</h3>
            <button
              type="button"
              class="inline-flex min-h-[42px] items-center gap-2 rounded-2xl bg-emerald-600 px-4 text-sm font-semibold text-white transition
                     hover:bg-emerald-700 active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40
                     dark:bg-emerald-500 dark:hover:bg-emerald-600 motion-reduce:transform-none"
              @click="addItem"
            >
              <Plus class="h-4 w-4" aria-hidden="true" /> Agregar item
            </button>
          </div>

          <p v-if="errorFor('detalles')" class="text-xs text-rose-600 dark:text-rose-400" role="alert">{{ errorFor('detalles') }}</p>

          <div v-if="items.length > 0" class="space-y-3">
            <div
              v-for="(item, index) in items"
              :key="index"
              class="grid grid-cols-2 gap-3 rounded-2xl border border-slate-200/70 bg-slate-50 p-4 dark:border-white/10 dark:bg-neutral-950/40 sm:grid-cols-6 lg:grid-cols-12"
            >
              <div class="col-span-1 sm:col-span-1 lg:col-span-1">
                <label :for="`cant-${index}`" class="block text-[11px] font-semibold text-slate-500 dark:text-neutral-400">Cantidad</label>
                <input :id="`cant-${index}`" v-model.number="item.cantidad" type="number" min="0" step="0.01" inputmode="decimal" :class="inputClass" />
                <p v-if="errorFor(`detalles.${index}.cantidad`)" class="mt-1 text-[11px] text-rose-600">{{ errorFor(`detalles.${index}.cantidad`) }}</p>
              </div>

              <div class="col-span-2 sm:col-span-5 lg:col-span-5">
                <label :for="`desc-${index}`" class="block text-[11px] font-semibold text-slate-500 dark:text-neutral-400">Descripción</label>
                <input :id="`desc-${index}`" v-model="item.descripcion" type="text" maxlength="255" :class="inputClass" />
                <p v-if="errorFor(`detalles.${index}.descripcion`)" class="mt-1 text-[11px] text-rose-600">{{ errorFor(`detalles.${index}.descripcion`) }}</p>
              </div>

              <div class="col-span-1 sm:col-span-2 lg:col-span-2">
                <label :for="`precio-${index}`" class="block text-[11px] font-semibold text-slate-500 dark:text-neutral-400">Precio unitario</label>
                <input :id="`precio-${index}`" v-model.number="item.precio_unitario" type="number" min="0" step="0.01" inputmode="decimal" :class="inputClass" />
              </div>

              <div class="col-span-1 flex items-end sm:col-span-1 lg:col-span-1">
                <label class="flex min-h-[42px] items-center gap-2 text-xs font-semibold text-slate-600 dark:text-neutral-300">
                  <input v-model="item.genera_iva" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                  IVA
                </label>
              </div>

              <div class="col-span-2 flex items-end justify-between gap-3 sm:col-span-3 lg:col-span-3">
                <dl class="grid grid-cols-3 gap-2 text-xs">
                  <div><dt class="text-slate-500 dark:text-neutral-400">Subtotal</dt><dd class="font-semibold tabular-nums">{{ money(item.subtotal) }}</dd></div>
                  <div><dt class="text-slate-500 dark:text-neutral-400">IVA</dt><dd class="font-semibold tabular-nums">{{ money(item.iva) }}</dd></div>
                  <div><dt class="text-slate-500 dark:text-neutral-400">Total</dt><dd class="font-extrabold tabular-nums">{{ money(item.total) }}</dd></div>
                </dl>
                <button
                  type="button"
                  class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-rose-600 transition hover:bg-rose-50
                         focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400/40 dark:hover:bg-rose-500/10"
                  :aria-label="`Eliminar item ${index + 1}`"
                  @click="removeItem(index)"
                >
                  <Trash2 class="h-4 w-4" aria-hidden="true" />
                </button>
              </div>
            </div>
          </div>

          <p v-else class="rounded-2xl border border-dashed border-slate-200 py-8 text-center text-sm text-slate-500 dark:border-white/10 dark:text-neutral-400">
            Agrega items para comenzar.
          </p>

          <div class="ml-auto w-full max-w-xs space-y-1 text-sm">
            <div class="flex justify-between text-slate-600 dark:text-neutral-300"><span>Subtotal</span><span class="font-semibold tabular-nums">{{ money(state.monto_subtotal) }}</span></div>
            <div class="flex justify-between border-t border-slate-200 pt-1 text-base font-extrabold text-slate-900 dark:border-white/10 dark:text-neutral-100"><span>Total</span><span class="tabular-nums">{{ money(state.monto_total) }}</span></div>
          </div>
        </section>

        <div class="flex flex-col-reverse items-stretch justify-end gap-3 sm:flex-row sm:items-center">
          <Link
            :href="route('requisiciones.index')"
            class="inline-flex min-h-[46px] items-center justify-center rounded-2xl border border-slate-200 px-4 text-sm font-semibold text-slate-700
                   transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/50
                   dark:border-white/10 dark:text-neutral-200 dark:hover:bg-white/5"
          >
            Cancelar
          </Link>

          <button
            type="button"
            class="inline-flex min-h-[46px] items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 text-sm font-extrabold text-white transition
                   hover:bg-slate-800 active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/60
                   disabled:opacity-60 dark:bg-neutral-800 dark:hover:bg-neutral-700 motion-reduce:transform-none"
            :disabled="saving"
            @click="saveDraft"
          >
            <Save class="h-4 w-4" aria-hidden="true" /> Guardar como borrador
          </button>

          <button
            type="button"
            class="inline-flex min-h-[46px] items-center justify-center gap-2 rounded-2xl bg-brand-button px-4 text-sm font-extrabold text-brand-button-fg transition
                   hover:bg-brand-button/90 active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-slate-400
                   disabled:opacity-60 motion-reduce:transform-none dark:focus-visible:ring-offset-neutral-900"
            :disabled="saving"
            @click="sendRequi"
          >
            <Loader2 v-if="saving" class="h-4 w-4 animate-spin" aria-hidden="true" />
            <Send v-else class="h-4 w-4" aria-hidden="true" />
            Enviar requisición
          </button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
