<!--
  Items de una requisición (alta y corrección): agregar, quitar y editar
  cantidad, descripción, precio e IVA, con subtotal/IVA/total por item y
  totales de la requisición. Los montos los recalcula el padre con
  recalcularItems() y el servidor vuelve a calcularlos al guardar.
  Con `readonly` muestra lo mismo sin poder editar.
-->
<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import { money, type ItemCaptura } from '../requisicionItems'

type Opcion = { id: number; nombre: string; detalle?: string | null }

const props = withDefaults(
  defineProps<{
    items: ItemCaptura[]
    subtotal: number
    total: number
    errorFor?: (key: string) => string | null
    readonly?: boolean
    /** Si se pasa, cada item muestra su sucursal (editable salvo en solo lectura). */
    sucursales?: Opcion[] | null
    titulo?: string
    idPrefix?: string
  }>(),
  { errorFor: () => null, readonly: false, sucursales: null, titulo: 'Items de la requisición', idPrefix: 'item' },
)

const emit = defineEmits<{
  (e: 'add'): void
  (e: 'remove', index: number): void
}>()

const inputClass =
  'w-full min-h-[42px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 ' +
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 dark:border-white/10 dark:bg-neutral-900 dark:text-neutral-100 ' +
  'disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-600 dark:disabled:bg-neutral-900/60 dark:disabled:text-neutral-300'

const sucursalNombre = (id: number | string | null) =>
  props.sucursales?.find((s) => String(s.id) === String(id))?.nombre ?? 'Sin sucursal'

const err = (key: string) => props.errorFor(key)
</script>

<template>
  <section
    class="space-y-4 rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900 sm:p-6"
    :aria-labelledby="`${idPrefix}-titulo`"
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="min-w-0">
        <h3 :id="`${idPrefix}-titulo`" class="text-base font-extrabold text-slate-900 dark:text-neutral-100">{{ titulo }}</h3>
        <slot name="subtitle" />
      </div>
      <button
        v-if="!readonly"
        type="button"
        class="inline-flex min-h-[42px] w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 text-sm font-semibold text-white transition
               hover:bg-emerald-700 active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40
               dark:bg-emerald-500 dark:hover:bg-emerald-600 motion-reduce:transform-none sm:w-auto"
        @click="emit('add')"
      >
        <Plus class="h-4 w-4" aria-hidden="true" /> Agregar item
      </button>
    </div>

    <slot name="aviso" />

    <p v-if="err('detalles')" class="text-xs text-rose-600 dark:text-rose-400" role="alert">{{ err('detalles') }}</p>

    <div v-if="items.length > 0" class="space-y-3">
      <div
        v-for="(item, index) in items"
        :key="index"
        class="grid grid-cols-2 gap-3 rounded-2xl border border-slate-200/70 bg-slate-50 p-4 dark:border-white/10 dark:bg-neutral-950/40 sm:grid-cols-6 lg:grid-cols-12"
      >
        <div class="col-span-1 sm:col-span-1 lg:col-span-1">
          <label :for="`${idPrefix}-cant-${index}`" class="block text-[11px] font-semibold text-slate-500 dark:text-neutral-400">Cantidad</label>
          <input :id="`${idPrefix}-cant-${index}`" v-model.number="item.cantidad" type="number" min="0" step="0.01" inputmode="decimal" :disabled="readonly" :class="inputClass" />
          <p v-if="err(`detalles.${index}.cantidad`)" class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">{{ err(`detalles.${index}.cantidad`) }}</p>
        </div>

        <div :class="sucursales ? 'col-span-2 sm:col-span-5 lg:col-span-3' : 'col-span-2 sm:col-span-5 lg:col-span-5'" class="order-first sm:order-none">
          <label :for="`${idPrefix}-desc-${index}`" class="block text-[11px] font-semibold text-slate-500 dark:text-neutral-400">Descripción</label>
          <input :id="`${idPrefix}-desc-${index}`" v-model="item.descripcion" type="text" maxlength="255" :disabled="readonly" :class="inputClass" />
          <p v-if="err(`detalles.${index}.descripcion`)" class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">{{ err(`detalles.${index}.descripcion`) }}</p>
        </div>

        <div v-if="sucursales" class="order-first col-span-2 sm:order-none sm:col-span-6 lg:col-span-2">
          <SearchableSelect
            v-if="!readonly"
            :id="`${idPrefix}-suc-${index}`"
            v-model="item.sucursal_id"
            :options="sucursales"
            label="Sucursal"
            placeholder="Sin sucursal"
            search-placeholder="Buscar sucursal…"
            allow-null
            null-label="Sin sucursal"
            rounded="xl"
            label-key="nombre"
            value-key="id"
            compact
          />
          <template v-else>
            <p class="block text-[11px] font-semibold text-slate-500 dark:text-neutral-400">Sucursal</p>
            <p class="mt-1 flex min-h-[42px] items-center rounded-xl border border-slate-200 bg-slate-100 px-3 text-sm text-slate-600 dark:border-white/10 dark:bg-neutral-900/60 dark:text-neutral-300">
              <span class="truncate">{{ sucursalNombre(item.sucursal_id) }}</span>
            </p>
          </template>
          <p v-if="err(`detalles.${index}.sucursal_id`)" class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">{{ err(`detalles.${index}.sucursal_id`) }}</p>
        </div>

        <div class="col-span-1 sm:col-span-2 lg:col-span-2">
          <label :for="`${idPrefix}-precio-${index}`" class="block text-[11px] font-semibold text-slate-500 dark:text-neutral-400">Precio unitario</label>
          <input :id="`${idPrefix}-precio-${index}`" v-model.number="item.precio_unitario" type="number" min="0" step="0.01" inputmode="decimal" :disabled="readonly" :class="inputClass" />
          <p v-if="err(`detalles.${index}.precio_unitario`)" class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">{{ err(`detalles.${index}.precio_unitario`) }}</p>
        </div>

        <div class="col-span-2 flex items-end sm:col-span-1 lg:col-span-1">
          <label class="flex min-h-[42px] items-center gap-2 text-xs font-semibold text-slate-600 dark:text-neutral-300" :class="readonly ? 'opacity-70' : ''">
            <input v-model="item.genera_iva" type="checkbox" :disabled="readonly" class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
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
            v-if="!readonly"
            type="button"
            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-rose-600 transition hover:bg-rose-50
                   focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400/40 dark:hover:bg-rose-500/10"
            :aria-label="`Eliminar item ${index + 1}`"
            @click="emit('remove', index)"
          >
            <Trash2 class="h-4 w-4" aria-hidden="true" />
          </button>
        </div>
      </div>
    </div>

    <p v-else class="rounded-2xl border border-dashed border-slate-200 py-8 text-center text-sm text-slate-500 dark:border-white/10 dark:text-neutral-400">
      {{ readonly ? 'Esta requisición no tiene items.' : 'Agrega items para comenzar.' }}
    </p>

    <div class="ml-auto w-full max-w-xs space-y-1 text-sm">
      <div class="flex justify-between text-slate-600 dark:text-neutral-300"><span>Subtotal</span><span class="font-semibold tabular-nums">{{ money(subtotal) }}</span></div>
      <slot name="totales-extra" />
      <div class="flex justify-between border-t border-slate-200 pt-1 text-base font-extrabold text-slate-900 dark:border-white/10 dark:text-neutral-100"><span>Total</span><span class="tabular-nums">{{ money(total) }}</span></div>
    </div>
  </section>
</template>
