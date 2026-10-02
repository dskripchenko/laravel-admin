/**
 * The value of Field\DateRange: `{from, to}` as the backend stores it; a
 * `[from, to]` pair or `{start, end}` from a host is read as well.
 */

export interface DateRangeValue {
  start: string | null
  end: string | null
}

/** The 'YYYY-MM-DD' a value starts with; anything else is no date at all. */
const datePart = (v: unknown): string | null =>
  typeof v === 'string' && /^\d{4}-\d{2}-\d{2}/.test(v) ? v.slice(0, 10) : null

export function readDateRange(raw: unknown): DateRangeValue {
  if (Array.isArray(raw)) return { start: datePart(raw[0]), end: datePart(raw[1]) }
  if (raw && typeof raw === 'object') {
    const r = raw as Record<string, unknown>
    return { start: datePart(r.from ?? r.start), end: datePart(r.to ?? r.end) }
  }
  return { start: null, end: null }
}
