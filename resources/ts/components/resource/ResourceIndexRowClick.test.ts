/**
 * A row click opens the record, but not when the click belongs to a control
 * inside the row: an editable cell starts its inline editor, a link or a
 * checkbox does its own thing. And the empty states: "nothing found" with a
 * reset for a search, "nothing here yet" for a truly empty resource.
 */
import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import ResourceIndexPage from './ResourceIndexPage.vue'
import InlineEditCell from './InlineEditCell.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useManifestStore } from '../../stores/manifest'
import { useResourceIndexStore } from '../../stores/resourceIndex'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = (): Router =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/r/articles', name: 'admin.resource.articles.index', component: Stub },
      { path: '/r/articles/create', name: 'admin.resource.articles.create', component: Stub },
      { path: '/r/articles/:id', name: 'admin.resource.articles.view', component: Stub },
    ],
  })

function seedManifest(): void {
  useManifestStore().manifest = {
    version: 'v1',
    locale: 'ru',
    resources: [
      {
        slug: 'articles',
        label: 'Статьи',
        permissions: { view: 'admin.articles.view', create: 'admin.articles.create' },
        fields: [],
        columns: [
          { type: 'text', key: 'id', label: 'ID' },
          { type: 'text', key: 'title', label: 'Заголовок', editable: { as: 'text', rules: [] } },
          { type: 'text', key: 'status', label: 'Статус' },
        ],
        filters: [],
        actions: [],
        searchable: ['title'],
        with: [],
        features: {},
      },
    ],
    screens: [],
    settings: [],
    dashboards: [],
    plugins: [],
    permissions: [],
  } as never
}

const page = (data: Array<Record<string, unknown>>) => ({
  success: true,
  payload: { data, meta: { page: 1, per_page: 20, total: data.length, last_page: 1 } },
})

describe('ResourceIndexPage — row clicks', () => {
  let mock: MockAdapter
  let router: Router

  beforeEach(async () => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    seedManifest()
    router = mkRouter()
    await router.push('/r/articles')
    await router.isReady()
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  const mountPage = () =>
    mount(ResourceIndexPage, { props: { slug: 'articles' }, global: { plugins: [router] }, attachTo: document.body })

  it('a click on a plain cell opens the record', async () => {
    mock.onPost('/articles/search').reply(200, page([{ id: 7, title: 'Hello', status: 'draft' }]))
    const w = mountPage()
    await flushPromises()
    const cells = w.findAll('tbody tr td')
    await cells[cells.length - 1]!.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('admin.resource.articles.view')
    w.unmount()
  })

  it('a click on an editable cell starts the inline editor and stays on the list', async () => {
    mock.onPost('/articles/search').reply(200, page([{ id: 7, title: 'Hello', status: 'draft' }]))
    const w = mountPage()
    await flushPromises()
    await w.find('.admin-inline-edit--editable').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('admin.resource.articles.index')
    expect(w.find('input.admin-inline-edit__input').exists()).toBe(true)
    w.unmount()
  })

  it('a click on the selection checkbox or a link inside a row does not open the record', async () => {
    mock.onPost('/articles/search').reply(200, page([{ id: 7, title: 'Hello', status: 'draft' }]))
    const w = mountPage()
    await flushPromises()
    await w.find('tbody tr input[type="checkbox"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('admin.resource.articles.index')

    // A host link rendered in a cell.
    const td = w.findAll('tbody tr td').at(-1)!.element
    const a = document.createElement('a')
    a.href = '#x'
    a.textContent = 'link'
    td.appendChild(a)
    a.click()
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('admin.resource.articles.index')
    w.unmount()
  })

  it('a search with no matches says so inside the card and offers a reset', async () => {
    mock.onPost('/articles/search').reply(200, page([]))
    const w = mountPage()
    await flushPromises()
    const index = useResourceIndexStore()
    index.search = 'zzz'
    await index.load()
    await flushPromises()
    const state = w.find('[data-testid="resource-state"]')
    expect(state.exists()).toBe(true)
    expect(state.classes()).toContain('admin-resource-index__panel--joined')
    expect(state.text()).toContain('Ничего не найдено')
    expect(state.text()).toContain('zzz')
    await w.find('[data-testid="resource-reset-filters"]').trigger('click')
    await flushPromises()
    expect(index.search).toBe('')
    expect(w.text()).toContain('Пока пусто')
    w.unmount()
  })

  it('an empty resource offers creating the first record', async () => {
    mock.onPost('/articles/search').reply(200, page([]))
    const w = mountPage()
    await flushPromises()
    const empty = w.find('[data-testid="resource-empty"]')
    expect(empty.text()).toContain('Создайте первую запись.')
    expect(empty.find('button').exists()).toBe(true)
    w.unmount()
  })
})

describe('InlineEditCell', () => {
  it('starts editing on a click and keeps the click from the row', async () => {
    const onRowClick = (): void => { rowClicks++ }
    let rowClicks = 0
    const Host = defineComponent({
      render: () => h('div', { onClick: onRowClick }, [
        h(InlineEditCell, { resourceSlug: 'articles', rowId: 1, column: 'title', value: 'Hello' }),
      ]),
    })
    const w = mount(Host)
    await w.find('.admin-inline-edit').trigger('click')
    await flushPromises()
    expect(rowClicks).toBe(0)
    expect(w.find('input.admin-inline-edit__input').exists()).toBe(true)
  })

  it('lets a click on a read-only cell through to the row', async () => {
    let rowClicks = 0
    const Host = defineComponent({
      render: () => h('div', { onClick: () => { rowClicks++ } }, [
        h(InlineEditCell, { resourceSlug: 'articles', rowId: 1, column: 'title', value: 'Hello', editable: false }),
      ]),
    })
    const w = mount(Host)
    await w.find('.admin-inline-edit').trigger('click')
    expect(rowClicks).toBe(1)
    expect(w.find('input').exists()).toBe(false)
  })
})
