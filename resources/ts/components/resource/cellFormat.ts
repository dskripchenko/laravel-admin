/**
 * Formats the cell values of ResourceIndexPage.
 *
 * The backend's TableColumn presets:
 *   - text       → as it is, a string.
 *   - date       → 'd.m.Y' by default.
 *   - datetime   → 'd.m.Y H:i:s' by default.
 *   - money      → '{value} {currency}', with decimals.
 *   - boolean    → the trueLabel or falseLabel from the meta.
 *   - badge      → the label from meta.labels (TableColumn::asBadge), or the
 *                  value as it is; the tone comes from badgeTone().
 *   - bytes      → a human-readable size.
 *   - image      → the URL; AdminTableCell draws the picture.
 *   - link       → the caption; AdminTableCell draws the link.
 *   - text       → the fallback.
 *
 * Columns with no explicit preset are formatted automatically: a value that
 * looks like an ISO datetime — the column ends in `_at`, or the string carries
 * T...Z — gets the default datetime format.
 */

import { currentLocale, formatNumber, trSafe as tr } from '../../stores/i18n'

export type CellPreset = 'text' | 'date' | 'datetime' | 'money' | 'boolean' | 'badge' | 'bytes' | 'image' | 'link'

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

  // The automatic detection of an ISO datetime, for the columns with no preset.
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
      return formatBoolean(value, tr(meta.trueLabel ?? 'Да'), tr(meta.falseLabel ?? 'Нет'))
    case 'bytes':
      return formatBytes(value)
    case 'badge':
      return badgeLabel(value, meta)
    case 'json':
      return safeJson(value)
    default:
      if (Array.isArray(value) || (value !== null && typeof value === 'object')) {
        return safeJson(value)
      }
      return String(value)
  }
}

export type BadgeTone = 'info' | 'success' | 'warning' | 'danger' | 'default'

/**
 * Tone names pass through; the colour names the docs use (green, red, …) map
 * onto the UI kit's tones.
 */
const BADGE_TONES: Record<string, BadgeTone> = {
  info: 'info', success: 'success', warning: 'warning', danger: 'danger', default: 'default',
  blue: 'info', green: 'success', yellow: 'warning', amber: 'warning', orange: 'warning',
  red: 'danger', gray: 'default', grey: 'default', neutral: 'default',
}

/** The badge tone of a value: the column's colour map, meta.colors. */
export function badgeTone(value: unknown, meta: CellMeta = {}): BadgeTone {
  const colors = (meta.colors as Record<string, string> | undefined) ?? {}
  const color = value === null || value === undefined ? undefined : colors[String(value)]
  return (color && BADGE_TONES[color]) || 'default'
}

/** The badge caption of a value: meta.labels, or the value itself. */
export function badgeLabel(value: unknown, meta: CellMeta = {}): string {
  if (value === null || value === undefined) return ''
  const labels = (meta.labels as Record<string, string> | undefined) ?? {}
  return labels[String(value)] ?? String(value)
}

/**
 * The href of a link column (TableColumn::asLink): `{field}` stands for a
 * field of the row, `:value` for the cell's own value. Empty when a field is
 * missing or null — then the cell stays plain text.
 */
export function resolveLinkHref(
  template: string | null | undefined,
  row: Record<string, unknown>,
  value: unknown,
): string {
  if (!template) return ''
  const str = (v: unknown): string => (v === null || v === undefined ? '' : String(v))
  let unresolved = false
  const href = template
    .replace(/\{([\w.]+)\}/g, (_m, f: string) => {
      const v = row[f]
      if (v === null || v === undefined || v === '') unresolved = true
      return str(v)
    })
    .replace(/:value/g, str(value))
  return unresolved || href === '' ? '' : href
}

function safeJson(value: unknown): string {
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return String(value)
  }
}

/**
 * Reads a date the way the backend sends it. A bare 'Y-m-d' is a calendar
 * date, taken in local time — `new Date('2026-10-01')` would read it as UTC
 * midnight and show the day before west of Greenwich. 'Y-m-d H:i:s' gets its
 * 'T', which not every engine adds by itself; an ISO string with an offset
 * or a 'Z' is converted to local time.
 */
