/**
 * The panel's side of @dskripchenko/flow — Layout::flow, Field\Flow, the
 * infolist entry and ```flow blocks — against stand-ins for FlowView and
 * FlowEditor: what each passes in, and how the screen methods are wired. The
 * package's core (pure TypeScript) is the real one.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createApp, defineComponent, h, nextTick, render, shallowReactive, type AppContext, type PropType } from 'vue'
import * as core from '@dskripchenko/flow'
import type { Graph } from '@dskripchenko/flow'
import { setFlowLoader, setFlowMermaidLoader, toGraph, type FlowModules } from './loadFlow'
import FlowLayout from '../layouts/FlowLayout.vue'
import FlowField from '../fields/FlowField.vue'
import FlowEntry from '../infolist/FlowEntry.vue'
import { provideFormState, type FormStateContext } from '../render/formState'
import { ScreenContextKey, type ScreenContext, type ScreenMethodCaller } from '../render/screenContext'
import { provideRecord } from '../infolist/recordContext'
import { clearRegistry, getField, getLayout } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'
import { registerBuiltinInfolistEntries } from '../infolist/builtin'
import { getInfolistEntry } from '../infolist/registry'
import { flowRenderer, provideBuiltinMarkdownRenderers } from '../markdown/blockRenderers'

const ORDER: Graph = {
  version: 1,
  nodes: [
    { id: 'start', type: 'start' },
    { id: 'paid', type: 'state', label: 'Paid', subgraph: 'payment' },
    { id: 'end', type: 'end' },
  ],
  edges: [
    { id: 'e1', from: 'start', to: 'paid' },
    { id: 'e2', from: 'paid', to: 'end' },
  ],
}

const SELECTION = { kind: 'node', element: { id: 'paid' }, path: [] as string[], state: undefined }

/** The last props each stand-in was rendered with. */
let viewProps: Record<string, unknown> = {}
let editorProps: Record<string, unknown> = {}
const refresh = vi.fn()

const FlowViewStub = defineComponent({
  name: 'FlowViewStub',
  props: {
    graph: { type: Object, required: true },
    state: { type: Object, default: undefined },
    poll: { type: Object, default: undefined },
    pollParams: { type: null, default: undefined },
    toggleable: Boolean,
    persistToggle: { type: Function, default: undefined },
    subgraphs: { type: Object, default: undefined },
    loadSubgraph: { type: Function, default: undefined },
    details: { type: Boolean, default: true },
    minimap: { type: Boolean, default: true },
    statusBar: { type: Boolean, default: true },
    direction: { type: String, default: undefined },
    edgeStyle: { type: String, default: undefined },
    locale: { type: String, default: undefined },
    toastProvider: { type: Boolean, default: true },
  },
  setup(props, { slots, expose }) {
    expose({ refresh })
    return () => {
      viewProps = { ...props }
      return h('div', { class: 'flow-view-stub' }, slots.details?.(SELECTION))
    }
  },
})

const FlowEditorStub = defineComponent({
  name: 'FlowEditorStub',
  props: {
    modelValue: { type: Object, required: true },
    readonly: Boolean,
    palette: { type: Array, default: undefined },
    locked: { type: Array, default: undefined },
    mermaid: { type: Function, default: undefined },
    direction: { type: String, default: undefined },
    edgeStyle: { type: String, default: undefined },
    minimap: Boolean,
    snapGrid: { type: Number, default: 20 },
    exportName: { type: String, default: undefined },
    locale: { type: String, default: undefined },
    toastProvider: { type: Boolean, default: true },
  },
  emits: ['update:modelValue'],
  setup(props) {
    return () => {
      editorProps = { ...props }
      return h('div', { class: 'flow-editor-stub' })
    }
  },
})

/** mountFlow as the package has it: renders into the element, in the host's context when given one. */
let mountedContext: AppContext | undefined
function mountFlow(el: Element, options: Record<string, unknown>) {
  const { appContext, ...rest } = options as { appContext?: AppContext }
  mountedContext = appContext
  const props = shallowReactive({ ...rest })
  const draw = () => {
    const vnode = h(FlowViewStub, props as never)
    vnode.appContext = appContext ?? null
    render(vnode, el)
  }
  draw()
  return {
    update: (next: Record<string, unknown>) => {
      Object.assign(props, next)
      draw()
    },
    unmount: () => render(null, el),
  }
}

