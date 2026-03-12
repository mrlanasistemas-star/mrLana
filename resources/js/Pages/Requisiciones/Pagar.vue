<!-- resources/js/Pages/Requisiciones/Pagar.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DatePickerShadcn from '@/Components/ui/DatePickerShadcn.vue'

import { ArrowLeft, Upload, FileText, X } from 'lucide-vue-next'
import type { RequisicionPagoPageProps } from './Pagar.types'
import { useRequisicionPago } from './useRequisicionPago'
import Swal from 'sweetalert2'

declare const route: any

const props = defineProps<RequisicionPagoPageProps>()

const {
  req,
  pagos,
  pendiente,
  money,
  fmtLong,

  form,
  submitting,

  fileKey,
  dragActive,
  pickedName,
  hasPicked,
  clearFile,
  onPickFile,
  onDropFile,
  onDragEnter,
  onDragOver,
  onDragLeave,

  uploadPreview,

  montoText,
  onMontoInput,
  onMontoBlur,

  canSubmit,
  submit,

  // preview pagos ya hechos
  preview,
  previewTitle,
  openPreview,
  closePreview,
} = useRequisicionPago(props)

const tot = computed(() => (props as any).totales ?? { pagado: 0, pendiente: 0 })

// Obtiene datos del usuario autenticado para determinar permisos
const page = usePage<any>()
const role = computed(() => String(page.props?.auth?.user?.rol ?? 'COLABORADOR').toUpperCase())
// Solo ADMIN o CONTADOR pueden autorizar cuando la requisición está capturada
const canAuthorize = computed(() =>
  ['ADMIN','CONTADOR'].includes(role.value) && req.value?.status === 'CAPTURADA'
)
// Solo ADMIN o CONTADOR pueden subir pagos
const canUploadPago = computed(() =>
  ['ADMIN','CONTADOR'].includes(role.value)
)

// Fecha de pago para autorizar y función para llamar la ruta
const fechaAutorizacion = ref<string>('')
function authorizePago() {
  if (!req.value?.id) return
  if (!fechaAutorizacion.value) {
    Swal.fire({
      title: 'Fecha requerida',
      text: 'Debes elegir una fecha de pago antes de autorizar.',
      icon: 'error',
    })
    return
  }
  router.post(
    route('requisiciones.autorizarPago', { requisicion: req.value.id }),
    { fecha_pago: fechaAutorizacion.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        fechaAutorizacion.value = ''
        Swal.fire({
          title: 'Pago autorizado',
          text: 'La fecha de pago ha sido guardada y se notificará al solicitante.',
          icon: 'success',
          timer: 2000,
          showConfirmButton: false,
        })
        // Recarga la página o sólo las props necesarias para que req.value.status se actualice
        router.reload({ only: ['requisicion', 'pagos', 'totales'] })
      },
      onError: () => {
        Swal.fire({
          title: 'Error al autorizar',
          text: 'Ocurrió un problema al autorizar el pago.',
          icon: 'error',
        })
      },
    }
  )
}

// Estilos base para inputs
const inputBase =
  'w-full rounded-2xl border border-slate-200/70 bg-white/90 px-4 py-3 text-sm font-semibold text-slate-900 ' +
  'placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500/40 ' +
  'dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 dark:placeholder:text-neutral-500'

const canSelectFile = computed(() => {
  return (
    String(req.value?.status ?? '').toUpperCase() === 'PAGO_AUTORIZADO' ||
    !!req.value?.fecha_autorizacion
  )
})
</script>

