export type Id = number

export type PaginationLink = {
  url: string | null
  label: string
  active: boolean
}

export type PaginatedMeta = {
  current_page?: number
  last_page?: number
  from?: number | null
  to?: number | null
  total?: number
  per_page?: number
  links?: PaginationLink[]
}

export type Paginated<T> = {
  data: T[]
  links?: PaginationLink[] // a veces viene aquí
  meta?: PaginatedMeta     // a veces viene aquí (común con Resource::collection)
}

export type NamedRef = {
  id: Id
  nombre?: string | null
  razon_social?: string | null
  codigo?: string | null
  logo_url?: string | null
}

export type RequisicionStatus =
  | 'BORRADOR'
  | 'ELIMINADA'
  | 'CAPTURADA'
  | 'PAGO_AUTORIZADO'
  | 'PAGO_RECHAZADO'
  | 'PAGADA'
  | 'POR_COMPROBAR'
  | 'COMPROBACION_ACEPTADA'
  | 'COMPROBACION_RECHAZADA'

/** Acciones permitidas por el servidor para el usuario actual sobre la requisición. */
export type RequisicionAbilities = {
  ver: boolean
  editar: boolean
  capturar: boolean
  eliminar: boolean
  solicitar_eliminacion: boolean
  ver_ajustes: boolean
  solicitar_ajuste: boolean
  ver_pagos: boolean
  autorizar_pago: boolean
  registrar_pago: boolean
  ver_comprobaciones: boolean
  subir_comprobante: boolean
  imprimir?: boolean
  rechazar_pago?: boolean
}

export type RequisicionRow = {
  id: Id
  folio: string
  status: RequisicionStatus
  monto_subtotal: number | string
  monto_total: number | string
  /** Fecha de solicitud (ISO). */
  fecha_solicitud: string | null
  /** Fecha esperada de pago capturada por el solicitante (YYYY-MM-DD, opcional). */
  fecha_pago_esperada?: string | null
  /** Momento real en que se autorizó el pago (ISO). */
  fecha_autorizacion: string | null
  /** Fecha programada/real de pago (YYYY-MM-DD). */
  fecha_pago: string | null
  fecha_registro_ymd?: string | null
  fecha_solicitud_ymd?: string | null
  fecha_pago_ymd?: string | null
  observaciones: string | null
  comprador: NamedRef | null         // corporativo
  sucursal: (NamedRef & { corporativo_id?: Id | null }) | null
  solicitante: NamedRef | null
  concepto: NamedRef | null
  proveedor: NamedRef | null
  creador?: { id: Id; nombre?: string | null; name?: string | null } | null
  created_at?: string | null
  updated_at?: string | null
  eliminacion_pendiente?: boolean
  can?: RequisicionAbilities
}

export type Catalogos = {
  corporativos: { id: Id; nombre: string; activo?: boolean }[]
  sucursales: { id: Id; nombre: string; codigo: string; corporativo_id: Id; activo?: boolean }[]
  empleados: { id: Id; nombre: string; sucursal_id: Id; puesto?: string; activo?: boolean }[]
  conceptos: { id: Id; nombre: string; activo?: boolean }[]
  proveedores: { id: Id; razon_social: string; rfc?: string; clabe?: string; banco?: string; status?: string }[]
  /** true cuando el usuario solo puede registrar requisiciones propias. */
  solicitante_fijo?: boolean
  /** Reglas de captura según los permisos especiales (las vuelve a aplicar el servidor). */
  captura?: CapturaInfo
}

export type CapturaInfo = {
  solicitante_fijo: boolean
  sucursal_fija: boolean
  corporativo_fijo: boolean
  /** Hasta dónde puede elegir sucursal: sucursal | corporativo | global | none. */
  alcance: string
  /** Datos del colaborador de la cuenta (null si no tiene). */
  propio: { solicitante_id: number; sucursal_id: number | null; corporativo_id: number | null } | null
}

export type RequisicionesFilters = {
  q?: string
  status?: string
  tab?: string
  comprador_corp_id?: string | number
  sucursal_id?: string | number
  solicitante_id?: string | number
  concepto_id?: string | number
  proveedor_id?: string | number
  fecha_from?: string
  fecha_to?: string
  fecha_pago_from?: string
  fecha_pago_to?: string
  fecha_registro_from?: string
  fecha_registro_to?: string
  perPage?: number | 'all'
  sort?: string
  dir?: 'asc' | 'desc'
}

export type RequisicionesPageProps = {
  requisiciones: Paginated<RequisicionRow>
  filters: RequisicionesFilters
  catalogos: Catalogos
  pagination?: { default_per_page: number; options: number[] }
}

/** Opciones de "por página" del listado. Debe coincidir con RequisicionController::PER_PAGE_OPTIONS. */
export const DEFAULT_PER_PAGE = 20
export const PER_PAGE_OPTIONS = [10, 15, 20, 50] as const
