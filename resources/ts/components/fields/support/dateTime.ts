/**
 * The value of a DatePicker with `withTime()`: a date and a time held in one
 * string the way the backend stores it — 'Y-m-d H:i:s' by default.
 */

export interface DateTimeParts {
  date: string | null
  time: string | null
}

/**
 * Splits a stored value into its date ('YYYY-MM-DD') and time ('HH:mm' or
 * 'HH:mm:ss'). An ISO string a model cast produced ('2026-05-01T10:30:00.000000Z')
 * is read as written, with no time-zone shift.
 */
export function splitDateTime(value: unknown): DateTimeParts {
  if (value === null || value === undefined || value === '') return { date: null, time: null }
  const m = /^(\d{4}-\d{2}-\d{2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?/.exec(String(value))
  if (!m) return { date: null, time: null }
  const time = m[2] !== undefined
    ? `${m[2].padStart(2, '0')}:${m[3]}${m[4] !== undefined ? `:${m[4]}` : ''}`
    : null
  return { date: m[1] ?? null, time }
}

/** Whether a PHP date format carries seconds ('H:i:s'). */
export function formatHasSeconds(format: string | null | undefined): boolean {
  return /[sS]/.test(format ?? 'Y-m-d H:i:s')
}

/** The separator between the date and the time a PHP format uses: 'T' or a space. */
export function formatSeparator(format: string | null | undefined): string {
  return /\\?T/.test(format ?? '') ? 'T' : ' '
}

/**
 * Joins a date and a time into the stored value; a missing time is midnight,
 * a missing date is no value at all.
 */
export function joinDateTime(
  date: string | null,
  time: string | null,
  format: string | null | undefined,
): string | null {
  if (!date) return null
  const seconds = formatHasSeconds(format)
  const [h = '00', i = '00', s = '00'] = (time ?? '').split(':')
  const hm = `${h.padStart(2, '0')}:${i.padStart(2, '0')}`
  return `${date}${formatSeparator(format)}${seconds ? `${hm}:${s.padStart(2, '0')}` : hm}`
}
