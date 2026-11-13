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
import { applyNodeUpdate, findNode as findNodeIn, makeRules, type BlockNode, type BlockPresetDef, type BlockTypeDef } from './tree'
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
    expect(editor.conflict.value).toEqual({ kind: 'publish', message: 'Changed elsewhere', differences: [{ ref: 1, change: 'title' }], fingerprint: 'fp-2' })
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

/* ---------------- 1.71: inherited fields, node rules, the draft's answers ---------------- */

/** The editor of the mounted layout (exposed for the tests). */
function findEditor(): BlockTreeEditor {
  return (wrapper!.findComponent(BlockTreeLayout).vm as unknown as { editor: BlockTreeEditor }).editor
}

function mountLayout(content: Record<string, unknown>, props: Record<string, unknown> = {}): void {
  clearRegistry()
  registerBuiltinComponents()
  const Host = defineComponent({
    setup() {
      form = provideFormState({ content })
      return () => h(BlockTreeLayout, { source: 'content', types, draftMethod: 'saveDraft', publishMethod: 'publish', ...props })
    },
  })
  wrapper = mount(Host, {
    attachTo: document.body,
    global: { provide: { [ScreenContextKey as symbol]: { call } as Partial<ScreenContext> } },
  })
}

describe('the inherited fields', () => {
  function inheriting(): Record<string, unknown> {
    const v = value()
    ;(v.nodes as BlockNode[])[0].meta = { fields: { title: { inherited: true, base: 'Welcome' } } }
    return v
  }

  it('marks a field inherited, overridden after an edit, and puts the base value back', async () => {
    mountLayout(inheriting(), { autosaveDelay: 0 })
    await wrapper!.find('.admin-block-tree-node__row[data-ref="1"]').trigger('click')
    const field = (): ReturnType<VueWrapper['find']> => wrapper!.find('.admin-block-tree-properties__field[data-field="title"]')
    expect(field().attributes('data-inheritance')).toBe('inherited')
    expect(field().text()).toContain('Унаследовано')
    expect(field().find('[data-action="revert-field"]').exists()).toBe(false)

    await field().find('input').setValue('Mine')
    expect(nodesOf()[0].data!.title).toBe('Mine')
    expect(field().attributes('data-inheritance')).toBe('overridden')
    expect(field().text()).toContain('Переопределено')
    const changed = wrapper!.find('.admin-block-tree-node__row[data-ref="1"]')
    expect(changed.text()).toContain('изменён')

    await field().find('[data-action="revert-field"]').trigger('click')
    expect(nodesOf()[0].data!.title).toBe('Welcome')
    expect((field().find('input').element as HTMLInputElement).value).toBe('Welcome')
    expect(field().attributes('data-inheritance')).toBe('inherited')
  })

  it('returns an override the server sent, and the node counts as changed', async () => {
    const v = value()
    ;(v.nodes as BlockNode[])[0].meta = { fields: { title: { inherited: false, base: 'Base title' } } }
    mountLayout(v, { autosaveDelay: 0 })
    await wrapper!.find('.admin-block-tree-node__row[data-ref="1"]').trigger('click')
    const field = wrapper!.find('.admin-block-tree-properties__field[data-field="title"]')
    expect(field.attributes('data-inheritance')).toBe('overridden')
    await field.find('[data-action="revert-field"]').trigger('click')
    expect(nodesOf()[0].data!.title).toBe('Base title')
    // The server sees the wish to inherit again.
    expect((nodesOf()[0].meta!.fields as Record<string, { inherited: boolean }>).title.inherited).toBe(true)
    expect(wrapper!.find('.admin-block-tree-node__row[data-ref="1"]').text()).toContain('изменён')
    expect(wrapper!.find('[data-action="undo"]').exists()).toBe(true)
  })
})

