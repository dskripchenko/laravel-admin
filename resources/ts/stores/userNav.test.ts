import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { createAdminClient } from '../api/client'
import { setAdminClient, clearAdminClient } from './registry'
import { useUserNavStore } from './userNav'
import { formatBadge, sumOfBadges, useMenuStore } from './menu'
import { useAuthStore } from './auth'
import type { AdminBootstrap } from '../types/bootstrap'

const ok = (payload: unknown) => [200, { success: true, payload }] as [number, unknown]

describe('userNav store: favourites, recent, groups, tabs (1.73)', () => {
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

  it('takes the navigation config from the bootstrap', () => {
    const auth = useAuthStore()
    auth.hydrate({
      user: null, permissions: [],
      config: {
        manifest: { etag: true }, bootstrap: { strategy: 'inline' },
        navigation: { pollInterval: 30, groupsExpanded: true, sumBadges: true, favoritesLimit: 3, breadcrumbs: 'compact' },
      },
    } as unknown as AdminBootstrap)
    const nav = useUserNavStore()
    expect(nav.config).toEqual({ pollInterval: 30, groupsExpanded: true, sumBadges: true, favoritesLimit: 3, breadcrumbs: 'compact' })
  })

  it('adds, moves and removes favourites through the server', async () => {
    const nav = useUserNavStore()
    const a = { url: '/r/a', label: 'A', icon: null }
    const b = { url: '/screens/b?tab=x', label: 'B · X', icon: null }
    mock.onPost('/navigation/favorite').replyOnce(...ok({ favorites: [a], recent: [], groups: {} }))
    await nav.toggleFavorite({ url: '/r/a', label: 'A' })
    expect(nav.isFavorite('/r/a')).toBe(true)

    mock.onPost('/navigation/favorite').replyOnce(...ok({ favorites: [a, b], recent: [], groups: {} }))
    await nav.addFavorite(b)

    mock.onPost('/navigation/reorderFavorites').replyOnce((config) => {
      expect(JSON.parse(config.data)).toEqual({ urls: ['/screens/b?tab=x', '/r/a'] })
      return ok({ favorites: [b, a], recent: [], groups: {} })
    })
    await nav.moveFavorite(1, 0)
    expect(nav.favorites.map((f) => f.url)).toEqual(['/screens/b?tab=x', '/r/a'])

    mock.onPost('/navigation/unfavorite').replyOnce(...ok({ favorites: [b], recent: [], groups: {} }))
    await nav.toggleFavorite({ url: '/r/a', label: 'A' })
    expect(nav.favorites.map((f) => f.url)).toEqual(['/screens/b?tab=x'])
  })

  it('notes a visit quietly and remembers a group', async () => {
    const nav = useUserNavStore()
    mock.onPost('/navigation/visit').replyOnce(...ok({ favorites: [], recent: [{ url: '/r/a/1', label: 'One' }], groups: {} }))
    await nav.visit({ url: '/r/a/1', label: 'One', kind: 'record' })
    expect(nav.recent[0]!.label).toBe('One')

    mock.onPost('/navigation/visit').replyOnce(500)
    await expect(nav.visit({ url: '/r/a/2', label: 'Two' })).resolves.toBeUndefined()

    const posted = vi.fn(() => ok({ groups: { dicts: false } }))
    mock.onPost('/navigation/group').reply(posted)
    expect(nav.groupOpen('dicts')).toBeNull()
    nav.setGroupOpen('dicts', false)
    expect(nav.groupOpen('dicts')).toBe(false)
    await new Promise((r) => setTimeout(r, 0))
    expect(posted).toHaveBeenCalledTimes(1)
  })

  it('keeps the open tabs outer first and the last query of every path', () => {
    const nav = useUserNavStore()
    nav.registerTab({ queryKey: 'crm-tab', label: 'Не переданы', depth: 1 })
    nav.registerTab({ queryKey: 'tab', label: 'CRM', depth: 0 })
    expect(nav.openTabs.map((t) => t.label)).toEqual(['CRM', 'Не переданы'])
    nav.registerTab({ queryKey: 'tab', label: 'Пролонгация', depth: 0 })
    expect(nav.openTabs.map((t) => t.label)).toEqual(['Пролонгация', 'Не переданы'])
    nav.unregisterTab('crm-tab')
    expect(nav.openTabs).toHaveLength(1)

    nav.rememberQuery('/r/orders', { 'filter[status]': 'new', page: '2' })
    expect(nav.queryOf('/r/orders')).toEqual({ 'filter[status]': 'new', page: '2' })
    nav.rememberQuery('/r/orders', {})
    expect(nav.queryOf('/r/orders')).toBeNull()
  })
})

