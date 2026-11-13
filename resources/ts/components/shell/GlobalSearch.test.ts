import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { createAdminClient } from '../../api/client'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { useMenuStore } from '../../stores/menu'
import { useUserNavStore } from '../../stores/userNav'
import GlobalSearch from './GlobalSearch.vue'

const pushSpy = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushSpy }),
}))

const wait = (ms: number) => new Promise((r) => setTimeout(r, ms))

describe('GlobalSearch (⌘K палитра)', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
    pushSpy.mockClear()
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('shows a hint until 2+ characters are typed', async () => {
    const w = mount(GlobalSearch, { props: { modelValue: true }, global: { stubs: { teleport: true } } })
    expect(w.text()).toContain('минимум 2 символа')
  })

  it('filters menu sections client-side (nav group)', async () => {
    const menu = useMenuStore()
    menu.items = [
      { key: 'clients', label: 'Клиенты', url: '/r/clients' },
      { key: 'tpl', label: 'Шаблоны', url: '/r/templates' },
    ]
    mock.onGet('/system/search').reply(200, { success: true, payload: { query: 'клиент', groups: [] } })

    const w = mount(GlobalSearch, { props: { modelValue: true }, global: { stubs: { teleport: true } } })
    await w.find('input').setValue('клиент')
    await wait(300)
    await flushPromises()

    expect(w.text()).toContain('Разделы')
    expect(w.text()).toContain('Клиенты')
    expect(w.text()).not.toContain('Шаблоны')
  })

  it('renders server record results and navigates on click', async () => {
    mock.onGet('/system/search').reply(200, {
      success: true,
      payload: {
        query: 'ромашка',
        groups: [
          {
            slug: 'clients',
            label: 'Клиенты',
            icon: 'building-2',
            items: [{ id: 5, title: 'Ромашка', subtitle: 'romashka@x.io', url: '/r/clients/5' }],
            hasMore: false,
            moreUrl: '/r/clients',
          },
        ],
      },
    })

    const w = mount(GlobalSearch, { props: { modelValue: true }, global: { stubs: { teleport: true } } })
    await w.find('input').setValue('ромашка')
    await wait(300)
    await flushPromises()

    expect(w.text()).toContain('Ромашка')
    expect(w.text()).toContain('romashka@x.io')

    await w.find('.admin-search__item').trigger('click')
    expect(pushSpy).toHaveBeenCalledWith('/r/clients/5')
    expect(w.emitted('update:modelValue')?.at(-1)).toEqual([false])
  })

  it('Enter activates the first result', async () => {
    mock.onGet('/system/search').reply(200, {
      success: true,
      payload: {
        query: 'test',
        groups: [
          {
            slug: 'clients', label: 'Клиенты', icon: null,
            items: [{ id: 1, title: 'Test One', subtitle: null, url: '/r/clients/1' }],
            hasMore: false, moreUrl: '/r/clients',
          },
        ],
      },
    })

    const w = mount(GlobalSearch, { props: { modelValue: true }, global: { stubs: { teleport: true } } })
    await w.find('input').setValue('test')
    await wait(300)
    await flushPromises()

    await w.find('input').trigger('keydown', { key: 'Enter' })
    expect(pushSpy).toHaveBeenCalledWith('/r/clients/1')
  })
})

describe('GlobalSearch: favourites, recent, tabs, actions, recognized groups (1.73)', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
    pushSpy.mockClear()
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  const mountOpen = () => mount(GlobalSearch, { props: { modelValue: true }, global: { stubs: { teleport: true } } })

  it('shows the favourites and the recent pages for an empty query', async () => {
    mock.onGet('/system/menuTabs').reply(200, { success: true, payload: { items: [] } })
    const nav = useUserNavStore()
    nav.apply({
      favorites: [{ url: '/screens/queue?tab=new', label: 'Очередь · Новые' }],
      recent: [{ url: '/r/clients/5', label: 'Ромашка', subtitle: 'Клиенты', kind: 'record' }],
    })
    const w = mountOpen()
    await flushPromises()

    expect(w.find('[data-testid="search-group-favorites"]').text()).toContain('Очередь · Новые')
    expect(w.find('[data-testid="search-group-recent"]').text()).toContain('Ромашка')
    expect(w.find('[data-testid="search-group-recent"]').text()).toContain('Клиенты')
    expect(w.find('[data-testid="search-hint"]').exists()).toBe(false)

    await w.find('input').trigger('keydown', { key: 'Enter' })
    expect(pushSpy).toHaveBeenCalledWith('/screens/queue?tab=new')
  })

  it('finds the screens tabs by label, path and key, and the create actions', async () => {
    mock.onGet('/system/menuTabs').reply(200, {
      success: true,
      payload: {
        items: [
          { key: 's.crm', label: 'CRM', screen: 'Заявки', path: ['Клиенты', 'Заявки', 'CRM'], url: '/screens/applications?tab=crm', keywords: ['crm'] },
          { key: 's.prolong', label: 'Пролонгация', screen: 'Заявки', path: ['Клиенты', 'Заявки', 'Пролонгация'], url: '/screens/applications?tab=prolongation', keywords: ['prolongation'] },
        ],
      },
    })
    mock.onGet('/system/search').reply(200, { success: true, payload: { query: '', groups: [] } })
    const menu = useMenuStore()
    menu.quickCreate = [{ key: 'resource.news', label: 'Новость', url: '/r/news/create' }]

    const w = mountOpen()
    await flushPromises()
    await w.find('input').setValue('заявки crm')
    await wait(300)
    await flushPromises()
    const sections = w.find('[data-testid="search-group-sections"]')
    expect(sections.text()).toContain('Заявки › CRM')
    expect(sections.text()).not.toContain('Пролонгация')

    await w.find('input').setValue('prolongation')
    await wait(300)
    await flushPromises()
    expect(w.find('[data-testid="search-group-sections"]').text()).toContain('Заявки › Пролонгация')

    await w.find('input').setValue('создать нов')
    await wait(300)
    await flushPromises()
    const actions = w.find('[data-testid="search-group-actions"]')
    expect(actions.text()).toContain('Создать: Новость')
    await actions.find('.admin-search__item').trigger('click')
    expect(pushSpy).toHaveBeenCalledWith('/r/news/create')
  })

  it('puts a recognized provider group above the sections', async () => {
    mock.onGet('/system/menuTabs').reply(200, { success: true, payload: { items: [] } })
    mock.onGet('/system/search').reply(200, {
      success: true,
      payload: {
        query: '777-123',
        groups: [
          { slug: 'clients', label: 'Клиенты', icon: null, items: [{ id: 1, title: '777-123 client', subtitle: null, url: '/r/clients/1' }], hasMore: false, moreUrl: '/r/clients', recognized: false, priority: 0 },
          { slug: 'provider.policies', label: 'Договоры', icon: null, items: [{ id: 'p', title: '777-123', subtitle: 'И*** П.', url: '/screens/policy?n=777-123' }], hasMore: false, moreUrl: null, recognized: true, priority: 10 },
        ],
      },
    })
    const menu = useMenuStore()
    menu.items = [{ key: 'p', label: 'Полисы 777-123', url: '/r/policies' }]
    const w = mountOpen()
    await w.find('input').setValue('777-123')
    await wait(300)
    await flushPromises()

    const groups = w.findAll('.admin-search__group').map((g) => g.attributes('data-testid'))
    expect(groups).toEqual(['search-group-provider-policies', 'search-group-sections', 'search-group-clients'])
    expect(w.find('.admin-search__group--recognized').exists()).toBe(true)
  })
})
