/**
 * Auth store: текущий user, permissions, login/logout/2FA flow.
 *
 * Wildcard в permissions:
 *   - `*` — полный доступ ко всему.
 *   - `admin.users.*` — доступ ко всем admin.users.{view,create,update,delete}.
 *
 * 2FA flow:
 *   1. login() с верными creds + 2FA включена → success: false +
 *      errorKey: 'two_factor_required' + challenge_token. State переходит в
 *      `pendingChallenge`.
 *   2. UI показывает 2FA-форму. twoFactorChallenge(code) или
 *      twoFactorRecovery(recovery_code) → success login flow.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'
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

  /** Заполнить store из bootstrap-payload'а (стартовая инициализация). */
  function hydrate(bootstrap: AdminBootstrap): void {
    user.value = bootstrap.user
    permissions.value = bootstrap.permissions
  }

  /**
   * Проверить permission. Поддерживает wildcards:
   *   - `*` — даёт доступ всем.
   *   - `admin.users.*` — даёт доступ ко всем admin.users.X.
   */
  function hasPermission(key: string): boolean {
    if (permissions.value.includes('*')) return true
    if (permissions.value.includes(key)) return true
    return permissions.value.some((permission) => {
      if (!permission.endsWith('.*')) return false
      const prefix = permission.slice(0, -1) // 'admin.users.'
      return key.startsWith(prefix)
    })
  }

  function hasAnyPermission(keys: string[]): boolean {
    return keys.some(hasPermission)
  }

  function hasAllPermissions(keys: string[]): boolean {
    return keys.every(hasPermission)
  }

  /**
   * POST /auth/login. При 2FA-required envelope success:false +
   * errorKey:'two_factor_required' конвертируется interceptor'ом в ApiError;
   * мы ловим его, переходим в challenge-mode и возвращаем 'two_factor_required'.
   * На полный success — заполняем user и возвращаем 'authenticated'.
   * На реальную ошибку — throw'ит ApiError выше.
   */
  async function login(payload: LoginPayload): Promise<'authenticated' | 'two_factor_required'> {
    const client = getAdminClient()
    try {
      const result = await client.post<{ user?: AdminUser; permissions?: string[] }>(
        '/auth/login',
        payload,
      )
      if (result?.user) {
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

  /** POST /auth/twoFactorChallenge. Завершает login. */
  async function twoFactorChallenge(code: string): Promise<void> {
    if (pendingChallenge.value === null) {
      throw new Error('No pending 2FA challenge')
    }
    const client = getAdminClient()
    const result = await client.post<{ user: AdminUser }>('/auth/twoFactorChallenge', {
      challenge_token: pendingChallenge.value.challengeToken,
      code,
    })
    user.value = result.user
    pendingChallenge.value = null
  }

  /** POST /auth/twoFactorRecovery. Использует recovery-код вместо TOTP. */
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
    user.value = result.user
    pendingChallenge.value = null
    return { remaining: result.recovery_codes_remaining }
  }

  /** Сбросить pending challenge — для возврата на login-форму. */
  function cancelChallenge(): void {
    pendingChallenge.value = null
  }

  /** POST /auth/logout. Чистит store. */
  async function logout(): Promise<void> {
    const client = getAdminClient()
    try {
      await client.post('/auth/logout')
    } finally {
      user.value = null
      permissions.value = []
      pendingChallenge.value = null
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
