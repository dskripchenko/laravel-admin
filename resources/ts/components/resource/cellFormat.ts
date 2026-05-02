/**
 * Formatter cell-значений для ResourceIndexPage.
 *
 * Backend-side TableColumn presets:
 *   - text       → as-is (string).
 *   - date       → 'd.m.Y' (default).
 *   - datetime   → 'd.m.Y H:i:s' (default).
 *   - money      → '{value} {currency}' с decimals.
 *   - boolean    → trueLabel / falseLabel из meta.
 *   - badge      → текст; стилизация на UI рендере (через slot или CSS-class).
 *   - bytes      → human-readable размер.
 *   - text       → fallback.
 *
 * `auto-format` для колонок без явного preset'а: если значение похоже на ISO
 * datetime (`*_at` или строка с T...Z) — применяется default datetime формат.
 */

export type CellPreset = 'text' | 'date' | 'datetime' | 'money' | 'boolean' | 'badge' | 'bytes'

export interface CellMeta {
  format?: string
  currency?: string
  decimals?: number
  trueLabel?: string | null
  falseLabel?: string | null
  [key: string]: unknown
}

const DEFAULT_DATETIME = 'd.m.Y H:i:s'
const DEFAULT_DATE = 'd.m.Y'

const ISO_DATETIME_RE = /^\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}/

export function formatCell(
  value: unknown,
  preset: string | undefined,
  meta: CellMeta = {},
): string {
  if (value === null || value === undefined) return ''

  // Auto-detection ISO datetime для колонок без preset'а.
  if (!preset && typeof value === 'string' && ISO_DATETIME_RE.test(value)) {
    return formatDateString(value, DEFAULT_DATETIME)
  }

  switch (preset) {
    case 'date':
      return formatDateString(String(value), meta.format ?? DEFAULT_DATE)
    case 'datetime':
      return formatDateString(String(value), meta.format ?? DEFAULT_DATETIME)
    case 'money':
      return formatMoney(value, meta.currency ?? 'RUB', meta.decimals ?? 2)
    case 'boolean':
      return formatBoolean(value, meta.trueLabel ?? 'Да', meta.falseLabel ?? 'Нет')
    case 'bytes':
      return formatBytes(value)
    default:
      return String(value)
  }
}

/**
 * PHP-стиль format strings → JS-Date.
 *
 * Поддерживаемые токены:
 *   d → 01-31, m → 01-12, Y → 2026, y → 26
 *   H → 00-23, h → 12-hour, i → minutes, s → seconds
 *   D → Mon-Sun (3-letter), l → full day name, M → Jan, F → January
 *   N → 1-7 (ISO weekday), w → 0-6
 *   U → Unix timestamp, c → ISO 8601
 */
function formatDateString(input: string, format: string): string {
  const date = new Date(input)
  if (isNaN(date.getTime())) return input

  const pad = (n: number, w = 2): string => String(n).padStart(w, '0')

  const tokens: Record<string, string> = {
    d: pad(date.getDate()),
    m: pad(date.getMonth() + 1),
    Y: String(date.getFullYear()),
    y: pad(date.getFullYear() % 100),
    H: pad(date.getHours()),
    h: pad(((date.getHours() + 11) % 12) + 1),
    i: pad(date.getMinutes()),
    s: pad(date.getSeconds()),
    U: String(Math.floor(date.getTime() / 1000)),
    c: date.toISOString(),
    N: String(((date.getDay() + 6) % 7) + 1),
    w: String(date.getDay()),
  }

  // Замена с учётом escape '\\' (PHP: backslash escapes the next char).
  let out = ''
  for (let i = 0; i < format.length; i++) {
    const ch = format[i]
    if (ch === '\\' && i + 1 < format.length) {
      out += format[i + 1]
      i++
      continue
    }
    out += tokens[ch] ?? ch
  }
  return out
}

function formatMoney(value: unknown, currency: string, decimals: number): string {
  const n = typeof value === 'number' ? value : Number(value)
  if (isNaN(n)) return String(value)
  return `${n.toFixed(decimals)} ${currency}`
}

function formatBoolean(value: unknown, trueLabel: string, falseLabel: string): string {
  const truthy = value === true || value === 1 || value === '1' || value === 'true'
  return truthy ? trueLabel : falseLabel
}

function formatBytes(value: unknown): string {
  const n = typeof value === 'number' ? value : Number(value)
  if (isNaN(n)) return String(value)
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  if (n < 1024 * 1024 * 1024) return `${(n / 1024 / 1024).toFixed(1)} MB`
  return `${(n / 1024 / 1024 / 1024).toFixed(1)} GB`
}
