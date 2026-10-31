/**
 * The document layouts — Layout::diagram, bpmn, dmn, drawio, asyncapi, kanban
 * and calendar — against stand-ins for the packages and the kit's kanban and
 * calendar: where the text comes from (inline, a state key, a URL), what each
 * passes on, the error states, and how the kanban and calendar talk to their
 * screen.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h, nextTick, type PropType } from 'vue'
import { UidAlert } from '@dskripchenko/ui'
import { setDiagramsLoader, setNotationLoaders, setOpenApiLoader, type BpmnVueModule, type DiagramsModule, type DrawioModule, type OpenApiModule } from './loaders'
import { clearDocumentCache, toText } from './useDocumentSource'
import DiagramLayout from '../layouts/DiagramLayout.vue'
import BpmnLayout from '../layouts/BpmnLayout.vue'
import DmnLayout from '../layouts/DmnLayout.vue'
import DrawioLayout from '../layouts/DrawioLayout.vue'
import AsyncApiLayout from '../layouts/AsyncApiLayout.vue'
import KanbanLayout from '../layouts/KanbanLayout.vue'
import CalendarLayout from '../layouts/CalendarLayout.vue'
import { provideFormState, type FormStateContext } from '../render/formState'
import { provideRecord } from '../infolist/recordContext'
import { ScreenContextKey, type ScreenContext, type ScreenMethodCaller } from '../render/screenContext'
import { clearRegistry, getLayout } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'
import { toastError } from '../../stores/toast'

vi.mock('../../stores/toast', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../../stores/toast')>()),
  toastError: vi.fn(),
  toastAlerts: vi.fn(() => 0),
}))

/* ---------------- stand-ins for the packages ---------------- */

class DiagramError extends Error {
  constructor(message: string, readonly line = 0, readonly column = 0, readonly hint?: string) {
    super(message)
  }
}

const diagramsRender = vi.fn((source: string, options?: { syntax?: string; idPrefix?: string }) => {
  if (source.includes('broken')) throw new DiagramError('Unexpected token', 2, 3, 'Write an arrow')
  return { svg: `<svg class="dg" data-syntax="${options?.syntax ?? 'auto'}"><text>${source.length}</text></svg>` }
})
const diagrams = { render: diagramsRender, DiagramError } as unknown as DiagramsModule

let viewerProps: Record<string, unknown> = {}
function viewerStub(name: string) {
  return defineComponent({
    name,
    props: {
      xml: { type: String, default: null },
      diagram: { type: [String, Number], default: undefined },
      decision: { type: String, default: undefined },
      highlight: { type: Array, default: () => [] },
      locale: { type: String, default: undefined },
    },
    emits: ['error'],
    setup(props, { emit }) {
      if (props.xml?.includes('broken')) emit('error', new Error('Not a BPMN file'))
      return () => {
        viewerProps = { ...props, name }
        return h('div', { class: `viewer-${name}` })
      }
    },
  })
}
const BpmnViewStub = viewerStub('BpmnView')
const DmnViewStub = viewerStub('DmnView')
const bpmnVue = { BpmnView: BpmnViewStub, DmnView: DmnViewStub } as unknown as BpmnVueModule

const drawioRender = vi.fn((source: string, options?: { page?: string | number }) => {
  if (source.includes('broken')) throw new Error('Not a draw.io file')
  return { svg: `<svg class="dw" data-page="${options?.page ?? 'first'}"></svg>` }
})
const drawio = { render: drawioRender } as unknown as DrawioModule

let referenceProps: Record<string, unknown> = {}
const AsyncApiReferenceStub = defineComponent({
  name: 'AsyncApiReference',
  props: { spec: { type: [String, Object], required: true }, locale: { type: String, default: undefined } },
  setup(props) {
    return () => {
      referenceProps = { ...props }
      return h('div', { class: 'asyncapi-stub' })
    }
  },
})
const openapi = { AsyncApiReference: AsyncApiReferenceStub } as unknown as OpenApiModule

/** The kit's kanban and calendar, keeping their props and emitting like the kit. */
function markupStub(name: string) {
  return defineComponent({
    name,
    props: ['source', 'editable', 'locked', 'lockable', 'view', 'date', 'views'],
    emits: ['change', 'lock'],
    setup() {
      return () => h('div', { class: `${name}-stub` })
    },
  })
}
const KanbanStub = markupStub('UidKanban')
const CalendarStub = markupStub('UidEventCalendar')
const stubs = { UidKanban: KanbanStub, UidEventCalendar: CalendarStub }

