import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import { UidAlert, UidBoard, UidCalendar } from '@dskripchenko/ui'
import WidgetRenderer from './WidgetRenderer.vue'
import { clearWidgetRegistry } from './registry'
import { registerBuiltinWidgets } from './builtin'
import { setDiagramsLoader, setNotationLoaders, type BpmnVueModule, type DiagramsModule } from '../documents/loaders'
import { clearDocumentCache } from '../documents/useDocumentSource'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useManifestStore, type AdminManifest } from '../../stores/manifest'
import { useAuthStore } from '../../stores/auth'
import { useResourceIndexStore } from '../../stores/resourceIndex'

/* ---------------- stand-ins for the packages ---------------- */

const diagramsRender = vi.fn((source: string, options?: { syntax?: string }) => ({
  svg: `<svg class="dg" data-syntax="${options?.syntax ?? 'auto'}"><text>${source}</text></svg>`,
}))
class DiagramError extends Error {}
const diagrams = { render: diagramsRender, DiagramError } as unknown as DiagramsModule

let viewerProps: Record<string, unknown> = {}
function viewerStub(name: string) {
  return defineComponent({
    name,
    props: ['xml', 'diagram', 'decision', 'highlight', 'locale'],
    setup(props) {
      return () => {
        viewerProps = { ...props, name }
        return h('div', { class: `viewer-${name}` })
      }
    },
  })
}
const bpmnVue = { BpmnView: viewerStub('BpmnView'), DmnView: viewerStub('DmnView') } as unknown as BpmnVueModule

function markupStub(name: string) {
  return defineComponent({
    name,
    props: ['source', 'editable', 'locked', 'lockable', 'view', 'date', 'views'],
    setup: () => () => h('div', { class: `${name}-stub` }),
  })
}
const stubs = { UidKanban: markupStub('UidKanban'), UidEventCalendar: markupStub('UidEventCalendar') }

/* ---------------- the panel ---------------- */

const kanbanConfig = {
  column: 'status', columns: null, lanes: null, lane_labels: null, limit: 5,
  order_by: null, saves_position: false, readonly: false, card: {},
}
const calendarConfig = {
  start: 'starts_at', end: null, date_only: false, all_day: null, title: null, color: null,
  views: ['month', 'week'], initial_view: 'month', readonly: false,
}

function setManifest(): void {
  useManifestStore().manifest = {
    version: '1',
    locale: 'ru',
    resources: [
      {
        slug: 'tasks', label: 'Tasks', views: ['list', 'kanban', 'calendar'], features: {},
        permissions: { view: 'admin.tasks.view', update: 'admin.tasks.update', reorder: 'admin.tasks.reorder' },
        kanban: kanbanConfig, calendar: calendarConfig,
      },
      {
        slug: 'secret', label: 'Secret', views: ['list', 'kanban'], features: {},
        permissions: { view: 'admin.secret.view' }, kanban: kanbanConfig, calendar: null,
      },
    ],
    screens: [], settings: [], dashboards: [], plugins: [], permissions: [],
  } as unknown as AdminManifest
}

const Page = defineComponent({ render: () => h('div') })
function mkRouter(): Router {
  return createRouter({ history: createMemoryHistory(), routes: [{ path: '/', component: Page }] })
}

let wrapper: VueWrapper | null = null
let mock: MockAdapter

