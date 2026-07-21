import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import FieldRenderer from './FieldRenderer.vue'
import { clearRegistry, registerField } from './registry'
import { provideFormState } from './formState'
import { registerBuiltinComponents } from './builtin'

const Wrapper = defineComponent({
  props: { initial: { type: Object, default: () => ({}) }, node: { type: Object, required: true } },
  setup(props) {
    provideFormState(props.initial as Record<string, unknown>)
    return () => h(FieldRenderer, { node: props.node as { type: string; name: string } })
  },
})

describe('FieldRenderer', () => {
  beforeEach(() => {
    clearRegistry()
  })

  it('renders UnknownField for unregistered type (UidAlert внутри)', () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: {},
        node: { type: 'no-such-type', name: 'x' },
      },
    })
    // UnknownField теперь — UidAlert; ищем по тексту с типом
    expect(wrapper.text()).toContain('no-such-type')
  })

  it('renders TextField (UidInput) from builtin registry', () => {
    registerBuiltinComponents()
    const wrapper = mount(Wrapper, {
      props: {
        initial: { title: 'Hello' },
        node: { type: 'text', name: 'title', label: 'Заголовок' },
      },
    })
    const input = wrapper.find('input')
    expect(input.exists()).toBe(true)
    expect((input.element as HTMLInputElement).value).toBe('Hello')
  })

  it('passes inputType through (email)', () => {
    registerBuiltinComponents()
    const wrapper = mount(Wrapper, {
      props: {
        initial: {},
        node: { type: 'email', name: 'email', label: 'Email', inputType: 'email' },
      },
    })
    expect(wrapper.find('input').attributes('type')).toBe('email')
  })

  it('uses host-registered custom field', () => {
    const Custom = defineComponent({
      props: ['name', 'label'],
      template: '<div class="custom-field">{{ label }}</div>',
    })
    registerField('custom', Custom)
    const wrapper = mount(Wrapper, {
      props: {
        initial: {},
        node: { type: 'custom', name: 'x', label: 'Custom!' },
      },
    })
    expect(wrapper.find('.custom-field').text()).toBe('Custom!')
  })
})

describe('FieldRenderer visibility by form mode', () => {
  beforeEach(() => {
    clearRegistry()
  })

  const ModeWrapper = defineComponent({
    props: {
      node: { type: Object, required: true },
      mode: { type: String, default: undefined },
    },
    setup(props) {
      provideFormState({}, {}, props.mode as 'create' | 'update' | undefined)
      return () => h(FieldRenderer, { node: props.node as { type: string; name: string } })
    },
  })

  it('hides a field with visibility.update=false in update mode', () => {
    registerBuiltinComponents()
    const wrapper = mount(ModeWrapper, {
      props: {
        mode: 'update',
        node: { type: 'text', name: 'password', visibility: { create: true, update: false } },
      },
    })
    expect(wrapper.find('input').exists()).toBe(false)
  })

  it('shows the same field in create mode', () => {
    registerBuiltinComponents()
    const wrapper = mount(ModeWrapper, {
      props: {
        mode: 'create',
        node: { type: 'text', name: 'password', visibility: { create: true, update: false } },
      },
    })
    expect(wrapper.find('input').exists()).toBe(true)
  })

  it('renders everything when mode is not provided (BC)', () => {
    registerBuiltinComponents()
    const wrapper = mount(ModeWrapper, {
      props: {
        node: { type: 'text', name: 'password', visibility: { create: true, update: false } },
      },
    })
    expect(wrapper.find('input').exists()).toBe(true)
  })
})