/* ---------------- the host ---------------- */

let wrapper: VueWrapper | null = null
let form: FormStateContext

function host(
  component: unknown,
  props: Record<string, unknown>,
  options: { state?: Record<string, unknown>; record?: Record<string, unknown>; call?: ScreenMethodCaller } = {},
): VueWrapper {
  const Host = defineComponent({
    props: { inner: { type: Object as PropType<Record<string, unknown>>, required: true } },
    setup(p) {
      form = provideFormState(options.state ?? {})
      if (options.record) provideRecord(options.record)
      return () => h(component as never, p.inner as never)
    },
  })
  const ctx: Partial<ScreenContext> = options.call ? { call: options.call } : {}
  wrapper = mount(Host, {
    props: { inner: props },
    global: { provide: { [ScreenContextKey as symbol]: ctx }, stubs },
  })
  return wrapper
}

const fetchMock = vi.fn<typeof fetch>()

beforeEach(() => {
  viewerProps = {}
  referenceProps = {}
  diagramsRender.mockClear()
  drawioRender.mockClear()
  vi.mocked(toastError).mockClear()
  setDiagramsLoader(async () => diagrams)
  setNotationLoaders({ bpmnVue: async () => bpmnVue, drawio: async () => drawio })
  setOpenApiLoader(async () => openapi)
  clearDocumentCache()
  fetchMock.mockReset()
  fetchMock.mockImplementation(async (input) => new Response(`fetched:${String(input)}`, { status: 200 }))
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  setDiagramsLoader(null)
  setNotationLoaders(null)
  setOpenApiLoader(null)
  vi.unstubAllGlobals()
})

function alertText(): string {
  const alert = wrapper!.findComponent(UidAlert)
  return alert.exists() ? alert.text() : ''
}

/* ---------------- tests ---------------- */

describe('registration', () => {
  it('draws the seven backend types', () => {
    clearRegistry()
    registerBuiltinComponents()
    expect(getLayout('diagram')).toBe(DiagramLayout)
    expect(getLayout('bpmn')).toBe(BpmnLayout)
    expect(getLayout('dmn')).toBe(DmnLayout)
    expect(getLayout('drawio')).toBe(DrawioLayout)
    expect(getLayout('asyncapi')).toBe(AsyncApiLayout)
    expect(getLayout('kanban')).toBe(KanbanLayout)
    expect(getLayout('calendar')).toBe(CalendarLayout)
  })
})

describe('the source', () => {
  it('reads a stored value as text', () => {
    expect(toText('a')).toBe('a')
    expect(toText({ a: 1 })).toBe('{"a":1}')
    expect(toText(null)).toBe('')
    expect(toText(3)).toBe('3')
  })

  it('fetches a URL of the same site once, with the session', async () => {
    host(DiagramLayout, { url: '/files/a.mmd' })
    await flushPromises()
    host(DiagramLayout, { url: '/files/a.mmd' })
    await flushPromises()
    expect(fetchMock).toHaveBeenCalledTimes(1)
    const [url, init] = fetchMock.mock.calls[0]
    expect(String(url)).toBe(`${window.location.origin}/files/a.mmd`)
    expect(init).toEqual({ credentials: 'same-origin' })
  })

  it('refuses a URL of another site', async () => {
    host(DiagramLayout, { url: 'https://evil.test/a.mmd' })
    await flushPromises()
    expect(fetchMock).not.toHaveBeenCalled()
    expect(alertText()).toContain('Документ можно загрузить только с этого же сайта')
  })

  it('shows a failed request', async () => {
    fetchMock.mockImplementation(async () => new Response('nope', { status: 404 }))
    host(DiagramLayout, { url: '/files/missing.mmd' })
    await flushPromises()
    expect(alertText()).toContain('HTTP 404')
  })
})

