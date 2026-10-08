import { reactive, computed, watch, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { swalOk, swalErr, swalLoading, swalClose } from '@/lib/swal'
import type { Catalogos } from './Requisiciones.types'

type Plantilla = Record<string, any> | null
type InertiaErrors = Record<string, string | string[]>

export const PROVEEDOR_REQUERIDO = 'Selecciona un proveedor activo antes de guardar o enviar la requisición.'
export const FECHA_PASADA = 'La fecha de solicitud no puede ser anterior a hoy.'
export const ESPERADA_ANTERIOR = 'La fecha esperada de pago no puede ser anterior a la fecha de solicitud.'

function firstErrorMessage(errors: InertiaErrors | undefined | null): string | null {
    if (!errors) return null
    const v = Object.values(errors)[0]
    if (!v) return null
    return Array.isArray(v) ? (v[0] ?? null) : v
}

/** YYYY-MM-DD comparables como texto. */
const ymd = (v: unknown): string => (typeof v === 'string' ? v.slice(0, 10) : '')

/**
 * Formulario de nueva requisición.
 * `today` lo calcula el servidor con la zona horaria de negocio; el servidor
 * vuelve a validar todas las reglas al guardar.
 */
export function useRequisicionCreate(catalogos: Catalogos, plantilla: Plantilla = null, today = '') {
    // Reglas de captura del servidor (CaptureContext). Los catálogos ya llegan
    // limitados a lo que la persona puede elegir; el servidor vuelve a validar.
    const captura = catalogos.captura ?? null
    const solicitanteFijo = computed(() => captura?.solicitante_fijo ?? catalogos.solicitante_fijo === true)
    const sucursalFija = computed(() => captura?.sucursal_fija ?? solicitanteFijo.value)
    const corporativoFijo = computed(() => captura?.corporativo_fijo ?? solicitanteFijo.value)

    const saving = ref(false)
    const showError = ref(false)
    const errors = ref<InertiaErrors>({})

    const state = reactive({
        corporativo_id: '' as number | string,
        sucursal_id: '' as number | string,
        solicitante_id: '' as number | string,
        comprador_corp_id: '' as number | string,
        proveedor_id: '' as number | string,
        concepto_id: '' as number | string,
        monto_subtotal: 0,
        monto_total: 0,
        fecha_solicitud: today,
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

    function fieldError(key: string): string | null {
        const v = errors.value?.[key]
        if (!v) return null
        return Array.isArray(v) ? (v[0] ?? null) : v
    }

    function loadFromPlantilla() {
        if (!plantilla) return
        state.corporativo_id = plantilla.comprador_corp_id ?? ''
        state.sucursal_id = plantilla.sucursal_id ?? ''
        state.solicitante_id = plantilla.solicitante_id ?? ''
        state.comprador_corp_id = plantilla.comprador_corp_id ?? ''
        state.proveedor_id = plantilla.proveedor_id ?? ''
        state.concepto_id = plantilla.concepto_id ?? ''
        state.monto_subtotal = Number(plantilla.monto_subtotal ?? 0)
        state.monto_total = Number(plantilla.monto_total ?? 0)
        // Una plantilla puede tener fechas pasadas: se usa hoy como mínimo.
        const fecha = ymd(plantilla.fecha_solicitud)
        state.fecha_solicitud = fecha && (!today || fecha >= today) ? fecha : today
        const esperada = ymd(plantilla.fecha_pago_esperada)
        state.fecha_pago_esperada = esperada && esperada >= state.fecha_solicitud ? esperada : null
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
    loadFromPlantilla()

    // Valores predeterminados: el colaborador de la cuenta, su sucursal y su corporativo.
    const propio = captura?.propio ?? null
    const sucursalesCatalogo = (catalogos.sucursales ?? []).filter((s) => s.activo !== false)
    const enCatalogo = (id: unknown) => sucursalesCatalogo.some((s) => Number(s.id) === Number(id))
    if (propio) {
        if (solicitanteFijo.value || !state.solicitante_id) state.solicitante_id = propio.solicitante_id
        if (sucursalFija.value || (!state.sucursal_id && enCatalogo(propio.sucursal_id))) {
            state.sucursal_id = propio.sucursal_id ?? ''
            state.corporativo_id = propio.corporativo_id ?? ''
            state.comprador_corp_id = propio.corporativo_id ?? ''
        } else if (corporativoFijo.value && !state.corporativo_id) {
            state.corporativo_id = propio.corporativo_id ?? ''
            state.comprador_corp_id = propio.corporativo_id ?? ''
        }
    } else if (!captura && solicitanteFijo.value) {
        // Compatibilidad: sin datos de captura se usa el único colaborador permitido.
        const e = (catalogos.empleados ?? [])[0]
        const s = e ? sucursalesCatalogo.find((x) => Number(x.id) === Number(e.sucursal_id)) : null
        if (e) state.solicitante_id = e.id
        if (s) {
            state.sucursal_id = s.id
            state.corporativo_id = s.corporativo_id
            state.comprador_corp_id = s.corporativo_id
        }
    }

    const corporativosActive = computed(() => (catalogos.corporativos ?? []).filter((c) => c.activo !== false))
    const sucursalesActive = computed(() => sucursalesCatalogo)

    /**
     * Si cambia el solicitante a alguien de otra sucursal no se cambia nada en
     * silencio: se ofrece "Usar datos del solicitante" cuando esa sucursal se
     * puede elegir.
     */
    const sugerenciaSolicitante = computed(() => {
        if (solicitanteFijo.value || sucursalFija.value) return null
        const e = (catalogos.empleados ?? []).find((x) => Number(x.id) === Number(state.solicitante_id || 0))
        if (!e || !e.sucursal_id || Number(e.sucursal_id) === Number(state.sucursal_id || 0)) return null
        const s = sucursalesCatalogo.find((x) => Number(x.id) === Number(e.sucursal_id))
        if (!s) return null
        const corp = (catalogos.corporativos ?? []).find((c) => Number(c.id) === Number(s.corporativo_id))
        return { sucursal: s, corporativo: corp ?? null }
    })

    function usarDatosSolicitante() {
        const sug = sugerenciaSolicitante.value
        if (!sug) return
        state.corporativo_id = sug.sucursal.corporativo_id
        state.sucursal_id = sug.sucursal.id
    }

    const empleadosActive = computed(() => (catalogos.empleados ?? []).filter((e) => e.activo !== false))
    const conceptosActive = computed(() => (catalogos.conceptos ?? []).filter((c) => c.activo !== false))
    const proveedoresList = computed(() =>
        (catalogos.proveedores ?? []).filter((p) => String(p.status ?? '').toUpperCase() === 'ACTIVO'),
    )

    // Un proveedor de plantilla que ya no está activo no se preselecciona.
    if (state.proveedor_id && !proveedoresList.value.some((p) => Number(p.id) === Number(state.proveedor_id))) {
        state.proveedor_id = ''
    }

    const sucursalesFiltered = computed(() => {
        const corpId = Number(state.corporativo_id || 0)
        if (!corpId) return []
        return sucursalesActive.value.filter((s) => Number(s.corporativo_id) === corpId)
    })

    const selectedProveedor = computed(() => {
        const id = Number(state.proveedor_id || 0)
        if (!id) return null
        return proveedoresList.value.find((p) => Number(p.id) === id) ?? null
    })

    watch(
        () => state.corporativo_id,
        (newVal) => {
            errors.value = {}
            const corpId = Number(newVal || 0)
            state.comprador_corp_id = corpId ? corpId : ''
            if (!corpId) {
                state.sucursal_id = ''
                state.detalles.forEach((d) => (d.sucursal_id = null))
                return
            }
            const sid = Number(state.sucursal_id || 0)
            if (sid) {
                const s = sucursalesActive.value.find((x) => Number(x.id) === sid)
                if (s && Number(s.corporativo_id) !== corpId) {
                    state.sucursal_id = ''
                    state.detalles.forEach((d) => (d.sucursal_id = null))
                }
            }
        },
    )

    watch(
        () => state.sucursal_id,
        (newVal) => {
            errors.value = {}
            if (!newVal) return
            const sId = Number(newVal)
            const s = sucursalesActive.value.find((x) => Number(x.id) === sId)
            if (s) {
                state.corporativo_id = s.corporativo_id
                state.comprador_corp_id = s.corporativo_id
                state.detalles.forEach((d) => {
                    if (!d.sucursal_id) d.sucursal_id = sId
                })
            }
        },
    )

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
        { deep: true, immediate: true },
    )

    /** Mínimo para la fecha esperada: la fecha de solicitud (o hoy). */
    const minFechaEsperada = computed(() => state.fecha_solicitud || today)

    /** Errores de cliente por campo (se muestran tras el primer intento de guardar). */
    const clientErrors = computed<Record<string, string>>(() => {
        const out: Record<string, string> = {}
        if (!state.corporativo_id) out.comprador_corp_id = 'Selecciona un comprador.'
        if (!state.sucursal_id) out.sucursal_id = 'Selecciona una sucursal.'
        if (!state.solicitante_id) out.solicitante_id = 'Selecciona un solicitante.'
        if (!state.concepto_id) out.concepto_id = 'Selecciona un concepto.'
        if (!state.proveedor_id) out.proveedor_id = PROVEEDOR_REQUERIDO
        if (!state.fecha_solicitud) out.fecha_solicitud = 'La fecha de solicitud es obligatoria.'
        else if (today && state.fecha_solicitud < today) out.fecha_solicitud = FECHA_PASADA
        if (state.fecha_pago_esperada && state.fecha_solicitud && state.fecha_pago_esperada < state.fecha_solicitud) {
            out.fecha_pago_esperada = ESPERADA_ANTERIOR
        }
        if (!state.detalles.length) out.detalles = 'Agrega al menos un item.'
        state.detalles.forEach((d, i) => {
            if (!d.descripcion || String(d.descripcion).trim().length < 2) out[`detalles.${i}.descripcion`] = 'Escribe una descripción.'
            if (!(Number(d.cantidad) > 0)) out[`detalles.${i}.cantidad`] = 'La cantidad debe ser mayor a 0.'
        })
        return out
    })

    /** Error visible de un campo: primero el del servidor, luego el de cliente. */
    function errorFor(key: string): string | null {
        return fieldError(key) ?? (showError.value ? clientErrors.value[key] ?? null : null)
    }

    function addItem() {
        errors.value = {}
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
        errors.value = {}
        state.detalles.splice(index, 1)
    }

    function money(v: unknown) {
        return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v ?? 0))
    }

    function makePayload() {
        return {
            solicitante_id: state.solicitante_id,
            sucursal_id: state.sucursal_id || null,
            comprador_corp_id: state.comprador_corp_id || null,
            proveedor_id: state.proveedor_id || null,
            concepto_id: state.concepto_id || null,
            fecha_solicitud: state.fecha_solicitud || null,
            fecha_pago_esperada: state.fecha_pago_esperada || null,
            observaciones: state.observaciones || null,
            detalles: state.detalles.map((d) => ({
                sucursal_id: d.sucursal_id || null,
                cantidad: d.cantidad,
                descripcion: d.descripcion,
                precio_unitario: d.precio_unitario,
                genera_iva: d.genera_iva,
            })),
        }
    }

    function submit(accion: 'BORRADOR' | 'ENVIAR') {
        showError.value = true
        const pending = Object.values(clientErrors.value)
        if (pending.length) {
            swalErr(pending[0] ?? 'Revisa los campos obligatorios.')
            return
        }
        errors.value = {}
        swalLoading(accion === 'ENVIAR' ? 'Enviando requisición…' : 'Guardando borrador…')
        saving.value = true
        const routeName = accion === 'ENVIAR' ? 'requisiciones.storeCaptured' : 'requisiciones.storeDraft'
        router.post(route(routeName), makePayload(), {
            preserveScroll: true,
            onError: (e: InertiaErrors) => {
                errors.value = e || {}
                const raw = firstErrorMessage(e)
                swalErr(raw && raw.includes('validation.') ? 'Revisa los campos obligatorios.' : raw || 'No se pudo guardar la requisición.')
            },
            onSuccess: () => {
                errors.value = {}
                showError.value = false
                swalOk(accion === 'ENVIAR' ? 'Requisición enviada correctamente.' : 'Borrador guardado correctamente.', 'Listo')
            },
            onFinish: () => {
                saving.value = false
                swalClose()
            },
        })
    }

    return {
        state,
        items: computed(() => state.detalles),
        corporativosActive,
        sucursalesFiltered,
        empleadosActive,
        conceptosActive,
        proveedoresList,
        selectedProveedor,
        minFechaEsperada,
        addItem,
        removeItem,
        saveDraft: () => submit('BORRADOR'),
        sendRequi: () => submit('ENVIAR'),
        money,
        solicitanteFijo,
        sucursalFija,
        corporativoFijo,
        sugerenciaSolicitante,
        usarDatosSolicitante,
        saving,
        errorFor,
    }
}
