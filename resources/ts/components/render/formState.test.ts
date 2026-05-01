import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { provideFormState, useFormState, tryUseFormState } from './formState'

const Provider = defineComponent({
  props: { initial: { type: Object, default: () => ({}) } },
  setup(props, { slots }) {
    provideFormState(props.initial as Record<string, unknown>)
    return () => h('div', slots.default?.())
  },
})

const Reader = defineComponent({
  setup() {
    const form = useFormState()
    return () =>
      h('div', { class: 'reader' }, [
        h('span', { class: 'name' }, String(form.getField('name') ?? '')),
        h('span', { class: 'errors' }, (form.errors.name ?? []).join(',')),
        h('button', {
          class: 'set',
          onClick: () => form.setField('name', 'Bob'),
        }, 'set'),
        h('button', {
          class: 'set-error',
          onClick: () => form.setError('name', ['required']),
        }, 'set-err'),
      ])
  },
})

const OptionalReader = defineComponent({
  setup() {
    const form = tryUseFormState()
    return () => h('div', { class: 'reader' }, form === null ? 'null' : 'present')
  },
})

describe('formState composable', () => {
  it('provides + reads via inject', () => {
    const wrapper = mount(Provider, {
      props: { initial: { name: 'Alice' } },
      slots: { default: () => h(Reader) },
    })
    expect(wrapper.find('.name').text()).toBe('Alice')
  })

  it('setField updates state and clears error', async () => {
    const wrapper = mount(Provider, {
      props: { initial: { name: 'X' } },
      slots: { default: () => h(Reader) },
    })

    await wrapper.find('.set-error').trigger('click')
    expect(wrapper.find('.errors').text()).toBe('required')

    await wrapper.find('.set').trigger('click')
    expect(wrapper.find('.name').text()).toBe('Bob')
    expect(wrapper.find('.errors').text()).toBe('')
  })

  it('useFormState throws outside provider', () => {
    expect(() => mount(Reader)).toThrow(/outside of provideFormState/)
  })

  it('tryUseFormState returns null outside provider', () => {
    const wrapper = mount(OptionalReader)
    expect(wrapper.find('.reader').text()).toBe('null')
  })

  it('setErrors replaces all errors', () => {
    let captured: ReturnType<typeof useFormState> | null = null
    const Captured = defineComponent({
      setup() {
        captured = useFormState()
        return () => h('div')
      },
    })
    mount(Provider, {
      slots: { default: () => h(Captured) },
    })
    expect(captured).not.toBeNull()
    const ctx = captured as unknown as ReturnType<typeof useFormState>
    ctx.setErrors({ a: ['x'], b: ['y'] })
    expect(ctx.errors).toEqual({ a: ['x'], b: ['y'] })
    ctx.setErrors({ a: ['z'] })
    expect(ctx.errors).toEqual({ a: ['z'] })
    ctx.clearErrors()
    expect(ctx.errors).toEqual({})
  })
})