async function render(node: Record<string, unknown>): Promise<VueWrapper> {
  const router = mkRouter()
  await router.push('/')
  await router.isReady()
  wrapper = mount(WidgetRenderer, { props: { node: node as never }, global: { plugins: [router], stubs } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  clearWidgetRegistry()
  registerBuiltinWidgets()
  setDiagramsLoader(async () => diagrams)
  setNotationLoaders({ bpmnVue: async () => bpmnVue })
  clearDocumentCache()
  diagramsRender.mockClear()
  viewerProps = {}
  const c = createAdminClient({ baseURL: 'http://api.test' })
  setAdminClient(c)
  mock = new MockAdapter(c.raw)
  setManifest()
  useAuthStore().permissions = ['admin.tasks.view']
  // The index page's state must not leak into a widget.
  const index = useResourceIndexStore()
  index.setSlug('tasks')
  index.search = 'from-the-index-page'
  index.filters = { priority: 'low' }
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  mock.reset()
  clearAdminClient()
  setDiagramsLoader(null)
  setNotationLoaders(null)
  vi.unstubAllGlobals()
})

describe('DiagramWidget', () => {
  it('draws the source in the given notation, from the backend data or a user config', async () => {
    const w = await render({ type: 'diagram', title: 'Flow', size: 6, data: { content: 'graph TD; A-->B', syntax: 'mermaid' } })
    expect(w.find('.admin-widget__title').text()).toBe('Flow')
    expect(w.find('[data-testid="widget-diagram"] svg.dg').attributes('data-syntax')).toBe('mermaid')

    wrapper!.unmount()
    // A user-added one: its config is the data; no syntax — detected from the text.
    const u = await render({ type: 'diagram', title: 'Mine', data: { title: 'Mine', syntax: null, content: 'Table a {}' } })
    expect(u.find('svg.dg').attributes('data-syntax')).toBe('auto')
  })
})

describe('BpmnWidget', () => {
  it('shows inline XML in the BPMN viewer', async () => {
    const w = await render({ type: 'bpmn', title: 'Process', data: { notation: 'bpmn', content: '<definitions/>', url: null, highlight: ['Task_1'] } })
    expect(w.find('.viewer-BpmnView').exists()).toBe(true)
    expect(viewerProps).toMatchObject({ name: 'BpmnView', xml: '<definitions/>', highlight: ['Task_1'] })
  })

  it('loads a DMN model from an address of the site', async () => {
    const fetchMock = vi.fn(async () => new Response('<dmn/>', { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)
    const w = await render({ type: 'bpmn', title: 'Rules', data: { notation: 'dmn', url: '/files/rules.dmn' } })
    await flushPromises()
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(w.find('.viewer-DmnView').exists()).toBe(true)
    expect(viewerProps.xml).toBe('<dmn/>')
  })
})

describe('KanbanWidget', () => {
  it('in the resource mode loads the board through /{slug}/board with its own search and filters', async () => {
    mock.onPost('/tasks/board').reply(200, {
      success: true,
      payload: {
        limit: 5,
        lanes: null,
        columns: [{ value: 'todo', label: 'To do', wip: null, total: 1, offset: 0, cards: [{ id: 1, value: 'todo', lane: null, title: 'Card 1', description: null, tags: [], assignee: null, due: null, overdue: false, locked: false }] }],
      },
    })
    const w = await render({
      type: 'kanban', title: 'Board',
      data: { mode: 'resource', resource: 'tasks', search: 'bug', filters: { status: ['todo', 'doing'] }, editable: true },
    })

    expect(mock.history.post).toHaveLength(1)
    expect(mock.history.post[0].url).toBe('/tasks/board')
    // The widget's preset, not the index page's search and filters.
    expect(JSON.parse(mock.history.post[0].data)).toEqual({ q: 'bug', filters: { status: 'todo,doing' } })
    expect(w.find('.uid-board__card-title').text()).toBe('Card 1')
    // editable, but the user may not update: the board stays read-only.
    expect(w.findComponent(UidBoard).props('readonly')).toBe(true)
  })

  it('lets the cards be dragged only when editable and the user may update', async () => {
    useAuthStore().permissions = ['admin.tasks.view', 'admin.tasks.update']
    mock.onPost('/tasks/board').reply(200, { success: true, payload: { limit: 5, lanes: null, columns: [] } })
    const w = await render({ type: 'kanban', data: { mode: 'resource', resource: 'tasks', editable: true } })
    expect(w.findComponent(UidBoard).props('readonly')).toBe(false)

    wrapper!.unmount()
    const r = await render({ type: 'kanban', data: { mode: 'resource', resource: 'tasks', editable: false } })
    expect(r.findComponent(UidBoard).props('readonly')).toBe(true)
  })

  it('does not load a resource the user may not view', async () => {
    const w = await render({ type: 'kanban', data: { mode: 'resource', resource: 'secret' } })
    expect(mock.history.post).toHaveLength(0)
    expect(w.findComponent(UidAlert).text()).toContain('Доска недоступна')
  })

  it('in the markup mode draws the kit board read-only', async () => {
    const w = await render({ type: 'kanban', title: 'Roadmap', data: { mode: 'markup', content: '## Now\n- A' } })
    const board = w.findComponent({ name: 'UidKanban' })
    expect(board.exists()).toBe(true)
    expect(board.props('source')).toBe('## Now\n- A')
    expect(board.props('editable')).toBe(false)
    expect(mock.history.post).toHaveLength(0)
  })
})

describe('CalendarWidget', () => {
  it('in the resource mode loads the events through /{slug}/calendar, opening on the widget view', async () => {
    mock.onPost('/tasks/calendar').reply(200, { success: true, payload: { events: [], truncated: false } })
    const w = await render({ type: 'calendar', data: { mode: 'resource', resource: 'tasks', initialView: 'agenda', search: 'demo' } })
    await flushPromises()

    const calendar = w.findComponent(UidCalendar)
    expect(calendar.exists()).toBe(true)
    // The agenda is not among the resource's views, so it is put first.
    expect(calendar.props('views')).toEqual(['agenda', 'month', 'week'])
    expect(calendar.props('view')).toBe('agenda')
    expect(calendar.props('editable')).toBe(false)
    expect(mock.history.post.length).toBeGreaterThan(0)
    expect(mock.history.post[0].url).toBe('/tasks/calendar')
    const body = JSON.parse(mock.history.post[0].data)
    expect(body.q).toBe('demo')
    expect(body.filters).toBeUndefined()
  })

  it('does not load a resource without a calendar view', async () => {
    useAuthStore().permissions = ['*']
    const w = await render({ type: 'calendar', data: { mode: 'resource', resource: 'secret' } })
    expect(mock.history.post).toHaveLength(0)
    expect(w.findComponent(UidAlert).text()).toContain('Календарь недоступен')
  })

  it('in the markup mode draws the kit calendar with the first view', async () => {
    const w = await render({ type: 'calendar', data: { mode: 'markup', content: 'every mon Planning', initialView: 'week' } })
    const calendar = w.findComponent({ name: 'UidEventCalendar' })
    expect(calendar.props('source')).toBe('every mon Planning')
    expect(calendar.props('view')).toBe('week')
    expect(calendar.props('editable')).toBe(false)
  })
})
