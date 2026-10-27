<!--
  Corrección administrativa de una requisición (permiso «requisiciones.corregir»).
  Es la misma captura del alta (Create.vue) en modo edición: mismas secciones,
  mismos componentes y el mismo editor de items. Diferencias:
  - Las listas muestran el catálogo completo, incluidos inactivos (marcados).
  - Items y montos solo se editan si el servidor lo permite (sin pagos,
    comprobantes ni ajustes pendientes); si no, se muestran en solo lectura.
  - Pide un motivo; folio y estatus no cambian.
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import {
  AlertTriangle,
  ArrowLeft,
  ArrowRight,
  FileText,
  History,
  Loader2,
  Lock,
  PencilLine,
  Save,
  Scale,
  UserCheck,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import DatePickerShadcn from '@/Components/ui/DatePickerShadcn.vue'
import { swalErr } from '@/lib/swal'
import RequisicionItemsEditor from './partials/RequisicionItemsEditor.vue'
import ProveedorDatosCard from './partials/ProveedorDatosCard.vue'
import { itemsClientErrors, money, nuevoItem, recalcularItems, type ItemCaptura } from './requisicionItems'

type Id = number
type Opcion = { id: Id; nombre: string; detalle?: string | null }
type Proveedor = Opcion & { razon_social?: string | null; rfc?: string | null; clabe?: string | null; banco?: string | null }

const props = defineProps<{
  requisicion: {
    id: Id
    folio: string
    status: string
    monto_subtotal: number
    monto_total: number
    ajustes_aplicados: number
    comprador_corp_id: Id
    sucursal_id: Id
    solicitante_id: Id
    concepto_id: Id
    proveedor_id: Id | null
    fecha_solicitud: string | null
    fecha_pago_esperada: string | null
    observaciones: string | null
    detalles: (ItemCaptura & { id: Id })[]
    items_total: number
    items_misma_sucursal: number
    items_editables: boolean
    items_bloqueo: string | null
  }
  catalogos: {
    corporativos: Opcion[]
    sucursales: (Opcion & { corporativo_id: Id })[]
    empleados: (Opcion & { sucursal_id: Id | null })[]
    conceptos: Opcion[]
    proveedores: Proveedor[]
  }
  today?: string
  can?: { ver_ajustes?: boolean }
}>()

const r = props.requisicion
const editables = r.items_editables

const STATUS_LABELS: Record<string, string> = {
  BORRADOR: 'Borrador',
  CAPTURADA: 'Capturada',
  PAGO_AUTORIZADO: 'Pago autorizado',
  PAGO_RECHAZADO: 'Pago rechazado',
  PAGADA: 'Pagada',
  POR_COMPROBAR: 'Por comprobar',
  COMPROBACION_ACEPTADA: 'Comprobación aceptada',
  COMPROBACION_RECHAZADA: 'Comprobación rechazada',
  ELIMINADA: 'Eliminada',
}
const statusLabel = STATUS_LABELS[r.status] ?? r.status
const statusTone = (() => {
  if (r.status === 'PAGADA' || r.status === 'COMPROBACION_ACEPTADA') return 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/20 dark:text-emerald-200'
  if (r.status === 'COMPROBACION_RECHAZADA' || r.status === 'PAGO_RECHAZADO') return 'bg-rose-500/10 text-rose-700 ring-rose-500/20 dark:text-rose-200'
  if (r.status === 'CAPTURADA' || r.status === 'PAGO_AUTORIZADO' || r.status === 'POR_COMPROBAR') return 'bg-indigo-500/10 text-indigo-700 ring-indigo-500/20 dark:text-indigo-200'
  return 'bg-slate-500/10 text-slate-700 ring-slate-500/20 dark:text-slate-200'
})()

const copiaItems = (): ItemCaptura[] =>
  r.detalles.map((d) => ({
    sucursal_id: d.sucursal_id ?? null,
    cantidad: Number(d.cantidad),
    descripcion: d.descripcion,
    precio_unitario: Number(d.precio_unitario),
    genera_iva: Boolean(d.genera_iva),
    subtotal: Number(d.subtotal),
    iva: Number(d.iva),
    total: Number(d.total),
  }))

const form = useForm({
  comprador_corp_id: r.comprador_corp_id as Id | null,
  sucursal_id: r.sucursal_id as Id | null,
  solicitante_id: r.solicitante_id as Id | null,
  concepto_id: r.concepto_id as Id | null,
  proveedor_id: r.proveedor_id as Id | null,
  fecha_solicitud: r.fecha_solicitud,
  fecha_pago_esperada: r.fecha_pago_esperada,
  observaciones: r.observaciones ?? '',
  detalles: copiaItems(),
  actualizar_items_sucursal: true,
  motivo: '',
})

/* ---------------------------------------------------------------- Cascada */

/** Sucursales del corporativo elegido (todas, activas o no). */
const sucursalesDelCorp = computed(() =>
  props.catalogos.sucursales.filter((s) => String(s.corporativo_id) === String(form.comprador_corp_id)),
)

watch(() => form.comprador_corp_id, () => {
  if (!sucursalesDelCorp.value.some((s) => String(s.id) === String(form.sucursal_id))) {
    form.sucursal_id = sucursalesDelCorp.value.length === 1 ? sucursalesDelCorp.value[0].id : null
  }
})

/** Igual que en el alta: si el solicitante es de otra sucursal se ofrece usar la suya, sin cambiarla sola. */
const sugerenciaSolicitante = computed(() => {
  const e = props.catalogos.empleados.find((x) => String(x.id) === String(form.solicitante_id))
  if (!e?.sucursal_id || String(e.sucursal_id) === String(form.sucursal_id)) return null
  const s = props.catalogos.sucursales.find((x) => String(x.id) === String(e.sucursal_id))
  if (!s) return null
  const corp = props.catalogos.corporativos.find((c) => String(c.id) === String(s.corporativo_id)) ?? null
  return { sucursal: s, corporativo: corp }
})

function usarDatosSolicitante() {
  const sug = sugerenciaSolicitante.value
  if (!sug) return
  form.comprador_corp_id = sug.sucursal.corporativo_id
  form.sucursal_id = sug.sucursal.id
}

/* ------------------------------------------------------------------ Items */

const itemsSubtotal = ref(r.monto_subtotal)
const itemsTotal = ref(r.monto_total)

watch(
  () => form.detalles,
  () => {
    const t = recalcularItems(form.detalles)
    itemsSubtotal.value = t.subtotal
    itemsTotal.value = t.total
  },
  { deep: true, immediate: true },
)

/** Total nuevo: items + ajustes ya aplicados (el servidor hace el mismo cálculo). */
const nuevoTotal = computed(() => (editables ? Math.max(0, Number((itemsTotal.value + r.ajustes_aplicados).toFixed(2))) : r.monto_total))
const nuevoSubtotal = computed(() => (editables ? itemsSubtotal.value : r.monto_subtotal))

const firmaItems = (items: ItemCaptura[]) =>
  JSON.stringify(items.map((d) => [
    d.sucursal_id ? Number(d.sucursal_id) : null,
    Number(d.cantidad || 0).toFixed(2),
    String(d.descripcion ?? '').trim(),
    Number(d.precio_unitario || 0).toFixed(2),
    Boolean(d.genera_iva),
  ]))
const firmaOriginal = firmaItems(copiaItems())
const itemsCambiaron = computed(() => editables && firmaItems(form.detalles) !== firmaOriginal)

function addItem() {
  form.detalles.push(nuevoItem(form.sucursal_id))
}
function removeItem(index: number) {
  form.detalles.splice(index, 1)
}

/** Items que siguen con la sucursal anterior de la requisición. */
const itemsConSucursalAnterior = computed(() =>
  form.detalles.filter((d) => String(d.sucursal_id ?? '') === String(r.sucursal_id)).length,
)
function moverItemsASucursalNueva() {
  form.detalles.forEach((d) => {
    if (String(d.sucursal_id ?? '') === String(r.sucursal_id)) d.sucursal_id = form.sucursal_id
  })
}

/* ---------------------------------------------------------------- Cambios */

const original = {
  comprador_corp_id: r.comprador_corp_id,
  sucursal_id: r.sucursal_id,
  solicitante_id: r.solicitante_id,
  concepto_id: r.concepto_id,
  proveedor_id: r.proveedor_id,
  fecha_solicitud: r.fecha_solicitud,
  fecha_pago_esperada: r.fecha_pago_esperada,
  observaciones: r.observaciones ?? '',
}
type Campo = keyof typeof original

const changed = (k: Campo) => String(form[k] ?? '').trim() !== String(original[k] ?? '').trim()
const sucursalCambia = computed(() => changed('sucursal_id'))

const nombreDe = (list: Opcion[], id: Id | null) => list.find((o) => String(o.id) === String(id))?.nombre ?? '—'
const fechaTexto = (v: string | null) => {
  if (!v) return 'Sin definir'
  const [y, m, d] = v.split('-').map(Number)
  return new Date(y, (m ?? 1) - 1, d ?? 1).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}

const CAMPOS: { key: Campo; label: string; texto: (v: any) => string }[] = [
  { key: 'comprador_corp_id', label: 'Corporativo', texto: (v) => nombreDe(props.catalogos.corporativos, v) },
  { key: 'sucursal_id', label: 'Sucursal', texto: (v) => nombreDe(props.catalogos.sucursales, v) },
  { key: 'solicitante_id', label: 'Solicitante', texto: (v) => nombreDe(props.catalogos.empleados, v) },
  { key: 'concepto_id', label: 'Concepto', texto: (v) => nombreDe(props.catalogos.conceptos, v) },
  { key: 'proveedor_id', label: 'Proveedor', texto: (v) => (v ? nombreDe(props.catalogos.proveedores, v) : 'Sin proveedor') },
  { key: 'fecha_solicitud', label: 'Fecha de solicitud', texto: fechaTexto },
  { key: 'fecha_pago_esperada', label: 'Fecha esperada de pago', texto: fechaTexto },
  { key: 'observaciones', label: 'Observaciones', texto: (v) => (String(v ?? '').trim() || 'Vacío') },
]
const antesDe = (k: Campo) => CAMPOS.find((c) => c.key === k)!.texto(original[k])

const cambios = computed(() => {
  const out = CAMPOS.filter((c) => changed(c.key)).map((c) => ({ label: c.label, antes: c.texto(original[c.key]), despues: c.texto(form[c.key]) }))
  if (itemsCambiaron.value) {
    out.push({ label: 'Items', antes: `${r.detalles.length} item(s)`, despues: `${form.detalles.length} item(s)` })
    if (nuevoTotal.value.toFixed(2) !== Number(r.monto_total).toFixed(2)) {
      out.push({ label: 'Total', antes: money(r.monto_total), despues: money(nuevoTotal.value) })
    }
  }
  return out
})
const anyChange = computed(() => cambios.value.length > 0)

const selectedProveedor = computed(() => props.catalogos.proveedores.find((p) => String(p.id) === String(form.proveedor_id)) ?? null)

/* ------------------------------------------------------------ Validación */

const showClient = ref(false)
const clientErrors = computed<Record<string, string>>(() => {
  const out: Record<string, string> = {}
  if (!form.comprador_corp_id) out.comprador_corp_id = 'Selecciona el corporativo comprador.'
  if (!form.sucursal_id) out.sucursal_id = 'Selecciona la sucursal.'
  if (!form.solicitante_id) out.solicitante_id = 'Selecciona el solicitante.'
  if (!form.concepto_id) out.concepto_id = 'Selecciona el concepto.'
  if (original.proveedor_id && !form.proveedor_id) out.proveedor_id = 'Selecciona el proveedor.'
  if (!form.fecha_solicitud) out.fecha_solicitud = 'La fecha de solicitud es obligatoria.'
  if (form.fecha_pago_esperada && form.fecha_solicitud && form.fecha_pago_esperada < form.fecha_solicitud) {
    out.fecha_pago_esperada = 'La fecha esperada de pago no puede ser anterior a la fecha de solicitud.'
  }
  const motivo = form.motivo.trim()
  if (motivo.length < 5) out.motivo = motivo ? 'El motivo debe tener al menos 5 caracteres.' : 'Escribe el motivo de la corrección.'
  return editables ? { ...out, ...itemsClientErrors(form.detalles) } : out
})

function errorFor(key: string): string | null {
  const server = (form.errors as Record<string, string | undefined>)[key]
  return server ?? (showClient.value ? clientErrors.value[key] ?? null : null)
}

function submit() {
  showClient.value = true
  const pendientes = Object.values(clientErrors.value)
  if (pendientes.length) {
    swalErr(pendientes[0] ?? 'Revisa los campos marcados.')
    return
  }
  form
    .transform((data: ReturnType<typeof form.data>) => {
      const { detalles, ...rest } = data
      return editables
        ? {
            ...rest,
            actualizar_items_sucursal: false,
            detalles: detalles.map((d: ItemCaptura) => ({
              sucursal_id: d.sucursal_id || null,
              cantidad: d.cantidad,
              descripcion: d.descripcion,
              precio_unitario: d.precio_unitario,
              genera_iva: d.genera_iva,
            })),
          }
        : rest
    })
    .patch(route('requisiciones.corregir.update', r.id), {
      preserveScroll: true,
      onError: (e: Record<string, string>) => {
        const first = Object.values(e)[0]
        swalErr(first || 'Revisa los campos marcados.')
      },
    })
}

const inputClass =
  'w-full min-h-[42px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 ' +
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 dark:border-white/10 dark:bg-neutral-900 dark:text-neutral-100'
const errorClass = 'mt-1 text-xs text-rose-600 dark:text-rose-400'
const antesClass = 'mt-1 flex items-center gap-1 text-[11px] text-amber-700 dark:text-amber-300'
const cardClass = 'rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900 sm:p-6'
</script>

<template>
  <Head :title="`Corregir ${r.folio}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex min-w-0 items-center gap-3">
        <PencilLine class="h-5 w-5 shrink-0" aria-hidden="true" />
        <span class="truncate">Corregir requisición</span>
        <span v-if="form.processing" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 dark:text-neutral-400">
          <Loader2 class="h-3.5 w-3.5 animate-spin" aria-hidden="true" /> Guardando…
        </span>
      </div>
    </template>

    <div class="w-full min-w-0 space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
      <!-- Cabecera: folio, estatus y total -->
      <section :class="cardClass" aria-labelledby="corregir-folio">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex min-w-0 items-start gap-3">
            <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-slate-100 text-slate-700 dark:bg-neutral-800 dark:text-neutral-200">
              <FileText class="h-6 w-6" aria-hidden="true" />
            </div>
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h2 id="corregir-folio" class="truncate text-lg font-black text-slate-900 dark:text-neutral-100 sm:text-xl">{{ r.folio }}</h2>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-extrabold ring-1 sm:text-xs" :class="statusTone">{{ statusLabel }}</span>
              </div>
              <p class="mt-1 text-sm text-slate-600 dark:text-neutral-400">
                Corrección administrativa. El folio y el estatus no cambian; cada lista muestra todo el catálogo, incluso registros inactivos.
              </p>
            </div>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <dl class="grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 px-4 py-3 dark:bg-neutral-950/40 sm:min-w-[18rem]">
              <div>
                <dt class="text-[11px] font-semibold text-slate-500 dark:text-neutral-400">Total actual</dt>
                <dd class="text-base font-black tabular-nums text-slate-900 dark:text-neutral-100">{{ money(r.monto_total) }}</dd>
              </div>
              <div>
                <dt class="text-[11px] font-semibold text-slate-500 dark:text-neutral-400">Total corregido</dt>
                <dd
                  class="text-base font-black tabular-nums"
                  :class="nuevoTotal.toFixed(2) !== Number(r.monto_total).toFixed(2) ? 'text-amber-700 dark:text-amber-300' : 'text-slate-900 dark:text-neutral-100'"
                >
                  {{ money(nuevoTotal) }}
                </dd>
              </div>
            </dl>
            <Link
              :href="route('requisiciones.show', r.id)"
              class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 transition
                     hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/50
                     dark:border-white/10 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
            >
              <ArrowLeft class="h-4 w-4" aria-hidden="true" /> Volver al detalle
            </Link>
          </div>
        </div>
      </section>

      <form class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]" novalidate @submit.prevent="submit">
        <div class="min-w-0 space-y-5">
          <!-- Datos generales (mismas secciones que el alta) -->
          <section class="space-y-4" :class="cardClass" aria-labelledby="datos-generales">
            <h3 id="datos-generales" class="text-base font-extrabold text-slate-900 dark:text-neutral-100">Datos generales</h3>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
              <div>
                <SearchableSelect
                  id="corregir-corporativo"
                  v-model="form.comprador_corp_id"
                  :options="catalogos.corporativos"
                  label="Comprador"
                  placeholder="Seleccione…"
                  search-placeholder="Buscar corporativo…"
                  :allow-null="false"
                  rounded="2xl"
                  label-key="nombre"
                  secondary-key="detalle"
                  value-key="id"
                  :error="errorFor('comprador_corp_id')"
                />
                <p v-if="errorFor('comprador_corp_id')" :class="errorClass" role="alert">{{ errorFor('comprador_corp_id') }}</p>
                <p v-else-if="changed('comprador_corp_id')" :class="antesClass"><History class="h-3 w-3" aria-hidden="true" /> Antes: {{ antesDe('comprador_corp_id') }}</p>
              </div>

              <div>
                <SearchableSelect
                  id="corregir-sucursal"
                  v-model="form.sucursal_id"
                  :options="sucursalesDelCorp"
                  label="Sucursal"
                  placeholder="Seleccione…"
                  search-placeholder="Buscar sucursal…"
                  :allow-null="false"
                  rounded="2xl"
                  label-key="nombre"
                  secondary-key="detalle"
                  value-key="id"
                  :error="errorFor('sucursal_id')"
                />
                <p v-if="errorFor('sucursal_id')" :class="errorClass" role="alert">{{ errorFor('sucursal_id') }}</p>
                <p v-else-if="changed('sucursal_id')" :class="antesClass"><History class="h-3 w-3" aria-hidden="true" /> Antes: {{ antesDe('sucursal_id') }}</p>
                <p v-else-if="!form.comprador_corp_id" class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">Primero elige un corporativo para ver sus sucursales.</p>
              </div>

              <div class="md:col-span-2 2xl:col-span-1">
                <SearchableSelect
                  id="corregir-solicitante"
                  v-model="form.solicitante_id"
                  :options="catalogos.empleados"
                  label="Solicitante"
                  placeholder="Seleccione…"
                  search-placeholder="Buscar colaborador…"
                  :allow-null="false"
                  rounded="2xl"
                  label-key="nombre"
                  secondary-key="detalle"
                  value-key="id"
                  :error="errorFor('solicitante_id')"
                />
                <p v-if="errorFor('solicitante_id')" :class="errorClass" role="alert">{{ errorFor('solicitante_id') }}</p>
                <p v-else-if="changed('solicitante_id')" :class="antesClass"><History class="h-3 w-3" aria-hidden="true" /> Antes: {{ antesDe('solicitante_id') }}</p>
              </div>
            </div>

            <div
              v-if="sugerenciaSolicitante"
              class="flex flex-col gap-2 rounded-2xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100 sm:flex-row sm:items-center sm:justify-between"
              role="status"
            >
              <p class="min-w-0 break-words">
                El solicitante pertenece a <strong>{{ sugerenciaSolicitante.sucursal.nombre }}</strong><template v-if="sugerenciaSolicitante.corporativo"> ({{ sugerenciaSolicitante.corporativo.nombre }})</template>.
                La sucursal y el comprador no se cambian solos.
              </p>
              <button
                type="button"
                class="inline-flex min-h-[40px] shrink-0 items-center justify-center gap-2 rounded-xl bg-sky-600 px-3 text-xs font-bold text-white transition hover:bg-sky-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400"
                @click="usarDatosSolicitante"
              >
                <UserCheck class="h-4 w-4" aria-hidden="true" /> Usar datos del solicitante
              </button>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-4">
              <div>
                <SearchableSelect
                  id="corregir-concepto"
                  v-model="form.concepto_id"
                  :options="catalogos.conceptos"
                  label="Concepto"
                  placeholder="Seleccione…"
                  search-placeholder="Buscar concepto…"
                  :allow-null="false"
                  rounded="2xl"
                  label-key="nombre"
                  secondary-key="detalle"
                  value-key="id"
                  :error="errorFor('concepto_id')"
                />
                <p v-if="errorFor('concepto_id')" :class="errorClass" role="alert">{{ errorFor('concepto_id') }}</p>
                <p v-else-if="changed('concepto_id')" :class="antesClass"><History class="h-3 w-3" aria-hidden="true" /> Antes: {{ antesDe('concepto_id') }}</p>
              </div>

              <div>
                <SearchableSelect
                  id="corregir-proveedor"
                  v-model="form.proveedor_id"
                  :options="catalogos.proveedores"
                  :label="original.proveedor_id ? 'Proveedor *' : 'Proveedor'"
                  placeholder="Seleccione…"
                  search-placeholder="Buscar por razón social o RFC…"
                  rounded="2xl"
                  label-key="nombre"
                  secondary-key="detalle"
                  value-key="id"
                  :allow-null="original.proveedor_id === null"
                  null-label="Sin proveedor"
                  :error="errorFor('proveedor_id')"
                />
                <p v-if="errorFor('proveedor_id')" :class="errorClass" role="alert">{{ errorFor('proveedor_id') }}</p>
                <p v-else-if="changed('proveedor_id')" :class="antesClass"><History class="h-3 w-3" aria-hidden="true" /> Antes: {{ antesDe('proveedor_id') }}</p>
              </div>

              <div>
                <DatePickerShadcn
                  id="corregir-fecha-solicitud"
                  v-model="form.fecha_solicitud"
                  label="Fecha de solicitud"
                  placeholder="Selecciona fecha"
                  :invalid="!!errorFor('fecha_solicitud')"
                />
                <p v-if="errorFor('fecha_solicitud')" :class="errorClass" role="alert">{{ errorFor('fecha_solicitud') }}</p>
                <p v-else-if="changed('fecha_solicitud')" :class="antesClass"><History class="h-3 w-3" aria-hidden="true" /> Antes: {{ antesDe('fecha_solicitud') }}</p>
                <p v-else class="mt-1 text-[11px] text-slate-500 dark:text-neutral-400">Al corregir se permite una fecha pasada.</p>
              </div>

              <div>
                <DatePickerShadcn
                  id="corregir-fecha-esperada"
                  v-model="form.fecha_pago_esperada"
                  label="Fecha esperada de pago (opcional)"
                  placeholder="Sin definir"
                  :min-value="form.fecha_solicitud"
                  clearable
                  :invalid="!!errorFor('fecha_pago_esperada')"
                />
                <p v-if="errorFor('fecha_pago_esperada')" :class="errorClass" role="alert">{{ errorFor('fecha_pago_esperada') }}</p>
                <p v-else-if="changed('fecha_pago_esperada')" :class="antesClass"><History class="h-3 w-3" aria-hidden="true" /> Antes: {{ antesDe('fecha_pago_esperada') }}</p>
              </div>
            </div>

            <div>
              <label for="corregir-observaciones" class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Observaciones</label>
              <textarea id="corregir-observaciones" v-model="form.observaciones" rows="3" maxlength="5000" :class="[inputClass, 'mt-1 rounded-2xl']" />
              <p v-if="errorFor('observaciones')" :class="errorClass" role="alert">{{ errorFor('observaciones') }}</p>
              <p v-else-if="changed('observaciones')" :class="antesClass"><History class="h-3 w-3" aria-hidden="true" /> Se modificaron las observaciones.</p>
            </div>

            <ProveedorDatosCard v-if="selectedProveedor" :proveedor="selectedProveedor" />

            <!-- Sin edición de items: se puede mover la sucursal de los items junto con la de la requisición. -->
            <label
              v-if="!editables && sucursalCambia && r.items_misma_sucursal > 0"
              class="flex min-h-[44px] cursor-pointer items-start gap-3 rounded-2xl border border-slate-200/70 bg-slate-50 p-3 text-sm dark:border-white/10 dark:bg-neutral-950/40"
            >
              <input v-model="form.actualizar_items_sucursal" type="checkbox" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
              <span class="text-slate-700 dark:text-neutral-200">
                Cambiar también la sucursal de {{ r.items_misma_sucursal }} de {{ r.items_total }} item(s) que tenían la sucursal anterior.
              </span>
            </label>
          </section>

          <!-- Items: el mismo editor del alta -->
          <RequisicionItemsEditor
            :items="form.detalles"
            :subtotal="nuevoSubtotal"
            :total="nuevoTotal"
            :error-for="errorFor"
            :readonly="!editables"
            :sucursales="catalogos.sucursales"
            id-prefix="corregir-items"
            @add="addItem"
            @remove="removeItem"
          >
            <template #subtitle>
              <p class="mt-1 text-xs text-slate-500 dark:text-neutral-400">
                <template v-if="editables">Los montos se recalculan igual que al capturar (IVA 16 %) y el servidor los vuelve a calcular al guardar.</template>
                <template v-else>Solo lectura.</template>
              </p>
            </template>

            <template #aviso>
              <div
                v-if="!editables"
                class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100 sm:flex-row sm:items-center sm:justify-between"
                role="status"
              >
                <p class="flex min-w-0 items-start gap-2">
                  <Lock class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                  <span class="break-words">{{ r.items_bloqueo }}</span>
                </p>
                <Link
                  v-if="can?.ver_ajustes"
                  :href="route('requisiciones.ajustes', r.id)"
                  class="inline-flex min-h-[40px] shrink-0 items-center justify-center gap-2 rounded-xl bg-amber-600 px-3 text-xs font-bold text-white transition hover:bg-amber-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400"
                >
                  <Scale class="h-4 w-4" aria-hidden="true" /> Ir a Ajustes
                </Link>
              </div>
              <div
                v-else-if="sucursalCambia && itemsConSucursalAnterior > 0"
                class="flex flex-col gap-2 rounded-2xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100 sm:flex-row sm:items-center sm:justify-between"
                role="status"
              >
                <p class="min-w-0 break-words">{{ itemsConSucursalAnterior }} item(s) siguen con la sucursal anterior ({{ antesDe('sucursal_id') }}).</p>
                <button
                  type="button"
                  class="inline-flex min-h-[40px] shrink-0 items-center justify-center gap-2 rounded-xl bg-sky-600 px-3 text-xs font-bold text-white transition hover:bg-sky-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400"
                  @click="moverItemsASucursalNueva"
                >
                  Usar la nueva sucursal en esos items
                </button>
              </div>
              <div
                v-if="editables && r.ajustes_aplicados !== 0"
                class="flex items-start gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700 dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-300"
                role="note"
              >
                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <span>Esta requisición tiene ajustes aplicados por {{ money(r.ajustes_aplicados) }}; se conservan sobre el nuevo total de items.</span>
              </div>
            </template>

            <template v-if="editables && r.ajustes_aplicados !== 0" #totales-extra>
              <div class="flex justify-between text-slate-600 dark:text-neutral-300">
                <span>Ajustes aplicados</span><span class="font-semibold tabular-nums">{{ money(r.ajustes_aplicados) }}</span>
              </div>
            </template>
          </RequisicionItemsEditor>
        </div>

        <!-- Motivo y resumen de cambios -->
        <aside class="min-w-0 xl:sticky xl:top-4 xl:self-start">
          <section class="space-y-4" :class="cardClass" aria-labelledby="corregir-motivo-titulo">
            <div>
              <h3 id="corregir-motivo-titulo" class="text-base font-extrabold text-slate-900 dark:text-neutral-100">Motivo y cambios</h3>
              <p class="mt-1 text-xs text-slate-500 dark:text-neutral-400">Queda en la bitácora con los valores anteriores y nuevos, y se notifica.</p>
            </div>

            <div>
              <label for="corregir-motivo" class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">Motivo de la corrección *</label>
              <textarea
                id="corregir-motivo"
                v-model="form.motivo"
                rows="3"
                maxlength="500"
                placeholder="Ej. El concepto se capturó por error; corresponde a mantenimiento."
                :class="[inputClass, 'mt-1 rounded-2xl']"
                :aria-invalid="!!errorFor('motivo') || undefined"
              />
              <div class="mt-1 flex items-start justify-between gap-2">
                <p v-if="errorFor('motivo')" class="text-xs text-rose-600 dark:text-rose-400" role="alert">{{ errorFor('motivo') }}</p>
                <span v-else class="text-[11px] text-slate-500 dark:text-neutral-400">Mínimo 5 caracteres.</span>
                <span class="shrink-0 text-[11px] tabular-nums text-slate-500 dark:text-neutral-400">{{ form.motivo.length }}/500</span>
              </div>
            </div>

            <div>
              <p class="text-xs font-semibold text-slate-600 dark:text-neutral-300">
                {{ anyChange ? `${cambios.length} cambio(s)` : 'Sin cambios todavía' }}
              </p>
              <ul v-if="anyChange" class="mt-2 space-y-2">
                <li
                  v-for="c in cambios"
                  :key="c.label"
                  class="rounded-2xl border border-slate-200/70 bg-slate-50 px-3 py-2 text-xs dark:border-white/10 dark:bg-neutral-950/40"
                >
                  <p class="font-bold text-slate-800 dark:text-neutral-100">{{ c.label }}</p>
                  <p class="mt-0.5 flex flex-wrap items-center gap-1 text-slate-600 dark:text-neutral-300">
                    <span class="break-words line-through decoration-slate-400/70">{{ c.antes }}</span>
                    <ArrowRight class="h-3 w-3 shrink-0" aria-hidden="true" />
                    <span class="break-words font-semibold text-slate-900 dark:text-neutral-100">{{ c.despues }}</span>
                  </p>
                </li>
              </ul>
              <p v-else class="mt-1 text-xs text-slate-500 dark:text-neutral-400">Modifica al menos un dato para poder guardar.</p>
            </div>
          </section>
        </aside>

        <!-- Barra de acciones: fija abajo mientras se edita -->
        <div class="sticky bottom-0 z-20 -mx-3 border-t border-slate-200/70 bg-white/95 px-3 py-3 backdrop-blur dark:border-white/10 dark:bg-neutral-900/95 sm:mx-0 sm:rounded-3xl sm:border sm:px-5 sm:shadow-lg xl:col-span-2">
          <div class="flex flex-col-reverse items-stretch gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p class="hidden text-xs text-slate-500 dark:text-neutral-400 sm:block">
              {{ anyChange ? `${cambios.length} cambio(s) por guardar` : 'Sin cambios' }}
              <template v-if="nuevoTotal.toFixed(2) !== Number(r.monto_total).toFixed(2)"> · Total {{ money(r.monto_total) }} → <strong class="text-slate-800 dark:text-neutral-100">{{ money(nuevoTotal) }}</strong></template>
            </p>
            <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
              <Link
                :href="route('requisiciones.show', r.id)"
                class="inline-flex min-h-[46px] items-center justify-center rounded-2xl border border-slate-200 px-4 text-sm font-semibold text-slate-700
                       transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/50
                       dark:border-white/10 dark:text-neutral-200 dark:hover:bg-white/5"
              >
                Cancelar
              </Link>
              <button
                type="submit"
                :disabled="form.processing || !anyChange"
                class="inline-flex min-h-[46px] items-center justify-center gap-2 rounded-2xl bg-brand-button px-4 text-sm font-extrabold text-brand-button-fg transition
                       hover:bg-brand-button/90 active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-slate-400
                       disabled:cursor-not-allowed disabled:opacity-60 motion-reduce:transform-none dark:focus-visible:ring-offset-neutral-900"
              >
                <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                <Save v-else class="h-4 w-4" aria-hidden="true" />
                {{ form.processing ? 'Guardando…' : 'Guardar corrección' }}
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
