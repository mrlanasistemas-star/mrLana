<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'
import { computed } from 'vue'

import {
  ArrowLeft,
  Upload,
  FileText,
  ExternalLink,
  X,
  Check,
  Ban,
  Trash2,
  Plus,
  Pencil,
  Search,
  MessageCircle,
  Mail,
  BellRing,
  Clock,
  Eye,
} from 'lucide-vue-next'

import type { RequisicionComprobarPageProps } from './Comprobar.types'
import { useRequisicionComprobar } from './useRequisicionComprobar'

declare const route: any

const props = defineProps<RequisicionComprobarPageProps>()

const {
  // core
  req,
  rows,
  money,
  fmtLong,
  tipoDocLabel,
  estatusLabel,
  estatusPillClass,
  isFinalizada,

  // permisos
  serverCan,
  canReview,
  canUseFoliosPanel,
  canEditFolio,

  // revisión
  approve,
  reject,
  destroyComprobante,
  canDeleteComprobante,

  // cola de carga
  queue,
  fileKey,
  dragActive,
  uploading,
  uploadProgress,
  onPickFiles,
  onDragEnter,
  onDragOver,
  onDragLeave,
  onDropFiles,
  onItemMontoInput,
  removeItem,
  clearQueue,
  openQueuePreview,
  itemSinMonto,
  fmtSize,
  queueCents,
  queueExcede,
  queueIncompleta,
  canSubmit,
  submitAll,

  // montos
  pendienteCents,
  pendientePorCargarCents,
  totalCents,
  approvedCents,
  enRevisionCents,
  rechazadoCents,
  approvedPct,
  enRevisionPct,
  isFullyLoaded,
  isFullyApproved,
  restanteTrasCents,
  canUploadMore,
  estaPagada,
  esperaPagoMensaje,

  // vista previa
  preview,
  previewTitle,
  openPreview,
  closePreview,
  previewWrapRef,

  // folios
  foliosOpen,
  toggleFoliosOpen,
  folioSelectedId,
  folioSelected,
  addFolio,
  editFolio,

  // avisos
  canNotify,
  canSendNotification,
  notifyWhatsApp,
  notifyEmail,
  notifySystem,
} = useRequisicionComprobar(props)

