import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { useNotificationsStore } from './notifications'
import { setAdminClient, clearAdminClient } from './registry'
import { createAdminClient } from '../api/client'

const sampleData = (overrides: Partial<{ id: string; read_at: string | null }> = {}) => ({
  id: overrides.id ?? 'uuid-1',
  type: 'App\\Notifications\\TestNotif',
  data: { title: 'Hi', body: 'Hello' },
  read_at: overrides.read_at ?? null,
  created_at: '2026-01-01T00:00:00+00:00',
})

describe('notifications store', () => {
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
    const n = useNotificationsStore()
    expect(n.items).toEqual([])
    expect(n.unreadCount).toBe(0)
    expect(n.hasUnread).toBe(false)
  })

  it('hydrate sets unreadCount', () => {
    const n = useNotificationsStore()
    n.hydrate({
      csrf: '', baseUrl: '', apiUrl: '', locale: 'ru',
      availableLocales: [], theme: 'light', availableThemes: [],
      brand: {}, user: null, permissions: [], manifestVersion: null,
      plugins: [], unread_notifications_count: 5,
      config: { manifest: { etag: true }, bootstrap: { strategy: 'inline' } },
    })
    expect(n.unreadCount).toBe(5)
    expect(n.hasUnread).toBe(true)
  })

  it('load fetches list with filter and meta', async () => {
    const n = useNotificationsStore()
    mock.onGet(/\/notifications\/list/).reply(200, {
      success: true,
      payload: {
        data: [sampleData(), sampleData({ id: 'uuid-2' })],
        meta: { page: 1, per_page: 20, total: 2, last_page: 1, unread_count: 2 },
      },
    })

    await n.load('all', 1)
    expect(n.items).toHaveLength(2)
    expect(n.unreadCount).toBe(2)
    expect(n.lastFilter).toBe('all')
    expect(n.meta?.total).toBe(2)
  })

  it('loadUnread updates count + items only when filter=unread', async () => {
    const n = useNotificationsStore()
    n.lastFilter = 'all'

    mock.onGet('/notifications/unread').reply(200, {
      success: true,
      payload: { count: 3, data: [sampleData()] },
    })

    await n.loadUnread()
    expect(n.unreadCount).toBe(3)
    expect(n.items).toEqual([])  // filter не unread, items не подменяются
  })

  it('markAsRead optimistically updates state', async () => {
    const n = useNotificationsStore()
    n.items.push(sampleData())
    n.unreadCount = 1
    mock.onPost('/notifications/markAsRead').reply(200, {
      success: true,
      payload: { id: 'uuid-1', unread_count: 0 },
    })

    await n.markAsRead('uuid-1')
    expect(n.items[0].read_at).not.toBeNull()
    expect(n.unreadCount).toBe(0)
  })

  it('markAllAsRead resets count and fills read_at for unread', async () => {
    const n = useNotificationsStore()
    n.items.push(sampleData(), sampleData({ id: 'uuid-2', read_at: '2026-01-01' }))
    n.unreadCount = 1
    mock.onPost('/notifications/markAllAsRead').reply(200, {
      success: true,
      payload: { updated: 1, unread_count: 0 },
    })

    await n.markAllAsRead()
    expect(n.unreadCount).toBe(0)
    expect(n.items[0].read_at).not.toBeNull()
    expect(n.items[1].read_at).toBe('2026-01-01') // already read, not touched
  })

  it('destroy removes item and decrements count if unread', async () => {
    const n = useNotificationsStore()
    n.items.push(sampleData())
    n.unreadCount = 1
    mock.onPost('/notifications/destroy').reply(200, {
      success: true,
      payload: { id: 'uuid-1', unread_count: 0 },
    })

    await n.destroy('uuid-1')
    expect(n.items).toHaveLength(0)
    expect(n.unreadCount).toBe(0)
  })

  it('destroy does not decrement when item already read', async () => {
    const n = useNotificationsStore()
    n.items.push(sampleData({ read_at: '2026-01-01' }))
    n.unreadCount = 5
    mock.onPost('/notifications/destroy').reply(200, {
      success: true,
      payload: { id: 'uuid-1', unread_count: 5 },
    })

    await n.destroy('uuid-1')
    expect(n.unreadCount).toBe(5)
  })
})
