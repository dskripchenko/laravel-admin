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
