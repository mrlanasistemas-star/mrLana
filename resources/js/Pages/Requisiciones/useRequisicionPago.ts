import { computed, onBeforeUnmount, ref } from 'vue'
import { router, useForm, usePage} from '@inertiajs/vue3'
import type { RequisicionPagoPageProps, PagoRow } from './Pagar.types'

declare const route: any

type PreviewKind = 'pdf' | 'image' | 'other'
type Preview = { url: string; name: string; kind: PreviewKind }

function normalizeIso(iso?: string | null) {
  if (!iso) return ''
  const s = String(iso).trim()
  if (!s) return ''
  if (s.includes('T')) return s
  if (s.includes(' ')) return s.replace(' ', 'T')
  return s
}

function detectKindFromName(name?: string | null): PreviewKind {
  const n = (name ?? '').toLowerCase()
  if (n.endsWith('.pdf') || n.includes('.pdf?')) return 'pdf'
  if (n.endsWith('.png') || n.endsWith('.jpg') || n.endsWith('.jpeg') || n.endsWith('.webp')) return 'image'
  if (n.includes('application/pdf')) return 'pdf'
  return 'other'
}

function detectKindFromUrl(url: string): PreviewKind {
  const u = url.toLowerCase()
  if (u.includes('.pdf')) return 'pdf'
  if (u.includes('.png') || u.includes('.jpg') || u.includes('.jpeg') || u.includes('.webp')) return 'image'
  return 'other'
}

function moneyMx(v: any) {
  const n = Number(v ?? 0)
  return n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
}

function fmtLongEs(iso?: string | null) {
  if (!iso) return '—'
  const s = normalizeIso(iso)
  const d = new Date(s)
  if (Number.isNaN(d.getTime())) return String(iso)
  return new Intl.DateTimeFormat('es-MX', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  }).format(d)
}

function sanitizeDecimalInput(raw: string) {
  let s = (raw ?? '').replace(',', '.')
  s = s.replace(/[^\d.]/g, '')

  const firstDot = s.indexOf('.')
  if (firstDot !== -1) {
    const before = s.slice(0, firstDot + 1)
    const after = s.slice(firstDot + 1).replace(/\./g, '')
    s = before + after
  }

  if (s.startsWith('.')) s = '0' + s

  const dot = s.indexOf('.')
  if (dot !== -1) {
    const a = s.slice(0, dot)
    const b = s.slice(dot + 1, dot + 1 + 2)
    s = a + '.' + b
  }

  s = s.replace(/^0+(\d)/, '$1')
  if (s === '') return ''
  if (s.startsWith('.')) return '0' + s
  return s
}

function centsFromText(txt: string) {
  const s = String(txt ?? '').trim()
  if (!s) return 0
  const parts = s.split('.')
  const whole = Number(parts[0] || '0')
  const dec = (parts[1] || '').slice(0, 2)
  const dec2 = dec.padEnd(2, '0')
  const cents = whole * 100 + Number(dec2 || '0')
  return Number.isFinite(cents) ? Math.max(0, cents) : 0
}

function textFromCents(cents: number) {
  const c = Math.max(0, Math.floor(cents))
  const whole = Math.floor(c / 100)
  const dec = c % 100
  if (dec === 0) return String(whole)
  if (dec % 10 === 0) return `${whole}.${Math.floor(dec / 10)}`
  return `${whole}.${String(dec).padStart(2, '0')}`
}

