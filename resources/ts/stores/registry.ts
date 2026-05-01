/**
 * Глобальный registry AdminClient'а для Pinia stores.
 *
 * Stores не получают client через параметры — это сильно усложняет API
 * (`useAuthStore(client)` каждый раз). Вместо этого client регистрируется
 * один раз при bootstrap'е приложения через `setAdminClient()`, и stores
 * читают его через `getAdminClient()`.
 *
 * Это singleton-pattern — допустим в случае admin-SPA, потому что
 * client всегда один на весь жизненный цикл.
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
