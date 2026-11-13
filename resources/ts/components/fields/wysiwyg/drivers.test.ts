/**
 * The Wysiwyg field's engines: WysiwygField picks the engine by its `driver`
 * attribute, an engine is loaded only when a field asks for it, and the
 * prebuilt application's Quill and TinyMCE are dynamic imports — their
 * modules are not evaluated until a field needs them.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h, type PropType } from 'vue'
import { provideFormState, type FormStateContext } from '../../render/formState'
import { clearWysiwygDrivers, hasWysiwygDriver, registerWysiwygDriver, resolveWysiwygDriver } from './drivers'

// The default editor, as a stand-in: it is not what these tests are about.
vi.mock('@dskripchenko/wysiwyg', () => ({
  DskWysiwyg: defineComponent({
    name: 'DskWysiwyg',
    props: ['modelValue'],
    setup: (p) => () => h('div', { class: 'stub-default' }, String(p.modelValue ?? '')),
  }),
}))

// The prebuilt application's drivers, counted: a factory runs when its module
// is first imported, so the counts show what was actually loaded.
const evaluated = vi.hoisted(() => ({ quill: 0, quillCss: 0, tinymce: 0 }))
vi.mock('./quill/QuillDriver.vue', async () => {
  evaluated.quill++
  const { defineComponent: dc, h: hh } = await import('vue')
  return { default: dc({ name: 'QuillDriver', setup: () => () => hh('div', { class: 'stub-quill' }) }) }
})
vi.mock('quill/dist/quill.snow.css', () => {
  evaluated.quillCss++
  return {}
})
vi.mock('./tinymce/TinymceDriver.vue', async () => {
  evaluated.tinymce++
  const { defineComponent: dc, h: hh } = await import('vue')
  return { default: dc({ name: 'TinymceDriver', setup: () => () => hh('div', { class: 'stub-tinymce' }) }) }
})

import WysiwygField from '../WysiwygField.vue'
import { registerBuiltinWysiwygDrivers } from './builtinDrivers'

/** A driver stand-in: shows what it was given and emits on demand. */
const seen: { props?: Record<string, unknown> } = {}
const DriverStub = defineComponent({
  name: 'DriverStub',
  props: {
    modelValue: { type: String, default: '' },
    config: { type: Object as PropType<Record<string, unknown>>, default: () => ({}) },
    placeholder: { type: String, default: undefined },
    disabled: Boolean,
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    return () => {
      seen.props = { ...props }
      return h('button', { class: 'stub-driver', onClick: () => emit('update:modelValue', '<p>edited</p>') })
    }
  },
})

let wrapper: VueWrapper | null = null
let form: FormStateContext

function mountField(props: Record<string, unknown>, state: Record<string, unknown> = { body: '<p>Hello</p>' }): VueWrapper {
  const Host = defineComponent({
    setup() {
      form = provideFormState(state)
      return () => h(WysiwygField, { name: 'body', ...props })
    },
  })
  wrapper = mount(Host)
  return wrapper
}

beforeEach(() => {
  clearWysiwygDrivers()
  seen.props = undefined
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  vi.restoreAllMocks()
})

describe('the driver registry', () => {
  it('knows only what was registered, and makes one async component per engine', () => {
    expect(resolveWysiwygDriver('quill')).toBeNull()
    registerWysiwygDriver('quill', async () => DriverStub)
    expect(hasWysiwygDriver('quill')).toBe(true)
    const a = resolveWysiwygDriver('quill')
    expect(a).not.toBeNull()
    expect(resolveWysiwygDriver('quill')).toBe(a)
  })
})

describe('WysiwygField picks the engine by the driver attribute', () => {
  it('draws the default editor without a driver, and loads no engine', async () => {
    const loader = vi.fn(async () => DriverStub)
    registerWysiwygDriver('quill', loader)
    const w = mountField({})
    await flushPromises()
    expect(w.find('.stub-default').exists()).toBe(true)
    expect(loader).not.toHaveBeenCalled()

    wrapper?.unmount()
    const w2 = mountField({ driver: 'default' })
    await flushPromises()
    expect(w2.find('.stub-default').exists()).toBe(true)
    expect(loader).not.toHaveBeenCalled()
  })

  it('loads the named engine on first use and hands it the value and the config', async () => {
    const loader = vi.fn(async () => DriverStub)
    registerWysiwygDriver('quill', loader)
    const w = mountField({ driver: 'quill', driverConfig: { output: 'html', toolbar: ['text'] }, placeholder: 'Type' })
    await flushPromises()

    expect(loader).toHaveBeenCalledTimes(1)
    expect(w.find('.stub-default').exists()).toBe(false)
    expect(w.find('.admin-wysiwyg-driver').attributes('data-driver')).toBe('quill')
    expect(seen.props).toMatchObject({
      modelValue: '<p>Hello</p>',
      config: { output: 'html', toolbar: ['text'] },
      placeholder: 'Type',
      disabled: false,
    })

    await w.find('.stub-driver').trigger('click')
    expect(form.getField('body')).toBe('<p>edited</p>')
  })

  it('falls back to the default editor, with a warning, for an engine nobody registered', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    const w = mountField({ driver: 'ckeditor' })
    await flushPromises()
    expect(w.find('.stub-default').exists()).toBe(true)
    expect(warn).toHaveBeenCalledWith(expect.stringContaining('"ckeditor"'))
  })

  it('shows an error when the engine fails to load', async () => {
    vi.spyOn(console, 'warn').mockImplementation(() => {})
    vi.spyOn(console, 'error').mockImplementation(() => {})
    registerWysiwygDriver('quill', () => Promise.reject(new Error('chunk failed')))
    const w = mountField({ driver: 'quill' })
    await flushPromises()
    expect(w.find('[role="alert"]').exists()).toBe(true)
  })
})

describe('the prebuilt application registers Quill and TinyMCE lazily', () => {
  it('evaluates no engine module until a field asks for it', async () => {
    registerBuiltinWysiwygDrivers()
    expect(hasWysiwygDriver('quill')).toBe(true)
    expect(hasWysiwygDriver('tinymce')).toBe(true)
    expect(evaluated).toEqual({ quill: 0, quillCss: 0, tinymce: 0 })

    const w = mountField({ driver: 'quill' })
    await flushPromises()
    expect(w.find('.stub-quill').exists()).toBe(true)
    expect(evaluated).toEqual({ quill: 1, quillCss: 1, tinymce: 0 })

    wrapper?.unmount()
    const w2 = mountField({ driver: 'tinymce' })
    await flushPromises()
    expect(w2.find('.stub-tinymce').exists()).toBe(true)
    expect(evaluated).toEqual({ quill: 1, quillCss: 1, tinymce: 1 })
  })
})
