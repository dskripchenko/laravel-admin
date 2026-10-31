import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import ResourceIndexPage from './ResourceIndexPage.vue'
import ResourceIndexLayout from '../layouts/ResourceIndexLayout.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useManifestStore, type ManifestResourceMeta } from '../../stores/manifest'
import { useAuthStore } from '../../stores/auth'
import { useResourceIndexStore } from '../../stores/resourceIndex'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const kanban = {
  column: 'status', columns: null, lanes: null, lane_labels: null, limit: 50,
  order_by: null, saves_position: false, readonly: false, card: {},
}
const calendar = {
  start: 'starts_at', end: null, date_only: false, all_day: null, title: null, color: null,
  views: ['month'], initial_view: 'month', readonly: false,
}

function seed(overrides: Partial<ManifestResourceMeta> = {}): void {
  useManifestStore().manifest = {
    version: 'v1',
    locale: 'ru',
    resources: [{
      slug: 'tasks',
      label: 'Tasks',
      permissions: { view: 'admin.tasks.view', update: 'admin.tasks.update' },
      fields: [],
      columns: [{ type: 'text', key: 'name', label: 'Name' }],
      filters: [],
      actions: [],
      searchable: [],
      with: [],
      features: { savedViews: true },
      views: ['list', 'kanban', 'calendar'],
      kanban,
      calendar,
      ...overrides,
    } as ManifestResourceMeta],
    screens: [],
    settings: [],
    dashboards: [],
    plugins: [],
    permissions: [],
  } as never
}

async function mountPage(url = '/r/tasks', component: unknown = ResourceIndexPage, props: Record<string, unknown> = { slug: 'tasks' }) {
  const router: Router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/r/tasks', name: 'admin.resource.tasks.index', component: Stub }],
  })
  await router.push(url)
  await router.isReady()
  const wrapper = mount(component as never, { props, global: { plugins: [router] } } as never)
  await flushPromises()
  return { wrapper, router }
}

describe('ResourceIndexPage views', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    useAuthStore().permissions = ['*']
    mock.onPost('/tasks/search').reply(200, {
      success: true,
      payload: { data: [{ id: 1, name: 'A' }], meta: { page: 1, per_page: 20, total: 1, last_page: 1 } },
    })
    mock.onPost('/tasks/board').reply(200, { success: true, payload: { columns: [], lanes: null, limit: 50 } })
    mock.onPost('/tasks/calendar').reply(200, { success: true, payload: { events: [] } })
    mock.onGet('/tasks_views/list').reply(200, { success: true, payload: { data: [] } })
    mock.onPost('/tasks_views/create').reply(200, { success: true, payload: { view: { id: 7 } } })
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('offers only the views the resource has', async () => {
    seed()
    const { wrapper } = await mountPage()
    expect(wrapper.findAll('[data-testid="resource-view-switcher"] [data-view]').map((b) => b.attributes('data-view')))
      .toEqual(['list', 'kanban', 'calendar'])

    seed({ views: ['list', 'calendar'], kanban: null })
    const other = await mountPage()
    expect(other.wrapper.findAll('[data-testid="resource-view-switcher"] [data-view]').map((b) => b.attributes('data-view')))
      .toEqual(['list', 'calendar'])
  })

  it('shows no switcher for a resource with the list alone', async () => {
    seed({ views: ['list'], kanban: null, calendar: null })
    const { wrapper } = await mountPage()
    expect(wrapper.find('[data-testid="resource-view-switcher"]').exists()).toBe(false)
  })

  it('switches to the kanban, keeping the view in the URL, and back to the list', async () => {
    seed()
    const { wrapper, router } = await mountPage('/r/tasks?q=abc')

    await wrapper.find('[data-view="kanban"]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ q: 'abc', view: 'kanban' })
    expect(wrapper.find('[data-testid="resource-kanban"]').exists()).toBe(true)
    expect(wrapper.find('.admin-resource-index__table').exists()).toBe(false)
    // The board takes the list's search.
    const board = mock.history.post.find((r) => r.url === '/tasks/board')
    expect(JSON.parse(board!.data).q).toBe('abc')

    await wrapper.find('[data-view="list"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ q: 'abc' })
    expect(wrapper.find('.admin-resource-index__table').exists()).toBe(true)
  })

  it('opens the view the URL names, without loading the list', async () => {
    seed()
    const { wrapper } = await mountPage('/r/tasks?view=calendar')

    expect(wrapper.find('[data-testid="resource-calendar"]').exists()).toBe(true)
    expect(mock.history.post.filter((r) => r.url === '/tasks/search')).toHaveLength(0)
  })

  it('ignores a view the resource does not have', async () => {
    seed({ views: ['list'], kanban: null, calendar: null })
    const { wrapper } = await mountPage('/r/tasks?view=kanban')

    expect(wrapper.find('[data-testid="resource-kanban"]').exists()).toBe(false)
    expect(wrapper.find('.admin-resource-index__table').exists()).toBe(true)
  })

  it('makes the kanban read-only for a user who may not update', async () => {
    seed()
    useAuthStore().permissions = ['admin.tasks.view']
    const { wrapper } = await mountPage('/r/tasks?view=kanban')

    expect(wrapper.findComponent({ name: 'ResourceKanbanView' }).props('readonly')).toBe(true)
  })

  it('saves the view in a saved view', async () => {
    seed()
    const { wrapper } = await mountPage('/r/tasks?view=kanban')
    useResourceIndexStore()
    await (wrapper.vm as unknown as { $: { setupState: { onSaveView: (l: string) => Promise<void> } } }).$.setupState.onSaveView('Board')
    await flushPromises()

    const saved = mock.history.post.find((r) => r.url === '/tasks_views/create')
    expect(JSON.parse(saved!.data).state.view).toBe('kanban')
  })

  it('Layout::resourceIndex()->initialView() opens the screen on that view', async () => {
    seed()
    const { wrapper } = await mountPage('/r/tasks', ResourceIndexLayout, { resource: 'tasks', view: 'kanban' })

    expect(wrapper.find('[data-testid="resource-kanban"]').exists()).toBe(true)
  })
})
