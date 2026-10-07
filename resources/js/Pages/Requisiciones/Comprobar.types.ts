export interface ComprobanteRow {
  id: number
  fecha_emision: string | null
  tipo_doc: 'FACTURA' | 'TICKET' | 'NOTA' | 'OTRO'
  monto: number
  archivo: null | { label: string; url: string }
  estatus: 'PENDIENTE' | 'APROBADO' | 'RECHAZADO'
  comentario_revision?: string | null
  revisado_at?: string | null
  user_carga?: { id?: number | null; name?: string | null } | null
  user_revision?: { id?: number | null; name?: string | null } | null
}

export interface FolioOption {
  id: number
  folio: string
  monto_total: number | string | null
}

export interface RequisicionComprobarLite {
  id: number
  folio: string
  concepto: string | null
  monto_total: number
  solicitante_nombre: string
  razon_social?: string | null
  rfc?: string | null
  direccion?: string | null
  correo?: string | null
}

export interface RequisicionComprobarPageProps {
  requisicion: { data: RequisicionComprobarLite } | RequisicionComprobarLite
  comprobantes: { data: ComprobanteRow[] } | ComprobanteRow[]
  totales: {
    cargado: number
    aprobado: number
    pendiente_por_comprobar: number
    pendiente_por_aprobar: number
  }
  tipoDocOptions: { id: string; nombre: string }[]
  canReview: boolean
  can?: {
    revisar: boolean
    subir: boolean
    eliminar: boolean
    administrar_folios: boolean
    ver_ajustes: boolean
    solicitar_ajuste: boolean
  }
  folios?: FolioOption[] | { data: FolioOption[] }
}
