import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { useManifestStore } from './manifest'
import { setAdminClient, clearAdminClient } from './registry'
import { createAdminClient } from '../api/client'

describe('manifest store', () => {
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

  it('starts empty', () => {
    const m = useManifestStore()
    expect(m.isLoaded).toBe(false)
    expect(m.version).toBeNull()
    expect(m.resources).toEqual([])
  })

  it('load fetches and stores manifest', async () => {
    const m = useManifestStore()
    mock.onGet('/system/manifest').reply(200, {
      success: true,
      payload: {
        version: 'v1-abc',
        locale: 'ru',
        resources: [{ slug: 'users', label: 'Users', permissions: {}, fields: [], columns: [], filters: [], actions: [], searchable: [], with: [], features: {} }],
        screens: [],
        settings: [],
        dashboards: [],
        plugins: [],
        permissions: [],
      },
    })

    const result = await m.load()
    expect(result.version).toBe('v1-abc')
    expect(m.isLoaded).toBe(true)
    expect(m.version).toBe('v1-abc')
    expect(m.resources).toHaveLength(1)
  })

  it('returns cached on second load without force', async () => {
    const m = useManifestStore()
    let calls = 0
    mock.onGet('/system/manifest').reply(() => {
      calls++
      return [200, { success: true, payload: { version: 'v1', locale: 'ru', resources: [], screens: [], settings: [], dashboards: [], plugins: [], permissions: [] } }]
    })

    await m.load()
    await m.load()
    expect(calls).toBe(1)
  })

  it('force=true reloads from server', async () => {
    const m = useManifestStore()
    let calls = 0
    mock.onGet('/system/manifest').reply(() => {
      calls++
      return [200, { success: true, payload: { version: 'v'+calls, locale: 'ru', resources: [], screens: [], settings: [], dashboards: [], plugins: [], permissions: [] } }]
    })

    await m.load()
    await m.load(true)
    expect(calls).toBe(2)
  })

  it('getResource finds by slug', async () => {
    const m = useManifestStore()
    mock.onGet('/system/manifest').reply(200, {
      success: true,
      payload: {
        version: 'v1', locale: 'ru',
        resources: [
          { slug: 'users', label: 'Users', permissions: {}, fields: [], columns: [], filters: [], actions: [], searchable: [], with: [], features: {} },
          { slug: 'posts', label: 'Posts', permissions: {}, fields: [], columns: [], filters: [], actions: [], searchable: [], with: [], features: {} },
        ],
        screens: [], settings: [], dashboards: [], plugins: [], permissions: [],
      },
    })

    await m.load()
    expect(m.getResource('users')?.label).toBe('Users')
    expect(m.getResource('unknown')).toBeNull()
  })

  it('reset clears manifest', async () => {
    const m = useManifestStore()
    mock.onGet('/system/manifest').reply(200, {
      success: true,
      payload: { version: 'v', locale: 'ru', resources: [], screens: [], settings: [], dashboards: [], plugins: [], permissions: [] },
    })
    await m.load()
    expect(m.isLoaded).toBe(true)
    m.reset()
    expect(m.isLoaded).toBe(false)
  })

  it('captures error', async () => {
    const m = useManifestStore()
    mock.onGet('/system/manifest').networkError()

    await expect(m.load()).rejects.toThrow()
    expect(m.error).not.toBeNull()
    expect(m.loading).toBe(false)
  })
})