<template>
  <Head title="Pagar requisición" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3 min-w-0">
        <Link
          :href="route('requisiciones.index')"
          class="inline-flex items-center justify-center h-10 w-10 rounded-2xl border border-slate-200 bg-white hover:bg-slate-50
                 dark:border-white/10 dark:bg-neutral-900 dark:hover:bg-white/10 transition active:scale-[0.98]"
          title="Volver"
        >
          <ArrowLeft class="h-5 w-5 text-slate-900 dark:text-neutral-100" />
        </Link>

        <div class="min-w-0">
          <div class="text-xl font-black text-slate-900 dark:text-neutral-100 truncate">Pagar</div>
        </div>
      </div>
    </template>

    <div class="w-full min-w-0 flex-1 overflow-x-hidden">
      <div class="mx-auto w-full min-w-0 max-w-[1900px] px-3 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-4">

        <!-- Resumen -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <!-- Datos de la requisición -->
          <div class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/70 backdrop-blur shadow-sm p-5">
            <div class="text-xs font-black text-slate-500 dark:text-neutral-300">DATOS DE LA REQUISICIÓN</div>

            <div class="mt-3 space-y-2 text-sm">
              <div>
                <span class="font-black text-slate-700 dark:text-neutral-200">Solicitante:</span>
                <span class="text-slate-900 dark:text-neutral-100"> {{ req?.solicitante_nombre || '—' }}</span>
              </div>

              <div>
                <span class="font-black text-slate-700 dark:text-neutral-200">Concepto:</span>
                <span class="text-slate-900 dark:text-neutral-100"> {{ req?.concepto || '—' }}</span>
              </div>

              <div>
                <span class="font-black text-slate-700 dark:text-neutral-200">Cantidad a pagar:</span>
                <span class="text-slate-900 dark:text-neutral-100"> {{ money(req?.monto_total) }}</span>
              </div>

              <div class="pt-2 grid grid-cols-2 gap-3">
                <div class="rounded-2xl border border-slate-200/60 dark:border-white/10 bg-slate-50/70 dark:bg-neutral-950/40 p-3">
                  <div class="text-[11px] font-black text-slate-500 dark:text-neutral-400">Pagado</div>
                  <div class="text-sm font-black text-slate-900 dark:text-neutral-100">{{ money(tot.pagado) }}</div>
                </div>

                <div class="rounded-2xl border border-slate-200/60 dark:border-white/10 bg-slate-50/70 dark:bg-neutral-950/40 p-3">
                  <div class="text-[11px] font-black text-slate-500 dark:text-neutral-400">Pendiente</div>
                  <div class="text-sm font-black text-slate-900 dark:text-neutral-100">{{ money(tot.pendiente) }}</div>
                </div>
              </div>
            </div>

            <!-- Sección para autorizar pago -->
            <div v-if="canAuthorize" class="mt-4 p-4 border-t border-slate-200/70 dark:border-white/10">
              <label class="block text-xs font-black text-slate-600 dark:text-neutral-300">Fecha de pago (autorización)</label>
              <DatePickerShadcn v-model="fechaAutorizacion" placeholder="Selecciona fecha" />

              <button
                type="button"
                class="mt-3 w-full inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-black
                       bg-indigo-600 text-white hover:bg-indigo-700 transition"
                @click="authorizePago"
              >
                Autorizar pago
              </button>
            </div>
          </div>

          <!-- Datos del beneficiario -->
          <div class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/70 backdrop-blur shadow-sm p-5">
            <div class="text-xs font-black text-slate-500 dark:text-neutral-300">DATOS DEL BENEFICIARIO</div>

            <div class="mt-3 space-y-2 text-sm">
              <div>
                <span class="font-black text-slate-700 dark:text-neutral-200">Beneficiario:</span>
                <span class="text-slate-900 dark:text-neutral-100"> {{ req?.beneficiario?.nombre || '—' }}</span>
              </div>
              <div>
                <span class="font-black text-slate-700 dark:text-neutral-200">RFC:</span>
                <span class="text-slate-900 dark:text-neutral-100"> {{ req?.beneficiario?.rfc || '—' }}</span>
              </div>
              <div>
                <span class="font-black text-slate-700 dark:text-neutral-200">Clabe:</span>
                <span class="text-slate-900 dark:text-neutral-100 break-all"> {{ req?.beneficiario?.clabe || '—' }}</span>
              </div>
              <div>
                <span class="font-black text-slate-700 dark:text-neutral-200">Banco:</span>
                <span class="text-slate-900 dark:text-neutral-100"> {{ req?.beneficiario?.banco || '—' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Relación de pagos -->
        <div class="rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/70 backdrop-blur shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200/70 dark:border-white/10">
            <div class="text-lg font-black text-slate-900 dark:text-neutral-100">Relación de pagos</div>
            <div class="text-sm text-slate-500 dark:text-neutral-300">
              Adjunta comprobante por movimiento. Sin archivo no hay pago.
            </div>
          </div>

          <!-- Tabla para escritorio -->
          <div class="hidden lg:block">
            <table class="w-full">
              <thead class="bg-slate-50/80 dark:bg-neutral-950/40">
                <tr class="text-left text-[12px] font-black text-slate-600 dark:text-neutral-300">
                  <th class="px-5 py-3 w-[90px]">Id</th>
                  <th class="px-5 py-3">Fecha</th>
                  <th class="px-5 py-3">Tipo</th>
                  <th class="px-5 py-3">Monto</th>
                  <th class="px-5 py-3">Archivo</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="p in pagos"
                  :key="p.id"
                  class="border-t border-slate-200/70 dark:border-white/10 hover:bg-slate-50/70 dark:hover:bg-white/5 transition"
                >
                  <td class="px-5 py-3 text-sm font-black text-slate-900 dark:text-neutral-100">{{ p.id }}</td>
                  <td class="px-5 py-3 text-sm text-slate-800 dark:text-neutral-100">{{ fmtLong(p.fecha_pago) }}</td>
                  <td class="px-5 py-3 text-sm text-slate-800 dark:text-neutral-100">{{ (p.tipo_pago || '').toLowerCase() }}</td>
                  <td class="px-5 py-3 text-sm font-black text-slate-900 dark:text-neutral-100">{{ money(p.monto) }}</td>
                  <td class="px-5 py-3 text-sm">
                    <button
                      v-if="p.archivo?.url"
                      type="button"
                      class="inline-flex items-center gap-2 font-black text-emerald-700 hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200 min-w-0"
                      @click="openPreview(p)"
                      title="Previsualizar aquí"
                    >
                      <FileText class="h-4 w-4 shrink-0" />
                      <span class="truncate max-w-[420px]">{{ p.archivo.label }}</span>
                    </button>
                    <span v-else class="text-slate-500 dark:text-neutral-400">—</span>
                  </td>
                </tr>

                <tr v-if="pagos.length === 0">
                  <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500 dark:text-neutral-400">
                    Aún no hay pagos registrados.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tarjetas para móvil/tablet -->
          <div class="lg:hidden divide-y divide-slate-200/70 dark:divide-white/10">
            <div v-if="pagos.length === 0" class="px-5 py-8 text-center text-sm text-slate-500 dark:text-neutral-400">
              Aún no hay pagos registrados.
            </div>

            <div v-for="p in pagos" :key="p.id" class="px-5 py-4">
              <div class="flex items-center justify-between gap-3">
                <div class="text-sm font-black text-slate-900 dark:text-neutral-100">#{{ p.id }}</div>
                <div class="text-xs text-slate-500 dark:text-neutral-400">{{ fmtLong(p.fecha_pago) }}</div>
              </div>

              <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                <div class="rounded-2xl border border-slate-200/60 dark:border-white/10 bg-slate-50/70 dark:bg-neutral-950/40 p-3">
                  <div class="text-[11px] font-black text-slate-500 dark:text-neutral-400">Tipo</div>
                  <div class="font-semibold text-slate-900 dark:text-neutral-100">{{ (p.tipo_pago || '').toLowerCase() }}</div>
                </div>

                <div class="rounded-2xl border border-slate-200/60 dark:border-white/10 bg-slate-50/70 dark:bg-neutral-950/40 p-3">
                  <div class="text-[11px] font-black text-slate-500 dark:text-neutral-400">Monto</div>
                  <div class="font-black text-slate-900 dark:text-neutral-100">{{ money(p.monto) }}</div>
                </div>
              </div>

              <div class="mt-3">
                <button
                  v-if="p.archivo?.url"
                  type="button"
                  class="inline-flex items-center gap-2 text-sm font-black text-emerald-700 hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200 min-w-0"
                  @click="openPreview(p)"
                >
                  <FileText class="h-4 w-4 shrink-0" />
                  <span class="truncate">{{ p.archivo.label }}</span>
                </button>
                <div v-else class="text-sm text-slate-500 dark:text-neutral-400">Sin archivo</div>
              </div>
            </div>
          </div>

          <!-- Vista previa de comprobantes -->
          <div class="px-5 pb-5">
            <div class="mt-4 rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/60 overflow-hidden">
              <div class="px-4 py-3 border-b border-slate-200/70 dark:border-white/10">
                <div class="flex items-start justify-between gap-3 min-w-0">
                  <div class="min-w-0">
                    <div class="text-xs font-black text-slate-500 dark:text-neutral-300">VISTA PREVIA</div>
                    <div class="text-sm font-black text-slate-900 dark:text-neutral-100 truncate" :title="previewTitle">
                      {{ previewTitle }}
                    </div>
                  </div>

                  <button
                    v-if="preview"
                    type="button"
                    class="inline-flex items-center justify-center h-9 w-9 rounded-2xl border border-slate-200 bg-white
                           hover:bg-slate-50 hover:shadow-sm dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15
                           transition active:scale-[0.98] shrink-0"
                    title="Cerrar vista previa"
                    @click="closePreview"
                  >
                    <X class="text-slate-700 dark:text-neutral-100 h-4 w-4" />
                  </button>
                </div>
              </div>

              <div class="p-3">
                <div
                  class="rounded-3xl border border-slate-200/60 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 overflow-hidden"
                  :class="preview ? 'p-0' : 'p-4'"
                >
                  <div v-if="!preview" class="text-sm text-slate-600 dark:text-neutral-300">
                    Da clic en el archivo de un pago para previsualizar aquí.
                  </div>

                  <div v-else class="w-full">
                    <div class="w-full h-[38vh] sm:h-[42vh] max-h-[520px]">
                      <iframe
                        v-if="preview.kind === 'pdf'"
                        :src="preview.url"
                        class="w-full h-full block"
                        style="border: 0"
                        title="Vista previa pago"
                      />
                      <div v-else-if="preview.kind === 'image'" class="w-full h-full flex items-center justify-center">
                        <img :src="preview.url" alt="Vista previa" class="w-full h-full object-contain" />
                      </div>
                      <div v-else class="w-full h-full flex items-center justify-center p-6 text-center">
                        <div class="text-sm text-slate-600 dark:text-neutral-300">
                          Este archivo no tiene preview aquí.
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Formulario para registrar pagos -->
            <div v-if="canUploadPago" class="p-5 border-t border-slate-200/70 dark:border-white/10">
            <div class="space-y-4">
                <!-- Selección de archivo -->
                <div class="min-w-0">
                <div class="flex items-center justify-between gap-3">
                    <label class="block text-xs font-black text-slate-600 dark:text-neutral-300">Comprobante de pago</label>
                    <div class="text-[12px] font-black text-slate-500 dark:text-neutral-300">
                    Pendiente:
                    <span class="text-slate-900 dark:text-neutral-100">{{ money(pendiente) }}</span>
                    </div>
                </div>

                <!-- Zona de arrastrar y soltar -->
                <div
                class="mt-1 rounded-3xl border bg-white/80 dark:bg-neutral-950/40 p-3 select-none min-w-0
                        transition duration-200 hover:shadow-sm hover:-translate-y-[1px]"
                :class="[
                    dragActive
                    ? 'border-emerald-400/60 ring-2 ring-emerald-500/20 dark:border-emerald-400/40'
                    : 'border-slate-200/70 dark:border-white/10',
                    !canSelectFile ? 'pointer-events-none opacity-60' : ''
                ]"
                @dragenter="onDragEnter"
                @dragover="onDragOver"
                @dragleave="onDragLeave"
                @drop="onDropFile"
                >
                …
                <div v-if="!canSelectFile" class="mt-2 text-xs text-rose-600">
                    Autoriza el pago y selecciona una fecha para poder subir comprobantes de pago.
                </div>
                </div>

                <div v-if="form.errors.archivo" class="mt-1 text-xs font-bold text-rose-600">
                    {{ form.errors.archivo }}
                </div>

                <!-- Preview del archivo antes de subir -->
                <div
                    v-if="uploadPreview"
                    class="mt-3 rounded-3xl border border-slate-200/70 dark:border-white/10 bg-white/85 dark:bg-neutral-900/60 overflow-hidden"
                >
                    <div class="px-4 py-3 border-b border-slate-200/70 dark:border-white/10">
                    <div class="text-xs font-black text-slate-500 dark:text-neutral-300">PREVISUALIZACIÓN ANTES DE SUBIR</div>
                    <div class="text-sm font-black text-slate-900 dark:text-neutral-100 truncate" :title="uploadPreview.name">
                        {{ uploadPreview.name }}
                    </div>
                    </div>

                    <div class="p-3">
                    <div class="rounded-3xl border border-slate-200/60 dark:border-white/10 bg-slate-50/60 dark:bg-white/5 overflow-hidden">
                        <div class="h-[38vh] sm:h-[42vh] max-h-[420px]">
                        <iframe
                            v-if="uploadPreview.kind === 'pdf'"
                            :src="uploadPreview.url"
                            class="w-full h-full block"
                            style="border: 0"
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
                    </div>
                </div>
                </div>

                <!-- Campos para fecha, monto y tipo -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end min-w-0">
                <div class="lg:col-span-4 min-w-0">
                    <label class="block text-xs font-black text-slate-600 dark:text-neutral-300">Fecha de pago</label>
                    <DatePickerShadcn v-model="form.fecha_pago" placeholder="Selecciona fecha" />
                    <div v-if="form.errors.fecha_pago" class="mt-1 text-xs font-bold text-rose-600">
                    {{ form.errors.fecha_pago }}
                    </div>
                </div>

                <div class="lg:col-span-4 min-w-0">
                    <div class="flex items-end justify-between gap-3">
                    <label class="block text-xs font-black text-slate-600 dark:text-neutral-300">Monto pagado</label>
                    <div class="text-[11px] font-black text-slate-500 dark:text-neutral-300">
                        Máx: {{ money(pendiente) }}
                    </div>
                    </div>

                    <input
                    :value="montoText"
                    type="text"
                    inputmode="decimal"
                    autocomplete="off"
                    :class="inputBase"
                    class="mt-1"
                    placeholder="0.00"
                    @input="onMontoInput"
                    @blur="onMontoBlur"
                    />

                    <div v-if="form.errors.monto" class="mt-1 text-xs font-bold text-rose-600">
                    {{ form.errors.monto }}
                    </div>

                    <div class="mt-1 text-[12px] text-slate-500 dark:text-neutral-400">
                    Pendiente: <span class="font-black">{{ money(pendiente) }}</span>
                    </div>
                </div>

                <div class="lg:col-span-4 min-w-0">
                    <label class="block text-xs font-black text-slate-600 dark:text-neutral-300">Tipo de pago</label>
                    <select v-model="form.tipo_pago" :class="inputBase" class="mt-1">
                    <option v-for="t in props.tipoPagoOptions" :key="t.id" :value="t.id">
                        {{ t.nombre }}
                    </option>
                    </select>
                    <div v-if="form.errors.tipo_pago" class="mt-1 text-xs font-bold text-rose-600">
                    {{ form.errors.tipo_pago }}
                    </div>
                </div>

                <div class="lg:col-span-12 min-w-0">
                    <button
                    type="button"
                    :disabled="!canSubmit || submitting"
                    @click="submit"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-black
                            bg-slate-900 text-white hover:bg-slate-950 dark:bg-white dark:text-slate-900 dark:hover:bg-neutral-200
                            transition active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                    <Upload class="h-4 w-4" />
                    Registrar pago
                    </button>
                </div>
                </div>
            </div>
            </div>

            <!-- Mensaje si no tiene permiso para subir pagos -->
            <div v-else class="p-5 border-t border-slate-200/70 dark:border-white/10 text-sm text-slate-500 dark:text-neutral-400">
            Sólo administradores o contadores pueden registrar pagos. Aquí sólo puedes ver los pagos existentes.
            </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