const modules = { vue: { FlowView: FlowViewStub, FlowEditor: FlowEditorStub, mountFlow }, core } as unknown as FlowModules

let wrapper: VueWrapper | null = null
let form: FormStateContext

function host(
  component: unknown,
  props: Record<string, unknown>,
  options: { state?: Record<string, unknown>; call?: ScreenMethodCaller } = {},
): VueWrapper {
  const Host = defineComponent({
    props: { inner: { type: Object as PropType<Record<string, unknown>>, required: true } },
    setup(p, { expose }) {
      form = provideFormState(options.state ?? {})
      provideRecord(options.state ?? {})
      expose({})
      return () => h(component as never, p.inner as never)
    },
  })
  const ctx: Partial<ScreenContext> = options.call ? { call: options.call } : {}
  wrapper = mount(Host, {
    props: { inner: props },
    global: { provide: { [ScreenContextKey as symbol]: ctx } },
  })
  return wrapper
}

beforeEach(() => {
  viewProps = {}
  editorProps = {}
  refresh.mockReset()
  setFlowLoader(async () => modules)
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  setFlowLoader(null)
  setFlowMermaidLoader(null)
})

describe('registration', () => {
  it('draws the backend type `flow` as a layout, a field and an entry', () => {
    clearRegistry()
    registerBuiltinComponents()
    registerBuiltinInfolistEntries()
    expect(getLayout('flow')).toBe(FlowLayout)
    expect(getField('flow')).toBe(FlowField)
    expect(getInfolistEntry('flow')).toBe(FlowEntry)
  })
})

describe('toGraph', () => {
  it('reads an object or JSON, keeping a complete model as is', () => {
    expect(toGraph(ORDER)).toBe(ORDER)
    expect(toGraph(JSON.stringify(ORDER))).toEqual(ORDER)
    expect(toGraph({ nodes: [] })).toEqual({ version: 1, nodes: [], edges: [] })
    expect(toGraph('not json')).toBeNull()
    expect(toGraph(null)).toBeNull()
    expect(toGraph([1, 2])).toBeNull()
  })
})

