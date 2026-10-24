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
  /**
   * Whether this user must have 2FA on (admin.auth.two_factor.enforce_for);
   * until they enable it the panel keeps them on the profile.
   */
  twoFactorRequired?: boolean
  /** The original user while this session is an impersonation. */
  impersonator?: { id: number | string; name: string } | null
  /** The role the panel is viewed as (admin.auth.role_preview), or null. */
  rolePreview?: { slug: string; name: string } | null
  /** Whether the user may view the panel as a role — by their own roles. */
  canPreviewRoles?: boolean
}

/** One "Sign in as …" button of demo mode (admin.demo.accounts). */
export interface AdminDemoAccount {
  label: string
  email: string
  password: string
  description?: string | null
}

/** Demo mode (admin.demo); the bootstrap carries null when it is off. */
export interface AdminDemo {
  readonly: boolean
  accounts: AdminDemoAccount[]
}

/**
 * A way to sign in other than the password form — "Sign in with SSO" — that a
 * host or a package registered for the panel (Admin::externalLogins).
 */
export interface AdminExternalLogin {
  label: string
  /** Where the button leads — a full page navigation, not a SPA route. */
  url: string
  /** An icon name from the panel's icon set. */
  icon?: string | null
  description?: string | null
}

/** A message above the login form — Auth\LoginNotice on the server. */
export interface AdminLoginNotice {
  message: string
  tone: 'danger' | 'warning' | 'info' | 'success'
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
  /** admin.auth.two_factor.enabled; older backends do not send it. */
  twoFactor?: { enabled: boolean }
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
  /** Demo mode; null or absent when it is off. */
  demo?: AdminDemo | null
  /** The login page's external ways in; absent on older backends. */
  externalLogins?: AdminExternalLogin[]
  /** A message for the login page (why an external sign-in failed), shown once. */
  loginNotice?: AdminLoginNotice | null
}

declare global {
  interface Window {
    __ADMIN_BOOTSTRAP__?: AdminBootstrap
  }
}
