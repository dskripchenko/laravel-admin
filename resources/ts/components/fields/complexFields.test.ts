import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
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

describe('complex fields (backlog: key_value / repeater / builder / relation_table)', () => {
  beforeEach(() => {
    clearRegistry()
    registerBuiltinComponents()
    ctx = null
  })

  it('key_value renders pairs from state and syncs edits back as an object', async () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: { meta: { color: 'red' } },
        node: { type: 'key_value', name: 'meta', label: 'Мета', keyLabel: 'К', valueLabel: 'З' },
      },
    })
    const inputs = wrapper.findAll('input')
    expect(inputs.length).toBeGreaterThanOrEqual(2)
    expect((inputs[0].element as HTMLInputElement).value).toBe('color')

    await inputs[1].setValue('blue')
    await inputs[1].trigger('blur')
    expect(ctx!.getField('meta')).toEqual({ color: 'blue' })

    // Добавление пары.
    await wrapper.find('button.admin-keyvalue__add').trigger('click')
    const after = wrapper.findAll('input')
    expect(after.length).toBeGreaterThanOrEqual(4)
  })

  it('repeater renders sub-forms per item and syncs nested edits', async () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: { lines: [{ title: 'A' }, { title: 'B' }] },
        node: {
          type: 'repeater',
          name: 'lines',
          label: 'Строки',
          fields: [{ kind: 'field', type: 'text', name: 'title', label: 'Заголовок' }],
        },
      },
    })
    const inputs = wrapper.findAll('input')
    expect(inputs.length).toBe(2)
    expect((inputs[0].element as HTMLInputElement).value).toBe('A')

    await inputs[1].setValue('B2')
    expect((ctx!.getField('lines') as Array<{ title: string }>)[1].title).toBe('B2')

    // Добавление item'а из defaultItem.
    await wrapper.find('button.admin-repeater__add').trigger('click')
    expect((ctx!.getField('lines') as unknown[]).length).toBe(3)
  })

  it('builder renders typed blocks and adds new ones from the catalog', async () => {
    const node = {
      type: 'builder',
      name: 'content',
      label: 'Контент',
      blocks: {
        hero: {
          type: 'hero',
          label: 'Hero',
          fields: [{ kind: 'field', type: 'text', name: 'title', label: 'Заголовок' }],
        },
      },
    }
    const wrapper = mount(Wrapper, {
      props: {
        initial: { content: [{ type: 'hero', data: { title: 'Привет' } }] },
        node,
      },
    })
    expect(wrapper.text()).toContain('Hero')
    const input = wrapper.find('input')
    expect((input.element as HTMLInputElement).value).toBe('Привет')

    await input.setValue('Обновлено')
    const state = ctx!.getField('content') as Array<{ type: string; data: { title: string } }>
    expect(state[0].data.title).toBe('Обновлено')
  })

  it('relation_table renders related rows with formatted cells', () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: {
          comments: [
            { id: 1, body: 'Привет', created_at: '2026-07-22 10:00:00', approved: true },
          ],
        },
        node: {
          type: 'relation_table',
          name: 'comments',
          label: 'Комментарии',
          columns: [
            { name: 'body', label: 'Текст' },
            { name: 'approved', label: 'Одобрен', preset: 'boolean' },
            { name: 'created_at', label: 'Создан', preset: 'date' },
          ],
        },
      },
    })
    expect(wrapper.text()).toContain('Привет')
    expect(wrapper.text()).toContain('Да')
    expect(wrapper.text()).toContain('22.07.2026')
  })

  it('registry covers all four backlog field types', () => {
    for (const t of ['key_value', 'repeater', 'builder', 'relation_table']) {
      const wrapper = mount(Wrapper, {
        props: { initial: {}, node: { type: t, name: 'x' } },
      })
      expect(wrapper.text()).not.toContain(t === 'x' ? '' : 'no-such')
      expect(wrapper.findComponent({ name: 'UnknownField' }).exists()).toBe(false)
    }
  })
})