export function useRequisicionPago(props: RequisicionPagoPageProps) {
  const req = computed(() => {
    const raw: any = (props as any).requisicion
    return raw?.data ?? raw ?? null
  })

  const pagos = computed<PagoRow[]>(() => {
    const raw: any = (props as any).pagos
    return raw?.data ?? raw ?? []
  })

  const pendiente = computed(() => {
    const tot: any = (props as any).totales
    if (tot && typeof tot.pendiente !== 'undefined') return Number(tot.pendiente ?? 0)
    const total = Number(req.value?.monto_total ?? 0)
    const pagado = pagos.value.reduce((acc, p) => acc + Number(p.monto ?? 0), 0)
    return Math.max(0, total - pagado)
  })

  const maxCents = computed(() => Math.max(0, Math.round(Number(pendiente.value ?? 0) * 100)))

  const money = (v: any) => moneyMx(v)
  const fmtLong = (iso?: string | null) => fmtLongEs(iso)

  const defaultTipo = computed(() => {
    const opts: any[] = (props as any).tipoPagoOptions ?? []
    return (opts[0]?.id ?? 'TRANSFERENCIA') as string
  })

  const form = useForm<{
    fecha_pago: string
    monto: string
    tipo_pago: string
    referencia: string | null
    archivo: File | null
  }>({
    fecha_pago: '',
    monto: '',
    tipo_pago: defaultTipo.value,
    referencia: null,
    archivo: null,
  })

  // Calcula el rol del usuario conectado
  const page = usePage<any>()
  const role = computed(() =>
    String(page.props?.auth?.user?.rol ?? 'COLABORADOR').toUpperCase()
  )

  // Sólo ADMIN/CONTADOR pueden autorizar (y sólo si el status es CAPTURADA)
  const canAuthorize = computed(() =>
    ['ADMIN', 'CONTADOR'].includes(role.value) &&
    req.value?.status === 'CAPTURADA'
  )

  // Sólo ADMIN/CONTADOR pueden subir pagos
  const canUploadPago = computed(() =>
    ['ADMIN', 'CONTADOR'].includes(role.value)
  )

  const submitting = computed(() => form.processing)

  // ---------- File UX ----------
  const fileKey = ref(0)
  const dragActive = ref(false)
  const pickedName = ref('Arrastra y suelta o selecciona un archivo.')
  const uploadPreview = ref<Preview | null>(null)
  let uploadObjectUrl: string | null = null

  const hasPicked = computed(() => !!form.archivo)

  const clearUploadObjectUrl = () => {
    if (uploadObjectUrl) {
      URL.revokeObjectURL(uploadObjectUrl)
      uploadObjectUrl = null
    }
  }

  const setPickedFile = (file: File | null) => {
    clearUploadObjectUrl()
    form.archivo = file

    if (!file) {
      pickedName.value = 'Arrastra y suelta o selecciona un archivo.'
      uploadPreview.value = null
      fileKey.value++
      return
    }

    pickedName.value = file.name

    uploadObjectUrl = URL.createObjectURL(file)
    const kind: PreviewKind =
      file.type?.includes('pdf') ? 'pdf' : file.type?.startsWith('image/') ? 'image' : detectKindFromName(file.name)

    uploadPreview.value = {
      url: uploadObjectUrl,
      name: file.name,
      kind,
    }
  }

  const clearFile = () => setPickedFile(null)

  const onPickFile = (e: Event) => {
    const input = e.target as HTMLInputElement
    const file = input.files?.[0] ?? null
    setPickedFile(file)
  }

  const onDragEnter = (e: DragEvent) => {
    e.preventDefault()
    dragActive.value = true
  }
  const onDragOver = (e: DragEvent) => {
    e.preventDefault()
    dragActive.value = true
  }
  const onDragLeave = (e: DragEvent) => {
    e.preventDefault()
    dragActive.value = false
  }
  const onDropFile = (e: DragEvent) => {
    e.preventDefault()
    dragActive.value = false
    const file = e.dataTransfer?.files?.[0] ?? null
    setPickedFile(file)
  }

  // ---------- Preview pagos ya hechos ----------
  const preview = ref<Preview | null>(null)
  const previewTitle = computed(() => preview.value?.name ?? '—')

  const openPreview = (p: any) => {
    const url = p?.archivo?.url
    if (!url) return
    const name = p?.archivo?.label ?? 'Archivo'
    const kind = detectKindFromUrl(url) || detectKindFromName(name)
    preview.value = { url, name, kind }
  }

  const closePreview = () => {
    preview.value = null
  }

  // ---------- Monto UX ----------
  const montoText = ref<string>(String(form.monto ?? ''))

  const onMontoInput = (e: Event) => {
    const el = e.target as HTMLInputElement
    let next = sanitizeDecimalInput(el.value)

    if (next === '') {
      montoText.value = ''
      form.monto = ''
      return
    }

    const cents = centsFromText(next)
    if (cents > maxCents.value) {
      next = textFromCents(maxCents.value)
    }

    montoText.value = next
    form.monto = next
  }

  const onMontoBlur = () => {
    let v = String(montoText.value ?? '').trim()
    if (v.endsWith('.')) v = v.slice(0, -1)
    if (v === '0') {
      montoText.value = '0'
      form.monto = '0'
      return
    }
    if (!v) {
      montoText.value = ''
      form.monto = ''
      return
    }

    v = sanitizeDecimalInput(v)
    const cents = centsFromText(v)
    if (cents > maxCents.value) v = textFromCents(maxCents.value)

    montoText.value = v
    form.monto = v
  }

  const canSubmit = computed(() => {
    if (!form.fecha_pago) return false
    if (!form.tipo_pago) return false
    if (!form.archivo) return false

    const cents = centsFromText(String(form.monto ?? ''))
    if (maxCents.value <= 0) return cents === 0
    return cents > 0 && cents <= maxCents.value
  })

  const submit = () => {
    if (!req.value?.id) return
    if (!canSubmit.value) return

    form.post(route('requisiciones.pagar.store', req.value.id), {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => {
        form.reset('fecha_pago', 'monto', 'referencia', 'archivo')
        montoText.value = ''
        clearFile()
      },
    })
  }

  onBeforeUnmount(() => {
    clearUploadObjectUrl()
  })

  return {
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
    canAuthorize,
    canUploadPago,
    montoText,
    onMontoInput,
    onMontoBlur,

    canSubmit,
    submit,

    preview,
    previewTitle,
    openPreview,
    closePreview,
  }
}
