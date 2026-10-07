import { reactive, computed, watch, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { swalOk, swalErr, swalLoading, swalClose } from '@/lib/swal'
import { usePermissions } from '@/Composables/usePermissions'

/**
 * Tipos de catálogo disponibles para crear/editar una Plantilla.
 */
type Catalogos = {
  corporativos: { id: number; nombre: string; activo?: boolean }[]
  sucursales:   { id: number; nombre: string; codigo: string; corporativo_id: number; activo?: boolean }[]
  empleados:    { id: number; nombre: string; sucursal_id: number; activo?: boolean }[]
  conceptos:    { id: number; nombre: string; activo?: boolean }[]
  proveedores:  { id: number; nombre?: string; razon_social?: string }[]
  solicitante_fijo?: boolean
}

type Plantilla = any | null

type InertiaErrors = Record<string, string | string[]>

function firstErrorMessage(errors: InertiaErrors | undefined | null): string | null {
  if (!errors) return null
  const v = Object.values(errors)[0]
  if (!v) return null
  return Array.isArray(v) ? (v[0] ?? null) : v
}

/**
 * Hook de composición para manejar el formulario de creación/edición de Plantillas.
 *
 * Este hook se encarga de:
 *   - Inicializar el estado reactivo del formulario, precargando datos si se edita una plantilla existente.
 *   - Auto-asignar solicitante, sucursal y corporativo para usuarios con rol COLABORADOR.
 *   - Filtrar los catálogos disponibles según el rol del usuario (los colaboradores sólo pueden ver su propia sucursal/corporativo/empleado).
 *   - Sincronizar los campos dependientes entre sí (por ejemplo, cuando se cambia la sucursal se actualiza el corporativo).
 *   - Calcular subtotales, IVA y totales de los items de la plantilla.
 *   - Exponer funciones de utilidad para agregar/quitar items y enviar la información al backend.
 */
export function usePlantillaCreate(catalogos: Catalogos, plantilla: Plantilla = null) {
  const page = usePage<any>()
  const { can } = usePermissions()
  /** Sin "ver todas las requisiciones", el solicitante es el colaborador de la cuenta. */
  const solicitanteFijo = computed(() => !can('requisiciones.ver_todos'))
  const empleadoId = page.props?.auth?.user?.empleado_id ?? null

  const saving = ref(false)
  const showError = ref(false)

  // Errores provenientes de las validaciones de Inertia/Laravel.
  const errors = ref<InertiaErrors>({})

  // Estado principal del formulario. Usamos reactive() para que sea reactivo en profundidad.
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
    // Guardamos la fecha como 'YYYY-MM-DD'; DatePickerShadcn ya emite ese formato.
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

  // Precarga de datos si se está editando una plantilla existente.
  if (plantilla) {
    state.nombre = plantilla.nombre ?? ''
    state.sucursal_id = plantilla.sucursal_id ?? ''
    state.solicitante_id = plantilla.solicitante_id ?? ''
    state.comprador_corp_id = plantilla.comprador_corp_id ?? ''
    state.corporativo_id = plantilla.comprador_corp_id ?? ''
    state.proveedor_id = plantilla.proveedor_id ?? ''
    state.concepto_id = plantilla.concepto_id ?? ''
    state.monto_subtotal = Number(plantilla.monto_subtotal ?? 0)
    state.monto_total = Number(plantilla.monto_total ?? 0)
    state.fecha_solicitud = plantilla.fecha_solicitud ?? ''
    state.fecha_pago_esperada = plantilla.fecha_pago_esperada || null
    state.observaciones = plantilla.observaciones ?? ''
    state.detalles = (plantilla.detalles ?? []).map((d: any) => ({
      sucursal_id: d.sucursal_id ?? '',
      cantidad: Number(d.cantidad ?? 1),
      descripcion: d.descripcion ?? '',
      precio_unitario: Number(d.precio_unitario ?? 0),
      genera_iva: Boolean(d.genera_iva ?? true),
      subtotal: Number(d.subtotal ?? 0),
      iva: Number(d.iva ?? 0),
      total: Number(d.total ?? 0),
    }))
  }

  // Auto-asignar solicitante, sucursal y corporativo para colaboradores.
  if (solicitanteFijo.value && empleadoId) {
    state.solicitante_id = empleadoId
    // Buscar el registro del empleado actual para determinar sucursal y corporativo.
    const empleadoRecord = (catalogos.empleados ?? []).find(
      (e) => Number(e.id) === Number(empleadoId)
    )
    if (empleadoRecord) {
      // Asignar sucursal por defecto.
      state.sucursal_id = empleadoRecord.sucursal_id
      // Derivar corporativo a partir de la sucursal.
      const sucursalRecord = (catalogos.sucursales ?? []).find(
        (s) => Number(s.id) === Number(empleadoRecord.sucursal_id)
      )
      if (sucursalRecord) {
        state.corporativo_id = sucursalRecord.corporativo_id
        state.comprador_corp_id = sucursalRecord.corporativo_id
      }
    }
  }

  /**
   * Cómputos para filtrar catálogos activos.
   * Para colaboradores, se limita la lista a su propio corporativo, sucursal y empleado.
   */
  const corporativosActive = computed(() => {
    const list = (catalogos.corporativos ?? []).filter((c) => c.activo !== false)
    if (solicitanteFijo.value) {
      const corpId = Number(state.corporativo_id || 0)
      return list.filter((c) => Number(c.id) === corpId)
    }
    return list
  })

  const sucursalesActive = computed(() => {
    const list = (catalogos.sucursales ?? []).filter((s) => s.activo !== false)
    if (solicitanteFijo.value) {
      const sucId = Number(state.sucursal_id || 0)
      return list.filter((s) => Number(s.id) === sucId)
    }
    return list
  })

  const empleadosActive = computed(() => {
    const list = (catalogos.empleados ?? []).filter((e) => e.activo !== false)
    if (solicitanteFijo.value) {
      return list.filter((e) => Number(e.id) === Number(empleadoId))
    }
    return list
  })

  const conceptosActive = computed(() => (catalogos.conceptos ?? []).filter((c) => c.activo !== false))
  const proveedoresList = computed(() => catalogos.proveedores ?? [])

  /**
   * Lista de sucursales filtrada según el corporativo seleccionado.
   * Si no se ha seleccionado un corporativo, devuelve todas las sucursales activas (aplicando el filtro de rol).
   */
  const sucursalesFiltered = computed(() => {
    const corpId = Number(state.corporativo_id || 0)
    if (!corpId) return sucursalesActive.value
    return sucursalesActive.value.filter((s) => Number(s.corporativo_id) === corpId)
  })

  // Sincronizar: cuando cambia la sucursal, actualizar corporativo y comprador_corp_id.
  watch(
    () => state.sucursal_id,
    (newVal) => {
      if (!newVal) return
      const sId = Number(newVal)
      const s = sucursalesActive.value.find((x) => Number(x.id) === sId)
      if (s) {
        state.corporativo_id = s.corporativo_id
        state.comprador_corp_id = s.corporativo_id
      }
    }
  )

  // Sincronizar: cuando cambia el corporativo, validar sucursal y actualizar comprador_corp_id.
  watch(
    () => state.corporativo_id,
    (newVal) => {
      if (!newVal) {
        state.sucursal_id = ''
        state.comprador_corp_id = ''
        return
      }
      const corpId = Number(newVal)
      if (state.sucursal_id) {
        const sId = Number(state.sucursal_id)
        const ok = sucursalesActive.value.some(
          (s) => Number(s.id) === sId && Number(s.corporativo_id) === corpId
        )
        if (!ok) state.sucursal_id = ''
      }
      state.comprador_corp_id = corpId
    }
  )

  // Calcular totales y subtotales de los detalles cada vez que la lista cambia.
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

  /**
   * Agrega un nuevo item a la plantilla. Para colaboradores, se precarga la sucursal actual.
   */
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

  /**
   * Elimina un item de la plantilla según su índice.
   */
  function removeItem(index: number) {
    state.detalles.splice(index, 1)
  }

  /**
   * Devuelve el mensaje de error para un campo específico, si existe.
   */
  function fieldError(key: string): string | null {
    const v = errors.value?.[key]
    if (!v) return null
    return Array.isArray(v) ? v[0] ?? null : v
  }

  /**
   * Construye el payload que se enviará al backend a partir del estado actual.
   */
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
      detalles: state.detalles.map((d) => ({
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

  /**
   * Envía una solicitud POST para crear una nueva plantilla.
   */
  function save() {
    showError.value = state.detalles.length === 0
    errors.value = {}
    swalLoading('Guardando plantilla...')
    saving.value = true
    router.post(route('plantillas.store'), makePayload(), {
      preserveScroll: true,
      onError: (e: InertiaErrors) => {
        errors.value = e || {}
        const msg = firstErrorMessage(e) || 'Error al guardar la plantilla'
        swalErr(msg)
      },
      onSuccess: () => {
        errors.value = {}
        swalOk('Plantilla guardada correctamente.', 'Listo')
        router.visit(route('plantillas.index'))
      },
      onFinish: () => {
        saving.value = false
        swalClose()
      },
    })
  }

  /**
   * Envía una solicitud PUT para actualizar una plantilla existente.
   */
  function update(id: number) {
    showError.value = state.detalles.length === 0
    errors.value = {}
    swalLoading('Actualizando plantilla...')
    saving.value = true
    router.put(route('plantillas.update', id), makePayload(), {
      preserveScroll: true,
      onError: (e: InertiaErrors) => {
        errors.value = e || {}
        const msg = firstErrorMessage(e) || 'Error al actualizar la plantilla'
        swalErr(msg)
      },
      onSuccess: () => {
        errors.value = {}
        swalOk('Plantilla actualizada correctamente.', 'Listo')
        router.visit(route('plantillas.index'))
      },
      onFinish: () => {
        saving.value = false
        swalClose()
      },
    })
  }

  /**
   * Formatea un número como moneda MXN.
   */
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
    save,
    update,
    money,
    solicitanteFijo,
    saving,
    showError,
    fieldError,
  }
}
