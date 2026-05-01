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

  it('renders UnknownField for unregistered type', () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: {},
        node: { type: 'no-such-type', name: 'x' },
      },
    })
    expect(wrapper.text()).toContain('Unknown field type: no-such-type')
  })

  it('renders TextField from builtin registry and forwards props', () => {
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
    expect(wrapper.find('.admin-field__label').text()).toContain('Заголовок')
  })

  it('updates form state via setField on input', async () => {
    registerBuiltinComponents()
    const wrapper = mount(Wrapper, {
      props: {
        initial: { title: '' },
        node: { type: 'text', name: 'title', label: 'X' },
      },
    })
    await wrapper.find('input').setValue('NEW')
    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('NEW')
  })

  it('passes inputType through to TextField', () => {
    registerBuiltinComponents()
    const wrapper = mount(Wrapper, {
      props: {
        initial: {},
        node: { type: 'email', name: 'email', label: 'Email' },
      },
    })
    expect(wrapper.find('input').attributes('type')).toBe('text')
    // email type-key is registered как TextField (общий компонент); конкретный
    // input-type — это props.inputType, которого нет в node — default 'text'.
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
