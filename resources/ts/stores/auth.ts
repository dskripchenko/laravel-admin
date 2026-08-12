/**
 * The auth store: the current user, the permissions, the login/logout/2FA flow.
 *
 * Wildcards in permissions:
 *   - `*` — full access to everything.
 *   - `admin.users.*` — access to every admin.users.{view,create,update,delete}.
 *
 * The 2FA flow:
 *   1. login() with valid credentials and 2FA enabled returns success: false
 *      plus errorKey: 'two_factor_required' and a challenge_token. The state
 *      moves to `pendingChallenge`.
 *   2. The UI shows the 2FA form. twoFactorChallenge(code) or
 *      twoFactorRecovery(recovery_code) completes the login.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'
import { useLocaleStore } from './locale'
import { ApiError } from '../api/errors'
import type { AdminUser, AdminBootstrap } from '../types/bootstrap'

export interface PendingChallenge {
  challengeToken: string
  remember: boolean
}

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export const useAuthStore = defineStore('admin-auth', () => {
  const user = ref<AdminUser | null>(null)
  const permissions = ref<string[]>([])
  const pendingChallenge = ref<PendingChallenge | null>(null)

  const isAuthenticated = computed(() => user.value !== null)
  const isChallengePending = computed(() => pendingChallenge.value !== null)

  /** Fills the store from the bootstrap payload — the initial setup. */
  function hydrate(bootstrap: AdminBootstrap): void {
    user.value = bootstrap.user
    permissions.value = bootstrap.permissions
  }

  /**
   * The locale of whoever just logged in, taken from the server's own answer.
   *
   * The login is an XHR with no page reload, while the locale store was
   * hydrated once, on the guest bootstrap — that is, from the browser's
   * Accept-Language. The user's saved preference was only picked up on the
   * next full load, so the panel spoke the browser's language right after the
   * login and the account's language after F5.
   *
   * We set it BEFORE assigning the user: the manifest loader is subscribed to
   * the user appearing, and the X-Admin-Locale header must already be right —
   * otherwise the manifest arrives in the wrong language.
   */
  function adoptUserLocale(next: AdminUser | null): void {
    const locale = next?.locale
    if (typeof locale !== 'string' || locale === '') return

    try {
      const localeStore = useLocaleStore()
      if (localeStore.current === locale) return
      localeStore.applyLocal(locale)
    } catch {
      // The user's locale is outside the available list, or the store is not
      // up yet — neither is a reason to break the login.
    }
  }

  /**
   * Checks a permission. Wildcards are supported:
   *   - `*` — grants everything.
   *   - `admin.users.*` — grants every admin.users.X.
   */
  function hasPermission(key: string): boolean {
    if (permissions.value.includes('*')) return true
    if (permissions.value.includes(key)) return true
    // The glob masks mirror the backend's Role::hasPermission (fnmatch): '*'
    // matches any characters, dots included, so both trailing
    // ('admin.users.*') and middle ('printable.*.view') patterns work.
    return permissions.value.some((permission) => {
      if (!permission.includes('*')) return false
      const pattern = permission
        .split('*')
        .map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
        .join('.*')
      return new RegExp(`^${pattern}$`).test(key)
    })
  }

  function hasAnyPermission(keys: string[]): boolean {
    return keys.some(hasPermission)
  }

  function hasAllPermissions(keys: string[]): boolean {
    return keys.every(hasPermission)
  }

  /**
   * POST /auth/login. On a 2FA-required envelope the interceptor turns
   * success:false + errorKey:'two_factor_required' into an ApiError; we catch
   * it, switch to challenge mode and return 'two_factor_required'. On a full
   * success we fill in the user and return 'authenticated'. A real error is
   * rethrown as an ApiError.
   */
  async function login(payload: LoginPayload): Promise<'authenticated' | 'two_factor_required'> {
    const client = getAdminClient()
    try {
      const result = await client.post<{ user?: AdminUser; permissions?: string[] }>(
        '/auth/login',
        payload,
      )
      if (result?.user) {
        adoptUserLocale(result.user)
        user.value = result.user
      }
      if (Array.isArray(result?.permissions)) {
        permissions.value = result.permissions
      }
      pendingChallenge.value = null
      return 'authenticated'
    } catch (err) {
      if (err instanceof ApiError && err.errorKey === 'two_factor_required') {
        const challengeToken = (err.payload as Record<string, unknown>).challenge_token
        pendingChallenge.value = {
          challengeToken: typeof challengeToken === 'string' ? challengeToken : '',
          remember: payload.remember ?? false,
        }
        return 'two_factor_required'
      }
      throw err
    }
  }

  /** POST /auth/twoFactorChallenge. Completes the login. */
  async function twoFactorChallenge(code: string): Promise<void> {
    if (pendingChallenge.value === null) {
      throw new Error('No pending 2FA challenge')
    }
    const client = getAdminClient()
    const result = await client.post<{ user: AdminUser }>('/auth/twoFactorChallenge', {
      challenge_token: pendingChallenge.value.challengeToken,
      code,
    })
    adoptUserLocale(result.user)
    user.value = result.user
    pendingChallenge.value = null
  }

  /** POST /auth/twoFactorRecovery. Uses a recovery code instead of a TOTP. */
  async function twoFactorRecovery(recoveryCode: string): Promise<{ remaining: number }> {
    if (pendingChallenge.value === null) {
      throw new Error('No pending 2FA challenge')
    }
    const client = getAdminClient()
    const result = await client.post<{
      user: AdminUser
      recovery_codes_remaining: number
    }>('/auth/twoFactorRecovery', {
      challenge_token: pendingChallenge.value.challengeToken,
      recovery_code: recoveryCode,
    })
    adoptUserLocale(result.user)
    user.value = result.user
    pendingChallenge.value = null
    return { remaining: result.recovery_codes_remaining }
  }

  /** Drops the pending challenge, to get back to the login form. */
  function cancelChallenge(): void {
    pendingChallenge.value = null
  }

  /** POST /auth/logout. Clears the store. */
  async function logout(): Promise<void> {
    const client = getAdminClient()
    try {
      await client.post('/auth/logout')
    } finally {
      user.value = null
      permissions.value = []
      pendingChallenge.value = null
      // The locale of whoever just left must not reach the next person: the
      // header sits above the account's saved preference in the chain.
      try {
        useLocaleStore().release()
      } catch {
        // The store is unavailable (tests without Pinia) — logging out matters more than tidying up.
      }
    }
  }

  return {
    // state
    user,
    permissions,
    pendingChallenge,
    // getters
    isAuthenticated,
    isChallengePending,
    // actions
    hydrate,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    login,
    twoFactorChallenge,
    twoFactorRecovery,
    cancelChallenge,
    logout,
  }
})
