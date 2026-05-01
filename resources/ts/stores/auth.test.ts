import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { useAuthStore } from './auth'
import { setAdminClient, clearAdminClient } from './registry'
import { createAdminClient } from '../api/client'
import type { AdminBootstrap, AdminUser } from '../types/bootstrap'

const mkBootstrap = (overrides: Partial<AdminBootstrap> = {}): AdminBootstrap => ({
  csrf: 'x',
  baseUrl: '',
  apiUrl: '',
  locale: 'ru',
  availableLocales: ['ru'],
  theme: 'light',
  availableThemes: ['light'],
  brand: {},
  user: null,
  permissions: [],
  manifestVersion: null,
  plugins: [],
  unread_notifications_count: 0,
  config: { manifest: { etag: true }, bootstrap: { strategy: 'inline' } },
  ...overrides,
})

describe('auth store', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('starts not authenticated, no challenge', () => {
    const auth = useAuthStore()
    expect(auth.isAuthenticated).toBe(false)
    expect(auth.isChallengePending).toBe(false)
  })

  it('hydrate fills user + permissions from bootstrap', () => {
    const auth = useAuthStore()
    const user: AdminUser = {
      id: 1,
      name: 'Alice',
      email: 'a@example.com',
      avatar: null,
      locale: null,
      theme: null,
      twoFactorEnabled: false,
    }
    auth.hydrate(mkBootstrap({ user, permissions: ['admin.users.view'] }))
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.user?.name).toBe('Alice')
    expect(auth.permissions).toEqual(['admin.users.view'])
  })

  describe('hasPermission', () => {
    it('exact match', () => {
      const auth = useAuthStore()
      auth.hydrate(mkBootstrap({ permissions: ['admin.users.view'] }))
      expect(auth.hasPermission('admin.users.view')).toBe(true)
      expect(auth.hasPermission('admin.users.update')).toBe(false)
    })

    it('wildcard `*` allows everything', () => {
      const auth = useAuthStore()
      auth.hydrate(mkBootstrap({ permissions: ['*'] }))
      expect(auth.hasPermission('admin.anything')).toBe(true)
      expect(auth.hasPermission('something.weird')).toBe(true)
    })

    it('wildcard `admin.users.*` allows admin.users.X', () => {
      const auth = useAuthStore()
      auth.hydrate(mkBootstrap({ permissions: ['admin.users.*'] }))
      expect(auth.hasPermission('admin.users.view')).toBe(true)
      expect(auth.hasPermission('admin.users.delete')).toBe(true)
      expect(auth.hasPermission('admin.posts.view')).toBe(false)
    })

    it('hasAnyPermission and hasAllPermissions', () => {
      const auth = useAuthStore()
      auth.hydrate(mkBootstrap({ permissions: ['admin.users.view'] }))
      expect(auth.hasAnyPermission(['admin.users.view', 'admin.posts.view'])).toBe(true)
      expect(auth.hasAllPermissions(['admin.users.view', 'admin.posts.view'])).toBe(false)
      expect(auth.hasAllPermissions(['admin.users.view'])).toBe(true)
    })
  })

  describe('login flow', () => {
    it('sets user on successful login', async () => {
      const auth = useAuthStore()
      const user: AdminUser = {
        id: 1,
        name: 'Bob',
        email: 'b@example.com',
        avatar: null,
        locale: null,
        theme: null,
        twoFactorEnabled: false,
      }
      mock.onPost('/auth/login').reply(200, {
        success: true,
        payload: { user, redirect_url: '/admin' },
      })

      const result = await auth.login({ email: 'b@example.com', password: 'x' })
      expect(result).toBe('authenticated')
      expect(auth.user?.id).toBe(1)
    })

    it('sets pendingChallenge on two_factor_required', async () => {
      const auth = useAuthStore()
      mock.onPost('/auth/login').reply(200, {
        success: false,
        payload: {
          errorKey: 'two_factor_required',
          message: 'Введите код',
          challenge_token: 'tok-abc',
        },
      })

      const result = await auth.login({
        email: 'a@example.com',
        password: 'x',
        remember: true,
      })
      expect(result).toBe('two_factor_required')
      expect(auth.isChallengePending).toBe(true)
      expect(auth.pendingChallenge?.challengeToken).toBe('tok-abc')
      expect(auth.pendingChallenge?.remember).toBe(true)
      expect(auth.user).toBeNull()
    })

    it('twoFactorChallenge throws if no pending', async () => {
      const auth = useAuthStore()
      await expect(auth.twoFactorChallenge('123456')).rejects.toThrow('No pending')
    })

    it('twoFactorChallenge completes login', async () => {
      const auth = useAuthStore()
      // Set up pending state.
      mock.onPost('/auth/login').reply(200, {
        success: false,
        payload: { errorKey: 'two_factor_required', message: '', challenge_token: 'tok' },
      })
      await auth.login({ email: 'a@a', password: 'x' })

      const user: AdminUser = {
        id: 7,
        name: 'C',
        email: 'c@a',
        avatar: null,
        locale: null,
        theme: null,
        twoFactorEnabled: true,
      }
      mock.onPost('/auth/twoFactorChallenge').reply(200, {
        success: true,
        payload: { user, redirect_url: '/admin' },
      })

      await auth.twoFactorChallenge('123456')
      expect(auth.user?.id).toBe(7)
      expect(auth.isChallengePending).toBe(false)
    })

    it('twoFactorRecovery returns remaining count', async () => {
      const auth = useAuthStore()
      mock.onPost('/auth/login').reply(200, {
        success: false,
        payload: { errorKey: 'two_factor_required', message: '', challenge_token: 'tok' },
      })
      await auth.login({ email: 'a@a', password: 'x' })

      const user: AdminUser = {
        id: 1, name: 'X', email: 'x@x',
        avatar: null, locale: null, theme: null, twoFactorEnabled: true,
      }
      mock.onPost('/auth/twoFactorRecovery').reply(200, {
        success: true,
        payload: { user, recovery_codes_remaining: 4, redirect_url: '/admin' },
      })

      const result = await auth.twoFactorRecovery('xxxxxx-yyyyyy')
      expect(result.remaining).toBe(4)
      expect(auth.user?.id).toBe(1)
    })

    it('cancelChallenge clears pending', async () => {
      const auth = useAuthStore()
      mock.onPost('/auth/login').reply(200, {
        success: false,
        payload: { errorKey: 'two_factor_required', message: '', challenge_token: 'tok' },
      })
      await auth.login({ email: 'a@a', password: 'x' })
      expect(auth.isChallengePending).toBe(true)
      auth.cancelChallenge()
      expect(auth.isChallengePending).toBe(false)
    })
  })

  describe('logout', () => {
    it('clears state and calls API', async () => {
      const auth = useAuthStore()
      auth.hydrate(mkBootstrap({
        user: {
          id: 1, name: 'X', email: 'x@x',
          avatar: null, locale: null, theme: null, twoFactorEnabled: false,
        },
        permissions: ['admin.users.view'],
      }))
      mock.onPost('/auth/logout').reply(200, { success: true, payload: {} })

      await auth.logout()
      expect(auth.user).toBeNull()
      expect(auth.permissions).toEqual([])
    })

    it('clears state even on API error', async () => {
      const auth = useAuthStore()
      auth.hydrate(mkBootstrap({
        user: {
          id: 1, name: 'X', email: 'x@x',
          avatar: null, locale: null, theme: null, twoFactorEnabled: false,
        },
      }))
      mock.onPost('/auth/logout').networkError()

      await expect(auth.logout()).rejects.toThrow()
      expect(auth.user).toBeNull()
    })
  })
})