describe('the rules of a node', () => {
  it('offers in the palette only the types of meta.accepts, and nothing in a locked node', async () => {
    const v = value()
    v.nodes = [
      ...(v.nodes as BlockNode[]),
      { ref: 4, type: 'columns', data: {}, children: [], meta: { accepts: [] } },
      { ref: 5, type: 'columns', data: {}, children: [], meta: { locked: true } },
    ]
    mountLayout(v)
    const row = (ref: number): ReturnType<VueWrapper['find']> => wrapper!.find(`.admin-block-tree-node__row[data-ref="${ref}"]`)
    expect(row(2).find('[data-action="add-inside"]').exists()).toBe(true)
    expect(row(4).find('[data-action="add-inside"]').exists()).toBe(false)
    expect(row(5).find('[data-action="add-inside"]').exists()).toBe(false)
    // A locked node: no drag, no removal, a mark; its form is read-only.
    expect(row(5).attributes('draggable')).toBe('false')
    expect(row(5).find('[data-action="remove"]').exists()).toBe(false)
    expect(row(5).find('.admin-block-tree-mark.is-locked').exists()).toBe(true)
    await row(5).trigger('click')
    expect(wrapper!.find('.admin-block-tree-properties').text()).toContain('Блок закреплён: только просмотр')
    await row(5).trigger('keydown', { key: 'Delete' })
    expect(nodesOf().map((n) => n.ref)).toEqual([1, 2, 4, 5])
  })

  it('offers no drop zone the node rules forbid', async () => {
    const v = value()
    v.nodes = [
      ...(v.nodes as BlockNode[]),
      { ref: 4, type: 'columns', data: {}, children: [], meta: { accepts: [] } },
      { ref: 5, type: 'columns', data: {}, children: [], meta: { locked: true } },
    ]
    mountLayout(v)
    const row = (ref: number): ReturnType<VueWrapper['find']> => wrapper!.find(`.admin-block-tree-node__row[data-ref="${ref}"]`)
    const dataTransfer = { setData: vi.fn(), effectAllowed: '', dropEffect: '' }
    await row(3).trigger('dragstart', { dataTransfer })
    // Into the middle of a node: "inside" when allowed.
    const over = async (ref: number): Promise<void> => {
      const el = row(ref).element as HTMLElement
      el.getBoundingClientRect = () => ({ top: 0, height: 20, bottom: 20, left: 0, right: 100, width: 100, x: 0, y: 0, toJSON: () => ({}) })
      await row(ref).trigger('dragover', { clientY: 10, dataTransfer })
    }
    await over(2)
    expect(row(2).classes()).toContain('is-drop-inside')
    await over(4)
    expect(row(4).classes().some((c) => c.startsWith('is-drop-'))).toBe(false)
    await over(5)
    expect(row(5).classes().some((c) => c.startsWith('is-drop-'))).toBe(false)
    await row(5).trigger('drop', { dataTransfer })
    expect(nodesOf()[3].children).toEqual([])
    await row(3).trigger('dragend')
    // A locked node does not start a drag.
    const locked = new Event('dragstart', { cancelable: true })
    row(5).element.dispatchEvent(locked)
    expect(locked.defaultPrevented).toBe(true)
  })
})