describe('DiagramLayout', () => {
  it('draws inline source with the syntax, the title and the height', async () => {
    host(DiagramLayout, { content: 'graph TD; A-->B', syntax: 'mermaid', title: 'Flow', height: '300px' })
    await flushPromises()
    expect(wrapper!.find('svg.dg').attributes('data-syntax')).toBe('mermaid')
    expect(wrapper!.text()).toContain('Flow')
    expect((wrapper!.find('.admin-document-layout__box').element as HTMLElement).style.height).toBe('300px')
  })

  it('detects the syntax when none is set', async () => {
    host(DiagramLayout, { content: '@startuml\nA -> B\n@enduml' })
    await flushPromises()
    expect(wrapper!.find('svg.dg').attributes('data-syntax')).toBe('auto')
  })

  it('reads a state key and follows it', async () => {
    host(DiagramLayout, { source: 'schema' }, { state: { schema: 'graph TD; A-->B' } })
    await flushPromises()
    expect(diagramsRender).toHaveBeenLastCalledWith('graph TD; A-->B', expect.anything())
    form.setField('schema', 'graph LR; X-->Y; Y-->Z')
    await nextTick()
    expect(diagramsRender).toHaveBeenLastCalledWith('graph LR; X-->Y; Y-->Z', expect.anything())
  })

  it('reads the infolist record when there is no form value', async () => {
    host(DiagramLayout, { source: 'schema' }, { record: { schema: 'graph TD; R-->S' } })
    await flushPromises()
    expect(diagramsRender).toHaveBeenLastCalledWith('graph TD; R-->S', expect.anything())
  })

  it('draws fetched source', async () => {
    host(DiagramLayout, { url: '/files/a.mmd' })
    await flushPromises()
    expect(diagramsRender.mock.calls[0][0]).toBe(`fetched:${window.location.origin}/files/a.mmd`)
    expect(wrapper!.find('svg.dg').exists()).toBe(true)
  })

  it('shows a parse error with its line and hint', async () => {
    host(DiagramLayout, { content: 'graph broken' })
    await flushPromises()
    expect(wrapper!.find('svg.dg').exists()).toBe(false)
    expect(alertText()).toContain('Строка 2: Unexpected token. Write an arrow')
  })

  it('shows a failed download of the package', async () => {
    setDiagramsLoader(() => Promise.reject(new Error('offline')))
    host(DiagramLayout, { content: 'graph TD; A-->B' })
    await flushPromises()
    expect(alertText()).toContain('Не удалось загрузить модуль диаграмм')
  })

  it('says so when the source is empty', async () => {
    host(DiagramLayout, { source: 'missing' })
    await flushPromises()
    expect(alertText()).toContain('Диаграмма пуста')
  })
})

describe('BpmnLayout and DmnLayout', () => {
  it('passes the XML, the highlight and the diagram to BpmnView, in a box of 480px', async () => {
    host(BpmnLayout, { content: '<definitions/>', highlight: ['Task_1'], diagram: 'D2' })
    await flushPromises()
    expect(viewerProps.name).toBe('BpmnView')
    expect(viewerProps.xml).toBe('<definitions/>')
    expect(viewerProps.highlight).toEqual(['Task_1'])
    expect(viewerProps.diagram).toBe('D2')
    expect((wrapper!.find('.admin-document-layout__box').element as HTMLElement).style.height).toBe('480px')
  })

  it('reads a state key and a URL', async () => {
    host(BpmnLayout, { source: 'process' }, { state: { process: '<definitions id="s"/>' } })
    await flushPromises()
    expect(viewerProps.xml).toBe('<definitions id="s"/>')
    wrapper!.unmount()
    host(BpmnLayout, { url: '/files/order.bpmn' })
    await flushPromises()
    expect(viewerProps.xml).toBe(`fetched:${window.location.origin}/files/order.bpmn`)
  })

  it('shows DMN with DmnView and the decision', async () => {
    host(DmnLayout, { content: '<definitions/>', decision: 'Decision_1', highlight: ['Decision_1'] })
    await flushPromises()
    expect(viewerProps.name).toBe('DmnView')
    expect(viewerProps.decision).toBe('Decision_1')
    expect(viewerProps.highlight).toEqual(['Decision_1'])
  })

  it('shows a file the viewer cannot read', async () => {
    host(BpmnLayout, { content: '<broken' })
    await flushPromises()
    expect(alertText()).toContain('Not a BPMN file')
    expect(wrapper!.find('.viewer-BpmnView').exists()).toBe(false)
  })
})

