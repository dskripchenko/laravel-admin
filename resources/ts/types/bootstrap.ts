/**
 * The bootstrap payload — what the backend assembles in
 * Support\BootstrapBuilder and delivers by one of two strategies:
 *
 *   - inline: window.__ADMIN_BOOTSTRAP__ is injected into shell.blade
 *   - xhr:    the SPA calls GET /api/admin/system/bootstrap
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
  /** The logo image's URL, for the sidebar and the auth pages. */
  logo?: string | null
  /** A short textual mark of one or two characters, the fallback without an image. */
  mark?: string | null
  favicon?: string | null
  /** The copyright line in the footer, "© 2026 Printable" for instance. */
  copyright?: string | null
  /** Extra text or a link in the sidebar's footer: a version, the docs. */
  footer?: string | null
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
  /** The panel's id; older backends do not send it in their bootstrap */
  panel?: string
  plugins: string[]
  unread_notifications_count: number
  config: AdminBootstrapConfig
}

declare global {
  interface Window {
    __ADMIN_BOOTSTRAP__?: AdminBootstrap
  }
}
