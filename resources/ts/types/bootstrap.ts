/**
 * Bootstrap-payload — структура которую backend строит через
 * Support\BootstrapBuilder и доставляет одной из двух стратегий:
 *
 *   - inline: window.__ADMIN_BOOTSTRAP__ инжектится в shell.blade
 *   - xhr:    SPA дёргает GET /api/admin/system/bootstrap
 */

export interface AdminUser {
  id: number | string
  name: string
  email: string
  avatar: string | null
  locale: string | null
  theme: string | null
  twoFactorEnabled: boolean
}

export interface AdminBrand {
  name?: string
  logo?: string | null
  favicon?: string | null
}

export interface AdminBootstrapConfig {
  manifest: { etag: boolean }
  bootstrap: { strategy: 'inline' | 'xhr' }
}

export interface AdminBootstrap {
  csrf: string
  baseUrl: string
  apiUrl: string
  locale: string
  availableLocales: string[]
  theme: string
  availableThemes: string[]
  brand: AdminBrand
  user: AdminUser | null
  permissions: string[]
  manifestVersion: string | null
  plugins: string[]
  unread_notifications_count: number
  config: AdminBootstrapConfig
}

declare global {
  interface Window {
    __ADMIN_BOOTSTRAP__?: AdminBootstrap
  }
}
