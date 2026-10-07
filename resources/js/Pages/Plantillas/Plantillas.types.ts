// resources/js/Pages/Plantillas/Plantillas.types.ts

export type Id = number

export type PaginationLink = {
  url: string | null
  label: string
  active: boolean
  cleanLabel?: string
}

export type PlantillaRow = {
  id: Id
  nombre: string
  status: 'BORRADOR' | 'ELIMINADA'
  monto_subtotal: string
  monto_total: string
  fecha_solicitud: string | null
  fecha_autorizacion: string | null
  sucursal: { id: Id; nombre: string; codigo: string } | null
  solicitante: { id: Id; nombre: string } | null
  proveedor: { id: Id; nombre: string } | null
  concepto: { id: Id; nombre: string } | null
  observaciones: string | null
}

export type PaginationMeta = {
  current_page: number
  last_page: number
  from: number | null
  to: number | null
  per_page: number
  total: number
}

export type Paginated<T> = {
  data: T[]
  links: PaginationLink[]
  meta: PaginationMeta
}

export type PlantillasFilters = {
  q: string
  status: string
  perPage: number
  sort: string
  dir: 'asc' | 'desc'
}

export type PlantillasPageProps = {
  plantillas: Paginated<PlantillaRow>
  filters: PlantillasFilters
  can?: { registrar: boolean; editar: boolean; eliminar: boolean; usar: boolean }
}
