/**
 * The block tree editor on a screen: the value in the screen's state, the
 * structure operations with undo, the draft's autosave, publish (a conflict,
 * the nodes' errors, success), discard — all through the screen's methods —
 * and the preview: the POST of the tree, a block chosen by a message from
 * the page, the selected block outlined in it.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import BlockTreeLayout from './BlockTreeLayout.vue'
import BlockPreview from './BlockPreview.vue'
import { useBlockTreeEditor, locateErrorKey, type BlockTreeEditor } from './useBlockTreeEditor'
import { fetchPreview, refFromMessage, withBase } from './preview'
import { makeRules, type BlockNode, type BlockTypeDef } from './tree'
import { provideFormState, type FormStateContext } from '../render/formState'
import { ScreenContextKey, type ScreenContext, type ScreenMethodCaller } from '../render/screenContext'
import { clearRegistry } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'
import { resolveConfirmDialog } from '../../composables/useConfirm'
import { ValidationError } from '../../api/errors'
import { adminToast, toastError } from '../../stores/toast'

vi.mock('../../stores/toast', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../../stores/toast')>()),
  toastError: vi.fn(),
  toastAlerts: vi.fn(() => 0),
  adminToast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
}))

vi.mock('./preview', async (importOriginal) => ({
  ...(await importOriginal<typeof import('./preview')>()),
  fetchPreview: vi.fn(async () => '<html><head></head><body><div data-builder-ref="1">Hero</div></body></html>'),
}))

const types: BlockTypeDef[] = [
  {
    type: 'hero',
    label: 'Hero',
    root: true,
    accepts: [],
    titleFrom: ['title'],
    fields: [{ kind: 'field', type: 'input', name: 'title', label: 'Title' }],
  },
  { type: 'columns', label: 'Columns', root: true, accepts: ['text'], fields: [] },
  { type: 'text', label: 'Text', accepts: [], titleFrom: ['body'], defaults: { body: 'New text' }, fields: [] },
]

function value(): Record<string, unknown> {
  return {
    nodes: [
      { ref: 1, type: 'hero', data: { title: 'Welcome' }, children: [] },
      { ref: 2, type: 'columns', data: {}, children: [{ ref: 3, type: 'text', data: { body: 'Hello' }, children: [] }] },
    ],
    fingerprint: 'fp-1',
  }
}

let form: FormStateContext
let editor: BlockTreeEditor
let wrapper: VueWrapper | null = null
let call: ReturnType<typeof vi.fn<ScreenMethodCaller>>

function mountEditor(
  props: { draftMethod?: string | null; publishMethod?: string | null; discardMethod?: string | null; autosaveDelay?: number } = {},
): void {
  const Editor = defineComponent({
    setup() {
      editor = useBlockTreeEditor({
        source: 'content',
        rules: () => makeRules(types, null, 3),
        draftMethod: () => props.draftMethod ?? 'saveDraft',
        publishMethod: () => props.publishMethod ?? 'publish',
        discardMethod: () => props.discardMethod ?? null,
        autosaveDelay: () => props.autosaveDelay ?? 1000,
        readOnly: () => false,
      })
      return () => h('div')
    },
  })
  const Host = defineComponent({
    setup() {
      form = provideFormState({ title: 'About', content: value() })
      return () => h(Editor)
    },
  })
  wrapper = mount(Host, { global: { provide: { [ScreenContextKey as symbol]: { call } as Partial<ScreenContext> } } })
}

const nodesOf = (): BlockNode[] => (form.getField('content') as { nodes: BlockNode[] }).nodes

beforeEach(() => {
  call = vi.fn<ScreenMethodCaller>(async () => ({}))
  vi.mocked(toastError).mockClear()
  vi.mocked(adminToast.success).mockClear()
  vi.mocked(adminToast.error).mockClear()
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  vi.useRealTimers()
})

describe('the structure', () => {
  it('adds a block from the palette into the screen state and selects it', () => {
    mountEditor()
    const node = editor.add(types[2], 2, 0)!
    expect(String(node.ref)).toMatch(/^new-/)
    expect(nodesOf()[1].children.map((n) => n.type)).toEqual(['text', 'text'])
    expect(nodesOf()[1].children[0].data).toEqual({ body: 'New text' })
    expect(editor.selectedRef.value).toBe(node.ref)
    expect(editor.diff.value.status.get(String(node.ref))).toBe('new')
    // Not allowed at the top level.
    expect(editor.add(types[2], null)).toBeNull()
  })

  it('moves only within the rules', () => {
    mountEditor()
    expect(editor.move(3, null, 0)).toBe(false)
    expect(editor.move(1, null, 2)).toBe(true)
    expect(nodesOf().map((n) => n.ref)).toEqual([2, 1])
  })

  it('removes a block and brings it back with undo', () => {
    mountEditor()
    editor.select(3)
    expect(editor.remove(3)).toBe(true)
    expect(nodesOf()[1].children).toEqual([])
    expect(editor.selectedRef.value).toBe(2)
    expect(editor.undoLabel.value).toContain('Text')
    expect(editor.diff.value.removed).toBe(1)
    expect(editor.undo()).toBe(true)
    expect(nodesOf()[1].children.map((n) => n.ref)).toEqual([3])
    expect(editor.selectedRef.value).toBe(3)
    expect(editor.diff.value.changes).toBe(0)
  })

  it('duplicates with new refs', () => {
    mountEditor()
    const copy = editor.duplicate(2)!
    expect(nodesOf().length).toBe(3)
    expect(copy.ref).not.toBe(2)
    expect(copy.children[0].ref).not.toBe(3)
    expect(copy.children[0].data).toEqual({ body: 'Hello' })
  })

  it('selects by a ref given as text, as a message from the page brings it', () => {
    mountEditor()
    expect(editor.select('3')).toBe(true)
    expect(editor.selectedRef.value).toBe(3)
    expect(editor.path.value.map((n) => n.ref)).toEqual([2, 3])
    expect(editor.select('404')).toBe(false)
  })
})

describe('the screen methods', () => {
  it('saves the draft a while after a change, with the whole state', async () => {
    vi.useFakeTimers()
    call.mockResolvedValueOnce({ extra: { blockTree: { savedAt: '2026-11-13T12:41:00+03:00' } } })
    mountEditor()
    nodesOf()[0].data!.title = 'Hi'
    await nextTick()
    expect(editor.status.value).toBe('dirty')
    expect(call).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(1000)
    expect(call).toHaveBeenCalledTimes(1)
    const [method, params] = call.mock.calls[0]
    expect(method).toBe('saveDraft')
    const state = (params as Record<string, unknown>[])[0]
    expect(state.title).toBe('About')
    expect((state.content as { nodes: BlockNode[]; fingerprint: string }).nodes[0].data).toEqual({ title: 'Hi' })
    expect((state.content as { fingerprint: string }).fingerprint).toBe('fp-1')
    expect(editor.status.value).toBe('saved')
    expect(editor.savedAt.value).toBe('2026-11-13T12:41:00+03:00')
    // The mark counts against the published tree, not the draft.
    expect(editor.diff.value.status.get('1')).toBe('changed')
  })

  it('shows a conflict of the fingerprint and publishes nothing', async () => {
    call.mockResolvedValueOnce({
      extra: { blockTree: { conflict: { message: 'Changed elsewhere', differences: [{ ref: 1, change: 'title' }], fingerprint: 'fp-2' } } },
    })
    mountEditor({ draftMethod: null })
    nodesOf()[0].data!.title = 'Hi'
    await nextTick()
    expect(await editor.publish()).toBe(false)
    expect(call).toHaveBeenCalledWith('publish', [expect.objectContaining({ content: expect.anything() })])
    expect(editor.conflict.value).toEqual({ message: 'Changed elsewhere', differences: [{ ref: 1, change: 'title' }], fingerprint: 'fp-2' })
    expect(editor.diff.value.changes).toBe(1)
  })

  it('marks the nodes the server refused, by ref and by path', async () => {
    call.mockResolvedValueOnce({
      extra: {
        blockTree: {
          errors: [
            { ref: 1, path: '0', field: 'title', message: 'The title is required.' },
            { ref: null, path: '1.children.0', field: null, message: 'Not allowed here' },
          ],
        },
      },
    })
    mountEditor()
    expect(await editor.publish()).toBe(false)
    expect(editor.errors.value.get('1')).toEqual({ messages: [], fields: { title: ['The title is required.'] } })
    expect(editor.errors.value.get('3')).toEqual({ messages: ['Not allowed here'], fields: {} })
    expect(adminToast.error).toHaveBeenCalled()
  })

  it('maps a ValidationError keyed by the tree path onto the nodes', async () => {
    call.mockRejectedValueOnce(
      new ValidationError({ errorKey: 'validation', message: 'Invalid', messages: { 'content.nodes.1.children.0.data.body': ['Too long'] } }),
    )
    mountEditor()
    expect(await editor.publish()).toBe(false)
    expect(editor.errors.value.get('3')).toEqual({ messages: [], fields: { body: ['Too long'] } })
    expect(locateErrorKey(nodesOf(), 'nodes.0')).toEqual({ ref: 1, field: null })
    expect(locateErrorKey(nodesOf(), 'title')).toBeNull()
  })

  it('goes on from the published value after a publish', async () => {
    const published = { nodes: [{ ref: 10, type: 'hero', data: { title: 'Hi' }, children: [] }], fingerprint: 'fp-3' }
    call.mockResolvedValueOnce({ message: 'Published', extra: { blockTree: { value: published } } })
    mountEditor()
    nodesOf()[0].data!.title = 'Hi'
    expect(await editor.publish()).toBe(true)
    expect(form.getField('content')).toMatchObject(published)
    expect(editor.diff.value.changes).toBe(0)
    expect(editor.status.value).toBe('clean')
    expect(adminToast.success).toHaveBeenCalledWith('Published')
  })

  it('discards through the screen method after a confirmation', async () => {
    call.mockResolvedValueOnce({ extra: { blockTree: { value: value() } } })
    mountEditor({ discardMethod: 'discardDraft' })
    editor.remove(1)
    const done = editor.discard()
    resolveConfirmDialog(true)
    expect(await done).toBe(true)
    expect(call).toHaveBeenCalledWith('discardDraft', [expect.any(Object)])
    expect(nodesOf().map((n) => n.ref)).toEqual([1, 2])
  })

  it('reverts locally when there is no discard method, and not without a confirmation', async () => {
    mountEditor({ draftMethod: null })
    editor.remove(1)
    const refused = editor.discard()
    resolveConfirmDialog(false)
    expect(await refused).toBe(false)
    expect(nodesOf().map((n) => n.ref)).toEqual([2])
    const done = editor.discard()
    resolveConfirmDialog(true)
    expect(await done).toBe(true)
    expect(nodesOf().map((n) => n.ref)).toEqual([1, 2])
    expect(call).not.toHaveBeenCalled()
  })
})

describe('the preview', () => {
  it('reads the message of the page only from its own frame', () => {
    const frame = document.createElement('iframe')
    document.body.appendChild(frame)
    const win = frame.contentWindow!
    const origin = window.location.origin
    expect(refFromMessage({ source: win, origin, data: { type: 'builder:select', ref: 15 } }, win)).toBe('15')
    expect(refFromMessage({ source: window, origin, data: { type: 'builder:select', ref: 15 } }, win)).toBeNull()
    expect(refFromMessage({ source: win, origin: 'https://evil.test', data: { type: 'builder:select', ref: 15 } }, win)).toBeNull()
    expect(refFromMessage({ source: win, origin, data: { type: 'other', ref: 15 } }, win)).toBeNull()
    frame.remove()
  })

  it('puts a base into the page unless it has one', () => {
    expect(withBase('<html><head><title>x</title></head></html>', 'https://site.test/')).toBe(
      '<html><head><base href="https://site.test/"><title>x</title></head></html>',
    )
    expect(withBase('<head><base href="/a/"></head>', 'https://site.test/')).toBe('<head><base href="/a/"></head>')
  })

  it('posts the tree with the params, a while after a change', async () => {
    vi.useFakeTimers()
    vi.mocked(fetchPreview).mockClear()
    const nodes = value().nodes as BlockNode[]
    const w = mount(BlockPreview, {
      props: { url: '/preview/page/3', params: { mode: 'page' }, nodes, selectedRef: null, delay: 500 },
      attachTo: document.body,
    })
    await flushPromises()
    expect(fetchPreview).toHaveBeenCalledTimes(1)
    expect(vi.mocked(fetchPreview).mock.calls[0][0]).toBe('/preview/page/3')
    expect(vi.mocked(fetchPreview).mock.calls[0][1]).toEqual({ mode: 'page', nodes })
    await w.setProps({ nodes: [...nodes].reverse() })
    await vi.advanceTimersByTimeAsync(400)
    expect(fetchPreview).toHaveBeenCalledTimes(1)
    await vi.advanceTimersByTimeAsync(200)
    expect(fetchPreview).toHaveBeenCalledTimes(2)
    w.unmount()
  })

  it('selects the node a message from the page names, and outlines the selected block', async () => {
    clearRegistry()
    registerBuiltinComponents()
    const Host = defineComponent({
      setup() {
        form = provideFormState({ content: value() })
        return () =>
          h(BlockTreeLayout, {
            source: 'content',
            types,
            previewUrl: '/preview/page/3',
            draftMethod: 'saveDraft',
            publishMethod: 'publish',
          })
      },
    })
    wrapper = mount(Host, {
      attachTo: document.body,
      global: { provide: { [ScreenContextKey as symbol]: { call } as Partial<ScreenContext> } },
    })
    await flushPromises()
    const frame = wrapper.find('iframe').element as HTMLIFrameElement
    const doc = frame.contentDocument!
    doc.body.innerHTML = '<div data-builder-ref="3" style="display: contents"><section id="text">Hello</section></div>'

    window.dispatchEvent(
      new MessageEvent('message', { data: { type: 'builder:select', ref: '3' }, origin: window.location.origin, source: frame.contentWindow }),
    )
    await flushPromises()

    // The properties show the path to the node.
    const crumbs = wrapper.findAll('.admin-block-tree__crumb').map((c) => c.text())
    expect(crumbs).toEqual(['Страница', 'Columns', 'Hello'])
    expect(wrapper.find('.admin-block-tree-node__row.is-selected').attributes('data-ref')).toBe('3')
    expect(doc.getElementById('text')!.hasAttribute('data-block-tree-active')).toBe(true)

    // A crumb selects the parent.
    await wrapper.findAll('.admin-block-tree__crumb')[1].trigger('click')
    expect(wrapper.find('.admin-block-tree-node__row.is-selected').attributes('data-ref')).toBe('2')
  })

  it('publishes from the bar and shows the conflict', async () => {
    call.mockResolvedValueOnce({ extra: { blockTree: { conflict: { message: 'Someone published', differences: ['Hero: title'] } } } })
    const Host = defineComponent({
      setup() {
        form = provideFormState({ content: value() })
        return () => h(BlockTreeLayout, { source: 'content', types, publishMethod: 'publish' })
      },
    })
    wrapper = mount(Host, { global: { provide: { [ScreenContextKey as symbol]: { call } as Partial<ScreenContext> } } })
    await wrapper.find('[data-action="publish"]').trigger('click')
    await flushPromises()
    expect(call).toHaveBeenCalledWith('publish', [expect.any(Object)])
    expect(wrapper.text()).toContain('Someone published')
    expect(wrapper.text()).toContain('Hero: title')
  })
})
