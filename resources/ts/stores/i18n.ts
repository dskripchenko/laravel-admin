/**
 * The i18n store — a plain message bag plus the t() helper.
 *
 * The backend puts the bag into `bootstrap.translations`, a
 * Record<key, string>. When the locale changes (`/system/setLocale`) either
 * the bootstrap is raised again or the helper calls `loadLocale(locale)`,
 * which POSTs for the messages.
 *
 * The form:
 *   t('admin.dashboard.add_widget')        // 'Add widget'
 *   t('admin.records.count', { n: 42 })    // 'Records: 42', interpolating :n
 *
 * The fallback: a key that is not found is returned as it is, which makes
 * missing translations easy to spot during development.
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { AdminBootstrap } from '../types/bootstrap'

/**
 * A standalone wrapper around store.tr() for the components: it does not fall
 * over without an active Pinia (unit tests, SSR edges) and returns the source
 * string instead.
 */
export function trSafe(text: string): string {
  try {
    return useI18nStore().tr(text)
  } catch {
    return text
  }
}

/**
 * The panel's current language, for formatting — month names, numbers: the
 * document's lang, else the bootstrap locale, else Russian, the source
 * language.
 */
export function currentLocale(): string {
  // The locale store keeps <html lang> in step with a switch made in place.
  if (typeof document !== 'undefined' && document.documentElement.lang) {
    return document.documentElement.lang
  }
  try {
    const locale = useI18nStore().locale
    if (locale) return locale
  } catch {
    // no active Pinia
  }
  return 'ru'
}

/**
 * A panel locale as a tag Intl accepts: Laravel's `pt_BR` becomes `pt-BR`,
 * and a tag Intl does not know falls back to English.
 */
export function intlLocale(locale: string): string {
  const tag = locale.replace(/_/g, '-')
  try {
    return Intl.NumberFormat.supportedLocalesOf([tag]).length > 0 ? tag : 'en'
  } catch {
    return 'en'
  }
}

/** The locale given to Intl: the panel's own; see intlLocale(). */
export function formatLocale(): string {
  return intlLocale(currentLocale())
}

const numberFormats = new Map<string, Intl.NumberFormat>()

/**
 * A number formatter in the panel's locale — decimal and group separators,
 * currencies — cached per locale and options. Never the browser's locale:
 * an English panel in a Russian browser shows English numbers.
 */
export function numberFormat(options: Intl.NumberFormatOptions = {}): Intl.NumberFormat {
  const locale = formatLocale()
  const key = `${locale}|${JSON.stringify(options)}`
  let format = numberFormats.get(key)
  if (!format) {
    format = new Intl.NumberFormat(locale, options)
    numberFormats.set(key, format)
  }
  return format
}

/** Formats a number in the panel's locale; see numberFormat(). */
export function formatNumber(value: number, options: Intl.NumberFormatOptions = {}): string {
  return numberFormat(options).format(value)
}

/** Formats a date in the panel's locale. */
export function formatDate(value: Date | string, options: Intl.DateTimeFormatOptions = {}): string {
  const date = typeof value === 'string' ? new Date(value) : value
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleString(formatLocale(), options)
}

export const useI18nStore = defineStore('admin-i18n', () => {
  const messages = ref<Record<string, string>>({})
  const locale = ref<string>('ru')

  function hydrate(bootstrap: AdminBootstrap): void {
    // The backend may put the translations into the bootstrap.
    const t = (bootstrap as unknown as { translations?: Record<string, string> }).translations
    if (t && typeof t === 'object') {
      messages.value = { ...t }
    }
    locale.value = bootstrap.locale ?? 'ru'
  }

  function setMessages(next: Record<string, string>): void {
    messages.value = { ...next }
  }

  /**
   * Translates, supporting Laravel-style `:name` interpolation. A missing key
   * is returned as it is, which makes the gap visible.
   */
  function t(key: string, replace: Record<string, string | number> = {}): string {
    let str = messages.value[key] ?? key
    for (const [k, v] of Object.entries(replace)) {
      str = str.replace(new RegExp(`:${k}`, 'g'), String(v))
    }
    return str
  }

  const has = (key: string): boolean => key in messages.value

  /**
   * Translates by a string key, JSON-style, where the key is the source string
   * in the development language. The backend mixes the current locale's JSON
   * translations — the host's lang/{locale}.json — into the bag. Without a
   * translation the string comes back untouched, with no interpolation.
   */
  function tr(text: string): string {
    return messages.value[text] ?? text
  }

  return {
    messages,
    locale,
    hydrate,
    setMessages,
    t,
    tr,
    has,
    keys: computed(() => Object.keys(messages.value)),
  }
})

/**
 * A convenience: the global t() for non-Vue contexts — utilities, services. In
 * a Vue component prefer `const { t } = useI18nStore()`.
 */
export function tRaw(key: string, replace?: Record<string, string | number>): string {
  try {
    return useI18nStore().t(key, replace ?? {})
  } catch {
    // Without an active Pinia — unit tests, a render before the
    // initialization — we return the source string with the values
    // substituted. This used to throw and take the whole component down: an
    // untranslated string beats an empty page.
    let str = key
    for (const [k, v] of Object.entries(replace ?? {})) {
      str = str.replace(new RegExp(`:${k}`, 'g'), String(v))
    }

    return str
  }
}
