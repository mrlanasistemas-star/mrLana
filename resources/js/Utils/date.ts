export function formatDateTime(value?: string | null): string {
  if (!value) return '—'

  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'

  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d)
}

/**
 * Extrae año, mes y día de un string YYYY-MM-DD (o ISO/datetime que empiece así).
 * No usa new Date(string) para evitar el desfase de timezone.
 */
export function dateOnlyParts(input: any): { year: number; month: number; day: number } | null {
  if (!input) return null
  const txt = String(input).trim()
  const match = txt.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (!match) return null
  const year = Number(match[1])
  const month = Number(match[2])
  const day = Number(match[3])
  if (!year || !month || !day) return null
  return { year, month, day }
}

/**
 * Formatea una fecha calendario (YYYY-MM-DD o ISO) a "DD mmm YYYY" en español MX,
 * sin recorrerse un día por timezone. Usa new Date(year, month-1, day) (hora local).
 */
export function formatDateOnlyEsMx(input: any, fallback = '—'): string {
  const parts = dateOnlyParts(input)
  if (!parts) return fallback
  const d = new Date(parts.year, parts.month - 1, parts.day)
  if (Number.isNaN(d.getTime())) return fallback
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(d)
}
