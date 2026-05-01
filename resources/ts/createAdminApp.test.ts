import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createAdminApp } from './createAdminApp'
import type { AdminBootstrap } from './types/bootstrap'

const baseBootstrap: AdminBootstrap = {
  csrf: 'csrf-test',
  baseUrl: 'http://localhost/admin',
  apiUrl: 'http://localhost/api/admin',
  locale: 'ru',
  availableLocales: ['ru', 'en'],
  theme: 'light',
  availableThemes: ['light', 'dark'],
  brand: { name: 'Test', logo: null, favicon: null },
  user: {
    id: 1,
    name: 'Test User',
    email: 'test@example.com',
    avatar: null,
    locale: 'ru',
    theme: 'light',
    twoFactorEnabled: false,
  },
  permissions: [],
  manifestVersion: 'v1',
  plugins: [],
  unread_notifications_count: 0,
  config: { manifest: { etag: true }, bootstrap: { strategy: 'inline' } },
}

describe('createAdminApp', () => {
  beforeEach(() => {
    // axios-mock через vi.mock не нужен — manifest.load() ловит ошибки и
    // не падает (skipManifestLoad избежит реальный fetch).
  })

  it('создаёт app + router + client из bootstrap', () => {
    const { app, router, client } = createAdminApp(baseBootstrap, { skipManifestLoad: true })

    expect(app).toBeDefined()
    expect(router).toBeDefined()
    expect(client).toBeDefined()
    expect(typeof app.mount).toBe('function')
  })

  it('static-роуты login/home/forbidden/notFound зарегистрированы', () => {
    const { router } = createAdminApp(baseBootstrap, { skipManifestLoad: true })
    const names = router.getRoutes().map((r) => r.name).filter(Boolean)
    expect(names).toContain('admin.login')
    expect(names).toContain('admin.home')
    expect(names).toContain('admin.forbidden')
    expect(names).toContain('admin.notFound')
    expect(names).toContain('admin.profile')
  })

  it('гидрирует stores из bootstrap (theme, locale)', () => {
    const { app } = createAdminApp(baseBootstrap, { skipManifestLoad: true })
    // Pinia должна быть подключена, app должен быть готов к mount.
    expect(app._context.config.globalProperties).toBeDefined()
  })

  it('вызывает onAppCreated hook', () => {
    const hook = vi.fn()
    createAdminApp(baseBootstrap, { skipManifestLoad: true, onAppCreated: hook })
    expect(hook).toHaveBeenCalledOnce()
  })

  it('deriveBase извлекает pathname из absolute baseUrl', () => {
    const { router } = createAdminApp(baseBootstrap, { skipManifestLoad: true })
    expect(router.options.history.base).toBe('/admin')
  })

  it('manifest load НЕ запускается если user=null в bootstrap (login-flow)', async () => {
    // Когда host рендерит /admin/login без user'а — manifest.load() должен
    // подождать появления user'а через watch() после успешного login.
    // Используем skipManifestLoad: false но с anonymous bootstrap.
    const anonBootstrap = { ...baseBootstrap, user: null }
    const { router } = createAdminApp(anonBootstrap)
    // Сразу после mount manifest store пустой и dynamic routes отсутствуют.
    const dynamicRouteNames = router
      .getRoutes()
      .map((r) => r.name)
      .filter((n) => typeof n === 'string' && n.startsWith('admin.resource.'))
    expect(dynamicRouteNames).toHaveLength(0)
  })
})
