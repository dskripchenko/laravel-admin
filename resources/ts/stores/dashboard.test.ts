import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { createAdminClient } from '../api/client'
import { setAdminClient, clearAdminClient } from './registry'
import { useDashboardStore } from './dashboard'

describe('dashboard store — period persistence (BL-16)', () => {
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

  it('openDashboard reads persisted period from the response', async () => {
    mock.onGet(/\/dashboard\/get/).reply(200, {
      success: true,
      payload: { layout: null, period: '90d' },
    })

    const store = useDashboardStore()
    await store.openDashboard('main')

    expect(store.period).toBe('90d')
  })

  it('savePeriod optimistically sets period and POSTs to the endpoint', async () => {
    let posted: unknown = null
    mock.onPost('/dashboard/savePeriod').reply((config) => {
      posted = JSON.parse(config.data as string)
      return [200, { success: true, payload: { period: '7d' } }]
    })

    const store = useDashboardStore()
    await store.savePeriod('main', '7d')

    expect(store.period).toBe('7d')
    expect(posted).toEqual({ key: 'main', period: '7d' })
  })

  it('reset clears the persisted period', async () => {
    mock.onGet(/\/dashboard\/get/).reply(200, {
      success: true,
      payload: { layout: null, period: '30d' },
    })
    const store = useDashboardStore()
    await store.openDashboard('main')
    expect(store.period).toBe('30d')

    store.reset()
    expect(store.period).toBeNull()
  })
})
