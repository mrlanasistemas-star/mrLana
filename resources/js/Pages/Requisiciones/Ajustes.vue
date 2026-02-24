<!-- resources/js/Pages/Requisiciones/Ajustes.vue -->
<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import DatePickerShadcn from '@/Components/ui/DatePickerShadcn.vue'
import { swalOk, swalErr } from '@/lib/swal'
import Swal from 'sweetalert2'

declare const route: any

type AjusteRow = {
  id: number
  tipo: string
  monto: string
  motivo?: string | null
  descripcion?: string | null
  fecha?: string | null
  fecha_registro?: string | null
  estatus?: 'PENDIENTE' | 'APROBADO' | 'RECHAZADO' | 'APLICADO' | 'CANCELADO'
  comentario_revision?: string | null
}

type Props = {
  requisicion?: any
  requisicionId?: number
  folio?: string
  ajustes?: AjusteRow[]
  errors?: Record<string, any>
}

const props = withDefaults(defineProps<Props>(), {
  ajustes: () => [],
})

const page = usePage<any>()

/** ---------- helpers ---------- */
const todayLocal = () => {
  const d = new Date()
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

const serverErrors = computed<Record<string, any>>(() => {
  return (props.errors ?? page.props?.errors ?? {}) as Record<string, any>
})

const role = computed(() =>
  String(page.props?.auth?.user?.rol ?? page.props?.auth?.user?.role ?? '').toUpperCase(),
)

const isAdminOrContador = computed(() => ['ADMIN', 'CONTADOR'].includes(role.value))
const isColaborador = computed(() => !isAdminOrContador.value)

const reqId = computed<number>(() => Number(props.requisicion?.id ?? props.requisicionId ?? 0))
const reqFolio = computed<string>(() => {
  const f = props.requisicion?.folio ?? props.folio
  return String(f ?? (reqId.value ? `REQ #${reqId.value}` : 'Requisición'))
})

const backUrl = computed(() => {
  try {
    return route('requisiciones.comprobar', { requisicion: reqId.value })
  } catch (_) {}
  try {
    return route('requisiciones.show', { requisicion: reqId.value })
  } catch (_) {}
  try {
    return route('requisiciones.index')
  } catch (_) {}
  return '/'
})

const money = (v: any) => {
  const n = Number(v ?? 0)
  return n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
}

const tipoLabel = (t: string) => {
  const x = String(t || '').toUpperCase()
  if (x === 'INCREMENTO_AUTORIZADO') return 'Incremento autorizado'
  if (x === 'FALTANTE') return 'Faltante'
  if (x === 'DEVOLUCION') return 'Devolución'
  return x || '—'
}

const estatusLabel = (e?: string) => {
  const x = String(e || '').toUpperCase()
  if (x === 'PENDIENTE') return 'Pendiente'
  if (x === 'APROBADO') return 'Aprobado'
  if (x === 'RECHAZADO') return 'Rechazado'
  if (x === 'APLICADO') return 'Aplicado'
  if (x === 'CANCELADO') return 'Cancelado'
  return x || '—'
}

const estatusPillClass = (e?: string) => {
  const x = String(e || '').toUpperCase()
  if (x === 'APROBADO')
    return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200'
  if (x === 'RECHAZADO')
    return 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200'
  if (x === 'APLICADO')
    return 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-200'
  if (x === 'CANCELADO')
    return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-neutral-200'
  return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200'
}

/** ---------- form ---------- */
const form = reactive({
  tipo: 'INCREMENTO_AUTORIZADO' as 'DEVOLUCION' | 'FALTANTE' | 'INCREMENTO_AUTORIZADO',
  monto: '',
  motivo: '',
  fecha: todayLocal(), // YYYY-MM-DD
})

const isSaving = ref(false)
const submitted = ref(false)

const sentido = computed(() =>
  form.tipo === 'DEVOLUCION' ? 'A_FAVOR_EMPRESA' : 'A_FAVOR_SOLICITANTE',
)

const montoNumber = computed(() => {
  const raw = String(form.monto ?? '').trim()
  const n = Number(raw.replace(',', '.'))
  return Number.isFinite(n) ? n : 0
})

const clientErrors = computed(() => {
  const errs: Record<string, string> = {}
  if (!reqId.value) errs._ = 'No se detectó requisición.'
  if (!form.fecha) errs.fecha = 'Selecciona una fecha.'
  if (!form.motivo.trim()) errs.motivo = 'Describe el motivo (obligatorio).'
  if (!form.monto || montoNumber.value <= 0) errs.monto = 'Ingresa un monto mayor a 0.'
  return errs
})

const canSubmit = computed(() => Object.keys(clientErrors.value).length === 0 && !isSaving.value)

const firstErr = (keys: string[]) => {
  for (const k of keys) {
    const be = serverErrors.value?.[k]
    if (be) return String(be)
  }
  return ''
}

const fieldErr = (primary: string, aliases: string[] = []) => {
  const be = firstErr([primary, ...aliases])
  if (be) return be
  if (!submitted.value) return ''
  return clientErrors.value?.[primary] ? String(clientErrors.value[primary]) : ''
}

function resetForm() {
  form.tipo = 'INCREMENTO_AUTORIZADO'
  form.monto = ''
  form.motivo = ''
  form.fecha = todayLocal()
  submitted.value = false
}

async function save() {
  submitted.value = true

  if (!canSubmit.value) {
    await swalErr('Completa monto, fecha y motivo antes de guardar.')
    return
  }

  isSaving.value = true

  const payload = {
    tipo: form.tipo,
    sentido: sentido.value,
    monto: String(montoNumber.value.toFixed(2)),

    // campos reales
    motivo: form.motivo.trim(),
    fecha_registro: form.fecha,

    // compat legacy
    descripcion: form.motivo.trim(),
    fecha: form.fecha,
  }

  router.post(route('requisiciones.ajustes.store', { requisicion: reqId.value }), payload, {
    preserveScroll: true,
    onSuccess: async () => {
      await swalOk('Ajuste creado y enviado a revisión.', 'Éxito')
      resetForm()
      router.reload({ preserveScroll: true })
    },
    onError: async () => {
      await swalErr('No se pudo guardar. Revisa los campos marcados.')
    },
    onFinish: () => {
      isSaving.value = false
    },
  })
}

/** ---------- actions (roles + reglas) ---------- */
const canApproveReject = computed(() => isAdminOrContador.value)
const canApply = computed(() => isAdminOrContador.value)

const showApprove = (a: AjusteRow) => canApproveReject.value && a.estatus === 'PENDIENTE'
const showReject = (a: AjusteRow) => canApproveReject.value && a.estatus === 'PENDIENTE'
const showApply = (a: AjusteRow) => canApply.value && a.estatus === 'APROBADO'

// Colaborador: SOLO cancelar si está PENDIENTE. Si ya fue aprobado, ya no hace nada.
const showCancel = (a: AjusteRow) => isColaborador.value && a.estatus === 'PENDIENTE'

async function review(ajusteId: number, accion: 'APROBAR' | 'RECHAZAR', comentario?: string) {
  if (!canApproveReject.value) return

  router.patch(
    route('requisiciones.ajustes.review', { ajuste: ajusteId }),
    { accion, comentario_revision: comentario ?? null },
    {
      preserveScroll: true,
      onSuccess: async () => {
        await swalOk('Revisión aplicada.', 'Listo')
        router.reload({ preserveScroll: true })
      },
      onError: async () => swalErr('No se pudo aplicar la revisión.'),
    },
  )
}

async function approve(ajusteId: number) {
  if (!canApproveReject.value) return
  const ok = await swalConfirm('¿Aprobar este ajuste?', 'Aprobar')
  if (!ok) return
  await review(ajusteId, 'APROBAR')
}

async function doApply(ajusteId: number) {
  router.post(
    route('requisiciones.ajustes.apply', { ajuste: ajusteId }),
    {},
    {
      preserveScroll: true,
      onSuccess: async () => {
        await swalOk('Ajuste aplicado correctamente.', 'Listo')
        router.reload({ preserveScroll: true })
      },
      onError: async () => swalErr('No se pudo aplicar el ajuste.'),
    },
  )
}

async function apply(ajusteId: number) {
  if (!canApply.value) return
  const ok = await swalConfirm('¿Aplicar este ajuste?', 'Aplicar')
  if (!ok) return
  await doApply(ajusteId)
}

async function doCancel(ajusteId: number) {
  router.post(
    route('requisiciones.ajustes.cancel', { ajuste: ajusteId }),
    {},
    {
      preserveScroll: true,
      onSuccess: async () => {
        await swalOk('Ajuste cancelado.', 'Listo')
        router.reload({ preserveScroll: true })
      },
      onError: async () => swalErr('No se pudo cancelar.'),
    },
  )
}

async function cancel(ajusteId: number) {
  if (!isColaborador.value) return
  const ok = await swalConfirm('¿Cancelar este ajuste?', 'Cancelar')
  if (!ok) return
  await doCancel(ajusteId)
}

async function rejectWithReason(ajusteId: number) {
  if (!canApproveReject.value) return
  const motivo = await swalRejectReason()
  if (!motivo) return
  await review(ajusteId, 'RECHAZAR', motivo)
}

/** ---------- data ---------- */
const ajustes = computed<AjusteRow[]>(() => (Array.isArray(props.ajustes) ? props.ajustes : []))

const ensureSwalTop = () => {
  const id = 'swal2-top-z'
  if (document.getElementById(id)) return
  const s = document.createElement('style')
  s.id = id
  s.textContent = `.swal2-container{z-index:20000 !important;}`
  document.head.appendChild(s)
}

const swalTheme = () => {
  const dark = document.documentElement.classList.contains('dark')
  return dark
    ? { background: '#0a0a0a', color: '#e5e7eb' }
    : { background: '#ffffff', color: '#0f172a' }
}

async function swalConfirm(title: string, confirmText = 'Confirmar') {
  ensureSwalTop()
  const r = await Swal.fire({
    title,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: confirmText,
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
    ...swalTheme(),
    customClass: {
      popup: 'rounded-3xl',
      confirmButton:
        'rounded-2xl px-4 py-2 font-black bg-slate-900 text-white hover:opacity-90',
      cancelButton:
        'rounded-2xl px-4 py-2 font-black bg-slate-200 text-slate-900 hover:opacity-90',
    },
    buttonsStyling: false,
  })
  return r.isConfirmed
}

async function swalRejectReason(): Promise<string | null> {
  ensureSwalTop()
  const r = await Swal.fire({
    title: 'Motivo del rechazo',
    input: 'textarea',
    inputPlaceholder: 'Escribe el motivo…',
    inputAttributes: { autocapitalize: 'off' },
    showCancelButton: true,
    confirmButtonText: 'Rechazar',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
    ...swalTheme(),
    preConfirm: (v) => {
      const t = String(v ?? '').trim()
      if (!t) {
        Swal.showValidationMessage('El motivo es obligatorio.')
        return false
      }
      return t
    },
    customClass: {
      popup: 'rounded-3xl',
      confirmButton:
        'rounded-2xl px-4 py-2 font-black bg-rose-600 text-white hover:opacity-90',
      cancelButton:
        'rounded-2xl px-4 py-2 font-black bg-slate-200 text-slate-900 hover:opacity-90',
      input: 'rounded-2xl',
    },
    buttonsStyling: false,
  })

  return r.isConfirmed ? String(r.value).trim() : null
}
</script>

<template>
  <Head :title="`Ajustes · ${reqFolio}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between gap-3 min-w-0">
        <div class="min-w-0">
          <div class="text-lg sm:text-xl font-black text-slate-900 dark:text-neutral-100 truncate">
            Ajustes · {{ reqFolio }}
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <SecondaryButton
            class="rounded-2xl transition duration-200 hover:-translate-y-[1px] active:scale-[0.98]"
            @click="$inertia.visit(backUrl)"
          >
            &lt;-- Volver
          </SecondaryButton>
        </div>
      </div>
    </template>

    <!-- wrapper anti-scroll global -->
    <div class="w-full min-w-0 flex-1 overflow-x-hidden">
      <!-- usa el ancho completo sin pasarte; respeta sidebar -->
      <div class="mx-auto w-full max-w-[1600px] px-4 sm:px-6 lg:px-8 2xl:px-10 py-4 sm:py-6">
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 sm:gap-5">

          <!-- LEFT: FORM -->
          <section class="xl:col-span-5 min-w-0 space-y-4">
            <div
              class="rounded-3xl border border-slate-200/70 dark:border-white/10
                     bg-white/90 dark:bg-neutral-900/70 backdrop-blur
                     shadow-sm hover:shadow-md transition duration-200 overflow-hidden"
            >
              <div class="px-5 py-3 border-b border-slate-200/70 dark:border-white/10">
                <div class="text-base font-black text-slate-900 dark:text-neutral-100">
                  Registrar ajuste
                </div>
              </div>

              <form @submit.prevent="save" class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                  <div class="min-w-0">
                    <label class="block text-xs font-black text-slate-600 dark:text-neutral-300">Tipo</label>
                    <select
                      v-model="form.tipo"
                      class="mt-1 w-full rounded-2xl border border-slate-200/70 bg-white/95 px-4 py-3 text-sm font-semibold text-slate-900
                             shadow-sm transition duration-200
                             focus:outline-none focus:ring-2 focus:ring-emerald-500/25
                             dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100"
                    >
                      <option value="INCREMENTO_AUTORIZADO">Incremento autorizado</option>
                      <option value="FALTANTE">Faltante</option>
                      <option value="DEVOLUCION">Devolución</option>
                    </select>
                  </div>

                  <div class="min-w-0">
                    <label class="block text-xs font-black text-slate-600 dark:text-neutral-300">Monto (MXN)</label>
                    <input
                      v-model="form.monto"
                      type="number"
                      step="0.01"
                      min="0"
                      inputmode="decimal"
                      class="mt-1 w-full rounded-2xl border border-slate-200/70 bg-white/95 px-4 py-3 text-sm font-semibold text-slate-900
                             shadow-sm transition duration-200
                             focus:outline-none focus:ring-2 focus:ring-emerald-500/25
                             dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100"
                      placeholder="0.00"
                      @keydown.enter.prevent
                    />
                    <div v-if="fieldErr('monto')" class="mt-1 text-xs font-extrabold text-rose-600">
                      {{ fieldErr('monto') }}
                    </div>
                  </div>

                  <div class="min-w-0">
                    <DatePickerShadcn
                      v-model="form.fecha"
                      label="Fecha"
                      placeholder="Selecciona fecha"
                    />
                    <div v-if="fieldErr('fecha', ['fecha_registro'])" class="mt-1 text-xs font-extrabold text-rose-600">
                      {{ fieldErr('fecha', ['fecha_registro']) }}
                    </div>
                  </div>
                </div>

                <div class="min-w-0">
                  <label class="block text-xs font-black text-slate-600 dark:text-neutral-300">Motivo</label>
                  <textarea
                    v-model="form.motivo"
                    rows="3"
                    class="mt-1 w-full rounded-2xl border border-slate-200/70 bg-white/95 px-4 py-3 text-sm font-semibold text-slate-900
                           shadow-sm transition duration-200
                           focus:outline-none focus:ring-2 focus:ring-emerald-500/25
                           dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100"
                    placeholder="Ej: faltó IVA / proveedor ajustó precio / autorización de..."
                  />
                  <div v-if="fieldErr('motivo', ['descripcion'])" class="mt-1 text-xs font-extrabold text-rose-600">
                    {{ fieldErr('motivo', ['descripcion']) }}
                  </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div class="text-xs sm:text-sm text-slate-500 dark:text-neutral-400">
                    Total solicitado:
                    <span class="font-extrabold text-slate-900 dark:text-neutral-100">
                      {{ money(montoNumber) }}
                    </span>
                  </div>

                  <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl px-5 py-3 text-sm font-black
                           bg-gradient-to-r from-slate-900 to-slate-950 text-white
                           shadow-sm transition duration-200 ease-out
                           hover:shadow-md hover:-translate-y-[2px] active:scale-[0.98]
                           focus:outline-none focus:ring-2 focus:ring-emerald-500/25
                           dark:from-white dark:to-neutral-200 dark:text-slate-900
                           disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:translate-y-0"
                  >
                    <span v-if="isSaving" class="inline-flex items-center gap-2">
                      <span class="h-4 w-4 rounded-full border-2 border-white/60 border-t-transparent animate-spin dark:border-slate-900/50 dark:border-t-transparent"></span>
                      Guardando…
                    </span>
                    <span v-else>Guardar</span>
                  </button>
                </div>

                <div v-if="serverErrors?._" class="text-xs font-extrabold text-rose-600">
                  {{ serverErrors._ }}
                </div>
              </form>
            </div>
          </section>

          <!-- RIGHT: LIST -->
          <section class="xl:col-span-7 min-w-0">
            <div
              class="rounded-3xl border border-slate-200/70 dark:border-white/10
                     bg-white/90 dark:bg-neutral-900/70 backdrop-blur
                     shadow-sm hover:shadow-md transition duration-200 overflow-hidden"
            >
              <div class="px-5 py-4 border-b border-slate-200/70 dark:border-white/10">
                <div class="text-base font-black text-slate-900 dark:text-neutral-100">
                  Ajustes registrados
                </div>
              </div>

              <!-- Desktop TABLE (solo xl+ para que el sidebar no te “mate” columnas) -->
              <div class="hidden xl:block">
                <table class="w-full table-auto">
                  <thead class="bg-slate-50/80 dark:bg-neutral-950/40">
                    <tr class="text-left text-[12px] font-black text-slate-600 dark:text-neutral-300">
                      <th class="px-5 py-3 w-[72px]">ID</th>
                      <th class="px-5 py-3 w-[210px]">Tipo</th>
                      <th class="px-5 py-3 w-[140px]">Monto</th>
                      <th class="px-5 py-3 w-[140px]">Fecha</th>
                      <th class="px-5 py-3">Motivo</th>
                      <th class="px-5 py-3 w-[140px]">Estatus</th>
                      <th class="px-5 py-3 w-[260px] text-right">Acciones</th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr
                      v-for="a in ajustes"
                      :key="a.id"
                      class="border-t border-slate-200/70 dark:border-white/10
                             hover:bg-slate-50/70 dark:hover:bg-white/5 transition duration-200"
                    >
                      <td class="px-5 py-3 text-sm font-black text-slate-900 dark:text-neutral-100">
                        {{ a.id }}
                      </td>

                      <td class="px-5 py-3 text-sm text-slate-800 dark:text-neutral-100">
                        {{ tipoLabel(a.tipo) }}
                      </td>

                      <td class="px-5 py-3 text-sm font-black text-slate-900 dark:text-neutral-100">
                        {{ money(a.monto) }}
                      </td>

                      <td class="px-5 py-3 text-sm text-slate-800 dark:text-neutral-100">
                        {{ a.fecha_registro ?? a.fecha ?? '—' }}
                      </td>

                      <td class="px-5 py-3 text-sm text-slate-800 dark:text-neutral-100 min-w-0">
                        <div class="truncate max-w-[520px]" :title="(a.motivo ?? a.descripcion ?? '')">
                          {{ a.motivo ?? a.descripcion ?? '—' }}
                        </div>
                        <div
                          v-if="a.estatus === 'RECHAZADO' && a.comentario_revision"
                          class="mt-1 text-[12px] font-semibold text-rose-700 dark:text-rose-200"
                        >
                          Motivo: {{ a.comentario_revision }}
                        </div>
                      </td>

                      <td class="px-5 py-3">
                        <div
                          class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[12px] font-black"
                          :class="estatusPillClass(a.estatus)"
                        >
                          {{ estatusLabel(a.estatus) }}
                        </div>
                      </td>

                      <td class="px-5 py-3 text-right">
                        <!-- Acciones: nunca se “cortan”; wrap + gap -->
                        <div class="inline-flex flex-wrap justify-end gap-2">
                          <!-- ADMIN/CONTADOR -->
                          <button
                            v-if="showApprove(a)"
                            type="button"
                            class="rounded-2xl px-3 py-2 text-xs font-black
                                   border border-emerald-200 bg-emerald-50 text-emerald-800
                                   shadow-sm transition duration-200
                                   hover:bg-emerald-100 hover:-translate-y-[1px] active:scale-[0.98]
                                   dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:hover:bg-emerald-500/15"
                            @click="approve(a.id)"
                          >
                            Aprobar
                          </button>

                          <button
                            v-if="showReject(a)"
                            type="button"
                            class="rounded-2xl px-3 py-2 text-xs font-black
                                   border border-rose-200 bg-rose-50 text-rose-800
                                   shadow-sm transition duration-200
                                   hover:bg-rose-100 hover:-translate-y-[1px] active:scale-[0.98]
                                   dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200 dark:hover:bg-rose-500/15"
                            @click="rejectWithReason(a.id)"
                          >
                            Rechazar
                          </button>

                          <button
                            v-if="showApply(a)"
                            type="button"
                            class="rounded-2xl px-3 py-2 text-xs font-black
                                   border border-indigo-200 bg-indigo-50 text-indigo-800
                                   shadow-sm transition duration-200
                                   hover:bg-indigo-100 hover:-translate-y-[1px] active:scale-[0.98]
                                   dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-200 dark:hover:bg-indigo-500/15"
                            @click="apply(a.id)"
                          >
                            Aplicar
                          </button>

                          <!-- COLABORADOR -->
                          <button
                            v-if="showCancel(a)"
                            type="button"
                            class="rounded-2xl px-3 py-2 text-xs font-black
                                   border border-slate-200 bg-white text-slate-800
                                   shadow-sm transition duration-200
                                   hover:bg-slate-50 hover:-translate-y-[1px] active:scale-[0.98]
                                   dark:border-white/10 dark:bg-white/10 dark:text-neutral-100 dark:hover:bg-white/15"
                            @click="cancel(a.id)"
                          >
                            Cancelar
                          </button>

                          <!-- si no hay acciones, no muestres nada -->
                        </div>
                      </td>
                    </tr>

                    <tr v-if="!ajustes || ajustes.length === 0">
                      <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-neutral-400">
                        No hay ajustes registrados.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Cards (mobile/tablet/desktop chico, hasta < xl) -->
              <div class="xl:hidden p-4 sm:p-5 space-y-3">
                <div
                  v-for="a in ajustes"
                  :key="a.id"
                  class="rounded-3xl border border-slate-200/70 dark:border-white/10
                         bg-white/95 dark:bg-neutral-950/30 p-4 shadow-sm
                         transition duration-200 hover:shadow-md hover:-translate-y-[1px]"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="text-sm font-black text-slate-900 dark:text-neutral-100">
                        {{ tipoLabel(a.tipo) }}
                      </div>
                      <div class="text-[12px] text-slate-500 dark:text-neutral-400">
                        {{ a.fecha_registro ?? a.fecha ?? '—' }} · {{ money(a.monto) }} · ID {{ a.id }}
                      </div>
                    </div>

                    <div
                      class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[12px] font-black shrink-0"
                      :class="estatusPillClass(a.estatus)"
                    >
                      {{ estatusLabel(a.estatus) }}
                    </div>
                  </div>

                  <div class="mt-3 text-sm text-slate-700 dark:text-neutral-200 break-words">
                    {{ a.motivo ?? a.descripcion ?? '—' }}
                  </div>

                  <div
                    v-if="a.estatus === 'RECHAZADO' && a.comentario_revision"
                    class="mt-2 text-[12px] font-semibold text-rose-700 dark:text-rose-200"
                  >
                    Motivo: {{ a.comentario_revision }}
                  </div>

                  <!-- acciones cards: mismas reglas -->
                  <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                    <button
                      v-if="showApprove(a)"
                      class="rounded-2xl px-3 py-2 text-xs font-black
                             border border-emerald-200 bg-emerald-50 text-emerald-800
                             shadow-sm transition duration-200
                             hover:bg-emerald-100 hover:-translate-y-[1px] active:scale-[0.98]
                             dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:hover:bg-emerald-500/15"
                      @click="approve(a.id)"
                    >
                      Aprobar
                    </button>

                    <button
                      v-if="showReject(a)"
                      class="rounded-2xl px-3 py-2 text-xs font-black
                             border border-rose-200 bg-rose-50 text-rose-800
                             shadow-sm transition duration-200
                             hover:bg-rose-100 hover:-translate-y-[1px] active:scale-[0.98]
                             dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200 dark:hover:bg-rose-500/15"
                      @click="rejectWithReason(a.id)"
                    >
                      Rechazar
                    </button>

                    <button
                      v-if="showApply(a)"
                      class="rounded-2xl px-3 py-2 text-xs font-black
                             border border-indigo-200 bg-indigo-50 text-indigo-800
                             shadow-sm transition duration-200
                             hover:bg-indigo-100 hover:-translate-y-[1px] active:scale-[0.98]
                             dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-200 dark:hover:bg-indigo-500/15"
                      @click="apply(a.id)"
                    >
                      Aplicar
                    </button>

                    <button
                      v-if="showCancel(a)"
                      class="rounded-2xl px-3 py-2 text-xs font-black
                             border border-slate-200 bg-white text-slate-800
                             shadow-sm transition duration-200
                             hover:bg-slate-50 hover:-translate-y-[1px] active:scale-[0.98]
                             dark:border-white/10 dark:bg-white/10 dark:text-neutral-100 dark:hover:bg-white/15"
                      @click="cancel(a.id)"
                    >
                      Cancelar
                    </button>
                  </div>
                </div>

                <div v-if="!ajustes || ajustes.length === 0" class="px-2 py-10 text-center text-sm text-slate-500 dark:text-neutral-400">
                  No hay ajustes registrados.
                </div>
              </div>

            </div>
          </section>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
