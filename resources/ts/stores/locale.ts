/**
 * The locale store: the current locale, its persistence and its synchronization
 * with AdminClient.
 *
 * Changing it has three effects:
 *   - `<html lang="...">` is updated
 *   - AdminClient.setLocale() is called, so every later request carries the
 *     X-Admin-Locale the panel is rendering — from the bootstrap on, after a
 *     switch and after a logout — and the server's messages come back in the
 *     language of the screen
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
   * Called on logout. The header is NOT dropped: it carries the locale the
   * panel is rendering, and the login form that follows is rendered in it
   * too — without the header the server would fall back to Accept-Language
   * and answer in another language than the screen. The next person's saved
   * preference still wins: the login answer carries it and the auth store
   * applies it (adoptUserLocale), header included.
   */
  function release(): void {
    applySideEffects(current.value)
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
