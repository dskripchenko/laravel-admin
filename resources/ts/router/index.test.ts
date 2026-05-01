import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { createMemoryHistory } from 'vue-router'
import { defineComponent, h } from 'vue'
import { createAdminRouter } from './index'
import { useAuthStore } from '../stores/auth'
import type { AdminManifest } from '../stores/manifest'
import type { AdminBootstrap, AdminUser } from '../types/bootstrap'

const stub = (name: string) => defineComponent({ name, render: () => h('div', name) })

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

const mkRouter = () =>
  createAdminRouter({
    history: createMemoryHistory('/admin'),
    components: {
      login: stub('Login'),
      home: stub('Home'),
      forbidden: stub('Forbidden'),
      notFound: stub('NotFound'),
      profile: stub('Profile'),
      notifications: stub('Notifications'),
      resourceIndex: stub('RIndex'),
      resourceCreate: stub('RCreate'),
      resourceEdit: stub('REdit'),
      resourceView: stub('RView'),
      screen: stub('Screen'),
      settings: stub('Settings'),
      dashboard: stub('Dashboard'),
    },
    titleGuard: { brand: 'Test' },
  })

const sampleManifest: AdminManifest = {
  version: 'v1',
  locale: 'ru',
  resources: [
    {
      slug: 'users',
      label: 'Users',
      permissions: { view: 'admin.users.view' },
      fields: [], columns: [], filters: [], actions: [],
      searchable: [], with: [], features: {},
    },
  ],
  screens: [{ slug: 'reports', name: 'Reports', description: null, permission: null }],
  settings: [],
  dashboards: [],
  plugins: [],
  permissions: [],
}

describe('createAdminRouter', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('registers static routes', () => {
    const router = mkRouter()
    expect(router.hasRoute('admin.login')).toBe(true)
    expect(router.hasRoute('admin.home')).toBe(true)
    expect(router.hasRoute('admin.forbidden')).toBe(true)
    expect(router.hasRoute('admin.profile')).toBe(true)
    expect(router.hasRoute('admin.notifications')).toBe(true)
    expect(router.hasRoute('admin.notFound')).toBe(true)
  })

  it('blocks unauthenticated navigation to protected route', async () => {
    const router = mkRouter()
    await router.push('/profile')
    expect(router.currentRoute.value.name).toBe('admin.login')
    expect(router.currentRoute.value.query.redirect).toBe('/profile')
  })

  it('allows authenticated user with permission', async () => {
    const router = mkRouter()
    router.replaceManifestRoutes(sampleManifest)
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['admin.users.view'] }))

    await router.push('/r/users')
    expect(router.currentRoute.value.name).toBe('admin.resource.users.index')
  })

  it('blocks user without permission → forbidden', async () => {
    const router = mkRouter()
    router.replaceManifestRoutes(sampleManifest)
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: [] }))

    await router.push('/r/users')
    expect(router.currentRoute.value.name).toBe('admin.forbidden')
  })

  it('replaceManifestRoutes adds dynamic routes and removes stale ones', async () => {
    const router = mkRouter()
    router.replaceManifestRoutes(sampleManifest)
    expect(router.hasRoute('admin.resource.users.index')).toBe(true)
    expect(router.hasRoute('admin.screen.reports')).toBe(true)

    // Заменяем — старые исчезают.
    const m2: AdminManifest = { ...sampleManifest, resources: [], screens: [] }
    router.replaceManifestRoutes(m2)
    expect(router.hasRoute('admin.resource.users.index')).toBe(false)
    expect(router.hasRoute('admin.screen.reports')).toBe(false)

    // catch-all всегда последний.
    expect(router.hasRoute('admin.notFound')).toBe(true)
  })

  it('catch-all 404 catches unknown paths', async () => {
    const router = mkRouter()
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['*'] }))
    await router.push('/no-such-page')
    expect(router.currentRoute.value.name).toBe('admin.notFound')
  })

  it('updates document.title via title guard', async () => {
    document.title = ''
    const router = mkRouter()
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser() }))
    await router.push('/')
    expect(document.title).toBe('Главная · Test')
  })
})
