/**
 * Public API of admin stores.
 *
 * Использование:
 *
 *     import { setAdminClient, useAuthStore, useThemeStore } from '@dskripchenko/laravel-admin'
 *
 *     const client = createAdminClient({...})
 *     setAdminClient(client)                  // ОБЯЗАТЕЛЬНО до использования stores
 *     const auth = useAuthStore()
 *     const theme = useThemeStore()
 */

export { setAdminClient, getAdminClient, hasAdminClient, clearAdminClient } from './registry'

export { useAuthStore } from './auth'
export type { LoginPayload, PendingChallenge } from './auth'

export { useManifestStore } from './manifest'
export type {
  AdminManifest,
  ManifestResourceMeta,
  ManifestScreenMeta,
  ManifestSettingsMeta,
} from './manifest'

export { useThemeStore } from './theme'
export { useLocaleStore } from './locale'

export { useNotificationsStore } from './notifications'
export type { NotificationItem, NotificationFilter } from './notifications'
