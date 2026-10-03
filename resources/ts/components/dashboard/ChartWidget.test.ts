import { afterEach, describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ChartWidget from './ChartWidget.vue'
import BarChartWidget from './BarChartWidget.vue'
import { niceScale, resolveLabels, stackSeries, toSeries, SERIES_PALETTE } from './chartGeometry'

/**
 * The chart widget used to draw line and area as bars, knew no radar and
 * dropped every dataset after the first. Each type now has its own SVG
 * renderer and draws every dataset as a series.
 */
const LABELS = ['Jan', 'Feb', 'Mar', 'Apr']
const TWO = [
  { label: 'Orders', data: [3, 5, 2, 8] },
  { label: 'Refunds', data: [1, 0, 2, 1], color: '#ff0000' },
]

const mountChart = (chartType: string, extra: Record<string, unknown> = {}) =>
  mount(ChartWidget, {
    props: { title: 'Sales', data: { chartType, labels: LABELS, datasets: TWO, ...extra } },
  })

describe('ChartWidget', () => {
  it('line: one path and one point per value for every dataset', () => {
    const w = mountChart('line')
    const lines = w.findAll('path.admin-chart__line')
    expect(lines).toHaveLength(2)
    expect(lines[0].attributes('d')).toMatch(/^M[\d.]+ [\d.]+( L[\d.]+ [\d.]+){3}$/)
    expect(w.findAll('[data-series="0"] .admin-chart__point')).toHaveLength(4)
    expect(w.findAll('[data-series="1"] .admin-chart__point')).toHaveLength(4)
    expect(w.findAll('path.admin-chart__area')).toHaveLength(0)
  })

  it('colours: the palette in order, an explicit colour wins', () => {
    const w = mountChart('line')
    const lines = w.findAll('path.admin-chart__line')
    expect(lines[0].attributes('stroke')).toBe(SERIES_PALETTE[0])
    expect(lines[1].attributes('stroke')).toBe('#ff0000')
  })

  it('legend lists every series for two and more', () => {
    const w = mountChart('line')
    const items = w.findAll('.admin-chart-legend__item')
    expect(items.map((i) => i.text())).toEqual(['Orders', 'Refunds'])
  })

  it('a single series has no legend box and takes the accent colour', () => {
    const w = mount(ChartWidget, {
      props: { data: { chartType: 'line', labels: LABELS, datasets: [TWO[0]] } },
    })
    expect(w.find('.admin-chart-legend').exists()).toBe(false)
    expect(w.find('path.admin-chart__line').attributes('stroke')).toBe('var(--uid-accent)')
  })

  it('area: a filled path under every line', () => {
    const w = mountChart('area')
    const areas = w.findAll('path.admin-chart__area')
    expect(areas).toHaveLength(2)
    expect(areas[0].attributes('d')).toMatch(/Z$/)
    expect(areas[0].attributes('fill-opacity')).toBe('0.16')
  })

  it('stacked area closes each band onto the series below', () => {
    const w = mountChart('area', { stacked: true })
    expect(w.findAll('path.admin-chart__area')).toHaveLength(2)
    expect(w.find('path.admin-chart__area').attributes('fill-opacity')).toBe('0.45')
  })

  it('bar: grouped bars, one per non-null value of every dataset', () => {
    const w = mountChart('bar')
    const bars = w.findAll('rect.admin-chart__bar')
    expect(bars).toHaveLength(8)
    expect(w.findAll('rect.admin-chart__bar[data-series="1"]')).toHaveLength(4)
    // Within a category the bars of the group sit side by side.
    const a = Number(bars[0].attributes('x'))
    const b = Number(bars[1].attributes('x'))
    expect(b).toBeGreaterThan(a)
  })

  it('radar: a closed polygon per series over the spokes', () => {
    const w = mountChart('radar')
    const shapes = w.findAll('path.admin-chart__radar-shape')
    expect(shapes).toHaveLength(2)
    expect(shapes[0].attributes('d')).toMatch(/^M.*Z$/)
    expect(w.findAll('.admin-chart__radar-label').map((t) => t.text())).toEqual(LABELS)
    expect(w.findAll('.admin-chart-legend__item')).toHaveLength(2)
  })

  it('pie and doughnut still go to the donut', () => {
    const w = mountChart('doughnut')
    expect(w.find('.admin-donut-widget').exists()).toBe(true)
  })

  it.each(['line', 'area', 'bar', 'radar'])('%s: empty data shows the empty state', (type) => {
    const w = mount(ChartWidget, { props: { data: { chartType: type, labels: [], datasets: [] } } })
    expect(w.find('.admin-widget__empty').exists()).toBe(true)
    expect(w.find('svg').exists()).toBe(false)
  })

  it('hover shows a tooltip with every series value at that category', async () => {
    const w = mountChart('line')
    const svg = w.find('svg.admin-chart__svg')
    // jsdom lays nothing out: the svg box is at 0,0, so clientX is the plot x.
    await svg.trigger('mousemove', { clientX: 10_000, clientY: 20 })
    const tip = w.find('.admin-chart-tooltip')
    expect(tip.exists()).toBe(true)
    expect(tip.find('.admin-chart-tooltip__title').text()).toBe('Apr')
    expect(tip.findAll('.admin-chart-tooltip__value').map((v) => v.text())).toEqual(['8', '1'])
    await svg.trigger('mouseleave')
    expect(w.find('.admin-chart-tooltip').exists()).toBe(false)
  })

  it('keeps the data readable for screen readers', () => {
    const w = mountChart('bar')
    const rows = w.findAll('table.admin-chart__sr tbody tr')
    expect(rows).toHaveLength(4)
    expect(rows[3].text()).toContain('Apr')
  })

  it('reads the flat props WidgetRenderer spreads when data is absent', () => {
    const w = mount(ChartWidget, { props: { chartType: 'line', labels: LABELS, datasets: TWO } })
    expect(w.findAll('path.admin-chart__line')).toHaveLength(2)
  })
})

describe('BarChartWidget (bar-chart manifest widget)', () => {
  it('draws one bar per datum in the accent colour', () => {
    const w = mount(BarChartWidget, {
      props: { data: [{ label: 'a', value: 1 }, { label: 'b', value: 3 }], accent: '#123456' },
    })
    const bars = w.findAll('rect.admin-chart__bar')
    expect(bars).toHaveLength(2)
    expect(bars[1].attributes('fill')).toBe('#123456')
    expect(bars[1].attributes('data-value')).toBe('3')
  })

  it('shows the empty state for all-zero data', () => {
    const w = mount(BarChartWidget, { props: { data: [{ label: 'a', value: 0 }] } })
    expect(w.find('.admin-widget__empty').exists()).toBe(true)
  })
})

describe('chartGeometry', () => {
  it('niceScale keeps zero inside and rounds the ends', () => {
    expect(niceScale(3, 87, 4)).toEqual({ min: 0, max: 100, ticks: [0, 25, 50, 75, 100] })
    const neg = niceScale(-12, 30, 4)
    expect(neg.min).toBeLessThanOrEqual(-12)
    expect(neg.ticks).toContain(0)
  })

  it('stackSeries accumulates per category, gaps as zero', () => {
    const s = toSeries([{ data: [1, 2] }, { data: [3, null] }])
    expect(stackSeries(s, 2)).toEqual([[1, 2], [4, 2]])
  })

  it('toSeries turns non-numbers into gaps and pads labels', () => {
    const s = toSeries([{ label: 'x', data: [1, 'oops', '2.5', null] }])
    expect(s[0].data).toEqual([1, null, 2.5, null])
    expect(resolveLabels(['a'], s)).toEqual(['a', '2', '3', '4'])
  })
})

describe('chart values (ChartWidget::money / precision)', () => {
  afterEach(() => {
    document.documentElement.lang = ''
  })

  it('reads as money in the panel locale', async () => {
    const { formatValue, formatTick } = await import('./chartGeometry')
    document.documentElement.lang = 'en'
    const money = { style: 'currency', currency: 'USD', decimals: 0 }
    expect(formatValue(1591285, money)).toBe('$1,591,285')
    expect(formatTick(1591285, money)).toBe('$1.6M')
    document.documentElement.lang = 'ru'
    expect(formatValue(1591285, money).replace(/\s/g, ' ')).toBe('1 591 285 $')
  })

  it('keeps fixed decimals and the plain default', async () => {
    const { formatValue } = await import('./chartGeometry')
    document.documentElement.lang = 'en'
    expect(formatValue(12.5, { style: 'decimal', decimals: 2 })).toBe('12.50')
    expect(formatValue(1591285)).toBe('1,591,285')
  })
})
