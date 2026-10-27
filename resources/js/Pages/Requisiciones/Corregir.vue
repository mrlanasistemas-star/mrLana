<script setup lang="ts">
import { computed, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Loader2, PencilLine, Save } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'

type Id = number
type Opcion = { id: Id; nombre: string; detalle?: string | null }

const props = defineProps<{
  requisicion: {
    id: Id
    folio: string
    status: string
    monto_total: number
    comprador_corp_id: Id
    sucursal_id: Id
    solicitante_id: Id
    concepto_id: Id
    proveedor_id: Id | null
    observaciones: string | null
    items_total: number
    items_misma_sucursal: number
  }
  catalogos: {
    corporativos: Opcion[]
    sucursales: (Opcion & { corporativo_id: Id })[]
    empleados: (Opcion & { sucursal_id: Id | null })[]
    conceptos: Opcion[]
    proveedores: Opcion[]
  }
}>()

const r = props.requisicion

const form = useForm({
  comprador_corp_id: r.comprador_corp_id as Id | null,
  sucursal_id: r.sucursal_id as Id | null,
  solicitante_id: r.solicitante_id as Id | null,
  concepto_id: r.concepto_id as Id | null,
  proveedor_id: r.proveedor_id as Id | null,
  observaciones: r.observaciones ?? '',
  actualizar_items_sucursal: true,
  motivo: '',
})

/** Sucursales del corporativo elegido (todas, activas o no). */
const sucursalesDelCorp = computed(() =>
  props.catalogos.sucursales.filter((s) => String(s.corporativo_id) === String(form.comprador_corp_id)),
)

// Cascada: al cambiar de corporativo, la sucursal debe pertenecer al nuevo.
watch(() => form.comprador_corp_id, () => {
  if (!sucursalesDelCorp.value.some((s) => String(s.id) === String(form.sucursal_id))) {
    form.sucursal_id = sucursalesDelCorp.value.length === 1 ? sucursalesDelCorp.value[0].id : null
  }
})

const original = {
  comprador_corp_id: r.comprador_corp_id,
  sucursal_id: r.sucursal_id,
  solicitante_id: r.solicitante_id,
  concepto_id: r.concepto_id,
  proveedor_id: r.proveedor_id,
  observaciones: r.observaciones ?? '',
}
const changed = (k: keyof typeof original) => String(form[k] ?? '') !== String(original[k] ?? '')
const anyChange = computed(() => (Object.keys(original) as (keyof typeof original)[]).some(changed))
const sucursalCambia = computed(() => changed('sucursal_id'))

const nombreDe = (list: Opcion[], id: Id | null) => list.find((o) => String(o.id) === String(id))?.nombre ?? '—'

const submit = () => {
  form.patch(route('requisiciones.corregir.update', r.id), { preserveScroll: true })
}

const inputClass =
  'w-full min-h-[42px] rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 ' +
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 dark:border-white/10 dark:bg-neutral-900 dark:text-neutral-100'
const errorClass = 'mt-1 text-xs text-rose-600 dark:text-rose-400'
const antesClass = 'mt-1 text-[11px] text-amber-700 dark:text-amber-300'
</script>

