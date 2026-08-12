/**
 * The theme store: the current theme, the available ones, and persistence
 * through the API.
 *
 * As a side effect it sets `<html data-theme="...">` on every change, which
 * the SPA relies on for its CSS-variable overrides.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'
import type { AdminBootstrap } from '../types/bootstrap'

export const useThemeStore = defineStore('admin-theme', () => {
  const current = ref<string>('light')
  const available = ref<string[]>(['light', 'dark'])

  const isDark = computed(() => current.value === 'dark')

  function hydrate(bootstrap: AdminBootstrap): void {
    current.value = bootstrap.theme
    available.value = bootstrap.availableThemes
    applyHtmlAttr(bootstrap.theme)
  }

  /**
   * Switches locally, with no round trip — for applying a theme at once,
   * before the server has answered.
   */
  function applyLocal(theme: string): void {
    if (!available.value.includes(theme)) {
      throw new Error(`Theme "${theme}" is not available`)
    }
    current.value = theme
    applyHtmlAttr(theme)
  }

  /**
   * POST /system/setTheme, persisting into user.theme and a cookie. The theme
   * is applied to the DOM immediately, optimistically.
   */
  async function setTheme(theme: string): Promise<void> {
    if (!available.value.includes(theme)) {
      throw new Error(`Theme "${theme}" is not available`)
    }

    const previous = current.value
    applyLocal(theme)

    try {
      const client = getAdminClient()
      await client.post('/system/setTheme', { theme })
    } catch (err) {
      // Roll the optimistic update back.
      applyLocal(previous)
      throw err
    }
  }

  function applyHtmlAttr(theme: string): void {
    if (typeof document !== 'undefined' && document.documentElement) {
      document.documentElement.setAttribute('data-theme', theme)
    }
  }

  return {
    current,
    available,
    isDark,
    hydrate,
    applyLocal,
    setTheme,
  }
})
