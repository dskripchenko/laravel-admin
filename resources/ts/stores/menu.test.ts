import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { useMenuStore, type MenuItem } from './menu'
import { useAuthStore } from './auth'
import { setAdminClient, clearAdminClient } from './registry'
import { createAdminClient } from '../api/client'
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

describe('menu store', () => {
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
    const m = useMenuStore()
    expect(m.items).toEqual([])
    expect(m.isLoaded).toBe(false)
  })

  it('load fetches and stores', async () => {
    const m = useMenuStore()
    mock.onGet('/system/menu').reply(200, {
      success: true,
      payload: {
        items: [{ key: 'users', label: 'Users', url: '/r/users', order: 1 }],
      },
    })
    await m.load()
    expect(m.items).toHaveLength(1)
    expect(m.isLoaded).toBe(true)
  })

  it('setItems установит напрямую и пометит loaded', () => {
    const m = useMenuStore()
    m.setItems([{ key: 'a', label: 'A' }])
    expect(m.isLoaded).toBe(true)
    expect(m.items).toHaveLength(1)
  })

  it('caches by default; force reloads', async () => {
    const m = useMenuStore()
    mock.onGet('/system/menu').replyOnce(200, {
      success: true, payload: { items: [{ key: 'a', label: 'A' }] },
    })
    await m.load()
    await m.load()
    expect(mock.history.get.filter((r) => r.url === '/system/menu')).toHaveLength(1)

    mock.onGet('/system/menu').replyOnce(200, {
      success: true, payload: { items: [{ key: 'b', label: 'B' }] },
    })
    await m.load(true)
    expect(m.items[0].key).toBe('b')
  })

  it('visibleItems отфильтровывает по permissions', () => {
    const m = useMenuStore()
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({
      user: mkUser(),
      permissions: ['admin.users.view'],
    }))
    const items: MenuItem[] = [
      { key: 'users', label: 'Users', permissions: ['admin.users.view'] },
      { key: 'posts', label: 'Posts', permissions: ['admin.posts.view'] },
      { key: 'public', label: 'Public' },
    ]
    m.setItems(items)
    const visible = m.visibleItems.map((i) => i.key)
    expect(visible).toEqual(['users', 'public'])
  })

  it('visibleItems отфильтровывает рекурсивно children', () => {
    const m = useMenuStore()
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({
      user: mkUser(),
      permissions: ['admin.users.view'],
    }))
    m.setItems([
      {
        key: 'group',
        label: 'Group',
        children: [
          { key: 'users', label: 'Users', permissions: ['admin.users.view'] },
          { key: 'posts', label: 'Posts', permissions: ['admin.posts.view'] },
        ],
      },
    ])
    expect(m.visibleItems[0].children?.map((c) => c.key)).toEqual(['users'])
  })

  it('wildcard `*` отдаёт всё', () => {
    const m = useMenuStore()
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['*'] }))
    m.setItems([
      { key: 'a', label: 'A', permissions: ['admin.x'] },
      { key: 'b', label: 'B' },
    ])
    expect(m.visibleItems).toHaveLength(2)
  })

  it('groupedItems собирает по group + сортирует order/label', () => {
    const m = useMenuStore()
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['*'] }))
    m.setItems([
      { key: 'b', label: 'B', group: 'main', order: 2 },
      { key: 'a', label: 'A', group: 'main', order: 1 },
      { key: 'x', label: 'X', group: 'other' },
      { key: 'y', label: 'Y' },
    ])
    const groups = m.groupedItems
    expect(groups).toHaveLength(3)
    expect(groups[0].group).toBe('main')
    expect(groups[0].items.map((i) => i.key)).toEqual(['a', 'b'])
    expect(groups[1].group).toBe('other')
    expect(groups[2].group).toBe(null)
  })

  it('reset очищает state', async () => {
    const m = useMenuStore()
    m.setItems([{ key: 'a', label: 'A' }])
    m.reset()
    expect(m.items).toEqual([])
    expect(m.isLoaded).toBe(false)
  })
})
