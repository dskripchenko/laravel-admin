import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { useLocaleStore } from './locale'
import { setAdminClient, clearAdminClient } from './registry'
import { createAdminClient, type AdminClient } from '../api/client'
import type { AdminBootstrap } from '../types/bootstrap'

const mkBootstrap = (overrides: Partial<AdminBootstrap> = {}): AdminBootstrap => ({
  csrf: 'x',
  baseUrl: '',
  apiUrl: '',
  locale: 'ru',
  availableLocales: ['ru', 'en', 'de'],
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

describe('locale store', () => {
  let mock: MockAdapter
  let client: AdminClient

  beforeEach(() => {
    setActivePinia(createPinia())
    client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
    document.documentElement.removeAttribute('lang')
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('hydrate applies locale + html lang + client header', () => {
    const l = useLocaleStore()
    l.hydrate(mkBootstrap({ locale: 'en' }))
    expect(l.current).toBe('en')
    expect(document.documentElement.getAttribute('lang')).toBe('en')
    expect(client.raw.defaults.headers.common['X-Admin-Locale']).toBe('en')
  })

  it('applyLocal throws on unknown', () => {
    const l = useLocaleStore()
    l.hydrate(mkBootstrap())
    expect(() => l.applyLocal('jp')).toThrow(/not available/)
  })

  it('setLocale persists via API and rolls back on error', async () => {
    const l = useLocaleStore()
    l.hydrate(mkBootstrap())

    mock.onPost('/system/setLocale').reply(200, { success: true, payload: { locale: 'en' } })
    await l.setLocale('en')
    expect(l.current).toBe('en')

    mock.onPost('/system/setLocale').networkError()
    await expect(l.setLocale('de')).rejects.toThrow()
    expect(l.current).toBe('en') // откат
  })
})
