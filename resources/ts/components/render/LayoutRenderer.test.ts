import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import LayoutRenderer, { type LayoutNode } from './LayoutRenderer.vue'
import { clearRegistry } from './registry'
import { registerBuiltinComponents } from './builtin'
import { provideFormState } from './formState'

const Wrapper = defineComponent({
  props: { initial: { type: Object, default: () => ({}) }, node: { type: Object, required: true } },
  setup(props) {
    provideFormState(props.initial as Record<string, unknown>)
    return () =>
      h(LayoutRenderer, { node: props.node as unknown as LayoutNode })
  },
})

describe('LayoutRenderer', () => {
  beforeEach(() => {
    clearRegistry()
    registerBuiltinComponents()
  })

  it('renders rows-layout (UidStack) с дочерними field-узлами', () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: { title: 'A', body: 'B' },
        node: {
          type: 'rows',
          items: [
            { type: 'text', name: 'title', label: 'Заголовок' },
            { type: 'textarea', name: 'body', label: 'Текст' },
          ],
        },
      },
    })
    expect(wrapper.findAll('input')).toHaveLength(1)
    expect(wrapper.findAll('textarea')).toHaveLength(1)
  })

  it('renders nested layouts (section → columns → fields)', () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: {},
        node: {
          type: 'section',
          title: 'Раздел',
          items: [
            {
              type: 'columns',
              items: [
                { type: 'text', name: 'a', label: 'A', span: 6 },
                { type: 'text', name: 'b', label: 'B', span: 6 },
              ],
            },
          ],
        },
      },
    })
    expect(wrapper.find('.admin-section__title').text()).toBe('Раздел')
    expect(wrapper.findAll('input')).toHaveLength(2)
  })

  it('renders tabs and renders panels', () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: { a: '1', b: '2' },
        node: {
          type: 'tabs',
          items: [
            { label: 'First', items: [{ type: 'text', name: 'a', label: 'A' }] },
            { label: 'Second', items: [{ type: 'text', name: 'b', label: 'B' }] },
          ],
        },
      },
    })
    expect(wrapper.text()).toContain('First')
    expect(wrapper.text()).toContain('Second')
    // Хотя бы один input для активной вкладки.
    expect(wrapper.findAll('input').length).toBeGreaterThan(0)
  })

  it('renders UnknownField (UidAlert) for unknown type', () => {
    const wrapper = mount(Wrapper, {
      props: { initial: {}, node: { type: 'wat', name: 'x' } },
    })
    expect(wrapper.text()).toContain('wat')
  })

  it('explicit kind:field maps to field-registry only', () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: {},
        node: { type: 'rows', kind: 'field', name: 'x' },
      },
    })
    // type 'rows' зарегистрирован как layout, но kind:field форсит FieldRenderer
    // → нет field-компонента 'rows' → UnknownField fallback (UidAlert).
    expect(wrapper.text()).toContain('rows')
  })

  it('columns layout uses span prop for grid-column style', () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: {},
        node: {
          type: 'columns',
          items: [
            { type: 'text', name: 'a', label: 'A', span: 8 },
            { type: 'text', name: 'b', label: 'B', span: 4 },
          ],
        },
      },
    })
    const items = wrapper.findAll('.admin-columns__item')
    expect(items).toHaveLength(2)
    expect(items[0].attributes('style')).toContain('span 8')
    expect(items[1].attributes('style')).toContain('span 4')
  })
})
