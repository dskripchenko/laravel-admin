import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ColumnsLayout from './ColumnsLayout.vue'

const items = [
  { type: 'markdown', markdown: 'left' },
  { type: 'markdown', markdown: 'right' },
]

describe('ColumnsLayout', () => {
  it('lays the columns out by Columns::ratios()', () => {
    const wrapper = mount(ColumnsLayout, {
      props: { items, ratios: [2, 'minmax(280px, 1fr)'] },
      global: { stubs: { LayoutRenderer: true } },
    })
    const grid = wrapper.find('.admin-columns--ratios')
    expect(grid.exists()).toBe(true)
    expect(grid.attributes('style')).toContain('grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr)')
    expect(wrapper.findAll('.admin-columns__item')).toHaveLength(2)
  })

  it('keeps the twelve-column grid without ratios, or when they do not match the items', () => {
    for (const ratios of [undefined, [1]]) {
      const wrapper = mount(ColumnsLayout, { props: { items, ratios }, global: { stubs: { LayoutRenderer: true } } })
      expect(wrapper.find('.admin-columns--ratios').exists()).toBe(false)
    }
  })
})
