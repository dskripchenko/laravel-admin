import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { useThemeStore } from './theme'
import { setAdminClient, clearAdminClient } from './registry'
import { createAdminClient } from '../api/client'
import type { AdminBootstrap } from '../types/bootstrap'

const mkBootstrap = (overrides: Partial<AdminBootstrap> = {}): AdminBootstrap => ({
  csrf: 'x',
  baseUrl: '',
  apiUrl: '',
  locale: 'ru',
  availableLocales: ['ru'],
  theme: 'light',
  availableThemes: ['light', 'dark'],
  brand: {},
  user: null,
  permissions: [],
  manifestVersion: null,
  plugins: [],
  unread_notifications_count: 0,
  config: { manifest: { etag: true }, bootstrap: { strategy: 'inline' } },
  ...overrides,
})

describe('theme store', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
    document.documentElement.removeAttribute('data-theme')
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('hydrate applies bootstrap theme + sets data-theme attr', () => {
    const t = useThemeStore()
    t.hydrate(mkBootstrap({ theme: 'dark' }))
    expect(t.current).toBe('dark')
    expect(t.isDark).toBe(true)
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
  })

  it('applyLocal sets attr without API call', () => {
    const t = useThemeStore()
    t.hydrate(mkBootstrap())
    t.applyLocal('dark')
    expect(t.current).toBe('dark')
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
  })

  it('applyLocal throws on unknown theme', () => {
    const t = useThemeStore()
    t.hydrate(mkBootstrap())
    expect(() => t.applyLocal('cyberpunk')).toThrow(/not available/)
  })

  it('setTheme persists via API', async () => {
    const t = useThemeStore()
    t.hydrate(mkBootstrap())
    mock.onPost('/system/setTheme', { theme: 'dark' }).reply(200, {
      success: true,
      payload: { theme: 'dark' },
    })

    await t.setTheme('dark')
    expect(t.current).toBe('dark')
  })

  it('setTheme rolls back on API failure', async () => {
    const t = useThemeStore()
    t.hydrate(mkBootstrap({ theme: 'light' }))
    mock.onPost('/system/setTheme').networkError()

    await expect(t.setTheme('dark')).rejects.toThrow()
    expect(t.current).toBe('light')
    expect(document.documentElement.getAttribute('data-theme')).toBe('light')
  })
})
