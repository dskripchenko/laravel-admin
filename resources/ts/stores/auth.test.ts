import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { useAuthStore } from './auth'
import { useLocaleStore } from './locale'
import { setAdminClient, clearAdminClient, getAdminClient } from './registry'
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

    it('подхватывает локаль вошедшего, а не оставляет гостевую', async () => {
      // The login is an XHR with no reload, while the locale store is hydrated
      // once, on the guest bootstrap, from the browser's Accept-Language.
      // Without this the panel spoke the browser's language after the login
      // and switched to the account's only after F5.
      const locale = useLocaleStore()
      locale.hydrate(mkBootstrap({ locale: 'en', availableLocales: ['ru', 'en'] }))
      expect(locale.current).toBe('en')

      const auth = useAuthStore()
      mock.onPost('/auth/login').reply(200, {
        success: true,
        payload: {
          user: {
            id: 2, name: 'Ann', email: 'a@example.com',
            avatar: null, locale: 'ru', theme: null, twoFactorEnabled: false,
          },
        },
      })

      await auth.login({ email: 'a@example.com', password: 'x' })

      expect(locale.current).toBe('ru')
    })

    it('выход отпускает локаль — она не достаётся следующему', async () => {
      // The X-Admin-Locale header sits ABOVE the account's saved preference in
      // the chain: while the tab keeps sending it, it overrides that
      // preference. After a logout it belongs to someone else — the next
      // person to log in from the same tab would get their predecessor's
      // language, having no preference of their own.
      const locale = useLocaleStore()
      locale.hydrate(mkBootstrap({ locale: 'en', availableLocales: ['ru', 'en'] }))

      const auth = useAuthStore()
      mock.onPost('/auth/logout').reply(200, { success: true, payload: {} })

      await auth.logout()

      mock.onGet('/probe').reply((config) => {
        expect(config.headers?.['X-Admin-Locale']).toBeUndefined()
        return [200, { success: true, payload: {} }]
      })
      await getAdminClient().get('/probe')
    })

    it('локаль не трогается, когда у пользователя её нет', async () => {
      const locale = useLocaleStore()
      locale.hydrate(mkBootstrap({ locale: 'en', availableLocales: ['ru', 'en'] }))

      const auth = useAuthStore()
      mock.onPost('/auth/login').reply(200, {
        success: true,
        payload: {
          user: {
            id: 3, name: 'Nil', email: 'n@example.com',
            avatar: null, locale: null, theme: null, twoFactorEnabled: false,
          },
        },
      })

      await auth.login({ email: 'n@example.com', password: 'x' })

      expect(locale.current).toBe('en')
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

    it('BL-44: гидратация на ru + пустая локаль у пользователя оставляет ru', async () => {
      // The case from the beta stand: the browser in ru, a bootstrap with
      // locale=ru, and locale=NULL on the account in the database. After the
      // login the panel went English.
      //
      // This test PASSED before the fix too — and that turned out to be the
      // most useful thing about it: it proved the client behaves correctly and
      // moved the search from the frontend to the backend. The culprit was the
      // login response, which substituted a default for an empty value (core
      // 1.20.1); the SPA honestly accepted a "user's choice" the user had
      // never made.
      //
      // It stays as a guard: should the client side ever start inventing a
      // locale of its own, it will fail here.
      const locale = useLocaleStore()
      locale.hydrate(mkBootstrap({ locale: 'ru', availableLocales: ['en', 'ru'] }))

      const auth = useAuthStore()
      mock.onPost('/auth/login').reply(200, {
        success: true,
        payload: {
          user: {
            id: 7, name: 'Beta', email: 'user@beta.printable.ink',
            avatar: null, locale: null, theme: null, twoFactorEnabled: false,
          },
        },
      })

      await auth.login({ email: 'user@beta.printable.ink', password: 'x' })

      expect(locale.current).toBe('ru')

      // And the header the next request will carry is ru as well: that is what
      // decides which language the menu arrives in.
      mock.onGet('/probe').reply((config) => {
        expect(config.headers?.['X-Admin-Locale']).toBe('ru')
        return [200, { success: true, payload: {} }]
      })
      await getAdminClient().get('/probe')
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

describe('glob permission matching mirrors backend fnmatch', () => {
  it('supports mid-pattern globs like printable.*.view', async () => {
    setActivePinia(createPinia())
    const auth = useAuthStore()
    auth.permissions = ['printable.*.view']

    expect(auth.hasPermission('printable.sections.view')).toBe(true)
    expect(auth.hasPermission('printable.storage.manage.view')).toBe(true)
    expect(auth.hasPermission('printable.sections.create')).toBe(false)

    auth.permissions = ['printable.templates.*']
    expect(auth.hasPermission('printable.templates.update')).toBe(true)
    expect(auth.hasPermission('printable.sections.update')).toBe(false)
  })
})