describe('the draft answers', () => {
  it('keeps what was typed while the draft was saving, and renames the new refs (draftSaved)', async () => {
    vi.useFakeTimers()
    let answer!: (r: Record<string, unknown>) => void
    call.mockImplementationOnce(() => new Promise((resolve) => (answer = resolve)))
    mountEditor()
    const added = editor.add(types[2], 2, 1)!
    const temp = String(added.ref)
    await nextTick()
    await vi.advanceTimersByTimeAsync(1000)
    expect(call).toHaveBeenCalledTimes(1)
    expect(editor.status.value).toBe('saving')

    // Typed while the request is out.
    findNodeIn(nodesOf(), temp)!.data!.body = 'Typed meanwhile'
    nodesOf()[0].data!.title = 'Also meanwhile'
    await nextTick()

    answer({ extra: { blockTree: { refs: { [temp]: 51 }, fingerprint: 'fp-draft-2', savedAt: '2026-11-13T12:00:00+03:00' } } })
    await flushPromises()

    expect(nodesOf()[1].children.map((n) => n.ref)).toEqual([3, 51])
    expect(nodesOf()[1].children[1].data).toEqual({ body: 'Typed meanwhile' })
    expect(nodesOf()[0].data!.title).toBe('Also meanwhile')
    expect(editor.selectedRef.value).toBe(51)
    expect((form.getField('content') as { fingerprint: string }).fingerprint).toBe('fp-draft-2')
    expect(editor.savedAt.value).toBe('2026-11-13T12:00:00+03:00')
    // What was typed meanwhile is not saved yet: it goes in the next draft, under the new ref.
    expect(editor.status.value).toBe('dirty')
    call.mockResolvedValueOnce({ extra: { blockTree: { refs: {}, fingerprint: 'fp-draft-3' } } })
    await vi.advanceTimersByTimeAsync(1000)
    expect(call).toHaveBeenCalledTimes(2)
    const sent = (call.mock.calls[1][1] as Record<string, { nodes: BlockNode[]; fingerprint: string }>[])[0].content
    expect(sent.fingerprint).toBe('fp-draft-2')
    expect(sent.nodes[1].children[1]).toMatchObject({ ref: 51, data: { body: 'Typed meanwhile' } })
    expect(editor.status.value).toBe('saved')
    // An undo does not bring the temporary ref back.
    editor.undo()
    expect(JSON.stringify(nodesOf())).not.toContain(temp)
  })

  it('keeps the selected node, its form and the folded nodes when the refs are renamed', async () => {
    let answer!: (r: Record<string, unknown>) => void
    call.mockImplementationOnce(() => new Promise((resolve) => (answer = resolve)))
    mountLayout(value(), { autosaveDelay: 0 })
    await wrapper!.find('.admin-block-tree-node__row[data-ref="2"] [data-action="add-inside"]').trigger('click')
    await wrapper!.find('.admin-block-tree-palette__item[data-type="text"]').trigger('click')
    const temp = String(nodesOf()[1].children[1].ref)
    const props = wrapper!.findComponent({ name: 'BlockProperties' })
    // Fold the columns, then save.
    await wrapper!.find('.admin-block-tree-node__row[data-ref="2"] .admin-block-tree-node__toggle').trigger('click')
    const save = findEditor().saveDraft()
    answer({ extra: { blockTree: { refs: { [temp]: 77 }, fingerprint: 'fp-x' } } })
    await save
    await flushPromises()
    expect(findEditor().selectedRef.value).toBe(77)
    expect(wrapper!.findComponent({ name: 'BlockProperties' }).vm.$.uid).toBe(props.vm.$.uid)
    expect(wrapper!.find('.admin-block-tree-node__row[data-ref="2"]').attributes('aria-expanded')).toBe('false')
  })

  it('shows a conflict of the draft and pauses the autosave until saved over', async () => {
    vi.useFakeTimers()
    call.mockResolvedValueOnce({
      extra: { blockTree: { conflict: { message: 'Edited in another tab', differences: ['Hero: title'], fingerprint: 'fp-9' } } },
    })
    mountEditor()
    nodesOf()[0].data!.title = 'Hi'
    await nextTick()
    await vi.advanceTimersByTimeAsync(1000)
    expect(call).toHaveBeenCalledTimes(1)
    expect(editor.conflict.value).toMatchObject({ kind: 'draft', message: 'Edited in another tab', fingerprint: 'fp-9' })
    expect(editor.paused.value).toBe('conflict')
    expect(editor.status.value).toBe('error')
    // Nothing of the tree is lost, and more edits do not send it again.
    expect(nodesOf()[0].data!.title).toBe('Hi')
    nodesOf()[0].data!.title = 'Hi again'
    await nextTick()
    await vi.advanceTimersByTimeAsync(5000)
    expect(call).toHaveBeenCalledTimes(1)
    editor.dismissConflict()
    await vi.advanceTimersByTimeAsync(5000)
    expect(call).toHaveBeenCalledTimes(1)

    call.mockResolvedValueOnce({ extra: { blockTree: { refs: {}, fingerprint: 'fp-10' } } })
    await editor.saveOver()
    expect(call).toHaveBeenCalledTimes(2)
    const sent = (call.mock.calls[1][1] as Record<string, { fingerprint: string }>[])[0].content
    expect(sent.fingerprint).toBe('fp-9')
    expect(editor.paused.value).toBeNull()
    expect(editor.status.value).toBe('saved')
    expect((form.getField('content') as { fingerprint: string }).fingerprint).toBe('fp-10')
  })

  it('marks the errors of the draft and waits for the next change', async () => {
    vi.useFakeTimers()
    call.mockResolvedValueOnce({
      extra: { blockTree: { errors: [{ ref: 1, path: '0', field: 'title', message: 'Too long' }], message: 'Fix it' } },
    })
    mountEditor()
    nodesOf()[0].data!.title = 'x'.repeat(50)
    await nextTick()
    await vi.advanceTimersByTimeAsync(1000)
    expect(editor.errors.value.get('1')).toEqual({ messages: [], fields: { title: ['Too long'] } })
    expect(editor.paused.value).toBe('invalid')
    expect(adminToast.error).toHaveBeenCalledWith('Fix it')
    await vi.advanceTimersByTimeAsync(5000)
    expect(call).toHaveBeenCalledTimes(1)

    // The next change resumes it; a saved draft clears the draft's errors.
    call.mockResolvedValueOnce({ extra: { blockTree: { refs: {}, fingerprint: 'fp-2' } } })
    nodesOf()[0].data!.title = 'Short'
    await nextTick()
    expect(editor.paused.value).toBeNull()
    await vi.advanceTimersByTimeAsync(1000)
    expect(call).toHaveBeenCalledTimes(2)
    expect(editor.errors.value.size).toBe(0)
    expect(editor.status.value).toBe('saved')
  })

  it('maps a ValidationError of the draft onto the nodes, and pauses', async () => {
    vi.useFakeTimers()
    call.mockRejectedValueOnce(
      new ValidationError({ errorKey: 'validation', message: 'Invalid', messages: { 'content.nodes.1.children.0': ['Not here'] } }),
    )
    mountEditor()
    nodesOf()[0].data!.title = 'Hi'
    await nextTick()
    await vi.advanceTimersByTimeAsync(1000)
    expect(editor.errors.value.get('3')).toEqual({ messages: ['Not here'], fields: {} })
    expect(editor.paused.value).toBe('invalid')
    expect(toastError).not.toHaveBeenCalled()
  })

  it('shows the draft conflict in the layout with "Save over"', async () => {
    call.mockResolvedValueOnce({ extra: { blockTree: { conflict: { message: 'Draft changed elsewhere', differences: [], fingerprint: 'fp-5' } } } })
    mountLayout(value(), { autosaveDelay: 0 })
    nodesOf()[0].data!.title = 'Hi'
    await nextTick()
    await findEditor().saveDraft()
    await flushPromises()
    expect(wrapper!.text()).toContain('Draft changed elsewhere')
    expect(wrapper!.find('.admin-block-tree__status').text()).toContain('автосохранение приостановлено')
    call.mockResolvedValueOnce({ extra: { blockTree: { refs: {}, fingerprint: 'fp-6' } } })
    await wrapper!.find('[data-action="save-over"]').trigger('click')
    await flushPromises()
    expect(call).toHaveBeenCalledTimes(2)
    expect(wrapper!.find('[data-action="save-over"]').exists()).toBe(false)
  })
})

