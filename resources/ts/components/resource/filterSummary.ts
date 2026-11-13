/**
 * The applied filters in one line, for «Ничего не найдено»:
 * `Статус «ошибка», Создано 01.11–13.11, «ВЗР»` — the user sees what to loosen
 * without opening each chip.
 */
import { trSafe as tr } from '../../stores/i18n'

interface FilterMeta {
  name?: unknown
  label?: unknown
  options?: unknown
}

function optionLabel(meta: FilterMeta | undefined, value: unknown): string {
  if (meta && Array.isArray(meta.options)) {
    const hit = (meta.options as Array<{ value?: unknown; label?: unknown }>).find((o) => String(o.value) === String(value))
    if (hit && typeof hit.label === 'string') return hit.label
  }
  if (value === true || value === '1' || value === 1) return tr('Да')
  if (value === false || value === '0' || value === 0) return tr('Нет')
  return String(value)
}

/** `2026-11-01` → `01.11`; anything else as it is. */
function shortDate(value: unknown): string {
  const s = String(value ?? '')
  const m = s.match(/^\d{4}-(\d{2})-(\d{2})/)
  return m ? `${m[2]}.${m[1]}` : s
}

function valueText(meta: FilterMeta | undefined, value: unknown): string | null {
  if (value === null || value === undefined || value === '') return null
  if (Array.isArray(value)) {
    const parts = value.map((v) => optionLabel(meta, v)).filter((v) => v !== '')
    return parts.length > 0 ? `«${parts.join(', ')}»` : null
  }
  if (typeof value === 'object') {
    const range = value as { from?: unknown; to?: unknown }
    if ('from' in range || 'to' in range) {
      const from = range.from ? shortDate(range.from) : ''
      const to = range.to ? shortDate(range.to) : ''
      if (from === '' && to === '') return null
      return `${from}–${to}`
    }
    return null
  }
  return `«${optionLabel(meta, value)}»`
}

export function filterSummary(
  filters: Record<string, unknown>,
  metas: ReadonlyArray<Record<string, unknown>>,
  search = '',
): string {
  const parts: string[] = []
  if (search !== '') parts.push(`«${search}»`)
  for (const [name, value] of Object.entries(filters)) {
    const meta = metas.find((m) => m.name === name) as FilterMeta | undefined
    const text = valueText(meta, value)
    if (text === null) continue
    const label = typeof meta?.label === 'string' && meta.label !== '' ? meta.label : name
    parts.push(`${label} ${text}`)
  }
  return parts.join(', ')
}
