/**
 * Theme store: текущая тема + список доступных + persist через API.
 *
 * Side effect: применяет `<html data-theme="...">` при изменении.
 * SPA рассчитывает на это для CSS-vars overrides.
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
   * Локальное переключение без round-trip'а. Используется когда нужно
   * мгновенно применить тему до получения ответа от сервера.
   */
  function applyLocal(theme: string): void {
    if (!available.value.includes(theme)) {
      throw new Error(`Theme "${theme}" is not available`)
    }
    current.value = theme
    applyHtmlAttr(theme)
  }

  /**
   * POST /system/setTheme — persist в user.theme + cookie.
   * Применяет тему к DOM немедленно (optimistic update).
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
      // Откатываем optimistic update.
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
