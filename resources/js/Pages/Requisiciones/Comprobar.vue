<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DatePickerShadcn from '@/Components/ui/DatePickerShadcn.vue'
import SearchableSelect from '@/Components/ui/SearchableSelect.vue'

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

  // roles/perms
  role,
  canDelete,
  canReview,
  canUseFoliosPanel,
  canEditFolio,

  // review actions
  approve,
  reject,
  destroyComprobante,

  // upload form + UX
  form,
  fileKey,
  dragActive,
  pickedName,
  hasPicked,
  clearFile,
  onPickFile,
  onDragEnter,
  onDragOver,
  onDragLeave,
  onDropFile,
  canSubmit,
  doSubmit,
  inputBase,

  // pending calc
  pendienteCents,

  // local preview (before upload)
  uploadPreview,

  // preview (existing uploaded docs)
  preview,
  previewTitle,
  openPreview,
  closePreview,
  previewWrapRef,

  // tipo dropdown
  tipoOpen,
  tipoWrap,
  tipoOptions,
  tipoSelected,
  setTipo,

  // folios panel
  foliosOpen,
  toggleFoliosOpen,
  folioSelectedId,
  folioSelected,
  addFolio,
  editFolio,

  // notifications (COLABORADOR)
  canNotify,
  notifyWhatsApp,
  notifyEmail,
} = useRequisicionComprobar(props)
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
        <!-- TOP BANNER -->
        <div
          class="rounded-3xl overflow-hidden border border-slate-200/70 dark:border-white/10 shadow-sm
                 bg-white/90 dark:bg-neutral-950/40 backdrop-blur"
        >
          <div
            class="px-5 py-4
                   bg-gradient-to-r from-indigo-500/10 via-transparent to-emerald-500/10
                   dark:from-indigo-500/15 dark:to-emerald-500/10"
          >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <div class="text-xs font-black text-slate-600 dark:text-neutral-300">
                  Folio
                </div>
                <div class="text-base sm:text-lg font-black text-slate-900 dark:text-white truncate">
                  {{ req?.folio || '—' }}
                </div>
                <div class="mt-1 text-xs text-slate-600 dark:text-neutral-300">
                  Pendiente por comprobar:
                  <span class="font-black text-slate-900 dark:text-white">
                    {{ money(pendienteCents / 100) }}
                  </span>
                </div>
              </div>

              <!-- acciones -->
              <div class="flex flex-wrap items-center gap-2">
                <button
                  v-if="canUseFoliosPanel"
                  type="button"
                  class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-black
                         border border-slate-200/70 bg-white/70 text-slate-900
                         hover:bg-white hover:shadow-sm hover:-translate-y-[1px]
                         transition active:scale-[0.98]
                         dark:border-white/10 dark:bg-white/10 dark:text-white dark:hover:bg-white/15"
                  @click="toggleFoliosOpen"
                >
                  <Search class="h-4 w-4" />
                  Buscar folios
                </button>

                <button
                  v-if="canNotify"
                  type="button"
                  class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-black
                         border border-emerald-300/30 bg-emerald-500/10 text-slate-900
                         hover:bg-emerald-500/15 hover:shadow-sm hover:-translate-y-[1px]
                         transition active:scale-[0.98]
                         dark:border-emerald-400/20 dark:bg-emerald-500/10 dark:text-white dark:hover:bg-emerald-500/15"
                  @click="notifyWhatsApp"
                >
                  <MessageCircle class="h-4 w-4" />
                  WhatsApp
                </button>

                <button
                  v-if="canNotify"
                  type="button"
                  class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-black
                         border border-sky-300/30 bg-sky-500/10 text-slate-900
                         hover:bg-sky-500/15 hover:shadow-sm hover:-translate-y-[1px]
                         transition active:scale-[0.98]
                         dark:border-sky-400/20 dark:bg-sky-500/10 dark:text-white dark:hover:bg-sky-500/15"
                  @click="notifyEmail"
                >
                  <Mail class="h-4 w-4" />
                  Correo
                </button>
              </div>
            </div>

            <!-- PANEL INLINE: FOLIOS -->
            <div v-if="foliosOpen && canUseFoliosPanel" class="mt-4">
              <div class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/70 dark:bg-white/5 backdrop-blur p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                  <div class="min-w-0">
                    <div class="text-sm font-black text-slate-900 dark:text-white">
                      Gestión de folios
                    </div>
                    <div class="text-xs text-slate-600 dark:text-neutral-300">
                      Selecciona un folio y vincúlalo sin romper trazabilidad.
                    </div>
                  </div>

                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-black
                           bg-slate-900 text-white hover:bg-slate-800 hover:shadow-sm hover:-translate-y-[1px]
                           transition active:scale-[0.98]
                           dark:bg-white dark:text-slate-900 dark:hover:bg-neutral-200"
                    @click="addFolio"
                  >
                    <Plus class="h-4 w-4" />
                    Agregar
                  </button>
                </div>

                <div class="mt-3 grid grid-cols-1 lg:grid-cols-12 gap-3 items-start">
                  <div class="lg:col-span-9">
                    <div class="flex items-start gap-2">
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
                        class="mt-[22px] inline-flex items-center gap-2 rounded-2xl px-4 py-3 text-sm font-black
                               border border-slate-200/70 bg-white/70 text-slate-900
                               hover:bg-white hover:shadow-sm hover:-translate-y-[1px]
                               transition active:scale-[0.98]
                               dark:border-white/10 dark:bg-white/10 dark:text-white dark:hover:bg-white/15
                               disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:shadow-none disabled:hover:translate-y-0"
                        :disabled="!folioSelectedId"
                        @click="editFolio"
                        title="Editar folio seleccionado"
                      >
                        <Pencil class="h-4 w-4" />
                        Editar
                      </button>
                    </div>

                    <div v-if="folioSelected" class="mt-2 text-xs text-slate-600 dark:text-neutral-300">
                      Seleccionado:
                      <span class="font-black text-slate-900 dark:text-white">{{ folioSelected.folio }}</span>
                      <span class="opacity-80"> · Monto:</span>
                      <span class="font-black text-slate-900 dark:text-white">{{ money(folioSelected.monto_total ?? 0) }}</span>
                    </div>
                  </div>

                  <div class="lg:col-span-3">
                    <div class="rounded-2xl border border-slate-200/70 dark:border-white/10 bg-white/70 dark:bg-white/5 p-3">
                      <div class="text-[11px] font-black text-slate-600 dark:text-neutral-300">
                        Contexto
                      </div>
                      <div class="mt-1 text-xs text-slate-600 dark:text-neutral-300">
                        Usa “Editar” solo si el folio seleccionado requiere ajuste documental.
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- /folios -->
          </div>
        </div>

        <!-- GRID MAESTRO -->
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start min-w-0">
          <!-- LEFT -->
          <div class="xl:col-span-8 2xl:col-span-7 min-w-0 space-y-4">
            <!-- Cards info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 min-w-0">
              <div class="h-full min-w-0 rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/70 backdrop-blur shadow-sm p-5">
                <div class="text-xs font-black text-slate-500 dark:text-neutral-300">
                  DATOS DE LA REQUISICIÓN
                </div>

                <div class="mt-3 grid gap-2 text-sm">
                  <div class="text-slate-900 dark:text-neutral-100 break-words">
                    <span class="font-black text-slate-700 dark:text-neutral-200">Solicitante: </span>
                    <span class="font-semibold"> {{ req?.solicitante_nombre || '—' }}</span>
                  </div>

                  <div class="text-slate-900 dark:text-neutral-100 break-words">
                    <span class="font-black text-slate-700 dark:text-neutral-200">Concepto: </span>
                    <span class="font-semibold"> {{ req?.concepto || '—' }}</span>
                  </div>

                  <div class="text-slate-900 dark:text-neutral-100">
                    <span class="font-black text-slate-700 dark:text-neutral-200">Cantidad a comprobar: </span>
                    <span class="font-black"> {{ money(req?.monto_total) }}</span>
                  </div>
                </div>
              </div>

              <div class="h-full min-w-0 rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/70 backdrop-blur shadow-sm p-5">
                <div class="text-xs font-black text-slate-500 dark:text-neutral-300">
                  DATOS PARA FACTURACIÓN
                </div>

                <div class="mt-3 grid gap-2 text-sm">
                  <div class="text-slate-900 dark:text-neutral-100 break-words">
                    <span class="font-black text-slate-700 dark:text-neutral-200">Nombre: </span>
                    <span class="font-semibold"> {{ req?.razon_social || '—' }}</span>
                  </div>

                  <div class="text-slate-900 dark:text-neutral-100 break-words">
                    <span class="font-black text-slate-700 dark:text-neutral-200">RFC: </span>
                    <span class="font-semibold"> {{ req?.rfc || '—' }}</span>
                  </div>

                  <div class="text-slate-900 dark:text-neutral-100 break-words">
                    <span class="font-black text-slate-700 dark:text-neutral-200">Dirección: </span>
                    <span class="font-semibold"> {{ req?.direccion || '—' }}</span>
                  </div>

                  <div class="text-slate-900 dark:text-neutral-100 break-words">
                    <span class="font-black text-slate-700 dark:text-neutral-200">Teléfono: </span>
                    <span class="font-semibold"> {{ req?.telefono || '—' }}</span>
                  </div>

                  <div class="text-slate-900 dark:text-neutral-100 break-words">
                    <span class="font-black text-slate-700 dark:text-neutral-200">Email: </span>
                    <span class="font-semibold break-all"> {{ req?.correo || '—' }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Relación + form -->
            <div class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/70 backdrop-blur shadow-sm overflow-visible">
              <div class="px-5 py-4 border-b border-slate-200/70 dark:border-white/10">
                <div class="text-lg font-black text-slate-900 dark:text-neutral-100">
                  Relación de comprobaciones de esta requisición
                </div>
                <div class="text-sm text-slate-500 dark:text-neutral-300">
                  Cada comprobante se revisa de forma independiente. Rechazar uno no tumba la requisición.
                </div>
              </div>

              <!-- Table 2XL+ -->
              <div class="hidden 2xl:block">
                <table class="w-full table-fixed">
                  <thead class="bg-slate-50/80 dark:bg-neutral-950/40">
                    <tr class="text-left text-[12px] font-black text-slate-600 dark:text-neutral-300">
                      <th class="px-5 py-3 w-[170px]">Fecha</th>
                      <th class="px-5 py-3 w-[110px]">Tipo</th>
                      <th class="px-5 py-3 w-[120px]">Monto</th>
                      <th class="px-5 py-3">Archivo</th>
                      <th class="px-5 py-3 w-[180px]">Estatus</th>
                      <th v-if="canReview" class="px-5 py-3 w-[140px] text-right">Acciones</th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr
                      v-for="c in rows"
                      :key="c.id"
                      class="border-t border-slate-200/70 dark:border-white/10
                             hover:bg-slate-50/70 dark:hover:bg-white/5 transition"
                    >
                      <td class="px-5 py-3 text-sm text-slate-800 dark:text-neutral-100 align-top">
                        {{ fmtLong(c.fecha_emision) }}
                      </td>

                      <td class="px-5 py-3 text-sm text-slate-800 dark:text-neutral-100 align-top">
                        {{ tipoDocLabel(c.tipo_doc) }}
                      </td>

                      <td class="px-5 py-3 text-sm font-black text-slate-900 dark:text-neutral-100 align-top">
                        {{ money(c.monto) }}
                      </td>

                      <td class="px-5 py-3 text-sm align-top min-w-0">
                        <div class="flex items-center gap-2 min-w-0">
                          <button
                            v-if="c.archivo?.url"
                            type="button"
                            class="inline-flex items-center gap-2 font-black
                                   text-indigo-700 hover:text-indigo-800
                                   dark:text-indigo-300 dark:hover:text-indigo-200
                                   min-w-0 transition hover:-translate-y-[1px] active:translate-y-0"
                            title="Previsualizar aquí"
                            @click="openPreview(c)"
                          >
                            <FileText class="h-4 w-4 shrink-0" />
                            <span class="truncate max-w-[340px]">{{ c.archivo.label || 'Ver archivo' }}</span>
                          </button>

                          <a
                            v-if="c.archivo?.url"
                            :href="c.archivo.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center h-9 w-9 rounded-2xl
                                   border border-slate-200/70 bg-white
                                   hover:bg-slate-50 hover:shadow-sm
                                   transition active:scale-[0.98] shrink-0
                                   dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                            title="Abrir en otra pestaña"
                          >
                            <ExternalLink class="h-4 w-4 text-slate-700 dark:text-neutral-100" />
                          </a>

                          <span v-if="!c.archivo?.url" class="text-slate-500 dark:text-neutral-400">—</span>
                        </div>
                      </td>

                      <td class="px-5 py-3 align-top">
                        <div class="flex items-center gap-2">
                          <div
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[12px] font-black"
                            :class="estatusPillClass(c.estatus)"
                          >
                            <span
                              class="h-2 w-2 rounded-full"
                              :class="c.estatus === 'APROBADO'
                                ? 'bg-emerald-500'
                                : c.estatus === 'RECHAZADO'
                                ? 'bg-rose-500'
                                : 'bg-slate-400'"
                            />
                            {{ estatusLabel(c.estatus) }}
                          </div>

                          <button
                            v-if="canDelete"
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-2xl
                                   border border-slate-200/70 bg-white
                                   hover:bg-slate-50 hover:shadow-sm
                                   dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15
                                   transition active:scale-[0.98]"
                            title="Eliminar comprobante (BD)"
                            @click="destroyComprobante(c.id)"
                          >
                            <Trash2 class="h-4 w-4 text-slate-700 dark:text-neutral-100" />
                          </button>
                        </div>

                        <div
                          v-if="c.estatus === 'RECHAZADO' && c.comentario_revision"
                          class="mt-2 text-[12px] font-semibold text-rose-700 dark:text-rose-200 break-words"
                        >
                          Motivo: {{ c.comentario_revision }}
                        </div>
                      </td>

                      <td v-if="canReview" class="px-5 py-3 align-top">
                        <div class="flex items-center justify-end gap-2">
                          <button
                            type="button"
                            class="inline-flex items-center justify-center h-9 w-9 rounded-2xl
                                   border border-emerald-200 bg-emerald-50 hover:bg-emerald-100
                                   dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/15
                                   transition active:scale-[0.98]"
                            title="Aprobar"
                            @click="approve(c.id)"
                          >
                            <Check class="h-4 w-4 text-emerald-700 dark:text-emerald-200" />
                          </button>

                          <button
                            type="button"
                            class="inline-flex items-center justify-center h-9 w-9 rounded-2xl
                                   border border-rose-200 bg-rose-50 hover:bg-rose-100
                                   dark:border-rose-500/20 dark:bg-rose-500/10 dark:hover:bg-rose-500/15
                                   transition active:scale-[0.98]"
                            title="Rechazar"
                            @click="reject(c.id)"
                          >
                            <Ban class="h-4 w-4 text-rose-700 dark:text-rose-200" />
                          </button>
                        </div>
                      </td>
                    </tr>

                    <tr v-if="rows.length === 0">
                      <td :colspan="canReview ? 6 : 5" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-neutral-400">
                        Aún no hay comprobantes cargados.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Cards <2XL -->
              <div class="2xl:hidden p-4 space-y-3">
                <div
                  v-for="c in rows"
                  :key="c.id"
                  class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/90 dark:bg-neutral-950/30 p-4 shadow-sm
                         hover:shadow-md hover:-translate-y-[1px] transition min-w-0"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="text-sm font-black text-slate-900 dark:text-neutral-100">
                        {{ tipoDocLabel(c.tipo_doc) }} · {{ money(c.monto) }}
                      </div>
                      <div class="text-[12px] text-slate-500 dark:text-neutral-400">
                        {{ fmtLong(c.fecha_emision) }}
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <div
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[12px] font-black"
                        :class="estatusPillClass(c.estatus)"
                      >
                        <span
                          class="h-2 w-2 rounded-full"
                          :class="c.estatus === 'APROBADO'
                            ? 'bg-emerald-500'
                            : c.estatus === 'RECHAZADO'
                            ? 'bg-rose-500'
                            : 'bg-slate-400'"
                        />
                        {{ estatusLabel(c.estatus) }}
                      </div>

                      <button
                        v-if="canDelete"
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-2xl
                               border border-slate-200/70 bg-white
                               hover:bg-slate-50 hover:shadow-sm
                               dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15
                               transition active:scale-[0.98]"
                        title="Eliminar comprobante (BD)"
                        @click="destroyComprobante(c.id)"
                      >
                        <Trash2 class="h-4 w-4 text-slate-700 dark:text-neutral-100" />
                      </button>
                    </div>
                  </div>

                  <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-2xl border border-slate-200/60 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 p-3">
                      <div class="text-[11px] font-black text-slate-600 dark:text-neutral-300">Archivo</div>
                      <div class="mt-1 min-w-0 flex items-center justify-between gap-2">
                        <button
                          v-if="c.archivo?.url"
                          type="button"
                          class="inline-flex items-center gap-2 font-black
                                 text-indigo-700 hover:text-indigo-800
                                 dark:text-indigo-300 dark:hover:text-indigo-200
                                 min-w-0 transition hover:-translate-y-[1px] active:translate-y-0"
                          @click="openPreview(c)"
                        >
                          <FileText class="h-4 w-4 shrink-0" />
                          <span class="truncate">{{ c.archivo.label || 'Ver archivo' }}</span>
                        </button>

                        <a
                          v-if="c.archivo?.url"
                          :href="c.archivo.url"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="inline-flex items-center justify-center h-9 w-9 rounded-2xl
                                 border border-slate-200/70 bg-white
                                 hover:bg-slate-50 hover:shadow-sm
                                 transition active:scale-[0.98] shrink-0
                                 dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                          title="Abrir en otra pestaña"
                        >
                          <ExternalLink class="h-4 w-4" />
                        </a>

                        <span v-if="!c.archivo?.url" class="text-slate-500 dark:text-neutral-400">—</span>
                      </div>
                    </div>

                    <div v-if="canReview" class="rounded-2xl border border-slate-200/60 dark:border-white/10 bg-slate-50/70 dark:bg-white/5 p-3">
                      <div class="text-[11px] font-black text-slate-600 dark:text-neutral-300">Acciones</div>
                      <div class="mt-2 flex items-center justify-end gap-2">
                        <button
                          type="button"
                          class="inline-flex items-center justify-center h-10 w-10 rounded-2xl
                                 border border-emerald-200 bg-emerald-50 hover:bg-emerald-100
                                 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/15
                                 transition active:scale-[0.98]"
                          title="Aprobar"
                          @click="approve(c.id)"
                        >
                          <Check class="h-4 w-4 text-emerald-700 dark:text-emerald-200" />
                        </button>

                        <button
                          type="button"
                          class="inline-flex items-center justify-center h-10 w-10 rounded-2xl
                                 border border-rose-200 bg-rose-50 hover:bg-rose-100
                                 dark:border-rose-500/20 dark:bg-rose-500/10 dark:hover:bg-rose-500/15
                                 transition active:scale-[0.98]"
                          title="Rechazar"
                          @click="reject(c.id)"
                        >
                          <Ban class="h-4 w-4 text-rose-700 dark:text-rose-200" />
                        </button>
                      </div>
                    </div>
                  </div>

                  <div
                    v-if="c.estatus === 'RECHAZADO' && c.comentario_revision"
                    class="mt-3 text-[12px] font-semibold text-rose-700 dark:text-rose-200 break-words"
                  >
                    Motivo: {{ c.comentario_revision }}
                  </div>
                </div>

                <div v-if="rows.length === 0" class="px-2 py-10 text-center text-sm text-slate-500 dark:text-neutral-400">
                  Aún no hay comprobantes cargados.
                </div>
              </div>

              <!-- Form de carga -->
              <div class="p-5 border-t border-slate-200/70 dark:border-white/10">
                <div class="space-y-4">
                  <!-- Archivo -->
                  <div class="min-w-0">
                    <div class="flex items-end justify-between gap-3">
                      <label class="block text-xs font-black text-slate-600 dark:text-neutral-300">
                        Carga el comprobante
                      </label>

                      <div class="text-[12px] font-black text-slate-500 dark:text-neutral-300">
                        Pendiente:
                        <span class="text-slate-900 dark:text-neutral-100">
                          {{ money(pendienteCents / 100) }}
                        </span>
                      </div>
                    </div>

                    <div
                      class="mt-1 rounded-3xl border bg-white/80 dark:bg-neutral-950/40 p-3 select-none
                             transition duration-200 hover:shadow-sm hover:-translate-y-[1px] min-w-0"
                      :class="dragActive
                        ? 'border-indigo-400/60 ring-2 ring-indigo-500/20 dark:border-indigo-400/40'
                        : 'border-slate-200/70 dark:border-white/10'"
                      @dragenter="onDragEnter"
                      @dragover="onDragOver"
                      @dragleave="onDragLeave"
                      @drop="onDropFile"
                    >
                      <div class="flex items-center gap-3 min-w-0">
                        <input :key="fileKey" id="comprobante-file" type="file" class="sr-only" @change="onPickFile" />

                        <label
                          for="comprobante-file"
                          class="inline-flex items-center justify-center gap-2 rounded-2xl px-6 py-3 text-sm font-black
                                 bg-indigo-600 text-white hover:bg-indigo-700 hover:shadow-md
                                 transition active:scale-[0.98] cursor-pointer shrink-0"
                        >
                          <Upload class="h-4 w-4" />
                          Seleccionar archivo
                        </label>

                        <div class="min-w-0 flex-1">
                          <div
                            class="text-sm font-black truncate"
                            :class="hasPicked ? 'text-slate-900 dark:text-neutral-100' : 'text-slate-500 dark:text-neutral-400'"
                            :title="pickedName"
                          >
                            {{ pickedName }}
                          </div>
                          <div class="text-[12px] text-slate-500 dark:text-neutral-400">
                            {{ dragActive ? 'Suelta aquí para adjuntar.' : (hasPicked ? 'Listo para subir.' : 'Adjunta el comprobante correspondiente.') }}
                          </div>
                        </div>

                        <button
                          v-if="hasPicked"
                          type="button"
                          class="inline-flex items-center justify-center h-10 w-10 rounded-2xl
                                 border border-slate-200/70 bg-white
                                 hover:bg-slate-50 hover:shadow-sm
                                 dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15
                                 transition active:scale-[0.98] shrink-0"
                          title="Quitar archivo"
                          @click="clearFile"
                        >
                          <X class="h-4 w-4 text-slate-700 dark:text-neutral-100" />
                        </button>
                      </div>
                    </div>

                    <div v-if="form.errors.archivo" class="mt-1 text-xs font-bold text-rose-600">
                      {{ form.errors.archivo }}
                    </div>

                    <!-- PREVIEW antes de subir -->
                    <div
                      v-if="uploadPreview"
                      class="mt-3 rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/60 overflow-hidden"
                    >
                      <div class="px-4 py-3 border-b border-slate-200/70 dark:border-white/10 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                          <div class="text-xs font-black text-slate-500 dark:text-neutral-300">
                            PREVISUALIZACIÓN ANTES DE SUBIR
                          </div>
                          <div class="text-sm font-black text-slate-900 dark:text-neutral-100 truncate" :title="uploadPreview.name">
                            {{ uploadPreview.name }}
                          </div>
                        </div>
                      </div>

                      <div class="p-3">
                        <div class="rounded-3xl border border-slate-200/60 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 overflow-hidden">
                          <div class="h-[38vh] sm:h-[42vh] max-h-[420px]">
                            <iframe
                              v-if="uploadPreview.kind === 'pdf'"
                              :src="uploadPreview.url"
                              class="w-full h-full block"
                              style="border:0;"
                              title="Preview antes de subir"
                            />
                            <div v-else-if="uploadPreview.kind === 'image'" class="w-full h-full flex items-center justify-center">
                              <img :src="uploadPreview.url" alt="Preview" class="w-full h-full object-contain" />
                            </div>
                            <div v-else class="w-full h-full flex items-center justify-center p-6 text-center">
                              <div class="text-sm text-slate-600 dark:text-neutral-300">
                                Este archivo no tiene preview aquí. Igual se puede subir.
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-2 text-[12px] text-slate-500 dark:text-neutral-400">
                          Se subirá exactamente este archivo.
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Monto + tipo + fecha + submit -->
                  <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end min-w-0">
                    <div class="lg:col-span-3 min-w-0">
                      <div class="flex items-end justify-between gap-3">
                        <label class="block text-xs font-black text-slate-600 dark:text-neutral-300">
                          Monto por comprobar
                        </label>
                        <div class="text-[11px] font-black text-slate-500 dark:text-neutral-300">
                          Máx: {{ money(pendienteCents / 100) }}
                        </div>
                      </div>

                      <input
                        v-model="form.monto"
                        type="number"
                        step="0.01"
                        :class="inputBase"
                        class="mt-1"
                        placeholder="0.00"
                      />

                      <div
                        v-if="form.errors.monto"
                        class="mt-1 text-xs font-bold text-rose-600 flex flex-wrap items-center gap-x-2 gap-y-1"
                      >
                        <span>{{ form.errors.monto }}</span>
                        <span class="opacity-70">—</span>
                        <Link
                          :href="route('requisiciones.ajustes', req?.id)"
                          class="underline font-black hover:text-rose-700 dark:hover:text-rose-300"
                        >
                          Solicitar ajuste
                        </Link>
                      </div>
                    </div>

                    <!-- Tipo dropdown -->
                    <div class="lg:col-span-4 min-w-0 relative z-[40]" ref="tipoWrap">
                      <label class="block text-xs font-black text-slate-600 dark:text-neutral-300">
                        Tipo de comprobante
                      </label>

                      <button
                        type="button"
                        class="mt-1 w-full rounded-2xl border px-4 py-3 text-sm font-semibold text-left
                               bg-white/90 text-slate-900 hover:bg-slate-50 hover:shadow-sm
                               dark:bg-neutral-950/40 dark:text-neutral-100 dark:hover:bg-white/5
                               border-slate-200/70 dark:border-white/10
                               focus:outline-none focus:ring-2 focus:ring-indigo-500/25 focus:border-indigo-500/40
                               transition active:scale-[0.99]"
                        @click="tipoOpen = !tipoOpen"
                      >
                        <div class="flex items-center justify-between gap-2 min-w-0">
                          <span class="truncate">
                            {{ tipoSelected?.nombre ?? 'Selecciona tipo' }}
                          </span>
                          <svg
                            class="h-4 w-4 opacity-70 shrink-0 transition-transform duration-200"
                            :class="tipoOpen ? 'rotate-180' : ''"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                          >
                            <path
                              fill-rule="evenodd"
                              d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                              clip-rule="evenodd"
                            />
                          </svg>
                        </div>
                      </button>

                      <div v-if="tipoOpen" class="absolute left-0 right-0 bottom-full z-[120] mb-2">
                        <div
                          class="rounded-3xl border border-slate-200/80 bg-white shadow-2xl overflow-hidden
                                 dark:border-white/10 dark:bg-neutral-950
                                 animate-in fade-in-0 zoom-in-95 duration-150"
                        >
                          <div class="max-h-64 overflow-auto">
                            <button
                              v-for="t in tipoOptions"
                              :key="t.id"
                              type="button"
                              class="w-full px-4 py-3 text-left text-sm font-semibold transition
                                     hover:bg-slate-50 dark:hover:bg-white/5 flex items-center justify-between gap-2"
                              :class="String(form.tipo_doc) === String(t.id)
                                ? 'bg-indigo-50 text-indigo-800 dark:bg-indigo-500/10 dark:text-indigo-200'
                                : 'text-slate-900 dark:text-neutral-100'"
                              @click="setTipo(String(t.id))"
                            >
                              <span class="truncate">{{ t.nombre }}</span>
                              <span v-if="String(form.tipo_doc) === String(t.id)" class="text-[12px] font-black">✓</span>
                            </button>
                          </div>
                        </div>
                      </div>

                      <div v-if="form.errors.tipo_doc" class="mt-1 text-xs font-bold text-rose-600">
                        {{ form.errors.tipo_doc }}
                      </div>
                    </div>

                    <!-- Fecha -->
                    <div class="lg:col-span-3 min-w-0 relative z-[30]">
                      <label class="block text-xs font-black text-slate-600 dark:text-neutral-300">
                        Fecha del comprobante
                      </label>

                      <DatePickerShadcn v-model="form.fecha_emision" placeholder="Selecciona fecha" />

                      <div v-if="form.errors.fecha_emision" class="mt-1 text-xs font-bold text-rose-600">
                        {{ form.errors.fecha_emision }}
                      </div>
                    </div>

                    <!-- Submit -->
                    <div class="lg:col-span-2 min-w-0">
                      <button
                        type="button"
                        @click="doSubmit"
                        :disabled="!canSubmit"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-black
                               bg-gradient-to-r from-slate-900 to-slate-950 text-white
                               hover:shadow-md hover:-translate-y-[1px]
                               transition active:scale-[0.98]
                               dark:from-white dark:to-neutral-200 dark:text-slate-900
                               disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:shadow-none disabled:hover:translate-y-0"
                      >
                        <Upload class="h-4 w-4" />
                        Subir
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <!-- /form -->
            </div>
          </div>

          <!-- RIGHT -->
          <div ref="previewWrapRef" class="xl:col-span-4 2xl:col-span-5 min-w-0 space-y-4">
            <!-- CTA Ajustes (bien puesto, no “colgando”) -->
            <div class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/70 backdrop-blur shadow-sm p-4">
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="font-black text-slate-900 dark:text-white">
                    ¿Hubo algún cambio en el monto de tu requisición?
                  </p>
                  <p class="text-sm text-slate-600 dark:text-neutral-300">
                    Solicita un ajuste para mantener auditoría y evitar ediciones directas de la requisición.
                  </p>
                </div>

                <Link
                  :href="route('requisiciones.ajustes', req.id)"
                  class="shrink-0 inline-flex items-center justify-center rounded-2xl px-4 py-2.5 text-sm font-black
                         bg-slate-900 text-white hover:bg-slate-800 hover:shadow-sm hover:-translate-y-[1px]
                         transition active:scale-[0.98]
                         dark:bg-white dark:text-slate-900 dark:hover:bg-neutral-200"
                >
                  Ir a Ajustes
                </Link>
              </div>
            </div>

            <!-- PREVIEW sticky -->
            <div class="xl:sticky xl:top-6">
              <div class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/70 backdrop-blur shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200/70 dark:border-white/10">
                  <div class="flex items-start justify-between gap-3 min-w-0">
                    <div class="min-w-0">
                      <div class="text-xs font-black text-slate-500 dark:text-neutral-300">VISTA PREVIA</div>
                      <div class="text-sm font-black text-slate-900 dark:text-neutral-100 truncate" :title="previewTitle">
                        {{ previewTitle }}
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <a
                        v-if="preview?.url"
                        :href="preview.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black
                               border border-slate-200/70 bg-white hover:bg-slate-50 hover:shadow-sm
                               transition active:scale-[0.98]
                               dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                        title="Abrir en otra pestaña"
                      >
                        <ExternalLink class="h-4 w-4" />
                        Abrir
                      </a>

                      <button
                        v-if="preview"
                        type="button"
                        class="inline-flex items-center justify-center h-9 w-9 rounded-2xl
                               border border-slate-200/70 bg-white hover:bg-slate-50 hover:shadow-sm
                               transition active:scale-[0.98]
                               dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                        title="Cerrar vista previa"
                        @click="closePreview"
                      >
                        <X class="h-4 w-4 text-slate-700 dark:text-neutral-100" />
                      </button>
                    </div>
                  </div>
                </div>

                <div class="p-4">
                  <div
                    class="rounded-3xl border border-slate-200/60 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 overflow-hidden"
                    :class="preview ? 'p-0' : 'p-4'"
                  >
                    <div v-if="!preview" class="text-sm text-slate-600 dark:text-neutral-300">
                      Da clic en el nombre del archivo en la lista para previsualizar aquí mismo.
                    </div>

                    <div v-else class="w-full">
                      <div class="w-full h-[70vh] sm:h-[75vh] xl:h-[calc(100vh-260px)] max-h-[820px] xl:max-h-none">
                        <iframe
                          v-if="preview.kind === 'pdf'"
                          :src="preview.url"
                          class="w-full h-full block"
                          style="border:0;"
                          title="Vista previa PDF"
                        />
                        <div v-else-if="preview.kind === 'image'" class="w-full h-full flex items-center justify-center">
                          <img :src="preview.url" alt="Vista previa" class="w-full h-full object-contain" />
                        </div>
                        <div v-else class="w-full h-full flex items-center justify-center p-6 text-center">
                          <div class="text-sm text-slate-600 dark:text-neutral-300">
                            No puedo previsualizar este tipo de archivo aquí. Usa “Abrir” para verlo en otra pestaña.
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-if="preview?.url" class="mt-3 text-[12px] text-slate-500 dark:text-neutral-400">
                    Tip: si el preview no carga por políticas del navegador, abre en otra pestaña.
                  </div>
                </div>
              </div>
            </div>
            <!-- /preview -->
          </div>
        </div>
        <!-- /grid -->
      </div>
    </div>
  </AuthenticatedLayout>
</template>
