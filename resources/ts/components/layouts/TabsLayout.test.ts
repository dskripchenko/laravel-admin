import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import TabsLayout from './TabsLayout.vue'
import { registerLayout, clearRegistry } from '../render/registry'

const Probe = defineComponent({ setup: () => () => h('p', { class: 'probe' }, 'x') })

describe('TabsLayout', () => {
  it('lays the panel out as a spaced column, not a bare stack of blocks', () => {
    clearRegistry()
    registerLayout('rows', Probe)

    const w = mount(TabsLayout, {
      props: {
        items: [{ label: 'One', items: [{ type: 'rows' }, { type: 'rows' }] }],
      },
    })

    // The regression: children were rendered straight into the panel, so the
    // fields touched — a label of the next field sat on the hint of the
    // previous one and the form read as one undivided ribbon.
    const stack = w.find('.uid-stack')
    expect(stack.exists()).toBe(true)
    expect(stack.attributes('style') ?? '').toContain('--uid-space-lg')
    expect(w.findAll('.probe')).toHaveLength(2)
  })
})
