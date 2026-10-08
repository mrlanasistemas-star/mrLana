// resources/js/Pages/Plantillas/usePlantillaEdit.ts
import { reactive, computed, watch, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { swalOk, swalErr, swalLoading, swalClose } from '@/lib/swal'
import { usePermissions } from '@/Composables/usePermissions'

type Catalogos = {
  corporativos: { id: number; nombre: string; activo?: boolean }[]
  sucursales:   { id: number; nombre: string; codigo: string; corporativo_id: number; activo?: boolean }[]
  empleados:    { id: number; nombre: string; sucursal_id: number; activo?: boolean }[]
  conceptos:    { id: number; nombre: string; activo?: boolean }[]
  proveedores:  { id: number; nombre: string }[]
}

type Routes = {
  index?: string
  update?: string
}

type InertiaErrors = Record<string, string | string[]>

function firstErrorMessage(errors: InertiaErrors | undefined | null): string | null {
  if (!errors) return null
  const v = Object.values(errors)[0]
  if (!v) return null
  return Array.isArray(v) ? (v[0] ?? null) : v
}

function pickId(plantilla: any, key: string, relKey: string): number | '' {
  const direct = plantilla?.[key]
  if (direct !== undefined && direct !== null && direct !== '') return Number(direct)
  const rel = plantilla?.[relKey]
  const relId = rel?.id
  if (relId !== undefined && relId !== null && relId !== '') return Number(relId)
  return ''
}

export function usePlantillaEdit(catalogos: Catalogos, plantilla: any, routes?: Routes) {
  const page = usePage<any>()
  const { can } = usePermissions()
  /** Sin "ver todas las requisiciones", el solicitante es el colaborador de la cuenta. */
  // Sin "Elegir solicitante" el solicitante queda fijo (el servidor lo vuelve a aplicar).
  const solicitanteFijo = computed(() => !can('requisiciones.elegir_solicitante'))
  const empleadoId = page.props?.auth?.user?.empleado_id ?? null

  const saving = ref(false)
  const showError = ref(false)
  const errors = ref<InertiaErrors>({})

  const state = reactive({
    nombre: '',
    corporativo_id: '' as number | string,
    sucursal_id: '' as number | string,
    solicitante_id: '' as number | string,
    comprador_corp_id: '' as number | string,
    proveedor_id: '' as number | string,
    concepto_id: '' as number | string,
    monto_subtotal: 0,
    monto_total: 0,
    fecha_solicitud: '' as string,
    fecha_pago_esperada: null as string | null,
    observaciones: '',
    detalles: [] as Array<{
      sucursal_id: number | string | null
      cantidad: number
      descripcion: string
      precio_unitario: number
      genera_iva: boolean
      subtotal: number
      iva: number
      total: number
    }>,
  })

  // Load inicial (edición)
  if (plantilla) {
    state.nombre = plantilla.nombre ?? ''

    state.sucursal_id = pickId(plantilla, 'sucursal_id', 'sucursal')
    state.solicitante_id = pickId(plantilla, 'solicitante_id', 'solicitante')
    state.proveedor_id = pickId(plantilla, 'proveedor_id', 'proveedor')
    state.concepto_id = pickId(plantilla, 'concepto_id', 'concepto')

    const corpIdDirect =
      plantilla.comprador_corp_id ?? plantilla.corporativo_id ?? plantilla?.corporativo?.id ?? null

    state.comprador_corp_id = corpIdDirect ? Number(corpIdDirect) : ''
    state.corporativo_id = corpIdDirect ? Number(corpIdDirect) : ''

    state.monto_subtotal = Number(plantilla.monto_subtotal ?? 0)
    state.monto_total = Number(plantilla.monto_total ?? 0)
    state.fecha_solicitud = plantilla.fecha_solicitud ?? ''
    state.fecha_pago_esperada = plantilla.fecha_pago_esperada || null
    state.observaciones = plantilla.observaciones ?? ''

    state.detalles = (plantilla.detalles ?? []).map((d: any) => ({
      sucursal_id: d.sucursal_id ?? state.sucursal_id ?? '',
      cantidad: Number(d.cantidad ?? 1),
      descripcion: d.descripcion ?? '',
      precio_unitario: Number(d.precio_unitario ?? 0),
      genera_iva: Boolean(d.genera_iva ?? true),
      subtotal: Number(d.subtotal ?? 0),
      iva: Number(d.iva ?? 0),
      total: Number(d.total ?? 0),
    }))
  }

  // Auto-solicitante para colaboradores
  if (solicitanteFijo.value && empleadoId) {
    state.solicitante_id = empleadoId
  }

  // Catálogos activos
  const corporativosActive = computed(() => (catalogos.corporativos ?? []).filter(c => c.activo !== false))
  const sucursalesActive = computed(() => (catalogos.sucursales ?? []).filter(s => s.activo !== false))
  const empleadosActive = computed(() => (catalogos.empleados ?? []).filter(e => e.activo !== false))
  const conceptosActive = computed(() => (catalogos.conceptos ?? []).filter(c => c.activo !== false))
  const proveedoresList = computed(() => (catalogos.proveedores ?? []))

  const sucursalesFiltered = computed(() => {
    const corpId = Number(state.corporativo_id || 0)
    if (!corpId) return sucursalesActive.value
    return sucursalesActive.value.filter(s => Number(s.corporativo_id) === corpId)
  })

  // Sync sucursal -> corporativo
  watch(() => state.sucursal_id, (newVal) => {
    if (!newVal) return
    const sId = Number(newVal)
    const s = sucursalesActive.value.find(x => Number(x.id) === sId)
    if (s) {
      state.corporativo_id = s.corporativo_id
      state.comprador_corp_id = s.corporativo_id
    }
  })

  // Sync corporativo -> comprador y valida sucursal
  watch(() => state.corporativo_id, (newVal) => {
    if (!newVal) {
      state.sucursal_id = ''
      state.comprador_corp_id = ''
      return
    }
    const corpId = Number(newVal)
    if (state.sucursal_id) {
      const sId = Number(state.sucursal_id)
      const ok = sucursalesActive.value.some(s => Number(s.id) === sId && Number(s.corporativo_id) === corpId)
      if (!ok) state.sucursal_id = ''
    }
    state.comprador_corp_id = corpId
  })

  // Cálculo totales
  watch(
    () => state.detalles,
    () => {
      let sub = 0
      let total = 0
      state.detalles.forEach((item) => {
        item.subtotal = Number((item.cantidad * item.precio_unitario).toFixed(2))
        item.iva = item.genera_iva ? Number((item.subtotal * 0.16).toFixed(2)) : 0
        item.total = Number((item.subtotal + item.iva).toFixed(2))
        sub += item.subtotal
        total += item.total
      })
      state.monto_subtotal = Number(sub.toFixed(2))
      state.monto_total = Number(total.toFixed(2))
    },
    { deep: true, immediate: true }
  )

  function addItem() {
    state.detalles.push({
      sucursal_id: state.sucursal_id || null,
      cantidad: 1,
      descripcion: '',
      precio_unitario: 0,
      genera_iva: true,
      subtotal: 0,
      iva: 0,
      total: 0,
    })
  }

  function removeItem(index: number) {
    state.detalles.splice(index, 1)
  }

  function fieldError(key: string): string | null {
    const v = errors.value?.[key]
    if (!v) return null
    return Array.isArray(v) ? (v[0] ?? null) : v
  }

  function makePayload() {
    return {
      nombre: state.nombre,
      solicitante_id: state.solicitante_id || null,
      sucursal_id: state.sucursal_id || null,
      comprador_corp_id: state.comprador_corp_id || null,
      proveedor_id: state.proveedor_id || null,
      concepto_id: state.concepto_id || null,
      monto_subtotal: state.monto_subtotal,
      monto_total: state.monto_total,
      fecha_solicitud: state.fecha_solicitud || null,
      fecha_pago_esperada: state.fecha_pago_esperada || null,
      observaciones: state.observaciones || null,
      detalles: state.detalles.map(d => ({
        sucursal_id: d.sucursal_id || null,
        cantidad: d.cantidad,
        descripcion: d.descripcion,
        precio_unitario: d.precio_unitario,
        genera_iva: d.genera_iva,
        subtotal: d.subtotal,
        iva: d.iva,
        total: d.total,
      })),
    }
  }

  function update() {
    showError.value = state.detalles.length === 0
    errors.value = {}

    swalLoading('Actualizando plantilla...')
    saving.value = true

    const url = routes?.update || (() => {
      // fallback por si no mandas routes
      try { return route('plantillas.update', { plantilla: plantilla.id }) } catch { return route('plantillas.update', plantilla.id) }
    })()

    router.put(url, makePayload(), {
      preserveScroll: true,
      onError: (e: InertiaErrors) => {
        errors.value = e || {}
        swalErr(firstErrorMessage(e) || 'Error al actualizar la plantilla')
      },
      onSuccess: () => {
        errors.value = {}
        swalOk('Plantilla actualizada correctamente.', 'Listo')
        router.visit(routes?.index || route('plantillas.index'))
      },
      onFinish: () => {
        saving.value = false
        swalClose()
      },
    })
  }

  function money(v: any) {
    const n = Number(v ?? 0)
    try {
      return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n)
    } catch {
      return String(v ?? '')
    }
  }

  return {
    state,
    items: computed(() => state.detalles),
    corporativosActive,
    sucursalesFiltered,
    empleadosActive,
    conceptosActive,
    proveedoresList,
    addItem,
    removeItem,
    update,
    money,
    solicitanteFijo,
    saving,
    showError,
    fieldError,
  }
}