describe('DrawioLayout', () => {
  it('draws the page asked for, from inline, state and URL sources', async () => {
    host(DrawioLayout, { content: '<mxfile/>', page: 'Rack B' })
    await flushPromises()
    expect(wrapper!.find('svg.dw').attributes('data-page')).toBe('Rack B')
    wrapper!.unmount()

    host(DrawioLayout, { source: 'net' }, { state: { net: '<mxfile id="s"/>' } })
    await flushPromises()
    expect(drawioRender).toHaveBeenLastCalledWith('<mxfile id="s"/>', expect.anything())
    wrapper!.unmount()

    host(DrawioLayout, { url: '/files/net.drawio' })
    await flushPromises()
    expect(drawioRender).toHaveBeenLastCalledWith(`fetched:${window.location.origin}/files/net.drawio`, expect.anything())
  })

  it('shows a file it cannot read', async () => {
    host(DrawioLayout, { content: 'broken' })
    await flushPromises()
    expect(alertText()).toContain('Not a draw.io file')
  })
})

describe('AsyncApiLayout', () => {
  it('hands the document to the reference, from inline, state and URL sources', async () => {
    host(AsyncApiLayout, { content: 'asyncapi: 3.0.0' })
    await flushPromises()
    expect(referenceProps.spec).toBe('asyncapi: 3.0.0')
    wrapper!.unmount()

    host(AsyncApiLayout, { source: 'spec' }, { state: { spec: { asyncapi: '3.0.0' } } })
    await flushPromises()
    expect(referenceProps.spec).toBe('{"asyncapi":"3.0.0"}')
    wrapper!.unmount()

    host(AsyncApiLayout, { url: '/docs/asyncapi.yaml' })
    await flushPromises()
    expect(referenceProps.spec).toBe(`fetched:${window.location.origin}/docs/asyncapi.yaml`)
  })

  it('shows a failed download of the package', async () => {
    setOpenApiLoader(() => Promise.reject(new Error('offline')))
    host(AsyncApiLayout, { content: 'asyncapi: 3.0.0' })
    await flushPromises()
    expect(alertText()).toContain('Не удалось загрузить модуль AsyncAPI')
  })
})

const BOARD = '## To do\n- Write docs\n## Done\n'
const MOVED = '## To do\n## Done\n- Write docs\n'

describe('KanbanLayout', () => {
  it('draws from inline, state and URL sources', async () => {
    host(KanbanLayout, { content: BOARD })
    await flushPromises()
    expect(wrapper!.findComponent(KanbanStub).props('source')).toBe(BOARD)
    wrapper!.unmount()

    host(KanbanLayout, { source: 'board' }, { state: { board: BOARD } })
    await flushPromises()
    expect(wrapper!.findComponent(KanbanStub).props('source')).toBe(BOARD)
    wrapper!.unmount()

    host(KanbanLayout, { url: '/boards/1.md' })
    await flushPromises()
    expect(wrapper!.findComponent(KanbanStub).props('source')).toBe(`fetched:${window.location.origin}/boards/1.md`)
  })

  it('sends a change to the screen method with the new markup', async () => {
    const call = vi.fn<ScreenMethodCaller>(async () => ({}))
    host(KanbanLayout, { source: 'board', editable: true, saveMethod: 'saveBoard' }, { call, state: { board: BOARD } })
    await flushPromises()
    const kanban = wrapper!.findComponent(KanbanStub)
    expect(kanban.props('editable')).toBe(true)

    kanban.vm.$emit('change', MOVED)
    await flushPromises()
    expect(call).toHaveBeenCalledWith('saveBoard', [MOVED])
    expect(kanban.props('source')).toBe(MOVED)
    expect(toastError).not.toHaveBeenCalled()
  })

  it('rolls a refused change back with a toast', async () => {
    const call = vi.fn<ScreenMethodCaller>(async () => {
      throw new Error('The board is closed')
    })
    host(KanbanLayout, { content: BOARD, editable: true, saveMethod: 'saveBoard' }, { call })
    await flushPromises()
    const kanban = wrapper!.findComponent(KanbanStub)
    kanban.vm.$emit('change', MOVED)
    await nextTick()
    expect(kanban.props('source')).toBe(MOVED)
    await flushPromises()
    expect(kanban.props('source')).toBe(BOARD)
    expect(toastError).toHaveBeenCalledWith(expect.objectContaining({ message: 'The board is closed' }), expect.any(String))
  })

  it('hides editing when not editable, or outside a screen', async () => {
    const call = vi.fn<ScreenMethodCaller>(async () => ({}))
    host(KanbanLayout, { content: BOARD, editable: false, saveMethod: null }, { call })
    await flushPromises()
    const kanban = wrapper!.findComponent(KanbanStub)
    expect(kanban.props('editable')).toBe(false)
    expect(kanban.props('lockable')).toBe(false)
    kanban.vm.$emit('change', MOVED)
    await flushPromises()
    expect(call).not.toHaveBeenCalled()
    wrapper!.unmount()

    host(KanbanLayout, { content: BOARD, editable: true, saveMethod: 'saveBoard' })
    await flushPromises()
    expect(wrapper!.findComponent(KanbanStub).props('editable')).toBe(false)
  })

  it('locks through the screen method, reads the lock from the state and rolls a refusal back', async () => {
    const call = vi.fn<ScreenMethodCaller>(async () => ({}))
    host(KanbanLayout, { content: BOARD, lockable: true, lockMethod: 'lockBoard', lockedSource: 'closed' }, { call, state: { closed: false } })
    await flushPromises()
    const kanban = wrapper!.findComponent(KanbanStub)
    expect(kanban.props('lockable')).toBe(true)
    expect(kanban.props('locked')).toBe(false)

    kanban.vm.$emit('lock', true)
    await flushPromises()
    expect(call).toHaveBeenCalledWith('lockBoard', [true])
    expect(kanban.props('locked')).toBe(true)

    call.mockRejectedValueOnce(new Error('No'))
    kanban.vm.$emit('lock', false)
    await flushPromises()
    expect(kanban.props('locked')).toBe(true)
    expect(toastError).toHaveBeenCalledTimes(1)

    // The state changing wins over the local lock.
    form.setField('closed', false)
    await nextTick()
    form.setField('closed', true)
    await nextTick()
    expect(kanban.props('locked')).toBe(true)
  })

  it('shows a failed request instead of the board', async () => {
    fetchMock.mockImplementation(async () => new Response('', { status: 500 }))
    host(KanbanLayout, { url: '/boards/1.md' })
    await flushPromises()
    expect(wrapper!.findComponent(KanbanStub).exists()).toBe(false)
    expect(alertText()).toContain('HTTP 500')
  })
})

