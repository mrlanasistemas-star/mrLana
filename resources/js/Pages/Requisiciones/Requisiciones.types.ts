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

export type RequisicionRow = {
  id: Id
  folio: string
  status: RequisicionStatus
  monto_subtotal: number | string
  monto_total: number | string
  fecha_solicitud: string | null
  fecha_autorizacion: string | null
  fecha_pago: string | null
  observaciones: string | null
  comprador: NamedRef | null         // corporativo
  sucursal: (NamedRef & { corporativo_id?: Id | null }) | null
  solicitante: NamedRef | null
  concepto: NamedRef | null
  proveedor: NamedRef | null
  creador?: { id: Id; nombre?: string | null; name?: string | null } | null
  created_at?: string | null
  updated_at?: string | null
}

export type Catalogos = {
  corporativos: { id: Id; nombre: string; activo?: boolean }[]
  sucursales: { id: Id; nombre: string; codigo: string; corporativo_id: Id; activo?: boolean }[]
  empleados: { id: Id; nombre: string; sucursal_id: Id; puesto?: string; activo?: boolean }[]
  conceptos: { id: Id; nombre: string; activo?: boolean }[]
  proveedores: { id: Id; razon_social: string; rfc?: string; clabe?: string; banco?: string; status?: string }[]
}

export type RequisicionesFilters = {
  q?: string
  status?: string
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
  perPage?: number
  sort?: string
  dir?: 'asc' | 'desc'
}


export type RequisicionesPageProps = {
  requisiciones: Paginated<RequisicionRow>
  filters: RequisicionesFilters
  catalogos: Catalogos
}