export function parseDateValue(input: string): Date | null {
  const s = input.trim()
  const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s)
  if (dateOnly) {
    const [y, m, day] = [Number(dateOnly[1]), Number(dateOnly[2]), Number(dateOnly[3])]
    const d = new Date(y, m - 1, day)
    // 2026-13-45 is no date, though Date would roll it over into the next year.
    return d.getFullYear() === y && d.getMonth() === m - 1 && d.getDate() === day ? d : null
  }
  const d = new Date(/^\d{4}-\d{2}-\d{2} \d/.test(s) ? s.replace(' ', 'T') : s)
  return isNaN(d.getTime()) ? null : d
}

/** The locale the names of months and days are given in: the panel's own. */
function dateLocale(): string {
  const lang = currentLocale()
  try {
    return Intl.DateTimeFormat.supportedLocalesOf([lang]).length > 0 ? lang : 'en'
  } catch {
    return 'en'
  }
}

function intlPart(date: Date, locale: string, options: Intl.DateTimeFormatOptions, type: Intl.DateTimeFormatPartTypes): string {
  try {
    return new Intl.DateTimeFormat(locale, options).formatToParts(date).find((p) => p.type === type)?.value ?? ''
  } catch {
    return ''
  }
}

function englishOrdinal(day: number): string {
  if (day % 100 >= 11 && day % 100 <= 13) return 'th'
  return ['th', 'st', 'nd', 'rd'][day % 10] ?? 'th'
}

function isoWeek(date: Date): { week: number; year: number } {
  const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
  const day = d.getUTCDay() || 7
  d.setUTCDate(d.getUTCDate() + 4 - day)
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1))
  return { week: Math.ceil(((d.getTime() - yearStart.getTime()) / 86_400_000 + 1) / 7), year: d.getUTCFullYear() }
}

function offset(date: Date, colon: boolean): string {
  const minutes = -date.getTimezoneOffset()
  const sign = minutes >= 0 ? '+' : '-'
  const abs = Math.abs(minutes)
  const hh = String(Math.floor(abs / 60)).padStart(2, '0')
  const mm = String(abs % 60).padStart(2, '0')
  return colon ? `${sign}${hh}:${mm}` : `${sign}${hh}${mm}`
}

/**
 * Formats a date by a PHP date() format string — every token of PHP's
 * date(), with the names of months and days in the panel's language:
 *
 *   day      d j D l N S w z      week  W
 *   month    F m M n t            year  L o Y y
 *   time     a A B g G h H i s u v
 *   zone     e I O P p T Z        full  c r U
 *
 * F before or after a day number takes the form a date reads with, which in
 * Russian is the genitive: «1 октября», not «1 октябрь». A backslash escapes
 * the next character, as in PHP.
 */
