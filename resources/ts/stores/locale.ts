/**
 * Locale store: текущая локаль + persist + sync с AdminClient.
 *
 * Side effects при смене:
 *   - `<html lang="...">` обновляется
 *   - AdminClient.setLocale() — все следующие запросы получат правильный
 *     X-Admin-Locale header
 *   - POST /system/setLocale → persist в user.locale + cookie
 */

import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getAdminClient } from './registry'
import type { AdminBootstrap } from '../types/bootstrap'

export const useLocaleStore = defineStore('admin-locale', () => {
  const current = ref<string>('ru')
  const available = ref<string[]>(['ru', 'en'])

  function hydrate(bootstrap: AdminBootstrap): void {
    current.value = bootstrap.locale
    available.value = bootstrap.availableLocales
    applySideEffects(bootstrap.locale)
  }

  function applyLocal(locale: string): void {
    if (!available.value.includes(locale)) {
      throw new Error(`Locale "${locale}" is not available`)
    }
    current.value = locale
    applySideEffects(locale)
  }

  async function setLocale(locale: string): Promise<void> {
    if (!available.value.includes(locale)) {
      throw new Error(`Locale "${locale}" is not available`)
    }

    const previous = current.value
    applyLocal(locale)

    try {
      const client = getAdminClient()
      await client.post('/system/setLocale', { locale })
    } catch (err) {
      applyLocal(previous)
      throw err
    }
  }

  function applySideEffects(locale: string): void {
    if (typeof document !== 'undefined' && document.documentElement) {
      document.documentElement.setAttribute('lang', locale)
    }
    // Try-catch потому что в test-окружении registry может быть пуст.
    try {
      getAdminClient().setLocale(locale)
    } catch {
      // ignore — клиент ещё не зарегистрирован
    }
  }

  return {
    current,
    available,
    hydrate,
    applyLocal,
    setLocale,
  }
})
