// resources/js/Pages/Requisiciones/Pagar.types.ts
export type FileLink = {
  label: string
  url: string
}

export type Beneficiario = {
  nombre?: string | null
  rfc?: string | null
  clabe?: string | null
  banco?: string | null
}

export type PagoRow = {
  id: number
  fecha_pago: string | null
  tipo_pago: string
  monto: number
  referencia?: string | null
  archivo?: FileLink | null
  beneficiario?: Beneficiario | null
}

export type RequisicionPagoData = {
  id: number
  folio: string
  concepto?: string | null
  monto_total: number
  solicitante_nombre?: string | null
  beneficiario?: Beneficiario | null
  status?: string | null
  fecha_solicitud?: string | null
  /** Fecha esperada de pago capturada por el solicitante. */
  fecha_pago_esperada?: string | null
  /** Fecha real de autorización del pago. */
  fecha_autorizacion?: string | null
  fecha_pago_programada?: string | null
  cantidad_pagos?: number
  puede_definir_fecha_pago_general?: boolean
}

export type TipoPagoOption = {
  id: string
  nombre: string
}

export type RequisicionPagoPageProps = {
  requisicion: { data: RequisicionPagoData } | RequisicionPagoData
  pagos: { data: PagoRow[] } | PagoRow[]
  totales?: { pagado: number; pendiente: number }
  tipoPagoOptions: TipoPagoOption[]
  /** Acciones permitidas por el servidor para el usuario actual. */
  can?: { autorizar: boolean; rechazar?: boolean; registrar: boolean; editar?: boolean; descargar?: boolean }
  auth?: any
  errors?: any
}