<template>
  <Head :title="`Corregir ${r.folio}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-2">
        <PencilLine class="h-5 w-5" aria-hidden="true" />
        <span>Corregir datos de {{ r.folio }}</span>
      </div>
    </template>

    <div class="w-full min-w-0 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
      <form class="mx-auto max-w-5xl space-y-6" novalidate @submit.prevent="submit">
        <section class="space-y-4 rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900 sm:p-6" aria-labelledby="datos-corregir">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <h3 id="datos-corregir" class="text-base font-extrabold text-slate-900 dark:text-neutral-100">Datos de la requisición</h3>
              <p class="mt-1 text-sm text-slate-600 dark:text-neutral-400">
                Cada lista muestra todos los registros del catálogo, incluso inactivos o de otras sucursales. Escribe para filtrar.
                No cambia el folio, el estatus, los montos ni los items.
              </p>
            </div>
            <Link
              :href="route('requisiciones.show', r.id)"
              class="inline-flex min-h-[40px] items-center gap-2 rounded-2xl px-3 py-2 text-sm font-bold ring-1 ring-black/5 bg-white text-slate-800 hover:bg-slate-50 dark:ring-white/10 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
            >
              <ArrowLeft class="h-4 w-4" aria-hidden="true" /> Volver al detalle
            </Link>
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <SearchableSelect
                id="corregir-corporativo"
                v-model="form.comprador_corp_id"
                :options="catalogos.corporativos"
                label="Corporativo comprador"
                placeholder="Seleccione…"
                search-placeholder="Buscar corporativo…"
                rounded="2xl"
                label-key="nombre"
                secondary-key="detalle"
                value-key="id"
              />
              <p v-if="changed('comprador_corp_id')" :class="antesClass">Antes: {{ nombreDe(catalogos.corporativos, original.comprador_corp_id) }}</p>
              <p v-if="form.errors.comprador_corp_id" :class="errorClass" role="alert">{{ form.errors.comprador_corp_id }}</p>
            </div>

            <div>
              <SearchableSelect
                id="corregir-sucursal"
                v-model="form.sucursal_id"
                :options="sucursalesDelCorp"
                label="Sucursal"
                placeholder="Seleccione…"
                search-placeholder="Buscar sucursal…"
                rounded="2xl"
                label-key="nombre"
                secondary-key="detalle"
                value-key="id"
              />
              <p v-if="changed('sucursal_id')" :class="antesClass">Antes: {{ nombreDe(catalogos.sucursales, original.sucursal_id) }}</p>
              <p v-if="form.errors.sucursal_id" :class="errorClass" role="alert">{{ form.errors.sucursal_id }}</p>
            </div>

            <div>
              <SearchableSelect
                id="corregir-solicitante"
                v-model="form.solicitante_id"
                :options="catalogos.empleados"
                label="Solicitante"
                placeholder="Seleccione…"
                search-placeholder="Buscar colaborador…"
                rounded="2xl"
                label-key="nombre"
                secondary-key="detalle"
                value-key="id"
              />
              <p v-if="changed('solicitante_id')" :class="antesClass">Antes: {{ nombreDe(catalogos.empleados, original.solicitante_id) }}</p>
              <p v-if="form.errors.solicitante_id" :class="errorClass" role="alert">{{ form.errors.solicitante_id }}</p>
            </div>

            <div>
              <SearchableSelect
                id="corregir-concepto"
                v-model="form.concepto_id"
                :options="catalogos.conceptos"
                label="Concepto"
                placeholder="Seleccione…"
                search-placeholder="Buscar concepto…"
                rounded="2xl"
                label-key="nombre"
                secondary-key="detalle"
                value-key="id"
              />
              <p v-if="changed('concepto_id')" :class="antesClass">Antes: {{ nombreDe(catalogos.conceptos, original.concepto_id) }}</p>
              <p v-if="form.errors.concepto_id" :class="errorClass" role="alert">{{ form.errors.concepto_id }}</p>
            </div>

            <div class="md:col-span-2">
              <SearchableSelect
                id="corregir-proveedor"
                v-model="form.proveedor_id"
                :options="catalogos.proveedores"
                label="Proveedor"
                placeholder="Seleccione…"
                search-placeholder="Buscar por razón social o RFC…"
                rounded="2xl"
                label-key="nombre"
                secondary-key="detalle"
                value-key="id"
                :allow-null="original.proveedor_id === null"
                null-label="Sin proveedor"
              />
              <p v-if="changed('proveedor_id')" :class="antesClass">Antes: {{ nombreDe(catalogos.proveedores, original.proveedor_id) }}</p>
              <p v-if="form.errors.proveedor_id" :class="errorClass" role="alert">{{ form.errors.proveedor_id }}</p>
            </div>

            <div class="md:col-span-2">
              <label for="corregir-observaciones" class="block text-sm font-semibold text-slate-700 dark:text-neutral-200">Observaciones</label>
              <textarea id="corregir-observaciones" v-model="form.observaciones" rows="3" maxlength="5000" :class="[inputClass, 'mt-1']" />
              <p v-if="form.errors.observaciones" :class="errorClass" role="alert">{{ form.errors.observaciones }}</p>
            </div>
          </div>

          <label
            v-if="sucursalCambia && r.items_misma_sucursal > 0"
            class="flex items-start gap-3 rounded-2xl border border-slate-200/70 bg-slate-50 p-3 text-sm dark:border-white/10 dark:bg-neutral-950/40"
          >
            <input v-model="form.actualizar_items_sucursal" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300" />
            <span class="text-slate-700 dark:text-neutral-200">
              Cambiar también la sucursal de {{ r.items_misma_sucursal }} de {{ r.items_total }} item(s) que tenían la sucursal anterior.
            </span>
          </label>
        </section>

        <section class="space-y-3 rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900 sm:p-6">
          <label for="corregir-motivo" class="block text-sm font-extrabold text-slate-900 dark:text-neutral-100">Motivo de la corrección *</label>
          <textarea
            id="corregir-motivo"
            v-model="form.motivo"
            rows="2"
            maxlength="500"
            placeholder="Ej. El concepto se capturó por error; corresponde a mantenimiento."
            :class="inputClass"
          />
          <p class="text-xs text-slate-500 dark:text-neutral-400">Queda en la bitácora junto con los valores anteriores y nuevos, y se notifica.</p>
          <p v-if="form.errors.motivo" :class="errorClass" role="alert">{{ form.errors.motivo }}</p>

          <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
            <Link
              :href="route('requisiciones.show', r.id)"
              class="inline-flex min-h-[42px] items-center rounded-2xl px-4 py-2 text-sm font-bold ring-1 ring-black/5 bg-white text-slate-800 hover:bg-slate-50 dark:ring-white/10 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
            >
              Cancelar
            </Link>
            <button
              type="submit"
              :disabled="form.processing || !anyChange"
              class="inline-flex min-h-[42px] items-center gap-2 rounded-2xl bg-brand-button px-4 py-2 text-sm font-black text-brand-button-fg shadow-sm hover:bg-brand-button/90 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
              <Save v-else class="h-4 w-4" aria-hidden="true" />
              Guardar corrección
            </button>
          </div>
          <p v-if="!anyChange" class="text-right text-xs text-slate-500 dark:text-neutral-400">Cambia al menos un dato para guardar.</p>
        </section>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