describe('menu store: counters, sections, sums (1.73)', () => {
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
    vi.useRealTimers()
  })

  it('formats a badge as 99+ above 99', () => {
    expect(formatBadge(5)).toBe('5')
    expect(formatBadge(100)).toBe('99+')
    expect(formatBadge('250')).toBe('99+')
    expect(formatBadge('new')).toBe('new')
    expect(formatBadge(null)).toBeNull()
    expect(formatBadge('')).toBeNull()
  })

  it('sums the numeric badges below a group, red when one is', () => {
    expect(sumOfBadges({
      key: 'g', label: 'G', children: [
        { key: 'a', label: 'A', url: '/a', badge: 3 },
        { key: 'b', label: 'B', children: [{ key: 'c', label: 'C', url: '/c', badge: '4', badgeTone: 'danger' }] },
        { key: 'd', label: 'D', url: '/d', badge: 'new' },
      ],
    })).toEqual({ total: 7, tone: 'danger' })
    expect(sumOfBadges({ key: 'g', label: 'G', children: [{ key: 'a', label: 'A', url: '/a' }] })).toBeNull()
  })

  it('lists a section\'s children at the root under its title', () => {
    const menu = useMenuStore()
    menu.setItems([
      { key: 'section.pinned', label: 'Закреплено', kind: 'section', children: [
        { key: 'desk', label: 'Рабочий стол', url: '/dashboard/desk' },
      ] },
      { key: 'clients', label: 'Клиенты', children: [{ key: 'q', label: 'Очередь', url: '/r/q' }] },
    ])
    expect(menu.visibleItems.map((i) => i.key)).toEqual(['desk', 'clients'])
    expect(menu.visibleItems[0]!.sectionOf).toBe('Закреплено')
    expect(menu.groupedItems.map((g) => g.group)).toEqual(['Закреплено', null])
  })

  it('loads the quick create items and the saved groups with the menu', async () => {
    mock.onGet('/system/menu').reply(...ok({
      items: [{ key: 'a', label: 'A', url: '/a' }],
      groups: { clients: false },
      create: [{ key: 'resource.news', label: 'Новость', url: '/r/news/create' }],
    }))
    const menu = useMenuStore()
    await menu.load()
    expect(menu.quickCreate.map((c) => c.url)).toEqual(['/r/news/create'])
    expect(useUserNavStore().groupOpen('clients')).toBe(false)
  })

  it('refreshes the counters by key, by the timer and on a return to the tab', async () => {
    vi.useFakeTimers()
    const menu = useMenuStore()
    menu.setItems([{ key: 'g', label: 'G', children: [{ key: 'q', label: 'Q', url: '/q', badge: 1 }] }])
    let n = 1
    mock.onGet('/system/menuCounters').reply(() => ok({ badges: { q: { badge: ++n, tone: n > 2 ? 'danger' : null } } }))

    menu.startPolling(60)
    await vi.advanceTimersByTimeAsync(60_000)
    await vi.waitFor(() => expect(menu.items[0]!.children![0]!.badge).toBe(2))

    document.dispatchEvent(new Event('visibilitychange'))
    await vi.waitFor(() => expect(menu.items[0]!.children![0]!.badge).toBe(3))
    expect(menu.items[0]!.children![0]!.badgeTone).toBe('danger')

    menu.stopPolling()
    await vi.advanceTimersByTimeAsync(120_000)
    expect(menu.items[0]!.children![0]!.badge).toBe(3)
  })
})
