import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import InfolistRenderer, { type InfolistNode } from './InfolistRenderer.vue'
import { clearInfolistRegistry } from './registry'
import { registerBuiltinInfolistEntries } from './builtin'
import { provideRecord } from './recordContext'
import { clearRegistry } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'

const Wrap = defineComponent({
  props: { record: { type: Object, default: () => ({}) }, node: { type: Object, required: true } },
  setup(props) {
    provideRecord(props.record as Record<string, unknown>)
    return () => h(InfolistRenderer, { node: props.node as unknown as InfolistNode })
  },
})

describe('InfolistRenderer', () => {
  beforeEach(() => {
    clearInfolistRegistry()
    clearRegistry()
    registerBuiltinInfolistEntries()
    registerBuiltinComponents() // нужен для layout-узлов (rows/section/...)
  })

  it('renders TextEntry from registry, reads record[name]', () => {
    const w = mount(Wrap, {
      props: {
        record: { title: 'Hello' },
        node: { type: 'text', name: 'title', label: 'Title' },
      },
    })
    expect(w.text()).toContain('Hello')
  })

  it('TextEntry uses placeholder for empty', () => {
    const w = mount(Wrap, {
      props: {
        record: {},
        node: { type: 'text', name: 'absent' },
      },
    })
    expect(w.text()).toBe('—')
  })

  it('BadgeEntry renders UidBadge with mapped variant', () => {
    const w = mount(Wrap, {
      props: {
        record: { status: 'published' },
        node: {
          type: 'badge',
          name: 'status',
          map: { published: 'success', draft: 'warning' },
        },
      },
    })
    expect(w.text()).toContain('published')
  })

  it('IconEntry renders true variant when value is truthy', () => {
    const w = mount(Wrap, {
      props: {
        record: { active: true },
        node: { type: 'icon', name: 'active', trueLabel: 'Активно', falseLabel: 'Нет' },
      },
    })
    expect(w.text()).toContain('Активно')
    expect(w.find('.admin-infolist-icon--on').exists()).toBe(true)
  })

  it('IconEntry renders false variant', () => {
    const w = mount(Wrap, {
      props: {
        record: { active: false },
        node: { type: 'icon', name: 'active', trueLabel: 'Y', falseLabel: 'N' },
      },
    })
    expect(w.text()).toContain('N')
    expect(w.find('.admin-infolist-icon--off').exists()).toBe(true)
  })

  it('KeyValueEntry lists object entries', () => {
    const w = mount(Wrap, {
      props: {
        record: { meta: { author: 'Alice', words: 1234 } },
        node: { type: 'keyvalue', name: 'meta' },
      },
    })
    expect(w.text()).toContain('author')
    expect(w.text()).toContain('Alice')
    expect(w.text()).toContain('1234')
  })

  it('renders Unknown for unregistered type', () => {
    const w = mount(Wrap, {
      props: {
        record: {},
        node: { type: 'no-such', name: 'x' },
      },
    })
    expect(w.text()).toContain('no-such')
  })

  it('descends into nested layout-узлы (rows + entries)', () => {
    const w = mount(Wrap, {
      props: {
        record: { title: 'A', body: 'B' },
        node: {
          type: 'rows',
          items: [
            { type: 'text', name: 'title' },
            { type: 'text', name: 'body' },
          ],
        },
      },
    })
    expect(w.text()).toContain('A')
    expect(w.text()).toContain('B')
  })

  it('explicit kind:entry blocks layout fallback', () => {
    const w = mount(Wrap, {
      props: {
        record: {},
        node: { type: 'rows', kind: 'entry', name: 'x' },
      },
    })
    // 'rows' зарегистрирован как layout, но kind:entry форсит entry-registry
    // → нет такого entry → UnknownEntry
    expect(w.text()).toContain('rows')
  })
  it('BadgeEntry maps colors variant and localizes label', () => {
    const wrapper = mount(Wrap, {
      props: {
        record: { status: 'active' },
        node: {
          kind: 'entry', type: 'badge', name: 'status', label: 'Статус',
          attributes: {
            colors: { active: 'success', archived: 'default' },
            labels: { active: 'Активен', archived: 'Архивирован' },
          },
        },
      },
    })
    // Отображается локализованная подпись, не сырое значение.
    expect(wrapper.text()).toContain('Активен')
    expect(wrapper.text()).not.toContain('active')
    // success-variant применён (класс бэйджа).
    expect(wrapper.html()).toMatch(/badge.*success|success.*badge/)
  })

})
