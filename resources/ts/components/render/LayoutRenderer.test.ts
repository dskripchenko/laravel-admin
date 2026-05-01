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

  it('renders rows with field children', () => {
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
    expect(wrapper.find('.admin-layout-rows').exists()).toBe(true)
  })

  it('renders nested layouts', () => {
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
    expect(wrapper.find('.admin-layout-section__title').text()).toBe('Раздел')
    expect(wrapper.findAll('.admin-layout-columns__item')).toHaveLength(2)
    expect(wrapper.findAll('input')).toHaveLength(2)
  })

  it('renders tabs and switches active panel', async () => {
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
    expect(wrapper.find('.admin-field__label').text()).toContain('A')

    const tabs = wrapper.findAll('.admin-layout-tabs__tab')
    await tabs[1].trigger('click')
    expect(wrapper.find('.admin-field__label').text()).toContain('B')
  })

  it('renders UnknownField for unknown type', () => {
    const wrapper = mount(Wrapper, {
      props: { initial: {}, node: { type: 'wat', name: 'x' } },
    })
    expect(wrapper.text()).toContain('Unknown field type: wat')
  })

  it('explicit kind:layout with unknown type → unknown', () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: {},
        node: { type: 'mystery', kind: 'layout', name: 'x' },
      },
    })
    expect(wrapper.text()).toContain('Unknown field type: mystery')
  })

  it('explicit kind:field even if type matches layout-registry', () => {
    const wrapper = mount(Wrapper, {
      props: {
        initial: {},
        node: { type: 'rows', kind: 'field', name: 'x' },
      },
    })
    // type 'rows' зарегистрирован как layout, но kind:field форсит FieldRenderer
    // → нет field-компонента 'rows' → UnknownField fallback.
    expect(wrapper.text()).toContain('Unknown field type: rows')
  })

  it('columns layout uses span prop for grid-column', () => {
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
    const items = wrapper.findAll('.admin-layout-columns__item')
    expect(items[0].attributes('style')).toContain('grid-column: span 8')
    expect(items[1].attributes('style')).toContain('grid-column: span 4')
  })
})