describe('FlowLayout', () => {
  it('draws an inline model with the options, without a toast provider of its own', async () => {
    host(FlowLayout, { graph: ORDER, direction: 'LR', edgeStyle: 'smooth', minimap: false, height: '600px', title: 'Orders' })
    await flushPromises()
    expect(viewProps.graph).toEqual(ORDER)
    expect(viewProps.direction).toBe('LR')
    expect(viewProps.edgeStyle).toBe('smooth')
    expect(viewProps.minimap).toBe(false)
    expect(viewProps.toastProvider).toBe(false)
    expect(viewProps.poll).toBeUndefined()
    expect(wrapper!.text()).toContain('Orders')
    expect((wrapper!.find('.admin-flow-layout__box').element as HTMLElement).style.height).toBe('600px')
  })

  it('reads the model and a fixed snapshot from the screen state', async () => {
    host(FlowLayout, { source: 'process', snapshotSource: 'live' }, {
      state: { process: JSON.stringify(ORDER), live: { nodes: { paid: { status: 'active' } }, edges: [] } },
    })
    await flushPromises()
    expect(viewProps.graph).toEqual(ORDER)
    // A PHP empty array becomes the object the snapshot wants.
    expect(viewProps.state).toEqual({ nodes: { paid: { status: 'active' } }, edges: {} })
  })

  it('draws an empty model when the state key holds nothing', async () => {
    host(FlowLayout, { source: 'missing' })
    await flushPromises()
    expect(viewProps.graph).toEqual({ version: 1, nodes: [], edges: [] })
  })

  it('polls a screen method with the level and the instance, starting from a fixed snapshot', async () => {
    const call = vi.fn<ScreenMethodCaller>(async () => ({ extra: { nodes: { paid: { status: 'done' } }, current: ['paid'] } }))
    host(FlowLayout, {
      graph: ORDER,
      poll: { method: 'orderState', interval: 3000 },
      instanceSource: 'order_id',
      snapshot: { nodes: { start: { status: 'done' } } },
    }, { call, state: { order_id: 42 } })
    await flushPromises()
    const poll = viewProps.poll as { intervalMs: number; fetch: (s: AbortSignal, c: { path: string[]; ref?: string }) => Promise<unknown> }
    expect(poll.intervalMs).toBe(3000)
    // The starting state; the view replaces it with the polled snapshots.
    expect(viewProps.state).toEqual({ nodes: { start: { status: 'done' } } })
    expect(viewProps.pollParams).toBe(42)

    const signal = new AbortController().signal
    const snapshot = await (poll.fetch as (s: AbortSignal, c: unknown) => Promise<unknown>)(signal, { path: ['paid'], ref: 'payment', params: 42 })
    expect(call).toHaveBeenCalledWith('orderState', [{ path: ['paid'], ref: 'payment', instance: 42 }], { signal })
    expect(snapshot).toEqual({ nodes: { paid: { status: 'done' } }, current: ['paid'] })

    // Another instance in focus: new poll params, which restart the polling.
    form.setField('order_id', 43)
    await nextTick()
    expect(viewProps.pollParams).toBe(43)
    await (poll.fetch as (s: AbortSignal, c: unknown) => Promise<unknown>)(signal, { path: [], params: 43 })
    expect(call).toHaveBeenLastCalledWith('orderState', [{ path: [], ref: null, instance: 43 }], { signal })
  })

  it('persists a toggle through the screen and lets a refusal reject (the view rolls back)', async () => {
    const call = vi.fn<ScreenMethodCaller>(async () => ({}))
    host(FlowLayout, { graph: ORDER, toggle: { method: 'toggleStep' }, toggleable: true }, { call })
    await flushPromises()
    expect(viewProps.toggleable).toBe(true)
    const persistToggle = viewProps.persistToggle as (c: unknown) => Promise<void>
    const change = { kind: 'node', id: 'paid', enabled: false, path: [] }
    await persistToggle(change)
    expect(call).toHaveBeenCalledWith('toggleStep', [change])

    call.mockRejectedValueOnce(new Error('Locked by policy'))
    await expect(persistToggle(change)).rejects.toThrow('Locked by policy')
  })

  it('loads a referenced sub-process through the screen', async () => {
    const sub: Graph = { version: 1, nodes: [{ id: 'a', type: 'state' }], edges: [] }
    const call = vi.fn<ScreenMethodCaller>(async () => ({ extra: sub as unknown as Record<string, unknown> }))
    host(FlowLayout, { graph: ORDER, subgraphs: { method: 'subprocess', graphs: { other: sub } } }, { call })
    await flushPromises()
    expect(viewProps.subgraphs).toEqual({ other: sub })
    const load = viewProps.loadSubgraph as (ref: string, node: unknown) => Promise<Graph>
    await expect(load('payment', ORDER.nodes[1])).resolves.toEqual(sub)
    expect(call).toHaveBeenCalledWith('subprocess', ['payment', ORDER.nodes[1]])

    call.mockResolvedValueOnce({ extra: { nope: true } })
    await expect(load('payment', ORDER.nodes[1])).rejects.toThrow()
  })

  it('wires nothing to the server outside a screen', async () => {
    host(FlowLayout, { graph: ORDER, poll: { method: 'state' }, toggle: { method: 't' }, subgraphs: { method: 's' } })
    await flushPromises()
    expect(viewProps.poll).toBeUndefined()
    expect(viewProps.persistToggle).toBeUndefined()
    expect(viewProps.loadSubgraph).toBeUndefined()
  })

  it('runs a drawer action with the selection and refreshes the monitoring', async () => {
    const call = vi.fn<ScreenMethodCaller>(async () => ({ message: '' }))
    host(FlowLayout, {
      graph: ORDER,
      instance: 7,
      actions: [{ name: 'retry', label: 'Retry', type: 'button', attributes: { method: 'retryStep' } }],
    }, { call })
    await flushPromises()
    const button = wrapper!.find('.admin-flow-layout__actions button')
    expect(button.text()).toBe('Retry')
    await button.trigger('click')
    await flushPromises()
    expect(call).toHaveBeenCalledWith('retryStep', [{ kind: 'node', id: 'paid', path: [], instance: 7 }])
    expect(refresh).toHaveBeenCalled()
  })
})