export function formatPhpDate(date: Date, format: string): string {
  const locale = dateLocale()
  const pad = (n: number, w = 2): string => String(n).padStart(w, '0')
  const day = date.getDate()
  const month = date.getMonth()
  const year = date.getFullYear()
  const hours = date.getHours()
  const leap = (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0
  const dayOfYear = Math.round(
    (Date.UTC(year, month, day) - Date.UTC(year, 0, 1)) / 86_400_000,
  )
  // F next to a day number: the month as it reads inside a date.
  const withDay = /(^|[^\\])[dj]/.test(format)

  const token = (ch: string): string | null => {
    switch (ch) {
      case 'd': return pad(day)
      case 'D': return intlPart(date, locale, { weekday: 'short' }, 'weekday')
      case 'j': return String(day)
      case 'l': return intlPart(date, locale, { weekday: 'long' }, 'weekday')
      case 'N': return String(((date.getDay() + 6) % 7) + 1)
      case 'S': return locale.startsWith('en') ? englishOrdinal(day) : ''
      case 'w': return String(date.getDay())
      case 'z': return String(dayOfYear)
      case 'W': return pad(isoWeek(date).week)
      case 'F':
        return withDay
          ? intlPart(date, locale, { day: 'numeric', month: 'long' }, 'month')
          : intlPart(date, locale, { month: 'long' }, 'month')
      case 'm': return pad(month + 1)
      case 'M': return intlPart(date, locale, { month: 'short' }, 'month')
      case 'n': return String(month + 1)
      case 't': return String(new Date(year, month + 1, 0).getDate())
      case 'L': return leap ? '1' : '0'
      case 'o': return String(isoWeek(date).year)
      case 'X':
      case 'x':
      case 'Y': return String(year)
      case 'y': return pad(year % 100)
      case 'a': return hours < 12 ? 'am' : 'pm'
      case 'A': return hours < 12 ? 'AM' : 'PM'
      case 'B': {
        const utc = (date.getUTCHours() * 3600 + date.getUTCMinutes() * 60 + date.getUTCSeconds() + 3600) % 86_400
        return pad(Math.floor(utc / 86.4), 3)
      }
      case 'g': return String(((hours + 11) % 12) + 1)
      case 'G': return String(hours)
      case 'h': return pad(((hours + 11) % 12) + 1)
      case 'H': return pad(hours)
      case 'i': return pad(date.getMinutes())
      case 's': return pad(date.getSeconds())
      case 'u': return pad(date.getMilliseconds() * 1000, 6)
      case 'v': return pad(date.getMilliseconds(), 3)
      case 'e': {
        try {
          return Intl.DateTimeFormat().resolvedOptions().timeZone ?? ''
        } catch {
          return ''
        }
      }
      case 'I': {
        const jan = new Date(year, 0, 1).getTimezoneOffset()
        const jul = new Date(year, 6, 1).getTimezoneOffset()
        return date.getTimezoneOffset() < Math.max(jan, jul) ? '1' : '0'
      }
      case 'O': return offset(date, false)
      case 'P': return offset(date, true)
      case 'p': return date.getTimezoneOffset() === 0 ? 'Z' : offset(date, true)
      case 'T': return intlPart(date, 'en', { timeZoneName: 'short' }, 'timeZoneName')
      case 'Z': return String(-date.getTimezoneOffset() * 60)
      case 'c': return formatPhpDate(date, 'Y-m-d\\TH:i:sP')
      case 'r': {
        const en = (o: Intl.DateTimeFormatOptions, t: Intl.DateTimeFormatPartTypes): string => intlPart(date, 'en', o, t)
        return `${en({ weekday: 'short' }, 'weekday')}, ${pad(day)} ${en({ month: 'short' }, 'month')} ${year} ${pad(hours)}:${pad(date.getMinutes())}:${pad(date.getSeconds())} ${offset(date, false)}`
      }
      case 'U': return String(Math.floor(date.getTime() / 1000))
      default: return null
    }
  }

  let out = ''
  for (let i = 0; i < format.length; i++) {
    const ch = format[i] ?? ''
    if (ch === '\\' && i + 1 < format.length) {
      out += format[i + 1]
      i++
      continue
    }
    out += token(ch) ?? ch
  }
  return out
}

/** A stored date value by a PHP format; a value that is no date is shown as it is. */
function formatDateString(input: string, format: string): string {
  const date = parseDateValue(input)
  return date === null ? input : formatPhpDate(date, format)
}

/**
 * Money in the panel's locale: its separators, and the currency's sign where
 * the currency is an ISO 4217 code. Anything else — a sign, a word — follows
 * the number as it is.
 */
function formatMoney(value: unknown, currency: string, decimals: number): string {
  const n = typeof value === 'number' ? value : Number(value)
  if (isNaN(n)) return String(value)
  const digits = { minimumFractionDigits: decimals, maximumFractionDigits: decimals }
  if (/^[A-Za-z]{3}$/.test(currency)) {
    try {
      return formatNumber(n, { style: 'currency', currency: currency.toUpperCase(), ...digits })
    } catch {
      // An unknown code: the plain form below.
    }
  }
  return `${formatNumber(n, digits)} ${currency}`
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