const EVENTS = '2026-11-02 10:00-11:00 Standup\n'
const MOVED_EVENT = '2026-11-03 10:00-11:00 Standup\n'

describe('CalendarLayout', () => {
  it('draws from inline, state and URL sources with the view, date and views', async () => {
    host(CalendarLayout, { content: EVENTS, view: 'week', date: '2026-11-02', views: ['week', 'month'] })
    await flushPromises()
    const cal = wrapper!.findComponent(CalendarStub)
    expect(cal.props('source')).toBe(EVENTS)
    expect(cal.props('view')).toBe('week')
    expect(cal.props('date')).toBe('2026-11-02')
    expect(cal.props('views')).toEqual(['week', 'month'])
    wrapper!.unmount()

    host(CalendarLayout, { source: 'schedule' }, { state: { schedule: EVENTS } })
    await flushPromises()
    expect(wrapper!.findComponent(CalendarStub).props('source')).toBe(EVENTS)
    expect(wrapper!.findComponent(CalendarStub).props('view')).toBe('')
    wrapper!.unmount()

    host(CalendarLayout, { url: '/calendars/1.txt' })
    await flushPromises()
    expect(wrapper!.findComponent(CalendarStub).props('source')).toBe(`fetched:${window.location.origin}/calendars/1.txt`)
  })

  it('sends a change to the screen method and rolls a refusal back', async () => {
    const call = vi.fn<ScreenMethodCaller>(async () => ({}))
    host(CalendarLayout, { content: EVENTS, editable: true, saveMethod: 'saveSchedule' }, { call })
    await flushPromises()
    const cal = wrapper!.findComponent(CalendarStub)
    expect(cal.props('editable')).toBe(true)
    cal.vm.$emit('change', MOVED_EVENT)
    await flushPromises()
    expect(call).toHaveBeenCalledWith('saveSchedule', [MOVED_EVENT])
    expect(cal.props('source')).toBe(MOVED_EVENT)

    call.mockRejectedValueOnce(new Error('Busy'))
    cal.vm.$emit('change', EVENTS)
    await flushPromises()
    expect(cal.props('source')).toBe(MOVED_EVENT)
    expect(toastError).toHaveBeenCalledTimes(1)
  })

  it('hides editing when not editable', async () => {
    host(CalendarLayout, { content: EVENTS }, { call: vi.fn<ScreenMethodCaller>(async () => ({})) })
    await flushPromises()
    expect(wrapper!.findComponent(CalendarStub).props('editable')).toBe(false)
  })
})
