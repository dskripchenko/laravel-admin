import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import DashboardLayout from './DashboardLayout.vue'
import { registerWidget, clearWidgetRegistry } from '../dashboard/registry'
import { hasLayout, clearRegistry } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'

const Probe = defineComponent({
  props: { content: { type: String, default: '' } },
  setup: (props) => () => h('p', { class: 'probe' }, props.content),
})

describe('DashboardLayout', () => {
  beforeEach(() => {
    clearWidgetRegistry()
    registerWidget('markdown', Probe)
  })

  it('renders every widget it is given', () => {
    const w = mount(DashboardLayout, {
      props: {
        items: [
          { type: 'markdown', size: 12, data: { content: 'first' } },
          { type: 'markdown', size: 6, data: { content: 'second' } },
        ],
      },
    })

    expect(w.findAll('.probe')).toHaveLength(2)
    expect(w.text()).toContain('first')
    expect(w.text()).toContain('second')
  })

  it('never lets a widget span wider than the grid', () => {
    const w = mount(DashboardLayout, {
      props: { gridColumns: 6, items: [{ type: 'markdown', size: 12 }] },
    })

    expect(w.find('.admin-dashboard-layout__cell').attributes('style')).toContain('span 6')
  })

  // The regression this file exists for: the type was registered on the
  // backend and missing on the frontend, so a screen using it drew nothing at
  // all — no error, no placeholder, just an empty page.
  it('is reachable by the type name the backend emits', () => {
    clearRegistry()
    registerBuiltinComponents()

    expect(hasLayout('dashboard')).toBe(true)
  })
})
