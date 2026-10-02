/**
 * What the list page sends to the backend, asserted against the shapes
 * ResourceController validates. The reorder body is compared with the very
 * fixture tests/Feature/ReorderTest.php posts, so the two sides cannot drift
 * apart again.
 */
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
import { useAuthStore } from '../../stores/auth'
import { useResourceIndexStore } from '../../stores/resourceIndex'
import reorderRequest from '../../__fixtures__/reorder-request.json'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = (): Router =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/r/items', name: 'admin.resource.items.index', component: Stub },
      { path: '/r/items/:id', name: 'admin.resource.items.view', component: Stub },
      { path: '/r/items/:id/edit', name: 'admin.resource.items.edit', component: Stub },
    ],
  })

const seedManifest = (features: Record<string, unknown>): void => {
  const manifest = useManifestStore()
  manifest.manifest = {
    version: 'v1',
    locale: 'ru',
    resources: [
      {
        slug: 'items',
        label: 'Items',
        permissions: { view: 'admin.items.view', replicate: 'admin.items.replicate' },
        fields: [],
        columns: [
          { type: 'text', key: 'id', label: 'ID', sortable: true },
          { type: 'text', key: 'name', label: 'Name', sortable: true },
        ],
        filters: [],
        actions: [],
        searchable: [],
        with: [],
        features,
      },
    ],
    screens: [],
    settings: [],
    dashboards: [],
    plugins: [],
    permissions: [],
  } as never
}

const rows = [
  { id: 1, name: 'A', position: 0 },
  { id: 2, name: 'B', position: 1 },
  { id: 3, name: 'C', position: 2 },
]

async function mountPage() {
  const router = mkRouter()
  await router.push('/r/items')
  await router.isReady()
  const wrapper = mount(ResourceIndexPage, {
    props: { slug: 'items' },
    global: { plugins: [router] },
    attachTo: document.body,
  })
  await flushPromises()
  return { wrapper, router }
}

/** jsdom lays nothing out: every rect is zero, so clientY -1 is the upper half — "before". */
function dragEvent(): Record<string, unknown> {
  return { dataTransfer: { effectAllowed: '', setData: () => undefined }, clientY: -1 }
}

describe('ResourceIndexPage ↔ ResourceController contract', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    mock.onPost('/items/search').reply(200, {
      success: true,
      payload: { data: rows, meta: { page: 1, per_page: 25, total: 3, last_page: 1 } },
    })
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
    document.body.innerHTML = ''
  })

  it('posts {ids, offset} on a drop — the body ReorderTest.php sends', async () => {
    seedManifest({ reorderable: true, reorderColumn: 'position' })
    mock.onPost('/items/reorder').reply(200, {
      success: true,
      payload: { count: 3, positions: { 3: 0, 1: 1, 2: 2 }, message: 'Reordered' },
    })
    const { wrapper } = await mountPage()
    const handles = wrapper.findAll('[data-row-drag-handle="true"]')
    expect(handles).toHaveLength(3)

    // Row C is dropped above row A.
    await handles[2]!.trigger('dragstart', dragEvent())
    await handles[0]!.trigger('dragover', dragEvent())
    await handles[0]!.trigger('drop', dragEvent())
    await flushPromises()

    const sent = mock.history.post.find((r) => r.url === '/items/reorder')
    expect(sent).toBeDefined()
    expect(JSON.parse(sent!.data as string)).toEqual(reorderRequest)

    // The rows show the positions the server answered.
    const index = useResourceIndexStore()
    expect(index.items.map((r) => [r.id, r.position])).toEqual([[3, 0], [1, 1], [2, 2]])
  })

  it('does not reorder while the list is sorted by another column', async () => {
    seedManifest({ reorderable: true, reorderColumn: 'position' })
    const { wrapper } = await mountPage()
    const index = useResourceIndexStore()
    index.sortKey = 'name'
    index.sortDirection = 'asc'
    await flushPromises()
    const handles = wrapper.findAll('[data-row-drag-handle="true"]')
    expect(handles[0]!.attributes('data-disabled')).toBe('true')
    await handles[2]!.trigger('dragstart', dragEvent())
    await handles[0]!.trigger('drop', dragEvent())
    await flushPromises()
    expect(mock.history.post.some((r) => r.url === '/items/reorder')).toBe(false)
  })

  it('exports with the same q, filters and order the search uses', async () => {
    seedManifest({ exportable: ['csv'] })
    mock.onPost('/items/export').reply(200, 'id\n1\n')
    const { wrapper } = await mountPage()
    const index = useResourceIndexStore()
    index.search = 'abc'
    index.filters = { status: ['a', 'b'] }
    index.sortKey = 'name'
    index.sortDirection = 'desc'
    const urlApi = URL as unknown as { createObjectURL?: unknown; revokeObjectURL?: unknown }
    urlApi.createObjectURL = () => 'blob:x'
    urlApi.revokeObjectURL = () => undefined
    await (wrapper.vm as unknown as { $: { setupState: { onExport: (f: string) => Promise<void> } } })
      .$.setupState.onExport('csv')
    await flushPromises()
    const sent = mock.history.post.find((r) => r.url === '/items/export')
    expect(JSON.parse(sent!.data as string)).toEqual({
      format: 'csv',
      q: 'abc',
      filters: { status: 'a,b' },
      order: [{ column: 'name', direction: 'desc' }],
    })
  })

  it('copies a record through POST /replicate {id} and opens the copy', async () => {
    seedManifest({ replicable: true })
    useAuthStore().permissions = ['admin.items.*']
    mock.onPost('/items/replicate').reply(200, {
      success: true,
      payload: { record: { id: 9, name: 'A (copy)' }, redirect_url: '/admin/r/items/9/edit', message: 'Replicated' },
    })
    const { wrapper, router } = await mountPage()
    const buttons = wrapper.findAll('[data-testid="row-replicate"]')
    expect(buttons).toHaveLength(3)
    await buttons[0]!.trigger('click')
    await flushPromises()
    const sent = mock.history.post.find((r) => r.url === '/items/replicate')
    expect(JSON.parse(sent!.data as string)).toEqual({ id: 1 })
    expect(router.currentRoute.value.fullPath).toBe('/r/items/9/edit')
  })

  it('hides the copy action without the replicate permission', async () => {
    seedManifest({ replicable: true })
    useAuthStore().permissions = ['admin.items.view']
    const { wrapper } = await mountPage()
    expect(wrapper.findAll('[data-testid="row-replicate"]')).toHaveLength(0)
  })
})
