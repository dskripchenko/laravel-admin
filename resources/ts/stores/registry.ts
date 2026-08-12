/**
 * The global registry of the AdminClient, for the Pinia stores.
 *
 * The stores do not take the client as a parameter — that would make the API
 * much clumsier, with `useAuthStore(client)` every single time. Instead the
 * client is registered once, while the application bootstraps, through
 * `setAdminClient()`, and the stores read it with `getAdminClient()`.
 *
 * It is a singleton, which is acceptable for an admin SPA: there is only ever
 * one client for the whole lifetime.
 */

import type { AdminClient } from '../api/client'

let _client: AdminClient | null = null

export function setAdminClient(client: AdminClient): void {
  _client = client
}

export function getAdminClient(): AdminClient {
  if (_client === null) {
    throw new Error(
      '[admin] AdminClient is not registered. Call setAdminClient(...) before using stores.',
    )
  }
  return _client
}

export function clearAdminClient(): void {
  _client = null
}

export function hasAdminClient(): boolean {
  return _client !== null
}
