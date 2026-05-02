import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import ResourceIndexPage from './ResourceIndexPage.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useManifestStore } from '../../stores/manifest'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = (): Router =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/r/articles', name: 'admin.resource.articles.index', component: Stub },
      { path: '/r/articles/create', name: 'admin.resource.articles.create', component: Stub },
    ],
  })

const seedManifest = (overrides: Record<string, unknown> = {}) => {
  const manifest = useManifestStore()
  manifest.manifest = {
    version: 'v1',
    locale: 'ru',
    resources: [
      {
        slug: 'articles',
        label: 'Статьи',
        permissions: { view: 'admin.articles.view' },
        fields: [],
        columns: [
          { type: 'text', key: 'id', label: 'ID', sortable: true, width: '60px' },
          { type: 'text', key: 'title', label: 'Заголовок', sortable: true },
          { type: 'text', key: 'status', label: 'Status' },
        ],
        filters: [],
        actions: [],
        searchable: [],
        with: [],
        features: {},
        ...overrides,
      },
    ],
    screens: [],
    settings: [],
    dashboards: [],
    plugins: [],
    permissions: [],
  }
}

async function mountPage(props: Record<string, unknown> = {}) {
  const router = mkRouter()
  await router.push('/r/articles')
  await router.isReady()
  return mount(ResourceIndexPage, {
    props: { slug: 'articles', ...props },
    global: { plugins: [router] },
  })
}

describe('ResourceIndexPage', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    seedManifest()
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('renders page header with manifest label as default title', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
    })
    const wrapper = await mountPage()
    await flushPromises()
    expect(wrapper.find('.admin-page__title').text()).toBe('Статьи')
  })

  it('renders custom title when prop provided', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
    })
    const wrapper = await mountPage({ title: 'Свои статьи' })
    expect(wrapper.find('.admin-page__title').text()).toBe('Свои статьи')
  })

  it('shows loading skeletons while fetching', async () => {
    mock.onPost('/articles/search').reply(() => new Promise(() => {}))
    const wrapper = await mountPage()
    await flushPromises()
    expect(wrapper.findAll('.admin-resource-index__loading > *').length).toBeGreaterThan(0)
  })

  it('shows EmptyState when no items', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
    })
    const wrapper = await mountPage()
    await flushPromises()
    expect(wrapper.text()).toContain('Пока пусто')
  })

  it('shows ErrorState on API error', async () => {
    mock.onPost('/articles/search').networkError()
    const wrapper = await mountPage()
    await flushPromises()
    expect(wrapper.text()).toContain('Не удалось загрузить')
  })

  it('renders bulk toolbar when items selected', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: {
        data: [
          { id: 1, title: 'A', status: 'published' },
          { id: 2, title: 'B', status: 'draft' },
        ],
        meta: { page: 1, per_page: 20, total: 2, last_page: 1 },
      },
    })
    const wrapper = await mountPage()
    await flushPromises()
    // Изначально filter-bar
    expect(wrapper.find('.admin-filter-bar').exists()).toBe(true)
    expect(wrapper.find('.admin-bulk-toolbar').exists()).toBe(false)

    // Эмулируем выбор через store напрямую (UidCheckbox в jsdom может не
    // эмитить полноценно).
    const { useResourceIndexStore } = await import('../../stores/resourceIndex')
    const idx = useResourceIndexStore()
    idx.toggleRow(1)
    await flushPromises()

    expect(wrapper.find('.admin-bulk-toolbar').exists()).toBe(true)
    expect(wrapper.find('.admin-filter-bar').exists()).toBe(false)
    expect(wrapper.find('.admin-bulk-toolbar').text()).toContain('Выбрано')
  })

  it('emits bulk-action with selected ids', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: {
        data: [{ id: 1 }, { id: 2 }],
        meta: { page: 1, per_page: 20, total: 2, last_page: 1 },
      },
    })
    const wrapper = await mountPage()
    await flushPromises()

    const { useResourceIndexStore } = await import('../../stores/resourceIndex')
    const idx = useResourceIndexStore()
    idx.toggleRow(1)
    idx.toggleRow(2)
    await flushPromises()

    const deleteBtn = wrapper
      .findAll('.admin-bulk-toolbar button')
      .find((b) => b.text() === 'Удалить')
    expect(deleteBtn).toBeDefined()
    await deleteBtn!.trigger('click')

    expect(wrapper.emitted('bulk-action')?.[0]).toEqual(['delete', [1, 2]])
  })

  it('shows pagination footer when items present', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: {
        data: [{ id: 1 }],
        meta: { page: 1, per_page: 20, total: 100, last_page: 5 },
      },
    })
    const wrapper = await mountPage()
    await flushPromises()
    expect(wrapper.find('.admin-resource-index__footer').exists()).toBe(true)
  })

  it('renders Создать button if createRouteName provided', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
    })
    const wrapper = await mountPage({ createRouteName: 'admin.resource.articles.create' })
    await flushPromises()
    // В EmptyState или header'е — где-то должна быть кнопка «Создать».
    expect(wrapper.text()).toContain('Создать')
  })
})
