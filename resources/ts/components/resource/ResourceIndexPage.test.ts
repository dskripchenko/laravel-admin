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

async function mountPage(props: Record<string, unknown> = {}, attach = false) {
  const router = mkRouter()
  await router.push('/r/articles')
  await router.isReady()
  return mount(ResourceIndexPage, {
    props: { slug: 'articles', ...props },
    global: { plugins: [router] },
    ...(attach ? { attachTo: document.body } : {}),
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

  it('shows loading skeletons after slow-loading delay (>200ms)', async () => {
    mock.onPost('/articles/search').reply(() => new Promise(() => {}))
    const wrapper = await mountPage()
    await flushPromises()
    // Over the first 0-100ms the skeleton must NOT flicker; fast responses skip it.
    expect(wrapper.findAll('.admin-resource-index__loading > *').length).toBe(0)
    // After 250ms slowLoading is true and the skeleton is drawn.
    await new Promise((r) => setTimeout(r, 250))
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
    // The filter bar to begin with
    expect(wrapper.find('.admin-toolbar').exists()).toBe(true)
    expect(wrapper.find('.admin-bulk-toolbar').exists()).toBe(false)

    // The selection is emulated through the store directly, since UidCheckbox
    // may not emit properly under jsdom.
    const { useResourceIndexStore } = await import('../../stores/resourceIndex')
    const idx = useResourceIndexStore()
    idx.toggleRow(1)
    await flushPromises()

    expect(wrapper.find('.admin-bulk-toolbar').exists()).toBe(true)
    expect(wrapper.find('.admin-toolbar').exists()).toBe(false)
    expect(wrapper.find('.admin-bulk-toolbar').text()).toContain('Выбрано')
  })

  it('bulk Удалить deletes each selected row and clears selection', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: {
        data: [{ id: 1 }, { id: 2 }],
        meta: { page: 1, per_page: 20, total: 2, last_page: 1 },
      },
    })
    const deleted: unknown[] = []
    mock.onPost('/articles/delete').reply((config) => {
      deleted.push(JSON.parse(config.data).id)
      return [200, { success: true, payload: {} }]
    })
    const wrapper = await mountPage({}, true)
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
    await flushPromises()
    // The confirmation is a dialog now, not window.confirm.
    expect(document.body.querySelector('[data-testid="action-confirm-message"]')?.textContent)
      .toContain('Удалить выбранные записи (2)?')
    ;(document.body.querySelector('[data-testid="action-confirm-ok"]') as HTMLElement).click()
    await flushPromises()

    expect(deleted.sort()).toEqual([1, 2])
    expect(idx.hasSelection).toBe(false)
    wrapper.unmount()

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
    // In the empty state or in the header — the "Create" button must be somewhere.
    expect(wrapper.text()).toContain('Создать')
  })


  /**
   * The saved views are switched on by a flag on the resource. Without it the
   * backend registers no routes, so the page must not go looking for them:
   * every list used to send a request that on most resources would answer 404.
   */
  it('без features.savedViews не запрашивает список представлений', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
    })
    await mountPage()
    await flushPromises()

    expect(mock.history.get.filter((r) => r.url?.includes('_views'))).toHaveLength(0)
  })

  it('с features.savedViews запрашивает список представлений', async () => {
    seedManifest({ features: { savedViews: true } })
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
    })
    mock.onGet('/articles_views/list').reply(200, { success: true, payload: { data: [] } })
    await mountPage()
    await flushPromises()

    expect(mock.history.get.filter((r) => r.url?.includes('_views'))).toHaveLength(1)
  })

  describe('resource actions', () => {
    const rows = {
      success: true,
      payload: {
        data: [{ id: 1 }, { id: 2 }],
        meta: { page: 1, per_page: 20, total: 2, last_page: 1 },
      },
    }

    it('bulk action with an object confirm asks in a dialog, then posts {key, ids}', async () => {
      seedManifest({
        actions: [{
          kind: 'action',
          name: 'archive',
          label: 'Архивировать',
          type: 'bulk',
          position: ['bulk'],
          confirm: { title: 'Архивация', message: 'Архивировать выбранное?' },
          attributes: { method: 'archive' },
        }],
      })
      mock.onPost('/articles/search').reply(200, rows)
      const posted: unknown[] = []
      mock.onPost('/articles/action').reply((config) => {
        posted.push(JSON.parse(config.data))
        return [200, { success: true, payload: { affected: 2 } }]
      })

      const wrapper = await mountPage({}, true)
      await flushPromises()
      const { useResourceIndexStore } = await import('../../stores/resourceIndex')
      const idx = useResourceIndexStore()
      idx.toggleRow(1)
      idx.toggleRow(2)
      await flushPromises()

      await wrapper.find('[data-testid="action-archive"]').trigger('click')
      await flushPromises()
      expect(document.body.querySelector('[data-testid="action-confirm-message"]')?.textContent)
        .toContain('Архивировать выбранное?')
      expect(document.body.textContent).toContain('Архивация')
      expect(posted).toEqual([])

      ;(document.body.querySelector('[data-testid="action-confirm-ok"]') as HTMLElement).click()
      await flushPromises()
      expect(posted).toEqual([{ key: 'archive', ids: [1, 2] }])
      wrapper.unmount()
    })

    it('modal action sends the form values as payload', async () => {
      const { registerBuiltinComponents } = await import('../render/builtin')
      registerBuiltinComponents()
      seedManifest({
        actions: [{
          kind: 'action',
          name: 'change-status',
          label: 'Сменить статус',
          type: 'modal',
          position: ['bulk'],
          confirm: null,
          attributes: {
            method: 'changeStatus',
            fields: [{ kind: 'field', type: 'input', name: 'status', label: 'Статус' }],
          },
        }],
      })
      mock.onPost('/articles/search').reply(200, rows)
      const posted: unknown[] = []
      mock.onPost('/articles/action').reply((config) => {
        posted.push(JSON.parse(config.data))
        return [200, { success: true, payload: { affected: 1 } }]
      })

      const wrapper = await mountPage({}, true)
      await flushPromises()
      const { useResourceIndexStore } = await import('../../stores/resourceIndex')
      useResourceIndexStore().toggleRow(2)
      await flushPromises()

      await wrapper.find('[data-testid="action-change-status"]').trigger('click')
      await flushPromises()
      const input = document.body.querySelector('[data-testid="action-form"] input') as HTMLInputElement
      input.value = 'review'
      input.dispatchEvent(new Event('input'))
      await flushPromises()
      ;(document.body.querySelector('[data-testid="action-modal-submit"]') as HTMLElement).click()
      await flushPromises()

      expect(posted).toEqual([{ key: 'change-status', ids: [2], payload: { status: 'review' } }])
      wrapper.unmount()
    })
    it("a row action runs from the row's own menu for that row alone", async () => {
      seedManifest({
        actions: [{
          kind: 'action',
          name: 'stamp',
          label: 'Stamp',
          type: 'button',
          position: ['row'],
          confirm: null,
          attributes: { method: 'stamp' },
        }],
      })
      mock.onPost('/articles/search').reply(200, rows)
      const posted: unknown[] = []
      mock.onPost('/articles/action').reply((config) => {
        posted.push(JSON.parse(config.data))
        return [200, { success: true, payload: { affected: 1 } }]
      })

      const wrapper = await mountPage({}, true)
      await flushPromises()
      const { useResourceIndexStore } = await import('../../stores/resourceIndex')
      const idx = useResourceIndexStore()
      idx.toggleRow(1)
      await flushPromises()

      const menus = wrapper.findAll('[data-testid="row-actions-menu"]')
      expect(menus).toHaveLength(2)
      await menus[1]!.trigger('click')
      await flushPromises()
      // The menu's item, not the bulk bar's button of the same action.
      ;(document.body.querySelector('.uid-menu [data-testid="action-stamp"]') as HTMLElement).click()
      await flushPromises()

      expect(posted).toEqual([{ key: 'stamp', ids: [2] }])
      // The selection is not the row menu's business.
      expect([...idx.selection]).toEqual([1])
      wrapper.unmount()
    })

    it('a standalone action runs from the header menu with no selection', async () => {
      seedManifest({
        actions: [{
          kind: 'action',
          name: 'recalculate',
          label: 'Recalculate',
          type: 'button',
          position: ['command_bar'],
          confirm: null,
          attributes: { method: 'recalculate' },
        }],
      })
      mock.onPost('/articles/search').reply(200, rows)
      const posted: unknown[] = []
      mock.onPost('/articles/action').reply((config) => {
        posted.push(JSON.parse(config.data))
        return [200, { success: true, payload: { affected: 0 } }]
      })

      const wrapper = await mountPage({}, true)
      await flushPromises()
      expect(wrapper.find('[data-testid="row-actions-menu"]').exists()).toBe(false)
      await wrapper.find('.admin-page__more').trigger('click')
      await flushPromises()
      ;(document.body.querySelector('.uid-menu [data-testid="action-recalculate"]') as HTMLElement).click()
      await flushPromises()

      expect(posted).toEqual([{ key: 'recalculate', ids: [] }])
      wrapper.unmount()
    })
  })
})
