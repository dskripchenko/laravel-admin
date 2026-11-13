/**
 * The Quill driver against the real Quill: the HTML it stores is the HTML
 * Orchid's Quill field stores, a stored value loads without loss and without
 * dirtying the form, and an edit emits the editor root's innerHTML.
 *
 * Orchid 14's field (resources/js/controllers/quill_controller.js) bundles
 * Quill 2.0.3: it puts the value into `editor.root.innerHTML` and saves
 * `.ql-editor`'s innerHTML on text-change. orchidSave() below repeats those
 * steps, so each case compares the two editors on the same input.
 */
import { afterEach, describe, expect, it } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import Quill from 'quill'
import quillPackage from 'quill/package.json'
import QuillDriver from './QuillDriver.vue'
import { quillToolbar, QUILL_TOOLBAR_GROUPS } from './toolbar'

/** Quill as Orchid 14 ships it (vendor/orchid/platform/public/js/vendor.js: `static version="2.0.3"`). */
const ORCHID_QUILL = '2.0.3'

/** Quill 2's own markup: what Orchid has been saving. */
const NATIVE = [
  '<p>Hello <strong>world</strong>, <em>italic</em> <u>under</u> <s>strike</s></p>',
  '<ol><li data-list="bullet"><span class="ql-ui" contenteditable="false"></span>one</li><li data-list="ordered"><span class="ql-ui" contenteditable="false"></span>two</li></ol>',
  '<p class="ql-align-center">centred</p><p><span style="color: rgb(230, 0, 0);">red</span> <span style="background-color: rgb(255, 255, 0);">mark</span></p>',
  '<h1>Title</h1><h2>Sub</h2><blockquote>quote</blockquote>',
  '<p>a&nbsp;b  c</p><p><br></p><p class="ql-indent-1">indented</p>',
  '<p><a href="https://example.com" rel="noopener noreferrer" target="_blank">link</a><img src="/storage/a.png"></p>',
  '<div class="ql-code-block-container" spellcheck="false"><div class="ql-code-block" data-language="plain">code</div></div>',
]

let wrapper: VueWrapper | null = null

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  document.body.innerHTML = ''
})

function mountDriver(value: string, config: Record<string, unknown> = {}) {
  wrapper = mount(QuillDriver, { props: { modelValue: value, config }, attachTo: document.body })
  const editor = (wrapper.vm as unknown as { editor: () => Quill }).editor()
  return { wrapper, editor }
}

/** Orchid's quill_controller: the value into the root, the root's HTML out on an edit. */
function orchidSave(value: string, edit: (q: Quill) => void): string {
  const el = document.createElement('div')
  document.body.appendChild(el)
  const q = new Quill(el, { theme: 'snow', modules: { toolbar: { container: quillToolbar(undefined) } } })
  q.root.innerHTML = value
  let saved = value
  q.on('text-change', () => {
    saved = el.querySelector('.ql-editor')?.innerHTML || ''
  })
  q.update()
  edit(q)
  return saved
}

describe('QuillDriver — HTML compatible with Orchid', () => {
  it('runs the same Quill version as Orchid 14', () => {
    expect(quillPackage.version).toBe(ORCHID_QUILL)
  })

  it.each(NATIVE)('loads %s without loss and without an update', async (html) => {
    const { wrapper, editor } = mountDriver(html)
    await flushPromises()
    expect(editor.root.innerHTML).toBe(html)
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it.each(NATIVE)('saves an edit of %s as Orchid does', async (html) => {
    const edit = (q: Quill) => q.insertText(0, 'X', 'user')
    const { wrapper, editor } = mountDriver(html)
    edit(editor)
    await flushPromises()
    const emitted = wrapper.emitted('update:modelValue') as string[][]
    expect(emitted.at(-1)?.[0]).toBe(orchidSave(html, edit))
    expect(emitted.at(-1)?.[0]).toBe(editor.root.innerHTML)
  })

  it('keeps formatting made in the editor in Quill markup', async () => {
    const { wrapper, editor } = mountDriver('<p><br></p>')
    editor.insertText(0, 'item', 'user')
    editor.formatLine(0, 1, 'list', 'bullet', 'user')
    await flushPromises()
    const html = (wrapper.emitted('update:modelValue') as string[][]).at(-1)?.[0]
    expect(html).toBe('<ol><li data-list="bullet"><span class="ql-ui" contenteditable="false"></span>item</li></ol>')
  })

  it('converts legacy markup Quill 2 has no blot for, instead of dropping its text', async () => {
    // Quill 1 (Orchid before Quill 2) stored <ul>/<ol> and <pre class="ql-syntax">;
    // straight into the root, Quill 2 drops them with their text.
    const legacy = '<ul><li>one</li><li>two</li></ul><pre class="ql-syntax" spellcheck="false">code\n</pre><div>block</div>'
    const { wrapper, editor } = mountDriver(legacy)
    await flushPromises()
    expect(editor.getText()).toBe('one\ntwo\ncode\nblock\n')
    expect(editor.root.innerHTML).toContain('<li data-list="bullet"><span class="ql-ui" contenteditable="false"></span>one</li>')
    // Still nothing is written back until the user edits.
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('loads a new value from outside and ignores its own echo', async () => {
    const { wrapper, editor } = mountDriver('<p>one</p>')
    await wrapper.setProps({ modelValue: '<p>two</p>' })
    expect(editor.root.innerHTML).toBe('<p>two</p>')
    editor.insertText(0, 'A', 'user')
    const html = (wrapper.emitted('update:modelValue') as string[][]).at(-1)?.[0] as string
    await wrapper.setProps({ modelValue: html })
    expect(editor.root.innerHTML).toBe('<p>Atwo</p>')
  })

  it("stores a Delta's JSON with output 'delta'", async () => {
    const { wrapper, editor } = mountDriver(JSON.stringify({ ops: [{ insert: 'Hi\n' }] }), { output: 'delta' })
    expect(editor.getText()).toBe('Hi\n')
    editor.insertText(2, '!', 'user')
    const json = (wrapper.emitted('update:modelValue') as string[][]).at(-1)?.[0] as string
    expect(JSON.parse(json)).toEqual({ ops: [{ insert: 'Hi!\n' }] })
  })

  it("builds Orchid's toolbar from group names and passes a Quill toolbar through", () => {
    expect(quillToolbar(undefined)).toHaveLength(7)
    expect(quillToolbar(['text', 'list'])).toEqual([QUILL_TOOLBAR_GROUPS.text, QUILL_TOOLBAR_GROUPS.list])
    const own = [['bold', 'italic'], ['link']]
    expect(quillToolbar(own)).toBe(own)
  })

  it('is read-only when disabled', () => {
    wrapper = mount(QuillDriver, { props: { modelValue: '<p>x</p>', disabled: true }, attachTo: document.body })
    const editor = (wrapper.vm as unknown as { editor: () => Quill }).editor()
    expect(editor.isEnabled()).toBe(false)
  })
})
