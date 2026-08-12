/**
 * Loads the bootstrap payload into the SPA.
 *
 * Both strategies work:
 *   - 'inline' reads window.__ADMIN_BOOTSTRAP__, injected by shell.blade when
 *     the strategy is inline.
 *   - 'xhr' fetches /api/admin/system/bootstrap.
 *
 * When neither yields anything it throws, and the SPA is expected to catch
 * that and show a full-screen error.
 */

import type { AdminBootstrap } from '../types/bootstrap'
import type { AdminClient } from './client'
import { NetworkError } from './errors'

export interface LoadBootstrapOptions {
  /** When a client is given, the xhr fallback goes through it. */
  client?: AdminClient
  /** Overrides the xhr's URL; '/system/bootstrap' by default. */
  xhrUrl?: string
}

/**
 * Returns the bootstrap, or null when it could not be loaded.
 *
 * In order:
 *   1. window.__ADMIN_BOOTSTRAP__, the inline strategy.
 *   2. an xhr fetch, when a client was given.
 *   3. null — and the caller decides what to do, such as showing an error
 *      screen.
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
        // A network failure: the caller should show an offline screen.
        return null
      }
      throw err
    }
  }

  return null
}

/**
 * Reads the bootstrap off window; in a browser context alone.
 */
export function readInlineBootstrap(): AdminBootstrap | null {
  if (typeof window === 'undefined') return null
  return window.__ADMIN_BOOTSTRAP__ ?? null
}

/**
 * Reads the CSRF token from the meta tag Blade injects with `csrf_token()`.
 *
 * It is the fallback for when bootstrap.csrf is missing.
 */
export function readCsrfFromMeta(): string | null {
  if (typeof document === 'undefined') return null
  const meta = document.querySelector('meta[name="csrf-token"]')
  return meta?.getAttribute('content') ?? null
}
