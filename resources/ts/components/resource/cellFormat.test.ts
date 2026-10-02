import { afterEach, describe, expect, it } from 'vitest'
import { formatCell, formatPhpDate, parseDateValue, resolveLinkHref } from './cellFormat'

/**
 * Dates by PHP format strings. M, j, D, F, l and n used to come out as the
 * letters themselves; every date() token is supported now, with the names of
 * months and days in the panel's language.
 */
const setLang = (lang: string): void => {
  document.documentElement.lang = lang
}

describe('formatPhpDate', () => {
  afterEach(() => setLang(''))

  // Thursday, 1 October 2026, 09:05:07 local time.
  const date = new Date(2026, 9, 1, 9, 5, 7)

  it('formats the numeric tokens', () => {
    setLang('en')
    expect(formatPhpDate(date, 'd.m.Y H:i:s')).toBe('01.10.2026 09:05:07')
    expect(formatPhpDate(date, 'j/n/y G:i')).toBe('1/10/26 9:05')
    expect(formatPhpDate(date, 'N w z t L')).toBe('4 4 273 31 0')
    expect(formatPhpDate(date, 'g:i a / h A')).toBe('9:05 am / 09 AM')
    expect(formatPhpDate(date, 'W o')).toBe('40 2026')
  })

  it('names months and days in English', () => {
    setLang('en')
    expect(formatPhpDate(date, 'D, j M Y')).toBe('Thu, 1 Oct 2026')
    expect(formatPhpDate(date, 'l, F jS')).toBe('Thursday, October 1st')
  })

  it('names them in Russian, the month in the genitive inside a date', () => {
    setLang('ru')
    expect(formatPhpDate(date, 'j F Y')).toBe('1 октября 2026')
    expect(formatPhpDate(date, 'F Y')).toBe('октябрь 2026')
    expect(formatPhpDate(date, 'l')).toBe('четверг')
    expect(formatPhpDate(date, 'jS')).toBe('1')
  })

  it('honours the backslash escape', () => {
    setLang('en')
    expect(formatPhpDate(date, '\\Y\\-Y')).toBe('Y-2026')
  })
})

describe('formatCell dates', () => {
  afterEach(() => setLang(''))

  it('reads a bare date as a calendar date, not UTC midnight', () => {
    expect(parseDateValue('2026-10-01')?.getDate()).toBe(1)
    expect(formatCell('2026-10-01', 'date', { format: 'd.m.Y' })).toBe('01.10.2026')
  })

  it('reads a Y-m-d H:i:s value', () => {
    expect(formatCell('2026-10-01 14:30:00', 'datetime', { format: 'd.m.Y H:i' })).toBe('01.10.2026 14:30')
  })

  it('shows a value that is no date as it is', () => {
    expect(formatCell('soon', 'date', {})).toBe('soon')
    expect(parseDateValue('2026-13-45')).toBeNull()
  })

  it('formats a date column by M j, Y', () => {
    setLang('en')
    expect(formatCell('2026-02-03', 'date', { format: 'M j, Y' })).toBe('Feb 3, 2026')
  })
})

describe('resolveLinkHref', () => {
  it('fills fields of the row and the value', () => {
    expect(resolveLinkHref('/r/{id}?q=:value', { id: 7 }, 'x')).toBe('/r/7?q=x')
  })

  it('gives nothing when a field is missing or empty', () => {
    expect(resolveLinkHref('{url}', { url: null }, 'x')).toBe('')
    expect(resolveLinkHref('{url}', {}, 'x')).toBe('')
    expect(resolveLinkHref('', { id: 1 }, 'x')).toBe('')
  })
})

describe('formatCell money follows the panel locale', () => {
  afterEach(() => {
    document.documentElement.lang = ''
  })

  it('formats an ISO currency with the locale separators and sign', () => {
    document.documentElement.lang = 'en'
    expect(formatCell(12579.83, 'money', { currency: 'USD', decimals: 2 })).toBe('$12,579.83')
    document.documentElement.lang = 'ru'
    expect(formatCell(12579.83, 'money', { currency: 'USD', decimals: 2 }).replace(/\s/g, ' ')).toBe('12 579,83 $')
  })

  it('keeps a currency that is no ISO code after the number', () => {
    document.documentElement.lang = 'en'
    expect(formatCell(1500, 'money', { currency: 'points', decimals: 0 })).toBe('1,500 points')
  })
})
