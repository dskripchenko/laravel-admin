/**
 * i18n store — простой message-bag + t() helper.
 *
 * Backend кладёт translations bag в `bootstrap.translations` (Record<key, string>).
 * При смене локали (`/system/setLocale`) bootstrap переподнимается либо
 * helper зовёт `loadLocale(locale)`, который POST'ит messages.
 *
 * Формат:
 *   t('admin.dashboard.add_widget')        // 'Add widget'
 *   t('admin.records.count', { n: 42 })    // 'Записей: 42' (interpolation :n)
 *
 * Fallback: если ключ не найден — возвращает сам ключ (помогает быстро
 * увидеть отсутствующие переводы во время разработки).
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { AdminBootstrap } from '../types/bootstrap'

export const useI18nStore = defineStore('admin-i18n', () => {
  const messages = ref<Record<string, string>>({})
  const locale = ref<string>('ru')

  function hydrate(bootstrap: AdminBootstrap): void {
    // Backend кладёт translations в bootstrap (опционально).
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
   * Translate. Поддерживает interpolation вида `:name` (Laravel-стиль).
   * Если ключ отсутствует — возвращается сам ключ (visible-fallback).
   */
  function t(key: string, replace: Record<string, string | number> = {}): string {
    let str = messages.value[key] ?? key
    for (const [k, v] of Object.entries(replace)) {
      str = str.replace(new RegExp(`:${k}`, 'g'), String(v))
    }
    return str
  }

  const has = (key: string): boolean => key in messages.value

  return {
    messages,
    locale,
    hydrate,
    setMessages,
    t,
    has,
    keys: computed(() => Object.keys(messages.value)),
  }
})

/**
 * Convenience: глобальный t() для не-Vue контекстов (utils, services).
 * В Vue-компонентах предпочтительнее `const { t } = useI18nStore()`.
 */
export function tRaw(key: string, replace?: Record<string, string | number>): string {
  return useI18nStore().t(key, replace ?? {})
}
