/**
 * Moving and removing the items of a repeater or a builder: each item's
 * sub-form must follow its item. With index keys and a sub-form that copied
 * its value once, a move rewrote the state while the inputs kept showing the
 * old order, and removing the first item showed the removed one's values.
 */
import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import FieldRenderer from '../render/FieldRenderer.vue'
import { provideFormState, type FormStateContext } from '../render/formState'
import { clearRegistry } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'

let ctx: FormStateContext | null = null

const Wrapper = defineComponent({
  props: {
    initial: { type: Object, default: () => ({}) },
    node: { type: Object, required: true },
  },
  setup(props) {
    ctx = provideFormState(props.initial as Record<string, unknown>)
    return () => h(FieldRenderer, { node: props.node as { type: string; name: string } })
  },
})

const titleField = { kind: 'field', type: 'text', name: 'title', label: 'Заголовок' }

function inputValues(wrapper: ReturnType<typeof mount>): string[] {
  return wrapper.findAll('input').map((i) => (i.element as HTMLInputElement).value)
}

describe('repeater and builder reordering', () => {
  beforeEach(() => {
    clearRegistry()
    registerBuiltinComponents()
    ctx = null
  })

  it('moves a repeater item down: the state and the inputs both follow', async () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: { lines: [{ title: 'A' }, { title: 'B' }, { title: 'C' }] },
        node: { type: 'repeater', name: 'lines', fields: [titleField] },
      },
    })
    await wrapper.findAll('.admin-repeater__item')[0]!.findAll('button')[1]!.trigger('click')
    await nextTick()
    expect(ctx!.getField('lines')).toEqual([{ title: 'B' }, { title: 'A' }, { title: 'C' }])
    expect(inputValues(wrapper)).toEqual(['B', 'A', 'C'])

    // And the moved item is still the same sub-form: editing it edits "A".
    await wrapper.findAll('input')[1]!.setValue('A2')
    await nextTick()
    expect(ctx!.getField('lines')).toEqual([{ title: 'B' }, { title: 'A2' }, { title: 'C' }])
  })

  it('moves a repeater item up', async () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: { lines: [{ title: 'A' }, { title: 'B' }] },
        node: { type: 'repeater', name: 'lines', fields: [titleField] },
      },
    })
    await wrapper.findAll('.admin-repeater__item')[1]!.findAll('button')[0]!.trigger('click')
    await nextTick()
    expect(ctx!.getField('lines')).toEqual([{ title: 'B' }, { title: 'A' }])
    expect(inputValues(wrapper)).toEqual(['B', 'A'])
  })

  it('removes the first repeater item without handing its values to the next', async () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: { lines: [{ title: 'A' }, { title: 'B' }] },
        node: { type: 'repeater', name: 'lines', fields: [titleField] },
      },
    })
    const first = wrapper.findAll('.admin-repeater__item')[0]!
    await first.findAll('button').at(-1)!.trigger('click')
    await nextTick()
    expect(ctx!.getField('lines')).toEqual([{ title: 'B' }])
    expect(inputValues(wrapper)).toEqual(['B'])
  })

  it('follows a value replaced from outside, a record reloaded', async () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: { lines: [{ title: 'A' }] },
        node: { type: 'repeater', name: 'lines', fields: [titleField] },
      },
    })
    ctx!.setField('lines', [{ title: 'Z' }])
    await nextTick()
    await nextTick()
    expect(inputValues(wrapper)).toEqual(['Z'])
  })

  it('moves a builder block down', async () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: { body: [{ type: 'text', data: { title: 'A' } }, { type: 'text', data: { title: 'B' } }] },
        node: {
          type: 'builder',
          name: 'body',
          blocks: { text: { type: 'text', label: 'Текст', fields: [titleField] } },
        },
      },
    })
    await wrapper.findAll('.admin-builder__block')[0]!.findAll('button')[1]!.trigger('click')
    await nextTick()
    expect(ctx!.getField('body')).toEqual([{ type: 'text', data: { title: 'B' } }, { type: 'text', data: { title: 'A' } }])
    expect(inputValues(wrapper)).toEqual(['B', 'A'])
  })
})
