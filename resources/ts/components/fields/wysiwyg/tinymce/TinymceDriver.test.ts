/**
 * The TinyMCE driver against a stand-in for TinyMCE's global: it loads the
 * host's self-hosted copy by URL (no cloud, no API key, license_key 'gpl'),
 * points base_url at the script's folder, and stores getContent().
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import TinymceDriver from './TinymceDriver.vue'
import { loadTinymce, resetTinymceLoader, tinymceBaseUrl, type TinymceGlobal } from './loadTinymce'

type Handler = () => void

/** A TinyMCE editor stand-in: keeps the content and the handlers. */
function fakeTinymce() {
  const calls: Record<string, unknown>[] = []
  const handlers: Record<string, Handler[]> = {}
  let content = ''
  const editor = {
    getContent: () => content,
    setContent: (html: string) => {
      content = html
    },
    on(events: string, handler: Handler) {
      for (const e of events.split(' ')) (handlers[e] ??= []).push(handler)
    },
    mode: { set: vi.fn() },
    remove: vi.fn(),
  }
  const tinymce: TinymceGlobal = {
    async init(options) {
      calls.push(options)
      content = (options.target as HTMLTextAreaElement).value
      ;(options.setup as (ed: typeof editor) => void)(editor)
      handlers.init?.forEach(h => h())
      return [editor]
    },
  }
  const fire = (event: string, html?: string) => {
    if (html !== undefined) content = html
    handlers[event]?.forEach(h => h())
  }
  return { tinymce, editor, calls, fire }
}

let wrapper: VueWrapper | null = null

beforeEach(() => {
  resetTinymceLoader()
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  delete window.tinymce
  document.head.querySelectorAll('script').forEach(s => s.remove())
  vi.restoreAllMocks()
})

describe('loadTinymce', () => {
  it('derives base_url from the script URL', () => {
    expect(tinymceBaseUrl('/js/platform/tinymce/tinymce/tinymce.min.js?v=7')).toBe('/js/platform/tinymce/tinymce')
  })

  it('adds the script once and resolves with the global', async () => {
    const fake = fakeTinymce()
    const a = loadTinymce('/vendor/tinymce/tinymce.min.js')
    const b = loadTinymce('/vendor/tinymce/tinymce.min.js')
    expect(a).toBe(b)
    const scripts = document.head.querySelectorAll('script[src="/vendor/tinymce/tinymce.min.js"]')
    expect(scripts).toHaveLength(1)
    window.tinymce = fake.tinymce
    scripts[0].dispatchEvent(new Event('load'))
    await expect(a).resolves.toBe(fake.tinymce)
  })

  it('fails without a URL and without TinyMCE on the page', async () => {
    await expect(loadTinymce(null)).rejects.toThrow(/not configured/)
  })
})

describe('TinymceDriver', () => {
  it('starts the self-hosted TinyMCE with the GPL key and the field options', async () => {
    const fake = fakeTinymce()
    window.tinymce = fake.tinymce
    wrapper = mount(TinymceDriver, {
      props: {
        modelValue: '<p>Hi</p>',
        config: { script: '/js/tinymce/tinymce.min.js', height: 420, plugins: 'lists link', toolbar: 'bold italic', init: { content_css: false } },
      },
      attachTo: document.body,
    })
    await flushPromises()

    const options = fake.calls[0]
    expect(options).toMatchObject({
      license_key: 'gpl',
      base_url: '/js/tinymce',
      suffix: '.min',
      height: 420,
      plugins: 'lists link',
      toolbar: 'bold italic',
      convert_urls: false,
      content_css: false,
    })
    expect(options).not.toHaveProperty('api_key')
    expect(options.target).toBeInstanceOf(HTMLTextAreaElement)
    expect((options.target as HTMLTextAreaElement).value).toBe('<p>Hi</p>')
  })

  it('stores getContent() on a change, and nothing on load', async () => {
    const fake = fakeTinymce()
    window.tinymce = fake.tinymce
    wrapper = mount(TinymceDriver, { props: { modelValue: '<p>Hi</p>', config: {} }, attachTo: document.body })
    await flushPromises()
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()

    fake.fire('change', '<p>Hi <strong>there</strong></p>')
    expect(wrapper.emitted('update:modelValue')).toEqual([['<p>Hi <strong>there</strong></p>']])

    // An outside value goes into the editor, without an echo.
    await wrapper.setProps({ modelValue: '<p>Reset</p>' })
    expect(fake.editor.getContent()).toBe('<p>Reset</p>')
    fake.fire('input')
    expect(wrapper.emitted('update:modelValue')).toHaveLength(1)
  })

  it('removes the editor on unmount and follows disabled', async () => {
    const fake = fakeTinymce()
    window.tinymce = fake.tinymce
    wrapper = mount(TinymceDriver, { props: { modelValue: '', config: {} }, attachTo: document.body })
    await flushPromises()
    await wrapper.setProps({ disabled: true })
    expect(fake.editor.mode.set).toHaveBeenCalledWith('readonly')
    wrapper.unmount()
    wrapper = null
    expect(fake.editor.remove).toHaveBeenCalled()
  })

  it('without a script keeps the HTML editable as text, unchanged', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    wrapper = mount(TinymceDriver, { props: { modelValue: '<p>Keep</p>', config: {} }, attachTo: document.body })
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    const plain = wrapper.find('textarea.admin-tinymce__plain')
    expect((plain.element as HTMLTextAreaElement).value).toBe('<p>Keep</p>')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    await plain.setValue('<p>Edited</p>')
    expect(wrapper.emitted('update:modelValue')).toEqual([['<p>Edited</p>']])
  })
})
