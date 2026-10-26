/**
 * The UI kit's locale, following the panel's: the kit's string bag for the
 * panel language (English for `en`, Russian otherwise) and the panel's own
 * locale as `code`, by which the kit formats numbers and dates.
 *
 * AdminApp provides it to the whole shell. Widgets mounted outside the
 * shell's component tree — the players of ```video / ```audio blocks — call
 * provideKitLocale() themselves: an app context carries the app-level
 * provides only.
 */
import { computed } from 'vue'
import { provideLocale, ru as uidRu, en as uidEn, type UidLocale } from '@dskripchenko/ui'
import { useLocaleStore } from '../stores/locale'
import { intlLocale } from '../stores/i18n'

export function kitLocaleFor(locale: string): UidLocale {
  return { ...(locale === 'en' ? uidEn : uidRu), code: intlLocale(locale) }
}

/** Provides the kit's locale to the calling component's subtree. */
export function provideKitLocale(): void {
  let current: () => string
  try {
    const store = useLocaleStore()
    current = () => store.current
  } catch {
    // No Pinia: a widget mounted on its own; the page's language stands in.
    current = () => (typeof document !== 'undefined' && document.documentElement.lang) || 'ru'
  }
  provideLocale(computed(() => kitLocaleFor(current())))
}