/** Etapa de la comprobación, para el indicador del resumen. */
const etapa = computed(() => {
  if (!estaPagada.value) return { texto: 'En espera de pago', clase: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200' }
  if (isFullyApproved.value) return { texto: 'Comprobación completa', clase: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200' }
  if (isFullyLoaded.value) return { texto: 'En revisión de Contabilidad', clase: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200' }
  return { texto: 'Faltan comprobantes por cargar', clase: 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-200' }
})

/** Por qué no se puede cargar (null cuando sí se puede). */
const motivoSinCarga = computed(() => {
  if (canUploadMore.value) return null
  if (!estaPagada.value) return esperaPagoMensaje.value
  if (isFullyApproved.value || isFinalizada.value) return 'Esta requisición ya quedó comprobada. No hace falta subir más archivos.'
  if (isFullyLoaded.value) return 'Ya cargaste comprobantes por el total. Avisa a Contabilidad y espera su revisión; si rechazan alguno podrás cargarlo de nuevo.'
  return 'No tienes permiso para cargar comprobantes en esta requisición.'
})

/** Por qué no se puede avisar todavía (se muestra en una sola línea). */
const motivoSinAviso = computed(() => {
  if (canSendNotification.value) return null
  return estaPagada.value
    ? 'Se habilita cuando lo cargado cubra el total.'
    : 'Se habilita cuando la requisición esté pagada.'
})

const textoSubir = computed(() => {
  if (uploading.value) return `Subiendo ${Math.min(uploadProgress.value.done + 1, uploadProgress.value.total)} de ${uploadProgress.value.total}…`
  return queue.value.length === 1 ? 'Subir comprobante' : `Subir ${queue.value.length} comprobantes`
})

const card = 'rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/70 backdrop-blur shadow-sm'
const iconBtn =
  'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border transition active:scale-[0.98] ' +
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40 motion-reduce:transform-none'
const iconBtnNeutral =
  'border-slate-200/70 bg-white hover:bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15 dark:text-neutral-100'
// Todos los campos de la cola miden lo mismo para que queden alineados.
const field =
  'block h-11 w-full rounded-2xl border border-slate-200/70 bg-white/90 px-3 text-sm font-semibold text-slate-900 ' +
  'placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/25 focus:border-indigo-500/40 ' +
  'disabled:opacity-60 disabled:cursor-not-allowed ' +
  'dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 dark:placeholder:text-neutral-500 dark:[color-scheme:dark]'
const fieldLabel = 'mb-1 block truncate text-xs font-bold text-slate-600 dark:text-neutral-300'
const notifyBtn =
  'inline-flex h-10 items-center gap-2 rounded-2xl border px-3 text-sm font-black transition active:scale-[0.98] ' +
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40 motion-reduce:transform-none ' +
  'disabled:cursor-not-allowed disabled:border-slate-200/70 disabled:bg-slate-100 disabled:text-slate-400 ' +
  'dark:disabled:border-white/10 dark:disabled:bg-white/5 dark:disabled:text-neutral-500'
</script>

<template>
  <Head title="Comprobar requisición" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3 min-w-0">
        <Link
          :href="route('requisiciones.index')"
          class="inline-flex items-center justify-center h-10 w-10 rounded-2xl border border-slate-200/70 bg-white
                 hover:bg-slate-50 hover:shadow-sm transition active:scale-[0.98]
                 dark:border-white/10 dark:bg-neutral-900/60 dark:hover:bg-white/10"
          title="Volver"
          aria-label="Volver a requisiciones"
        >
          <ArrowLeft class="h-5 w-5 text-slate-800 dark:text-neutral-100" />
        </Link>

        <div class="min-w-0">
          <div class="text-lg sm:text-xl font-black text-slate-900 dark:text-neutral-100 truncate">
            Comprobar
          </div>
        </div>
      </div>
    </template>

    <div class="w-full min-w-0 flex-1 overflow-x-hidden">
      <div class="mx-auto w-full min-w-0 max-w-[1900px] px-3 sm:px-5 md:px-6 lg:px-8 xl:px-8 2xl:px-10 py-4 sm:py-6 space-y-4">
        <!-- Aviso: no se comprueba hasta que la requisición esté pagada -->
        <div
          v-if="!estaPagada"
          role="status"
          class="flex items-start gap-3 rounded-3xl border border-amber-300/70 bg-amber-50 px-5 py-4
                 dark:border-amber-500/30 dark:bg-amber-500/10"
        >
          <Clock class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-300" aria-hidden="true" />
          <div class="min-w-0">
            <div class="text-sm font-black text-amber-900 dark:text-amber-100">
              Requisición pendiente de pago
            </div>
            <div class="mt-0.5 text-sm text-amber-800 dark:text-amber-200">
              {{ esperaPagoMensaje }}
            </div>
            <div class="mt-1 text-xs font-bold text-amber-700 dark:text-amber-300">
              Pagado: {{ money(props.pago?.pagado ?? 0) }}. Pendiente de pago: {{ money(props.pago?.pendiente ?? 0) }}.
            </div>
          </div>
        </div>

        <!-- Encabezado + resumen de montos -->
        <section :class="card" class="p-5" aria-labelledby="resumen-comprobacion">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <h2 id="resumen-comprobacion" class="text-base sm:text-lg font-black text-slate-900 dark:text-white truncate">
                Requisición {{ req?.folio || '—' }}
              </h2>
              <p class="mt-0.5 text-sm text-slate-600 dark:text-neutral-300 break-words">
                {{ req?.concepto || 'Sin concepto' }}, solicitada por {{ req?.solicitante_nombre || '—' }}
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <span class="inline-flex items-center rounded-full border px-3 py-1 text-[12px] font-black" :class="etapa.clase">
                {{ etapa.texto }}
              </span>
              <button
                v-if="canUseFoliosPanel"
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-2xl border border-slate-200/70 bg-white px-4 text-sm font-black text-slate-900
                       hover:bg-slate-50 transition active:scale-[0.98]
                       dark:border-white/10 dark:bg-white/10 dark:text-white dark:hover:bg-white/15"
                :aria-expanded="foliosOpen"
                @click="toggleFoliosOpen"
              >
                <Search class="h-4 w-4" aria-hidden="true" />
                Buscar folios
              </button>
            </div>
          </div>

          <!-- Panel de folios -->
          <div
            v-if="foliosOpen && canUseFoliosPanel"
            class="mt-4 rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 p-4"
          >
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="text-sm font-black text-slate-900 dark:text-white">Gestión de folios</div>
                <div class="text-xs text-slate-600 dark:text-neutral-300">
                  Elige un folio para consultarlo. Edita su monto solo si requiere un ajuste documental.
                </div>
              </div>

              <button
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-2xl bg-brand-button px-4 text-sm font-black text-brand-button-fg
                       hover:bg-brand-button/90 transition active:scale-[0.98]"
                @click="addFolio"
              >
                <Plus class="h-4 w-4" aria-hidden="true" />
                Agregar folio
              </button>
            </div>

            <div class="mt-3 flex items-start gap-2">
              <div class="flex-1 min-w-0">
                <SearchableSelect
                  v-model="folioSelectedId"
                  :options="(props as any).folios ?? []"
                  label="Folio"
                  placeholder="Selecciona un folio..."
                  searchPlaceholder="Escribe para filtrar..."
                  labelKey="folio"
                  secondaryKey="monto_total"
                  valueKey="id"
                  :compact="true"
                  maxHeightClass="max-h-64"
                  :panelClass="'rounded-3xl'"
                />
              </div>

              <button
                v-if="canEditFolio"
                type="button"
                class="mt-[22px] inline-flex items-center gap-2 rounded-2xl border border-slate-200/70 bg-white px-4 py-3 text-sm font-black text-slate-900
                       hover:bg-slate-50 transition active:scale-[0.98]
                       dark:border-white/10 dark:bg-white/10 dark:text-white dark:hover:bg-white/15
                       disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="!folioSelectedId"
                title="Editar el monto del folio seleccionado (el número de folio es fijo)"
                @click="editFolio"
              >
                <Pencil class="h-4 w-4" aria-hidden="true" />
                Editar
              </button>
            </div>

            <div v-if="folioSelected" class="mt-2 text-xs text-slate-600 dark:text-neutral-300">
              Seleccionado:
              <span class="font-black text-slate-900 dark:text-white">{{ folioSelected.folio }}</span>,
              monto
              <span class="font-black text-slate-900 dark:text-white">{{ money(folioSelected.monto_total ?? 0) }}</span>
            </div>
          </div>

          <!-- Montos -->
          <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 p-4 min-w-0">
              <div class="flex items-center gap-2 text-[12px] font-black text-slate-600 dark:text-neutral-300">
                <FileText class="h-4 w-4 shrink-0" aria-hidden="true" />
                Total a comprobar
              </div>
              <div class="mt-1 text-lg sm:text-xl font-black tabular-nums text-slate-900 dark:text-white truncate">
                {{ money(totalCents / 100) }}
              </div>
              <div class="mt-0.5 text-[11px] text-slate-500 dark:text-neutral-400">Monto de la requisición</div>
            </div>

            <div class="rounded-2xl border border-emerald-200/80 dark:border-emerald-500/20 bg-emerald-50/70 dark:bg-emerald-500/10 p-4 min-w-0">
              <div class="flex items-center gap-2 text-[12px] font-black text-emerald-800 dark:text-emerald-200">
                <Check class="h-4 w-4 shrink-0" aria-hidden="true" />
                Comprobado
              </div>
              <div class="mt-1 text-lg sm:text-xl font-black tabular-nums text-emerald-900 dark:text-emerald-100 truncate">
                {{ money(approvedCents / 100) }}
              </div>
              <div class="mt-0.5 text-[11px] text-emerald-800/80 dark:text-emerald-200/80">Aprobado por Contabilidad</div>
            </div>

            <div class="rounded-2xl border border-amber-200/80 dark:border-amber-500/20 bg-amber-50/70 dark:bg-amber-500/10 p-4 min-w-0">
              <div class="flex items-center gap-2 text-[12px] font-black text-amber-800 dark:text-amber-200">
                <Clock class="h-4 w-4 shrink-0" aria-hidden="true" />
                En revisión
              </div>
              <div class="mt-1 text-lg sm:text-xl font-black tabular-nums text-amber-900 dark:text-amber-100 truncate">
                {{ money(enRevisionCents / 100) }}
              </div>
              <div class="mt-0.5 text-[11px] text-amber-800/80 dark:text-amber-200/80">Cargado, falta que lo revisen</div>
            </div>

            <div class="rounded-2xl border border-indigo-200/80 dark:border-indigo-500/20 bg-indigo-50/70 dark:bg-indigo-500/10 p-4 min-w-0">
              <div class="flex items-center gap-2 text-[12px] font-black text-indigo-800 dark:text-indigo-200">
                <Upload class="h-4 w-4 shrink-0" aria-hidden="true" />
                Por cargar
              </div>
              <div class="mt-1 text-lg sm:text-xl font-black tabular-nums text-indigo-900 dark:text-indigo-100 truncate">
                {{ money(pendientePorCargarCents / 100) }}
              </div>
              <div class="mt-0.5 text-[11px] text-indigo-800/80 dark:text-indigo-200/80">Falta subir su comprobante</div>
            </div>
          </div>

          <!-- Avance -->
          <div class="mt-4">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm text-slate-700 dark:text-neutral-200">
              <div>
                Comprobado
                <span class="font-black tabular-nums text-slate-900 dark:text-white">{{ money(approvedCents / 100) }}</span>
                de
                <span class="font-black tabular-nums text-slate-900 dark:text-white">{{ money(totalCents / 100) }}</span>
              </div>
              <div>
                Por comprobar:
                <span class="font-black tabular-nums text-slate-900 dark:text-white">{{ money(pendienteCents / 100) }}</span>
              </div>
            </div>

            <div
              class="mt-2 flex h-3 w-full overflow-hidden rounded-full bg-slate-200/80 dark:bg-white/10"
              role="img"
              :aria-label="`Comprobado ${money(approvedCents / 100)}, en revisión ${money(enRevisionCents / 100)}, por cargar ${money(pendientePorCargarCents / 100)}, de un total de ${money(totalCents / 100)}`"
            >
              <div class="h-full bg-emerald-500 transition-[width] duration-500 motion-reduce:transition-none" :style="{ width: approvedPct + '%' }" />
              <div class="h-full bg-amber-400 transition-[width] duration-500 motion-reduce:transition-none" :style="{ width: enRevisionPct + '%' }" />
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] font-semibold text-slate-600 dark:text-neutral-300">
              <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500" aria-hidden="true" />Comprobado</span>
              <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-400" aria-hidden="true" />En revisión</span>
              <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-white/20" aria-hidden="true" />Por cargar</span>
              <span v-if="rechazadoCents > 0" class="inline-flex items-center gap-1.5 text-rose-700 dark:text-rose-300">
                <Ban class="h-3.5 w-3.5" aria-hidden="true" />
                Rechazado: {{ money(rechazadoCents / 100) }} (no cuenta; hay que volver a cargarlo)
              </span>
            </div>
          </div>
        </section>

        <!-- Dos columnas: comprobantes cargados + vista previa | carga + datos -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-start min-w-0">
          <!-- Comprobantes cargados + vista previa -->
          <div class="min-w-0 space-y-4">
            <section :class="card" class="overflow-hidden" aria-labelledby="titulo-relacion">
              <div class="px-5 py-4 border-b border-slate-200/70 dark:border-white/10">
                <h2 id="titulo-relacion" class="text-lg font-black text-slate-900 dark:text-neutral-100">
                  Comprobantes cargados
                  <span v-if="rows.length" class="ml-1 text-sm font-bold text-slate-500 dark:text-neutral-400">({{ rows.length }})</span>
                </h2>
                <p class="text-sm text-slate-500 dark:text-neutral-300">
                  Cada comprobante se revisa por separado. Rechazar uno no afecta a los demás.
                </p>
              </div>

              <ul v-if="rows.length" class="divide-y divide-slate-200/70 dark:divide-white/10">
                <li v-for="c in rows" :key="c.id" class="px-5 py-3">
                  <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <div class="min-w-0 flex-1 basis-56">
                      <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="text-sm font-black tabular-nums text-slate-900 dark:text-neutral-100">{{ money(c.monto) }}</span>
                        <span class="text-sm font-semibold text-slate-600 dark:text-neutral-300">{{ tipoDocLabel(c.tipo_doc) }}</span>
                        <span
                          class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-black"
                          :class="estatusPillClass(c.estatus)"
                        >
                          <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="c.estatus === 'APROBADO' ? 'bg-emerald-500' : c.estatus === 'RECHAZADO' ? 'bg-rose-500' : 'bg-amber-400'"
                            aria-hidden="true"
                          />
                          {{ c.estatus === 'PENDIENTE' ? 'En revisión' : estatusLabel(c.estatus) }}
                        </span>
                      </div>

                      <div class="mt-0.5 flex min-w-0 items-center gap-2 text-[12px] text-slate-500 dark:text-neutral-400">
                        <span class="shrink-0">{{ fmtLong(c.fecha_emision) }}</span>
                        <button
                          v-if="c.archivo?.url"
                          type="button"
                          class="inline-flex min-w-0 items-center gap-1 font-bold text-indigo-700 hover:underline dark:text-indigo-300"
                          :title="`Ver ${c.archivo.label || 'archivo'}`"
                          @click="openPreview(c)"
                        >
                          <FileText class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                          <span class="truncate">{{ c.archivo.label || 'Ver archivo' }}</span>
                        </button>
                        <span v-else>Sin archivo</span>
                      </div>
                    </div>

                    <div class="flex items-center gap-2">
                      <a
                        v-if="c.archivo?.url"
                        :href="c.archivo.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        :class="[iconBtn, iconBtnNeutral]"
                        title="Abrir en otra pestaña"
                        aria-label="Abrir archivo en otra pestaña"
                      >
                        <ExternalLink class="h-4 w-4" aria-hidden="true" />
                      </a>

                      <template v-if="canReview">
                        <button
                          v-if="!isFinalizada && c.estatus !== 'APROBADO'"
                          type="button"
                          :class="iconBtn"
                          class="border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100
                                 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:hover:bg-emerald-500/15"
                          title="Aprobar"
                          aria-label="Aprobar comprobante"
                          @click="approve(c.id)"
                        >
                          <Check class="h-4 w-4" aria-hidden="true" />
                        </button>

                        <button
                          v-if="c.estatus !== 'RECHAZADO'"
                          type="button"
                          :class="iconBtn"
                          class="border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100
                                 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200 dark:hover:bg-rose-500/15"
                          title="Rechazar"
                          aria-label="Rechazar comprobante"
                          @click="reject(c.id)"
                        >
                          <Ban class="h-4 w-4" aria-hidden="true" />
                        </button>
                      </template>

                      <button
                        v-if="canDeleteComprobante(c)"
                        type="button"
                        :class="[iconBtn, iconBtnNeutral]"
                        title="Eliminar comprobante"
                        aria-label="Eliminar comprobante"
                        @click="destroyComprobante(c.id)"
                      >
                        <Trash2 class="h-4 w-4" aria-hidden="true" />
                      </button>
                    </div>
                  </div>

                  <p
                    v-if="c.estatus === 'RECHAZADO' && c.comentario_revision"
                    class="mt-2 rounded-xl bg-rose-50 px-3 py-2 text-[12px] font-semibold text-rose-700 break-words dark:bg-rose-500/10 dark:text-rose-200"
                  >
                    Motivo del rechazo: {{ c.comentario_revision }}
                  </p>
                </li>
              </ul>

              <div v-else class="px-5 py-8 text-center">
                <FileText class="mx-auto h-8 w-8 text-slate-300 dark:text-neutral-600" aria-hidden="true" />
                <p class="mt-2 text-sm font-bold text-slate-700 dark:text-neutral-200">Aún no hay comprobantes cargados</p>
                <p v-if="serverCan.subir && canUploadMore" class="text-sm text-slate-500 dark:text-neutral-400">
                  Arrastra tus archivos a «Cargar comprobantes» para empezar.
                </p>
              </div>

              <!-- Aviso a Contabilidad -->
              <div
                v-if="canNotify"
                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-slate-200/70 bg-slate-50/60 px-5 py-3
                       dark:border-white/10 dark:bg-white/5"
              >
                <div class="min-w-0">
                  <div class="text-sm font-black text-slate-900 dark:text-neutral-100">Avisar a Contabilidad</div>
                  <div class="text-xs text-slate-500 dark:text-neutral-400">
                    {{ motivoSinAviso ?? 'Ya cargaste todo: avisa para que inicien la revisión.' }}
                  </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    :disabled="!canSendNotification"
                    :class="[notifyBtn, canSendNotification ? 'border-transparent bg-brand-button text-brand-button-fg hover:bg-brand-button/90' : '']"
                    title="Les llega a su campana de notificaciones"
                    @click="notifySystem"
                  >
                    <BellRing class="h-4 w-4" aria-hidden="true" />
                    En el sistema
                  </button>

                  <button
                    type="button"
                    :disabled="!canSendNotification"
                    :class="[notifyBtn, canSendNotification ? 'border-sky-300/60 bg-sky-50 text-sky-800 hover:bg-sky-100 dark:border-sky-400/20 dark:bg-sky-500/10 dark:text-sky-200' : '']"
                    title="Enviar aviso por correo"
                    @click="notifyEmail"
                  >
                    <Mail class="h-4 w-4" aria-hidden="true" />
                    Correo
                  </button>

                  <button
                    type="button"
                    :disabled="!canSendNotification"
                    :class="[notifyBtn, canSendNotification ? 'border-emerald-300/60 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-400/20 dark:bg-emerald-500/10 dark:text-emerald-200' : '']"
                    title="Abrir WhatsApp con el aviso escrito"
                    @click="notifyWhatsApp"
                  >
                    <MessageCircle class="h-4 w-4" aria-hidden="true" />
                    WhatsApp
                  </button>
                </div>
              </div>
            </section>

            <!-- Vista previa -->
            <section ref="previewWrapRef" :class="card" class="overflow-hidden" aria-labelledby="titulo-preview">
              <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-slate-200/70 dark:border-white/10">
                <div class="min-w-0">
                  <h2 id="titulo-preview" class="text-sm font-black text-slate-900 dark:text-neutral-100">Vista previa</h2>
                  <div v-if="preview" class="text-xs text-slate-500 dark:text-neutral-400 truncate" :title="previewTitle">
                    {{ previewTitle }}
                  </div>
                </div>

                <div v-if="preview" class="flex items-center gap-2 shrink-0">
                  <a
                    :href="preview.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    :class="[iconBtn, iconBtnNeutral]"
                    title="Abrir en otra pestaña"
                    aria-label="Abrir en otra pestaña"
                  >
                    <ExternalLink class="h-4 w-4" aria-hidden="true" />
                  </a>
                  <button type="button" :class="[iconBtn, iconBtnNeutral]" title="Cerrar vista previa" aria-label="Cerrar vista previa" @click="closePreview">
                    <X class="h-4 w-4" aria-hidden="true" />
                  </button>
                </div>
              </div>

              <div v-if="!preview" class="px-5 py-6 text-sm text-slate-500 dark:text-neutral-400">
                Haz clic en el nombre de un archivo, cargado o por subir, para verlo aquí.
              </div>

              <div v-else class="bg-slate-50/60 dark:bg-white/5">
                <div class="w-full h-[65vh] max-h-[760px]">
                  <iframe v-if="preview.kind === 'pdf'" :src="preview.url" class="w-full h-full block" style="border:0;" title="Vista previa del PDF" />
                  <div v-else-if="preview.kind === 'image'" class="w-full h-full flex items-center justify-center">
                    <img :src="preview.url" alt="Vista previa del comprobante" class="w-full h-full object-contain" />
                  </div>
                  <div v-else class="w-full h-full flex items-center justify-center p-6 text-center text-sm text-slate-600 dark:text-neutral-300">
                    Este tipo de archivo no se puede mostrar aquí. Ábrelo en otra pestaña.
                  </div>
                </div>
              </div>
            </section>
          </div>

          <!-- Carga + datos -->
          <div class="min-w-0 space-y-4">
            <section v-if="serverCan.subir" :class="card" aria-labelledby="titulo-carga">
              <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 py-4 border-b border-slate-200/70 dark:border-white/10">
                <h2 id="titulo-carga" class="text-lg font-black text-slate-900 dark:text-neutral-100">Cargar comprobantes</h2>
                <div class="text-sm text-slate-600 dark:text-neutral-300">
                  Falta por cargar
                  <span class="font-black tabular-nums text-slate-900 dark:text-neutral-100">{{ money(pendientePorCargarCents / 100) }}</span>
                </div>
              </div>

              <div class="p-5 space-y-4">
                <!-- Zona de arrastre -->
                <div v-if="motivoSinCarga" class="rounded-2xl border border-slate-200/70 bg-slate-50/70 px-4 py-4 text-sm font-semibold text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-neutral-300">
                  {{ motivoSinCarga }}
                </div>

                <div v-else>
                  <input
                    :key="fileKey"
                    id="comprobante-files"
                    type="file"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.xls,.docx,.doc"
                    class="peer sr-only"
                    :disabled="uploading"
                    @change="onPickFiles"
                  />
                  <label
                    for="comprobante-files"
                    class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-3xl border-2 border-dashed px-4 text-center
                           transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500/40"
                    :class="[
                      queue.length ? 'py-4' : 'py-9',
                      dragActive
                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10'
                        : 'border-slate-300 bg-slate-50/60 hover:border-indigo-400 hover:bg-indigo-50/50 dark:border-white/15 dark:bg-white/5 dark:hover:border-indigo-400/60',
                      uploading ? 'pointer-events-none opacity-60' : '',
                    ]"
                    @dragenter="onDragEnter"
                    @dragover="onDragOver"
                    @dragleave="onDragLeave"
                    @drop="onDropFiles"
                  >
                    <Upload class="h-7 w-7 text-indigo-600 dark:text-indigo-300" aria-hidden="true" />
                    <span class="text-base font-black text-slate-900 dark:text-neutral-100">
                      {{ dragActive ? 'Suelta los archivos aquí' : (queue.length ? 'Arrastra más archivos aquí' : 'Arrastra tus archivos aquí') }}
                    </span>
                    <span class="text-sm text-slate-600 dark:text-neutral-300">
                      o <span class="font-black text-indigo-700 underline dark:text-indigo-300">selecciónalos de tu equipo</span>. Puedes subir varios a la vez.
                    </span>
                    <span class="text-xs text-slate-500 dark:text-neutral-400">PDF, imagen, Excel o Word, hasta 10 MB cada uno</span>
                  </label>
                </div>

                <!-- Archivos por subir -->
                <ul v-if="queue.length" class="space-y-3">
                  <li
                    v-for="(item, i) in queue"
                    :key="item.uid"
                    class="rounded-2xl border p-3"
                    :class="item.error ? 'border-rose-300 bg-rose-50/50 dark:border-rose-500/30 dark:bg-rose-500/5' : 'border-slate-200/70 bg-white/70 dark:border-white/10 dark:bg-white/5'"
                  >
                    <div class="flex items-center gap-3">
                      <button
                        type="button"
                        class="relative h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-slate-200/70 bg-slate-100
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40
                               dark:border-white/10 dark:bg-white/10"
                        :title="item.kind === 'other' ? 'Este archivo no tiene vista previa' : 'Ver en vista previa'"
                        :aria-label="`Ver ${item.file.name} en vista previa`"
                        :disabled="item.kind === 'other'"
                        @click="openQueuePreview(item)"
                      >
                        <img v-if="item.kind === 'image'" :src="item.url" alt="" class="h-full w-full object-cover" />
                        <span v-else class="flex h-full w-full flex-col items-center justify-center text-slate-500 dark:text-neutral-300">
                          <FileText class="h-5 w-5" aria-hidden="true" />
                          <span class="text-[10px] font-black">{{ item.kind === 'pdf' ? 'PDF' : 'Archivo' }}</span>
                        </span>
                      </button>

                      <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-black text-slate-900 dark:text-neutral-100" :title="item.file.name">
                          {{ item.file.name }}
                        </div>
                        <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-neutral-400">
                          <span>{{ fmtSize(item.file.size) }}</span>
                          <button
                            v-if="item.kind !== 'other'"
                            type="button"
                            class="inline-flex items-center gap-1 font-bold text-indigo-700 hover:underline dark:text-indigo-300"
                            @click="openQueuePreview(item)"
                          >
                            <Eye class="h-3.5 w-3.5" aria-hidden="true" />
                            Ver
                          </button>
                        </div>
                      </div>

                      <button
                        type="button"
                        :class="[iconBtn, iconBtnNeutral]"
                        :disabled="uploading"
                        title="Quitar archivo"
                        :aria-label="`Quitar ${item.file.name}`"
                        @click="removeItem(item.uid)"
                      >
                        <X class="h-4 w-4" aria-hidden="true" />
                      </button>
                    </div>

                    <!-- Los tres campos comparten altura y etiqueta de una línea -->
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                      <div class="min-w-0">
                        <label :for="`monto-${item.uid}`" :class="fieldLabel">Monto que ampara</label>
                        <div class="relative">
                          <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-black text-slate-400 dark:text-neutral-500" aria-hidden="true">$</span>
                          <input
                            :id="`monto-${item.uid}`"
                            v-model="item.monto"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            :disabled="uploading"
                            :class="[field, itemSinMonto(item) || queueExcede ? 'border-rose-300 dark:border-rose-500/40' : '']"
                            class="pl-7 tabular-nums"
                            @input="onItemMontoInput(item)"
                          />
                        </div>
                      </div>

                      <div class="min-w-0">
                        <label :for="`tipo-${item.uid}`" :class="fieldLabel">Tipo</label>
                        <select :id="`tipo-${item.uid}`" v-model="item.tipo_doc" :disabled="uploading" :class="field">
                          <option v-for="t in props.tipoDocOptions" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                        </select>
                      </div>

                      <div class="min-w-0">
                        <label :for="`fecha-${item.uid}`" :class="fieldLabel">Fecha del comprobante</label>
                        <input :id="`fecha-${item.uid}`" v-model="item.fecha_emision" type="date" :disabled="uploading" :class="field" />
                      </div>
                    </div>

                    <p v-if="item.error" class="mt-2 text-xs font-bold text-rose-700 dark:text-rose-300" role="alert">
                      {{ item.error }}
                    </p>
                    <p v-else-if="itemSinMonto(item)" class="mt-2 text-xs font-semibold text-rose-700 dark:text-rose-300">
                      Escribe el monto que ampara este archivo{{ i > 0 ? ' o quítalo de la lista' : '' }}.
                    </p>
                  </li>
                </ul>

                <!-- Totales de la cola + subir -->
                <div v-if="queue.length" class="space-y-3">
                  <div
                    class="rounded-2xl border px-4 py-3 text-sm"
                    :class="queueExcede
                      ? 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200'
                      : 'border-slate-200/70 bg-slate-50/70 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-neutral-200'"
                    aria-live="polite"
                  >
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                      <span>
                        Suma de {{ queue.length === 1 ? 'este archivo' : `estos ${queue.length} archivos` }}:
                        <span class="font-black tabular-nums">{{ money(queueCents / 100) }}</span>
                      </span>
                      <span v-if="!queueExcede">
                        Después quedará por cargar
                        <span class="font-black tabular-nums">{{ money(restanteTrasCents / 100) }}</span>
                      </span>
                    </div>
                    <div v-if="queueExcede" class="mt-1 font-bold">
                      La suma supera lo que falta por cargar ({{ money(pendientePorCargarCents / 100) }}). Baja algún monto o quita un archivo.
                      <template v-if="serverCan.solicitar_ajuste">
                        Si la requisición cambió de monto,
                        <Link :href="route('requisiciones.ajustes', req?.id)" class="underline font-black">solicita un ajuste</Link>.
                      </template>
                    </div>
                  </div>

                  <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                      type="button"
                      class="inline-flex h-11 items-center rounded-2xl border border-slate-200/70 bg-white px-4 text-sm font-black text-slate-700
                             hover:bg-slate-50 transition active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed
                             dark:border-white/10 dark:bg-white/10 dark:text-neutral-100 dark:hover:bg-white/15"
                      :disabled="uploading"
                      @click="clearQueue"
                    >
                      Quitar todos
                    </button>
                    <button
                      type="button"
                      class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 text-sm font-black text-white
                             hover:bg-indigo-700 transition active:scale-[0.98]
                             focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/50 focus-visible:ring-offset-2
                             disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500
                             dark:disabled:bg-white/10 dark:disabled:text-neutral-500"
                      :disabled="!canSubmit"
                      :title="queueIncompleta ? 'Completa monto, tipo y fecha de cada archivo' : undefined"
                      @click="submitAll"
                    >
                      <Upload class="h-4 w-4" aria-hidden="true" />
                      {{ textoSubir }}
                    </button>
                  </div>
                </div>
              </div>
            </section>

            <!-- Ajustes -->
            <div
              v-if="!isFinalizada && serverCan.solicitar_ajuste"
              :class="card"
              class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
            >
              <div class="min-w-0">
                <p class="text-sm font-black text-slate-900 dark:text-white">¿Cambió el monto de tu requisición?</p>
                <p class="text-sm text-slate-600 dark:text-neutral-300">Solicita un ajuste en lugar de editarla; queda registrado.</p>
              </div>
              <Link
                :href="route('requisiciones.ajustes', req.id)"
                class="inline-flex h-10 shrink-0 items-center rounded-2xl bg-brand-button px-4 text-sm font-black text-brand-button-fg
                       hover:bg-brand-button/90 transition active:scale-[0.98]"
              >
                Ir a Ajustes
              </Link>
            </div>

            <!-- Datos para facturación -->
            <section :class="card" class="p-5" aria-labelledby="titulo-facturacion">
              <h2 id="titulo-facturacion" class="text-sm font-black text-slate-900 dark:text-neutral-100">Datos para facturación</h2>
              <p class="text-xs text-slate-500 dark:text-neutral-400">Pide tus facturas a nombre de:</p>

              <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <div class="min-w-0 sm:col-span-2">
                  <dt class="text-xs font-bold text-slate-500 dark:text-neutral-400">Razón social</dt>
                  <dd class="font-semibold text-slate-900 dark:text-neutral-100 break-words">{{ req?.razon_social || '—' }}</dd>
                </div>
                <div class="min-w-0">
                  <dt class="text-xs font-bold text-slate-500 dark:text-neutral-400">RFC</dt>
                  <dd class="font-semibold text-slate-900 dark:text-neutral-100 break-words">{{ req?.rfc || '—' }}</dd>
                </div>
                <div class="min-w-0">
                  <dt class="text-xs font-bold text-slate-500 dark:text-neutral-400">Teléfono</dt>
                  <dd class="font-semibold text-slate-900 dark:text-neutral-100 break-words">{{ req?.telefono || '—' }}</dd>
                </div>
                <div class="min-w-0 sm:col-span-2">
                  <dt class="text-xs font-bold text-slate-500 dark:text-neutral-400">Dirección</dt>
                  <dd class="font-semibold text-slate-900 dark:text-neutral-100 break-words">{{ req?.direccion || '—' }}</dd>
                </div>
                <div class="min-w-0 sm:col-span-2">
                  <dt class="text-xs font-bold text-slate-500 dark:text-neutral-400">Correo</dt>
                  <dd class="font-semibold text-slate-900 dark:text-neutral-100 break-all">{{ req?.correo || '—' }}</dd>
                </div>
              </dl>
            </section>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
