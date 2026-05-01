import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import type { RouteLocationNormalized } from 'vue-router'
import { createAuthGuard, createTitleGuard } from './guards'
import { useAuthStore } from '../stores/auth'
import type { AdminBootstrap, AdminUser } from '../types/bootstrap'

const mkUser = (): AdminUser => ({
  id: 1, name: 'A', email: 'a@a',
  avatar: null, locale: null, theme: null, twoFactorEnabled: false,
})

const mkBootstrap = (overrides: Partial<AdminBootstrap> = {}): AdminBootstrap => ({
  csrf: '', baseUrl: '', apiUrl: '', locale: 'ru',
  availableLocales: [], theme: 'light', availableThemes: [],
  brand: {}, user: null, permissions: [], manifestVersion: null,
  plugins: [], unread_notifications_count: 0,
  config: { manifest: { etag: true }, bootstrap: { strategy: 'inline' } },
  ...overrides,
})

const mkRoute = (overrides: Partial<RouteLocationNormalized> = {}): RouteLocationNormalized =>
  ({
    name: 'admin.test',
    path: '/x',
    fullPath: '/x',
    hash: '',
    query: {},
    params: {},
    matched: [],
    redirectedFrom: undefined,
    meta: {},
    ...overrides,
  }) as RouteLocationNormalized

describe('createAuthGuard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('passes through login route always', () => {
    const guard = createAuthGuard()
    const result = guard(
      mkRoute({ name: 'admin.login', meta: {} }),
      mkRoute(),
      () => undefined,
    )
    expect(result).toBe(true)
  })

  it('redirects unauthenticated → login with ?redirect', () => {
    const guard = createAuthGuard()
    const result = guard(
      mkRoute({ fullPath: '/r/users', meta: { requiresAuth: true } }),
      mkRoute(),
      () => undefined,
    )
    expect(result).toEqual({
      name: 'admin.login',
      query: { redirect: '/r/users' },
    })
  })

  it('passes through if user is authenticated and no permissions required', () => {
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser() }))
    const guard = createAuthGuard()
    const result = guard(
      mkRoute({ meta: { requiresAuth: true } }),
      mkRoute(),
      () => undefined,
    )
    expect(result).toBe(true)
  })

  it('redirects authenticated-with-pending-2FA → login', () => {
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser() }))
    auth.pendingChallenge = { challengeToken: 'tok', remember: false }
    const guard = createAuthGuard()
    const result = guard(
      mkRoute({ fullPath: '/dash', meta: { requiresAuth: true } }),
      mkRoute(),
      () => undefined,
    )
    expect(result).toEqual({
      name: 'admin.login',
      query: { redirect: '/dash' },
    })
  })

  it('blocks user without required permission → forbidden', () => {
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['admin.posts.view'] }))
    const guard = createAuthGuard()
    const result = guard(
      mkRoute({ meta: { requiresAuth: true, permissions: ['admin.users.view'] } }),
      mkRoute(),
      () => undefined,
    )
    expect(result).toEqual({ name: 'admin.forbidden' })
  })

  it('allows ANY-permission match', () => {
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['admin.users.view'] }))
    const guard = createAuthGuard()
    const result = guard(
      mkRoute({
        meta: { requiresAuth: true, permissions: ['admin.users.view', 'admin.posts.view'] },
      }),
      mkRoute(),
      () => undefined,
    )
    expect(result).toBe(true)
  })

  it('respects wildcard permissions', () => {
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['*'] }))
    const guard = createAuthGuard()
    const result = guard(
      mkRoute({ meta: { requiresAuth: true, permissions: ['admin.foo.bar'] } }),
      mkRoute(),
      () => undefined,
    )
    expect(result).toBe(true)
  })

  it('uses custom loginRouteName', () => {
    const guard = createAuthGuard({ loginRouteName: 'custom.signin' })
    const result = guard(
      mkRoute({ meta: { requiresAuth: true } }),
      mkRoute(),
      () => undefined,
    )
    expect(result).toMatchObject({ name: 'custom.signin' })
  })
})

describe('createTitleGuard', () => {
  beforeEach(() => {
    document.title = ''
  })

  it('uses default template "{title} · {brand}" when both present', () => {
    const guard = createTitleGuard({ brand: 'My App' })
    guard(mkRoute({ meta: { title: 'Dashboard' } }), mkRoute(), () => undefined)
    expect(document.title).toBe('Dashboard · My App')
  })

  it('uses just title when no brand', () => {
    const guard = createTitleGuard()
    guard(mkRoute({ meta: { title: 'Hi' } }), mkRoute(), () => undefined)
    expect(document.title).toBe('Hi')
  })

  it('uses fallback when no meta.title', () => {
    const guard = createTitleGuard({ fallback: 'Admin' })
    guard(mkRoute({ meta: {} }), mkRoute(), () => undefined)
    expect(document.title).toBe('Admin')
  })

  it('respects custom template', () => {
    const guard = createTitleGuard({ template: '[{brand}] {title}', brand: 'X' })
    guard(mkRoute({ meta: { title: 'Y' } }), mkRoute(), () => undefined)
    expect(document.title).toBe('[X] Y')
  })
})
