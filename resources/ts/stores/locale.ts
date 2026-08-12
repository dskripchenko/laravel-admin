/**
 * The locale store: the current locale, its persistence and its synchronization
 * with AdminClient.
 *
 * Changing it has three effects:
 *   - `<html lang="...">` is updated
 *   - AdminClient.setLocale() is called, so every later request carries the
 *     right X-Admin-Locale header
 *   - POST /system/setLocale persists it into user.locale and a cookie
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

  /**
   * Lets the locale go: the pinned header is removed, so that the server
   * resolves it through the whole chain again.
   *
   * The header sits ABOVE the user's saved preference in that chain — while
   * the tab keeps sending it, it overrides the account's setting. After a
   * logout it belongs to someone else: the next person to log in from this tab
   * would get their predecessor's language, unless they have a saved
   * preference of their own.
   */
  function release(): void {
    try {
      getAdminClient().clearLocale()
    } catch {
      // The client is not registered yet, so there is nothing to let go of.
    }
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
    // A try/catch, because in a test environment the registry may be empty.
    try {
      getAdminClient().setLocale(locale)
    } catch {
      // ignored: the client is not registered yet
    }
  }

  return {
    current,
    available,
    hydrate,
    applyLocal,
    setLocale,
    release,
  }
})
