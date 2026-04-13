import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import type { RequisicionComprobarPageProps, ComprobanteRow } from './Comprobar.types'

declare const route: any

type SubmitOpts = { onAfterSuccess?: () => void }
type ReviewStatus = 'APROBADO' | 'RECHAZADO'

type PreviewKind = 'pdf' | 'image' | 'other'
type PreviewState = { url: string; label: string; kind: PreviewKind } | null

type UploadPreviewState = { url: string; name: string; kind: PreviewKind } | null

export function useRequisicionComprobar(props: RequisicionComprobarPageProps) {
  const page = usePage<any>()

  /** =========================================================
   * Role / perms
   * ========================================================= */
  const role = computed(() =>
    String(page.props?.auth?.user?.rol ?? page.props?.auth?.user?.role ?? '').toUpperCase(),
  )

  const canDelete = computed(() => ['ADMIN', 'CONTADOR'].includes(role.value))
  const canUseFoliosPanel = computed(() => ['ADMIN', 'CONTADOR'].includes(role.value))
  const canEditFolio = computed(() => role.value === 'ADMIN')
  const canNotify = computed(() => ['COLABORADOR', 'ADMIN', 'CONTADOR'].includes(role.value))
    const canSendNotification = computed(() => canNotify.value && pendientePorCargarCents.value <= 0)

  /** =========================================================
   * Req + rows
   * ========================================================= */
  const req = computed(() => {
    const raw: any = props.requisicion
    return raw?.data ?? raw ?? null
  })

  const rows = computed<ComprobanteRow[]>(() => {
    const raw: any = props.comprobantes
    return raw?.data ?? raw ?? []
  })

  const reqStatus = computed(() =>
    String(req.value?.status ?? '').toUpperCase()
    )

    const isFinalizada = computed(() =>
    reqStatus.value === 'COMPROBACION_ACEPTADA'
    )

  const money = (v: any) => {
    const n = Number(v ?? 0)
    return n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
  }

  const fmtLong = (iso?: string | null) => {
    if (!iso) return '—'
    const s = String(iso)
    const d = s.includes('T') ? new Date(s) : new Date(`${s}T00:00:00`)
    if (Number.isNaN(d.getTime())) return '—'
    return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' })
  }

  const tipoDocLabel = (tipo?: string | null) => {
    const t = String(tipo || '').toUpperCase()
    const opt = (props.tipoDocOptions || []).find((x: any) => String(x.id).toUpperCase() === t)
    return opt?.nombre ?? (t || '—')
  }

  const estatusLabel = (e: ComprobanteRow['estatus']) => {
    if (e === 'APROBADO') return 'Aprobado'
    if (e === 'RECHAZADO') return 'Rechazado'
    return 'Pendiente'
  }

  const estatusPillClass = (e: ComprobanteRow['estatus']) => {
    if (e === 'APROBADO') {
      return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200'
    }
    if (e === 'RECHAZADO') {
      return 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200'
    }
    return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-neutral-200'
  }

  /** =========================================================
   * Review permissions (mantengo tu lógica base)
   * ========================================================= */
  const userRole = computed(() => {
    const u = page?.props?.auth?.user
    return String(u?.role ?? u?.rol ?? '').toUpperCase()
  })

  const canReview = computed(() => {
    const fromBackend = (props as any)?.canReview
    if (typeof fromBackend === 'boolean') return fromBackend
    return ['ADMIN', 'CONTADOR'].includes(role.value)
  })

  /** =========================================================
   * Form upload (NO cambio nombres)
   * ========================================================= */
  const form = useForm<{
    archivo: File | null
    monto: string
    tipo_doc: string
    fecha_emision: string
  }>({
    archivo: null,
    monto: '',
    tipo_doc: 'NOTA',
    fecha_emision: new Date().toISOString().slice(0, 10),
  })

  const fileKey = ref(0)
  const dragActive = ref(false)

  const pickedName = computed(() => form.archivo?.name || 'Sin archivo seleccionado')
  const hasPicked = computed(() => !!form.archivo)

  const clearFile = () => {
    form.archivo = null
    fileKey.value++
  }

  const onPickFile = (e: Event) => {
    if (isFullyApproved.value) return

    const input = e.target as HTMLInputElement
    form.archivo = input.files?.[0] ?? null
    }

    const onDropFile = (e: DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    dragActive.value = false

    if (isFullyApproved.value) return

    const f = e.dataTransfer?.files?.[0] ?? null
    if (f) form.archivo = f
    }

  const onDragEnter = (e: DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    if (isFullyApproved.value) return
    dragActive.value = true
    }

    const onDragOver = (e: DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    if (isFullyApproved.value) return
    }
  const onDragLeave = (e: DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    dragActive.value = false
  }

  /** =========================================================
 * Helpers cents + pendiente
 * ========================================================= */
    const toNumber = (v: any) => {
    const n = typeof v === 'number' ? v : parseFloat(String(v ?? '').replace(',', '.'))
    return Number.isFinite(n) ? n : 0
    }
    const toCents = (v: any) => Math.round((toNumber(v) + Number.EPSILON) * 100)

    // Total requisición
    const totalCents = computed(() => toCents(req.value?.monto_total ?? 0))

    // SUMA SOLO APROBADOS (esto es lo que “cuenta” para comprobar)
    const approvedCents = computed(() =>
    rows.value.reduce((acc, c) => {
        const est = String(c?.estatus ?? '').toUpperCase()
        if (est !== 'APROBADO') return acc
        return acc + toCents(c?.monto ?? 0)
    }, 0),
    )

// Pendiente por comprobar = total - aprobados
const pendienteCents = computed(() => Math.max(0, totalCents.value - approvedCents.value))

const isFullyApproved = computed(() => pendienteCents.value <= 0)

const nonRejectedCents = computed(() =>
  rows.value.reduce((acc, c) => {
    const est = String(c?.estatus ?? '').toUpperCase()
    if (est === 'RECHAZADO') return acc
    return acc + toCents(c?.monto ?? 0)
  }, 0),
)

const pendientePorCargarCents = computed(() =>
  Math.max(0, totalCents.value - nonRejectedCents.value),
)

const isFullyLoaded = computed(() => pendientePorCargarCents.value <= 0)

const canUploadMore = computed(() => {
  return ['COLABORADOR', 'ADMIN', 'CONTADOR'].includes(role.value)
    && !isFullyLoaded.value
    && !isFinalizada.value
})

  /** =========================================================
   * Autocompletar monto con el pendiente (tu intención original)
   * ========================================================= */
  const montoTouched = ref(false)
  const centsToFixed = (c: number) => (c / 100).toFixed(2)

  const syncMontoToPendiente = () => {
    form.monto = centsToFixed(pendientePorCargarCents.value)
    }

  watch(
  pendientePorCargarCents,
  () => {
    if (!montoTouched.value) syncMontoToPendiente()
  },
  { immediate: true },
)

  watch(
    () => form.monto,
    () => {
      montoTouched.value = true
    },
  )

  /** =========================================================
   * canSubmit
   * ========================================================= */
  const canSubmit = computed(() => {
    if (isFullyApproved.value) return false

    const hasFile = !!form.archivo
    const hasTipo = !!(form.tipo_doc && String(form.tipo_doc).trim())
    const hasFecha = !!(form.fecha_emision && String(form.fecha_emision).trim())
    const m = String(form.monto ?? '').trim()
    const hasMonto = m !== '' && !Number.isNaN(Number(m))

    return hasFile && hasTipo && hasFecha && hasMonto && !form.processing
    })

  /** =========================================================
   * Submit
   * ========================================================= */
  const submit = (opts?: SubmitOpts) => {
    if (!req.value?.id) return

    Swal.fire({
      title: 'Subiendo comprobante…',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
    })

    form.post(route('requisiciones.comprobar.store', { requisicion: req.value.id }), {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: 'Comprobante cargado', timer: 1200, showConfirmButton: false })
        form.reset('archivo', 'monto')
        opts?.onAfterSuccess?.()
        // resync
        montoTouched.value = false
        syncMontoToPendiente()
        // reset picker
        fileKey.value++
        dragActive.value = false
      },
      onError: (errors) => {
        console.error('Error al subir comprobante:', errors)
        Swal.fire({
          icon: 'error',
          title: 'No se pudo subir',
          text: 'Revisa los campos y vuelve a intentar.',
        })
      },
      onFinish: () => {
        if (Swal.isLoading()) Swal.close()
      },
    })
  }

  const doSubmit = () => submit()

  /** =========================================================
   * Preview (archivo por subir)
   * ========================================================= */
  const uploadPreview = ref<UploadPreviewState>(null)

  const extOf = (s: string) => {
    const clean = (s || '').split('?')[0].split('#')[0]
    const parts = clean.split('.')
    return (parts.length > 1 ? parts.pop() : '')?.toLowerCase() ?? ''
  }

  const detectKind = (urlOrName: string): PreviewKind => {
    const ext = extOf(urlOrName)
    if (ext === 'pdf') return 'pdf'
    if (['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'].includes(ext)) return 'image'
    return 'other'
  }

  const revokeUploadPreview = () => {
    const u = uploadPreview.value?.url
    if (u && u.startsWith('blob:')) URL.revokeObjectURL(u)
    uploadPreview.value = null
  }

  watch(
    () => form.archivo,
    (f) => {
      revokeUploadPreview()
      if (!f) return
      const url = URL.createObjectURL(f)
      uploadPreview.value = { url, name: f.name, kind: detectKind(f.name) }
    },
    { immediate: true },
  )

  onBeforeUnmount(() => {
    revokeUploadPreview()
  })

  /** =========================================================
   * Preview (archivos ya subidos)
   * ========================================================= */
  const preview = ref<PreviewState>(null)
  const previewWrapRef = ref<HTMLElement | null>(null)

  const detectKindByUrl = (url: string, label: string): PreviewKind => {
    const ext = extOf(url) || extOf(label)
    if (ext === 'pdf') return 'pdf'
    if (['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'].includes(ext)) return 'image'
    return 'other'
  }

  const openPreview = async (row: any) => {
    const url = row?.archivo?.url
    const label = row?.archivo?.label || 'Archivo'
    if (!url) return

    preview.value = { url, label, kind: detectKindByUrl(url, label) }
    await nextTick()

    if (previewWrapRef.value && window.matchMedia('(max-width: 1279px)').matches) {
      previewWrapRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  }

  const closePreview = () => {
    preview.value = null
  }

  const previewTitle = computed(() => preview.value?.label || 'Selecciona un archivo')

  /** =========================================================
   * Tipo select “bonito” (tu versión, pero centralizada)
   * ========================================================= */
  const tipoOpen = ref(false)
  const tipoWrap = ref<HTMLElement | null>(null)

  const tipoOptions = computed(() => (Array.isArray(props.tipoDocOptions) ? props.tipoDocOptions : []))
  const tipoSelected = computed(() => {
    const id = (form.tipo_doc ?? '').toString()
    return tipoOptions.value.find((o: any) => (o?.id ?? '').toString() === id) ?? null
  })

  const setTipo = (id: string) => {
    form.tipo_doc = id
    tipoOpen.value = false
  }

  const closeTipoIfOutside = (ev: MouseEvent) => {
    if (!tipoOpen.value) return
    const el = tipoWrap.value
    const t = ev.target as Node | null
    if (el && t && !el.contains(t)) tipoOpen.value = false
  }

  const closeTipoOnEsc = (ev: KeyboardEvent) => {
    if (ev.key === 'Escape') tipoOpen.value = false
  }

  onMounted(() => {
    document.addEventListener('mousedown', closeTipoIfOutside, { passive: true })
    document.addEventListener('keydown', closeTipoOnEsc)
  })

  onBeforeUnmount(() => {
    document.removeEventListener('mousedown', closeTipoIfOutside as any)
    document.removeEventListener('keydown', closeTipoOnEsc as any)
  })

  /** =========================================================
   * Review routes
   * ========================================================= */
  const resolveReviewUrl = (id: number) => {
    const candidates = ['comprobantes.review', 'requisiciones.comprobantes.review', 'requisiciones.comprobantes.revisar']
    for (const name of candidates) {
      try {
        return route(name, { comprobante: id })
      } catch (_) {}
    }
    const msg = `No encuentro una ruta de revisión. Probé: ${candidates.join(', ')}`
    console.error(msg)
    throw new Error(msg)
  }

  const patchReview = (id: number, estatus: ReviewStatus, comentario: string | null) => {
    return new Promise<void>((resolve, reject) => {
      let url = ''
      try {
        url = resolveReviewUrl(id)
      } catch (e) {
        Swal.fire({ icon: 'error', title: 'Ruta no encontrada', text: (e as any)?.message ?? 'Error' })
        return reject(e)
      }

      Swal.fire({ title: 'Aplicando revisión…', allowOutsideClick: false, didOpen: () => Swal.showLoading() })

      router.patch(
        url,
        { estatus, comentario_revision: comentario },
        {
          preserveScroll: true,
          onSuccess: () => {
            Swal.fire({
              icon: 'success',
              title: estatus === 'APROBADO' ? 'Aprobado' : 'Rechazado',
              timer: 900,
              showConfirmButton: false,
            })
            resolve()
          },
          onError: (errors) => {
            console.error('Error review:', { id, estatus, errors })
            Swal.fire({ icon: 'error', title: 'No se pudo aplicar', text: 'Revisa permisos/validación.' })
            reject(errors)
          },
          onFinish: () => {
            if (Swal.isLoading()) Swal.close()
          },
        },
      )
    })
  }

  const approve = async (id: number) => {
    if (!canReview.value) {
        Swal.fire({ icon: 'warning', title: 'Sin permisos', text: 'Tu rol no puede aprobar/rechazar comprobantes.' })
        return
    }

    if (isFullyApproved.value) {
        Swal.fire({
        icon: 'info',
        title: 'Requisición ya comprobada',
        text: 'Ya no puedes aprobar más comprobantes porque el monto total ya fue cubierto.',
        })
        return
    }

    const r = await Swal.fire({
        icon: 'question',
        title: 'Aprobar comprobante',
        text: `¿Confirmas aprobar el comprobante con ID ${id}?`,
        showCancelButton: true,
        confirmButtonText: 'Aprobar',
        cancelButtonText: 'Cancelar',
    })

    if (!r.isConfirmed) return
    await patchReview(id, 'APROBADO', null)
    }

  const reject = async (id: number) => {
    if (!canReview.value) {
      Swal.fire({ icon: 'warning', title: 'Sin permisos', text: 'Tu rol no puede aprobar/rechazar comprobantes.' })
      return
    }

    const r = await Swal.fire({
      icon: 'warning',
      title: 'Rechazar comprobante',
      input: 'textarea',
      inputLabel: 'Motivo del rechazo',
      inputPlaceholder: 'Ej: comprobante repetido / no corresponde / monto inconsistente…',
      inputAttributes: { 'aria-label': 'Motivo del rechazo' },
      showCancelButton: true,
      confirmButtonText: 'Rechazar',
      cancelButtonText: 'Cancelar',
      preConfirm: (value) => {
        const v = String(value ?? '').trim()
        if (!v) {
          Swal.showValidationMessage('Escribe el motivo del rechazo.')
          return
        }
        return v
      },
    })

    if (!r.isConfirmed) return
    const motivo = String(r.value ?? '').trim()
    await patchReview(id, 'RECHAZADO', motivo)
  }

  /** =========================================================
   * Delete comprobante (bote de basura)
   * ========================================================= */
  function destroyComprobante(id: number) {
    Swal.fire({
      title: 'Eliminar comprobante',
      text: 'Esto lo borra de la base de datos.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc2626',
    }).then((r) => {
      if (!r.isConfirmed) return

      router.delete(route('comprobantes.destroy', id), {
        preserveScroll: true,
        onError: (errors) => console.error('DELETE comprobante error:', errors),
      })
    })
  }

  /** =========================================================
   * Folios panel (inline, no navegar)
   * - usa props.folios (array) que tú mandas desde backend
   * ========================================================= */
  const foliosOpen = ref(false)
  const toggleFoliosOpen = () => {
    foliosOpen.value = !foliosOpen.value
  }

  const folioSelectedId = ref<string | number | null>(null)
  const folioSelected = computed<any | null>(() => {
    const list: any[] = ((props as any).folios ?? []) as any[]
    if (!folioSelectedId.value) return null
    const idNum = Number(folioSelectedId.value)
    return list.find((x) => Number(x?.id) === idNum) ?? null
  })

  // Modal styles (más chico, más pro, responsive)
  const swalCompact = {
    width: 520,
    padding: '1rem',
    backdrop: true,
    customClass: {
      popup: 'rounded-3xl',
      title: 'text-lg font-black',
      htmlContainer: 'text-left',
      confirmButton: 'rounded-2xl px-5 py-2.5 font-black',
      cancelButton: 'rounded-2xl px-5 py-2.5 font-black',
      input: 'rounded-2xl',
    } as any,
  }

  const resolveFolioStoreUrl = () => {
    const candidates = ['folios.store']
    for (const name of candidates) {
      try {
        return route(name)
      } catch (_) {}
    }
    throw new Error('No encuentro la ruta folios.store')
  }

  const resolveFolioUpdateUrl = (id: number) => {
    const candidates = ['folios.update']
    for (const name of candidates) {
      try {
        return route(name, { folio: id })
      } catch (_) {}
    }
    throw new Error('No encuentro la ruta folios.update')
  }

  const addFolio = async () => {
    if (!canUseFoliosPanel.value) return

    const r = await Swal.fire({
      ...swalCompact,
      title: 'Agregar folio',
      html: `
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-semibold text-left">Folio</label>
            <input id="swal-folio" class="swal2-input" placeholder="Ej: A-2026-000123" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-left">Monto total (opcional)</label>
            <input id="swal-monto" class="swal2-input" placeholder="0.00" type="number" step="0.01" />
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Guardar',
      cancelButtonText: 'Cancelar',
      preConfirm: () => {
        const folio = (document.getElementById('swal-folio') as HTMLInputElement)?.value?.trim()
        const monto = (document.getElementById('swal-monto') as HTMLInputElement)?.value?.trim()
        if (!folio) {
          Swal.showValidationMessage('El folio es requerido.')
          return
        }
        return { folio, monto_total: monto || null }
      },
    })

    if (!r.isConfirmed) return

    Swal.fire({ title: 'Guardando…', allowOutsideClick: false, didOpen: () => Swal.showLoading() })

    const url = resolveFolioStoreUrl()
    router.post(url, { ...(r.value as any) }, {
    preserveScroll: true,
    onSuccess: () => {
        const flash = page.props?.flash
        Swal.fire({ icon: 'success', title: flash?.success ?? 'Folio agregado', timer: 1000, showConfirmButton: false })
        // si quieres autoseleccionar el recién creado:
        if (flash?.folio_created_id) folioSelectedId.value = flash.folio_created_id
        // refresca SOLO folios (si tu backend manda folios en props)
        router.reload({ only: ['folios'], preserveScroll: true })
    },
    onError: (e) => {
        console.error('Folio store error:', e)
        Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: 'Revisa validación o consola.' })
    },
    onFinish: () => { if (Swal.isLoading()) Swal.close() },
    })
  }

  const editFolio = async () => {
    if (!canEditFolio.value) return
    if (!folioSelected.value?.id) {
      Swal.fire({ icon: 'info', title: 'Selecciona un folio', text: 'Primero elige un folio en la lista.' })
      return
    }
    const current = folioSelected.value
    const r = await Swal.fire({
      ...swalCompact,
      title: 'Editar folio',
      html: `
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-semibold text-left">Folio</label>
            <input id="swal-folio" class="swal2-input" value="${String(current.folio ?? '').replaceAll('"', '&quot;')}" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-left">Monto total (opcional)</label>
            <input id="swal-monto" class="swal2-input" value="${String(current.monto_total ?? '')}" type="number" step="0.01" />
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Guardar',
      cancelButtonText: 'Cancelar',
      preConfirm: () => {
        const folio = (document.getElementById('swal-folio') as HTMLInputElement)?.value?.trim()
        const monto = (document.getElementById('swal-monto') as HTMLInputElement)?.value?.trim()
        if (!folio) {
          Swal.showValidationMessage('El folio es requerido.')
          return
        }
        return { folio, monto_total: monto || null }
      },
    })

    if (!r.isConfirmed) return

    Swal.fire({ title: 'Actualizando…', allowOutsideClick: false, didOpen: () => Swal.showLoading() })

    const url = resolveFolioUpdateUrl(Number(current.id))
    router.patch(
      url,
      { ...(r.value as any) },
      {
        preserveScroll: true,
        onSuccess: () => {
        const flash = (page.props as any)?.flash ?? {}
        Swal.fire({
            icon: 'success',
            title: flash?.success ?? 'Folio actualizado',
            timer: 1000,
            showConfirmButton: false,
        })

        // refresca lista (solo sirve si "folios" viene como prop TOP LEVEL)
        router.reload({ only: ['folios'], preserveScroll: true })

        // mantén seleccionado el mismo id
        folioSelectedId.value = current.id
        },
        onError: (e) => {
          console.error('Folio update error:', e)
          Swal.fire({ icon: 'error', title: 'No se pudo actualizar', text: 'Revisa validación o consola.' })
        },
        onFinish: () => {
          if (Swal.isLoading()) Swal.close()
        },
      },
    )
  }

  /** =========================================================
   * Notificaciones (COLABORADOR):
   * - WhatsApp: solo abre app con mensaje precargado
   * - Email: envío Laravel (un solo general)
   * ========================================================= */
  const buildNotifyText = () => {
    const folio = req.value?.folio ?? '—'
    const total = money(req.value?.monto_total ?? 0)
    const pendiente = money(pendienteCents.value / 100)
    const count = rows.value?.length ?? 0

    return `Comprobaciones cargadas para folio ${folio}.\nTotal: ${total}\nComprobantes: ${count}\nPendiente: ${pendiente}\n\nFavor de revisar en el ERP.`
  }

  const notifyWhatsApp = () => {
    if (!canNotify.value) return
    if (!req.value?.id) return

    if (!canSendNotification.value) {
        Swal.fire({
        icon: 'info',
        title: 'Aún no puedes notificar',
        text: 'Primero debes subir comprobantes por el total de la requisición.',
        })
        return
    }

    let url = ''
    try {
        url = resolveNotifyEmailUrl()
    } catch (e: any) {
        Swal.fire({
        icon: 'error',
        title: 'Falta ruta',
        text: e?.message ?? 'No se encontró la ruta.',
        })
        return
    }

    Swal.fire({
        title: 'Preparando notificación…',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    })

    router.post(
        url,
        { message: buildNotifyText() },
        {
        preserveScroll: true,
        onSuccess: () => {
            const phone = '522217494252'
            const text = encodeURIComponent(buildNotifyText())
            const waUrl = `https://wa.me/${phone}?text=${text}`

            Swal.fire({
            icon: 'success',
            title: 'Notificación preparada',
            timer: 1000,
            showConfirmButton: false,
            })

            window.open(waUrl, '_blank', 'noopener,noreferrer')
        },
        onError: (e) => {
            console.error('notifyWhatsApp error:', e)
            Swal.fire({
            icon: 'error',
            title: 'No se pudo preparar la notificación',
            text: 'No se actualizó el estado o faltó configuración.',
            })
        },
        onFinish: () => {
            if (Swal.isLoading()) Swal.close()
        },
        },
    )
    }

  const resolveNotifyEmailUrl = () => {
    const candidates = ['requisiciones.comprobaciones.notify', 'requisiciones.notify.comprobaciones']
    for (const name of candidates) {
      try {
        return route(name, { requisicion: req.value?.id })
      } catch (_) {}
    }
    throw new Error('No encuentro la ruta para enviar correo (notify).')
  }

  const notifyEmail = async () => {
    if (!canNotify.value) return
    if (!req.value?.id) return

    if (!canSendNotification.value) {
        Swal.fire({
        icon: 'info',
        title: 'Aún no puedes notificar',
        text: 'Primero debes comprobar el monto completo de la requisición para avisar a contabilidad.',
        })
        return
    }

    Swal.fire({
        title: 'Espere mientras enviamos el correo…',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    })

    let url = ''
    try {
        url = resolveNotifyEmailUrl()
    } catch (e: any) {
        Swal.fire({ icon: 'error', title: 'Falta ruta', text: e?.message ?? 'No se encontró la ruta.' })
        return
    }

    router.post(
        url,
        { message: buildNotifyText() },
        {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({ icon: 'success', title: 'Correo enviado', timer: 1200, showConfirmButton: false })
        },
        onError: (e) => {
            console.error('notifyEmail error:', e)
            Swal.fire({ icon: 'error', title: 'No se pudo enviar', text: 'Revisa configuración de correo o consola.' })
        },
        onFinish: () => {
            if (Swal.isLoading()) Swal.close()
        },
        },
    )
    }

  /** =========================================================
   * Input base
   * ========================================================= */
  const inputBase =
    'w-full rounded-2xl border border-slate-200/70 bg-white/90 px-4 py-3 text-sm font-semibold text-slate-900 ' +
    'placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/25 focus:border-indigo-500/40 ' +
    'dark:border-white/10 dark:bg-neutral-950/40 dark:text-neutral-100 dark:placeholder:text-neutral-500'

  return {
    // core
    req,
    rows,
    money,
    fmtLong,
    tipoDocLabel,
    estatusLabel,
    estatusPillClass,

    // perms
    role,
    canDelete,
    canReview,
    canUseFoliosPanel,
    canEditFolio,

    // actions
    approve,
    reject,
    destroyComprobante,
    isFullyApproved,
    canUploadMore,
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
    submit,
    doSubmit,
    inputBase,

    // pending
    pendienteCents,

    // preview before upload
    uploadPreview,

    // preview existing
    preview,
    previewTitle,
    openPreview,
    closePreview,
    previewWrapRef,
    canSendNotification,
    // tipo dropdown
    tipoOpen,
    tipoWrap,
    tipoOptions,
    tipoSelected,
    setTipo,

    // folios
    foliosOpen,
    toggleFoliosOpen,
    folioSelectedId,
    folioSelected,
    addFolio,
    editFolio,
    reqStatus,
    isFinalizada,

    // notifications
    canNotify,
    notifyWhatsApp,
    notifyEmail,
  }
}