describe('the palette presets', () => {
  const presets: BlockPresetDef[] = [
    {
      key: 'library-42',
      label: 'Footer (shared)',
      type: 'text',
      group: 'Shared blocks',
      data: { body: '<p>©</p>' },
      meta: { library: 42, fields: { body: { inherited: true, base: '<p>©</p>' } } },
    },
    { key: 'hero-plain', label: 'Plain hero', type: 'hero', data: { title: 'Hello' }, meta: { accepts: [] } },
    // Its child is not allowed inside columns: never offered.
    { key: 'bad-columns', label: 'Bad columns', type: 'columns', children: [{ type: 'hero' }] },
    { key: 'cta', label: 'Call to action', type: 'columns', data: {}, children: [{ type: 'text', data: { body: 'Buy' } }] },
  ]

  it('inserts a node of the preset type with its data and meta, under a new ref', async () => {
    mountLayout(value(), { presets, autosaveDelay: 0 })
    await wrapper!.find('[data-testid="block-tree-node-2"] [data-testid="block-tree-node-add-inside"]').trigger('click')
    const palette = wrapper!.find('[data-testid="block-tree-palette"]')
    // Inside the columns: the type and the presets of type text; not the hero ones.
    expect(palette.find('[data-testid="block-tree-palette-type-text"]').exists()).toBe(true)
    expect(palette.find('[data-testid="block-tree-palette-preset-library-42"]').exists()).toBe(true)
    expect(palette.find('[data-testid="block-tree-palette-preset-hero-plain"]').exists()).toBe(false)
    expect(palette.text()).toContain('Shared blocks')

    await palette.find('[data-testid="block-tree-palette-preset-library-42"]').trigger('click')
    const node = nodesOf()[1].children[1]
    expect(String(node.ref)).toMatch(/^new-/)
    expect(node.type).toBe('text')
    expect(node.data).toEqual({ body: '<p>©</p>' })
    expect(node.meta).toEqual({ library: 42, fields: { body: { inherited: true, base: '<p>©</p>' } } })
    expect(findEditor().selectedRef.value).toBe(node.ref)
    expect(findEditor().diff.value.status.get(String(node.ref))).toBe('new')

    // A second pick is a copy of its own, not the same object.
    await wrapper!.find('[data-testid="block-tree-node-2"] [data-testid="block-tree-node-add-inside"]').trigger('click')
    await wrapper!.find('[data-testid="block-tree-palette-preset-library-42"]').trigger('click')
    const second = nodesOf()[1].children[2]
    expect(second.ref).not.toBe(node.ref)
    second.data!.body = 'Changed'
    expect(node.data!.body).toBe('<p>©</p>')
    expect(presets[0].data!.body).toBe('<p>©</p>')
  })

  it('follows the type rules and meta.accepts, children included', async () => {
    const v = value()
    v.nodes = [...(v.nodes as BlockNode[]), { ref: 4, type: 'columns', data: {}, children: [], meta: { accepts: [] } }]
    mountLayout(v, { presets })
    // At the top level: the root presets whose children fit.
    await wrapper!.find('[data-testid="block-tree-add"]').trigger('click')
    const keys = wrapper!.findAll('.admin-block-tree-palette__item.is-preset').map((b) => b.attributes('data-preset'))
    expect(keys).toEqual(['hero-plain', 'cta'])
    await wrapper!.find('[data-testid="block-tree-palette-close"]').trigger('click')
    // meta.accepts = [] on the node: nothing goes in, presets neither.
    expect(wrapper!.find('[data-testid="block-tree-node-4"] [data-testid="block-tree-node-add-inside"]').exists()).toBe(false)

    const editor = findEditor()
    expect(editor.addPreset(presets[0], 4)).toBeNull()
    expect(editor.addPreset(presets[0], null)).toBeNull()
    expect(editor.addPreset(presets[2], null)).toBeNull()
    const cta = editor.addPreset(presets[3], null)!
    expect(cta.children.map((c) => [c.type, c.data])).toEqual([['text', { body: 'Buy' }]])
    expect(String(cta.children[0].ref)).toMatch(/^new-/)
    // A preset's meta.accepts rules its own inside: the plain hero takes nothing.
    const hero = editor.addPreset(presets[1], null)!
    expect(editor.add(types[2], hero.ref)).toBeNull()
    // Undo takes the inserted preset away.
    expect(editor.undo()).toBe(true)
    expect(nodesOf().map((n) => n.type)).toEqual(['hero', 'columns', 'columns', 'columns'])
  })
})