describe('FlowField', () => {
  it('edits the model in the form state, keeping its identity for the editor', async () => {
    host(FlowField, { name: 'process', label: 'Process', palette: ['state', 'end'] }, { state: { process: ORDER } })
    await flushPromises()
    expect(editorProps.modelValue).toStrictEqual(ORDER)
    expect(editorProps.palette).toEqual(['state', 'end'])
    expect(editorProps.readonly).toBe(false)
    expect(editorProps.exportName).toBe('process.json')

    const next: Graph = { ...ORDER, nodes: [...ORDER.nodes, { id: 'x', type: 'state' }] }
    wrapper!.findComponent(FlowEditorStub).vm.$emit('update:modelValue', next)
    await nextTick()
    expect(form.getField('process')).toStrictEqual(next)
    expect(editorProps.modelValue).toBe(next)
  })

  it('starts from an empty model, reads a JSON string and passes the locked types', async () => {
    host(FlowField, { name: 'process', locked: ['start'], readonly: true }, { state: { process: null } })
    await flushPromises()
    expect(editorProps.modelValue).toEqual({ version: 1, nodes: [], edges: [] })
    expect(editorProps.readonly).toBe(true)
    expect(editorProps.locked).toEqual(['start'])

    form.setField('process', JSON.stringify(ORDER))
    await nextTick()
    expect(editorProps.modelValue).toEqual(ORDER)
  })

  it('offers the Mermaid import once the importer has loaded', async () => {
    const fromMermaid = vi.fn()
    setFlowMermaidLoader(async () => ({ fromMermaid }) as never)
    host(FlowField, { name: 'process' }, { state: {} })
    await flushPromises()
    expect(editorProps.mermaid).toBe(fromMermaid)
  })

  it('shows the validation error under the editor', async () => {
    host(FlowField, { name: 'process' }, { state: {} })
    form.setErrors({ process: ['Invalid process model'] })
    await flushPromises()
    expect(wrapper!.text()).toContain('Invalid process model')
  })
})

describe('FlowEntry', () => {
  it('draws a stored model read-only', async () => {
    host(FlowEntry, { name: 'process', height: 300 }, { state: { process: JSON.stringify(ORDER) } })
    await flushPromises()
    expect(viewProps.graph).toEqual(ORDER)
    expect(viewProps.statusBar).toBe(false)
    expect(viewProps.poll).toBeUndefined()
  })

  it('shows the placeholder for an empty value', async () => {
    host(FlowEntry, { name: 'process' }, { state: {} })
    await flushPromises()
    expect(wrapper!.find('.flow-view-stub').exists()).toBe(false)
    expect(wrapper!.text()).toBe('—')
  })
})

describe('flow block renderer', () => {
  it('mounts a read-only view of a JSON model, in the panel app context', async () => {
    const app = createApp({ render: () => null })
    provideBuiltinMarkdownRenderers(app)
    const result = await flowRenderer(JSON.stringify(ORDER), { language: 'flow', index: 0 })
    expect(result.error).toBeUndefined()
    const el = document.createElement('div')
    const handle = result.mount!(el, { code: '', language: 'flow', editable: false, setCode: () => undefined })
    expect(el.querySelector('.flow-view-stub')).not.toBeNull()
    expect(viewProps.graph).toMatchObject({ nodes: ORDER.nodes })
    expect(viewProps.toastProvider).toBe(false)
    expect(mountedContext).toBe(app._context)
    if (handle && typeof handle === 'object') handle.update?.('{"nodes":[{"id":"z"}]}')
    await flushPromises()
    expect(viewProps.graph).toMatchObject({ nodes: [{ id: 'z' }] })
    if (handle && typeof handle === 'object') handle.destroy?.()
    expect(el.querySelector('.flow-view-stub')).toBeNull()
  })

  it('reads Mermaid state diagrams too', async () => {
    const result = await flowRenderer('stateDiagram-v2\n  [*] --> Idle\n  Idle --> [*]', { language: 'flow', index: 0 })
    expect(result.error).toBeUndefined()
    expect(typeof result.mount).toBe('function')
  })

  it('reports a broken model as an error', async () => {
    const result = await flowRenderer('{"nodes": 3}', { language: 'flow', index: 0 })
    expect(result.mount).toBeUndefined()
    expect(result.error).toBeTruthy()
  })
})
