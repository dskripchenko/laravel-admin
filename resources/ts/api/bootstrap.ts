/**
 * Загрузка bootstrap-payload'а в SPA.
 *
 * Поддерживает обе стратегии:
 *   - 'inline' — читает window.__ADMIN_BOOTSTRAP__ (инжект через
 *     shell.blade при strategy=inline).
 *   - 'xhr' — fetch'ит /api/admin/system/bootstrap.
 *
 * Если оба способа не дали результата — throw'ит. SPA должна это поймать
 * и показать full-screen error.
 */

import type { AdminBootstrap } from '../types/bootstrap'
import type { AdminClient } from './client'
import { NetworkError } from './errors'

export interface LoadBootstrapOptions {
  /** Если задан client — fallback на xhr через него. */
  client?: AdminClient
  /** Override URL для xhr. Default: '/system/bootstrap'. */
  xhrUrl?: string
}

/**
 * Возвращает bootstrap или null если не загрузился.
 *
 * Порядок:
 *   1. window.__ADMIN_BOOTSTRAP__ (inline-strategy).
 *   2. xhr fetch если передан client.
 *   3. null — caller решает что делать (показать error-screen).
 */
export async function loadBootstrap(
  opts: LoadBootstrapOptions = {},
): Promise<AdminBootstrap | null> {
  const inline = readInlineBootstrap()
  if (inline) {
    return inline
  }

  if (opts.client) {
    try {
      const url = opts.xhrUrl ?? '/system/bootstrap'
      return await opts.client.get<AdminBootstrap>(url)
    } catch (err) {
      if (err instanceof NetworkError) {
        // Network failure — caller должен показать offline-screen.
        return null
      }
      throw err
    }
  }

  return null
}

/**
 * Прочитать bootstrap из window — только в браузерном контексте.
 */
export function readInlineBootstrap(): AdminBootstrap | null {
  if (typeof window === 'undefined') return null
  return window.__ADMIN_BOOTSTRAP__ ?? null
}

/**
 * Прочитать CSRF-token из meta-tag (Blade-injection через `csrf_token()`).
 *
 * Используется как fallback если bootstrap.csrf отсутствует.
 */
export function readCsrfFromMeta(): string | null {
  if (typeof document === 'undefined') return null
  const meta = document.querySelector('meta[name="csrf-token"]')
  return meta?.getAttribute('content') ?? null
}