describe('the draft answer with the nodes', () => {
  it('applies the server data and meta, keeps the fields typed meanwhile, and the form keeps its focus', async () => {
    let answer!: (r: Record<string, unknown>) => void
    call.mockImplementationOnce(() => new Promise((resolve) => (answer = resolve)))
    const v = value()
    ;(v.nodes as BlockNode[])[0].data = { title: 'Welcome', image: 'tmp/upload-1.png', tone: 'light' }
    mountLayout(v, { autosaveDelay: 0 })
    await wrapper!.find('[data-testid="block-tree-node-1"]').trigger('click')
    const props = wrapper!.findComponent({ name: 'BlockProperties' })
    const input = (): HTMLInputElement => wrapper!.find('[data-field="title"] input').element as HTMLInputElement

    // A change goes in the draft…
    nodesOf()[0].data!.tone = 'dark'
    await nextTick()
    const save = findEditor().saveDraft()
    // …and the title is typed while the request is out.
    input().focus()
    await wrapper!.find('[data-field="title"] input').setValue('Typed meanwhile')
    expect(document.activeElement).toBe(input())

    answer({
      extra: {
        blockTree: {
          refs: {},
          fingerprint: 'fp-n',
          nodes: {
            1: {
              data: { title: 'Server title', image: '/storage/blocks/1.png', tone: 'dark' },
              meta: { library: 42, title: 'From library' },
            },
          },
        },
      },
    })
    await save
    await flushPromises()

    const node = nodesOf()[0]
    // The server's fields; the typed one stays; a field the server left out would go.
    expect(node.data).toEqual({ title: 'Typed meanwhile', image: '/storage/blocks/1.png', tone: 'dark' })
    expect(node.meta).toEqual({ library: 42, title: 'From library' })
    // The form is the same, the input still focused and showing the input.
    expect(wrapper!.findComponent({ name: 'BlockProperties' }).vm.$.uid).toBe(props.vm.$.uid)
    expect(document.activeElement).toBe(input())
    expect(input().value).toBe('Typed meanwhile')
    // The typed title is not saved yet.
    expect(findEditor().status.value).toBe('dirty')
    expect(wrapper!.find('[data-testid="block-tree-node-1"]').text()).toContain('изменён')

    // The next draft sends it; the answer without nodes leaves the data alone.
    call.mockResolvedValueOnce({ extra: { blockTree: { refs: {}, fingerprint: 'fp-n2' } } })
    await findEditor().saveDraft()
    await flushPromises()
    const sent = (call.mock.calls[1][1] as Record<string, { nodes: BlockNode[] }>[])[0].content
    expect(sent.nodes[0].data).toEqual({ title: 'Typed meanwhile', image: '/storage/blocks/1.png', tone: 'dark' })
    expect(sent.nodes[0].meta).toEqual({ library: 42, title: 'From library' })
    expect(findEditor().status.value).toBe('saved')
  })

  it('finds a renamed node by its new ref or its temporary one, and drops the fields the server left out', async () => {
    vi.useFakeTimers()
    mountEditor()
    const added = editor.addPreset({ key: 'p', label: 'P', type: 'text', data: { body: 'x', upload: 'tmp/1' }, meta: { preset: 'p' } }, 2)!
    const other = editor.add(types[2], 2)!
    const temp = String(added.ref)
    const temp2 = String(other.ref)
    call.mockResolvedValueOnce({
      extra: {
        blockTree: {
          refs: { [temp]: 51, [temp2]: 52 },
          fingerprint: 'fp-r',
          nodes: {
            51: { data: { body: 'x', image: '/storage/1.png' }, meta: { library: 7 } },
            [temp2]: { meta: { title: 'Second' } },
          },
        },
      },
    })
    await editor.saveDraft()
    await flushPromises()
    const children = nodesOf()[1].children
    expect(children.map((n) => n.ref)).toEqual([3, 51, 52])
    expect(children[1].data).toEqual({ body: 'x', image: '/storage/1.png' })
    expect(children[1].meta).toEqual({ library: 7 })
    expect(children[2].data).toEqual({ body: 'New text' })
    expect(children[2].meta).toEqual({ title: 'Second' })
    expect(editor.status.value).toBe('saved')
  })

  it('keeps the meta.fields entry of a field reverted while the request was out', () => {
    const current: BlockNode = {
      ref: 1,
      type: 'hero',
      data: { title: 'Base' },
      children: [],
      meta: { fields: { title: { inherited: true, base: 'Base' } } },
    }
    const sent: BlockNode = { ...JSON.parse(JSON.stringify(current)), data: { title: 'Mine' } }
    const kept = applyNodeUpdate(current, sent, {
      data: { title: 'Mine' },
      meta: { fields: { title: { inherited: false, base: 'Base' } }, library: 1 },
    })
    expect(kept).toEqual(['title'])
    expect(current.data).toEqual({ title: 'Base' })
    expect(current.meta).toEqual({ fields: { title: { inherited: true, base: 'Base' } }, library: 1 })
    expect(sent.meta).toEqual({ fields: { title: { inherited: false, base: 'Base' } }, library: 1 })
  })
})
