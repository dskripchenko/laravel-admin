import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { UidHeatmapMatrix } from '@dskripchenko/ui'
import HeatmapWidget from './HeatmapWidget.vue'
import WidgetRenderer from './WidgetRenderer.vue'
import { registerBuiltinWidgets } from './builtin'

const rows = ['Mon', 'Tue']
const cols = ['May', 'Jun', 'Jul']

describe('HeatmapWidget', () => {
  it('renders the kit matrix heatmap with every column labelled', () => {
    const wrapper = mount(HeatmapWidget, {
      props: { title: 'Orders', rows, cols, matrix: [[1, 2, 3], [4, 5, null]], colorScale: 'viridis' },
    })
    const heatmap = wrapper.findComponent(UidHeatmapMatrix)
    expect(heatmap.exists()).toBe(true)
    expect(heatmap.props('colorScale')).toBe('viridis')
    expect(heatmap.props('values')).toEqual([[1, 2, 3], [4, 5, null]])
    expect(wrapper.findAll('.uid-heatmap-matrix__col-label').map((l) => l.text())).toEqual(cols)
    expect(wrapper.findAll('[role="gridcell"]')[5]!.classes()).toContain('uid-heatmap-matrix__cell--empty')
    expect(wrapper.get('[role="grid"]').attributes('aria-label')).toBe('Orders')
  })

  it('formats values as money from the backend format', async () => {
    const wrapper = mount(HeatmapWidget, {
      props: { rows, cols, matrix: [[1200, 2, 3], [4, 5, 6]], format: { style: 'currency', currency: 'USD', decimals: 0 } },
    })
    await wrapper.findAll('[role="gridcell"]')[0]!.trigger('mouseenter')
    const tip = wrapper.get('[role="tooltip"]').text()
    expect(tip).toContain('Mon × May')
    expect(tip.replace(/\D/g, '')).toContain('1200')
    expect(tip).toMatch(/\$|USD/)
  })

  it('falls back to the default scale and passes the domain', () => {
    const wrapper = mount(HeatmapWidget, {
      props: { rows, cols, matrix: [[1, 2, 3], [4, 5, 6]], min: 0, max: 10 },
    })
    const heatmap = wrapper.findComponent(UidHeatmapMatrix)
    expect(heatmap.props('colorScale')).toBe('default')
    expect(heatmap.props('min')).toBe(0)
    expect(heatmap.props('max')).toBe(10)
  })

  it('shows the empty text without data', () => {
    const wrapper = mount(HeatmapWidget, { props: { rows: [], cols: [], matrix: [] } })
    expect(wrapper.findComponent(UidHeatmapMatrix).exists()).toBe(false)
    expect(wrapper.text()).toContain('Нет данных')
  })

  it('renders from a backend widget node', () => {
    registerBuiltinWidgets()
    const wrapper = mount(WidgetRenderer, {
      props: {
        node: {
          type: 'heatmap',
          title: 'Orders by weekday and month',
          data: { rows, cols, matrix: [[1, 2, 3], [4, 5, null]], colorScale: 'magma', min: null, max: null, format: null },
        },
      },
    })
    expect(wrapper.findComponent(UidHeatmapMatrix).props('colorScale')).toBe('magma')
    expect(wrapper.findAll('[role="gridcell"]')).toHaveLength(6)
  })
})
