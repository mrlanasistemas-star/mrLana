/**
 * Items de una requisición en captura: misma regla que el servidor
 * (DetallesCalculator): subtotal = cantidad × precio, IVA 16 % si aplica.
 * Lo usan el alta (Create) y la corrección (Corregir).
 */
export const IVA_RATE = 0.16

export type ItemCaptura = {
  sucursal_id: number | string | null
  cantidad: number
  descripcion: string
  precio_unitario: number
  genera_iva: boolean
  subtotal: number
  iva: number
  total: number
}

const round2 = (v: number) => Number((Number.isFinite(v) ? v : 0).toFixed(2))

/** Recalcula cada item en su lugar y devuelve los montos de la requisición. */
export function recalcularItems(items: ItemCaptura[]): { subtotal: number; total: number } {
  let sub = 0
  let total = 0
  items.forEach((item) => {
    item.subtotal = round2(Number(item.cantidad) * Number(item.precio_unitario))
    item.iva = item.genera_iva ? round2(item.subtotal * IVA_RATE) : 0
    item.total = round2(item.subtotal + item.iva)
    sub += item.subtotal
    total += item.total
  })
  return { subtotal: round2(sub), total: round2(total) }
}

export function nuevoItem(sucursalId: number | string | null = null): ItemCaptura {
  return {
    sucursal_id: sucursalId || null,
    cantidad: 1,
    descripcion: '',
    precio_unitario: 0,
    genera_iva: true,
    subtotal: 0,
    iva: 0,
    total: 0,
  }
}

export function money(v: unknown): string {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v ?? 0))
}

/** Errores de cliente de los items (mismas reglas que el alta). */
export function itemsClientErrors(items: ItemCaptura[]): Record<string, string> {
  const out: Record<string, string> = {}
  if (!items.length) out.detalles = 'Agrega al menos un item.'
  items.forEach((d, i) => {
    if (!d.descripcion || String(d.descripcion).trim().length < 2) out[`detalles.${i}.descripcion`] = 'Escribe una descripción.'
    if (!(Number(d.cantidad) > 0)) out[`detalles.${i}.cantidad`] = 'La cantidad debe ser mayor a 0.'
    if (Number(d.precio_unitario) < 0) out[`detalles.${i}.precio_unitario`] = 'El precio unitario no puede ser negativo.'
  })
  return out
}
