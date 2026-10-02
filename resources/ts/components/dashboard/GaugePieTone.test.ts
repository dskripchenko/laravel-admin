import { describe, it, expect } from 'vitest'
import { computed, defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { UidGauge, provideLocale, en as uidEn } from '@dskripchenko/ui'
import GaugeWidget from './GaugeWidget.vue'
import ChartWidget from './ChartWidget.vue'
import { toneColor } from './toneColor'

describe('toneColor', () => {
  it('maps tone and colour names onto the kit tokens', () => {
    expect(toneColor('success')).toBe('var(--uid-color-success)')
    expect(toneColor('green')).toBe('var(--uid-color-success)')
    expect(toneColor('Amber')).toBe('var(--uid-color-warning)')
    expect(toneColor('red')).toBe('var(--uid-color-danger)')
    expect(toneColor('error')).toBe('var(--uid-color-danger)')
    expect(toneColor('info')).toBe('var(--uid-color-info)')
  })

  it('passes a CSS colour through and gives nothing for nothing', () => {
    expect(toneColor('#ff0000')).toBe('#ff0000')
    expect(toneColor('rgb(1, 2, 3)')).toBe('rgb(1, 2, 3)')
    expect(toneColor('')).toBe('')
    expect(toneColor(null)).toBe('')
  })
})

// The kit formats numbers by the provided locale (ru-RU when none is), as
// the panel provides its own in AdminApp; these tests read English digits.
function mountEn(props: Record<string, unknown>) {
  const Host = defineComponent({
    setup() {
      provideLocale(computed(() => uidEn))
      return () => h(GaugeWidget, props)
    },
  })
  return mount(Host)
}

describe('GaugeWidget', () => {
  it('draws the zones in the kit tokens', () => {
    const w = mount(GaugeWidget, {
      props: {
        value: 50,
        thresholds: [
          { from: 0, to: 80, color: 'red' },
          { from: 80, to: 90, color: 'amber' },
          { from: 90, to: 100, color: '#00ff00' },
        ],
      },
    })
    expect(w.findAll('.uid-gauge__range').map((p) => p.attributes('stroke'))).toEqual([
      'var(--uid-color-danger)',
      'var(--uid-color-warning)',
      '#00ff00',
    ])
  })

  it('shows a fractional value with its decimals, a whole one without', () => {
    expect(mount(GaugeWidget, { props: { value: 82.6 } }).findComponent(UidGauge).props('precision')).toBe(1)
    expect(mountEn({ value: 82.6 }).find('.uid-gauge__value').text()).toContain('82.6')
    expect(mount(GaugeWidget, { props: { value: 83 } }).findComponent(UidGauge).props('precision')).toBe(0)
    expect(mount(GaugeWidget, { props: { value: 1.23456 } }).findComponent(UidGauge).props('precision')).toBe(2)
  })

  it('takes the precision the backend sets', () => {
    expect(mountEn({ value: 82.6, precision: 0 }).find('.uid-gauge__value').text()).toContain('83')
    expect(mountEn({ value: 5, precision: 2 }).find('.uid-gauge__value').text()).toContain('5.00')
  })
})

describe('ChartWidget pie and doughnut', () => {
  const data = (chartType: string) => ({
    data: { chartType, labels: ['A', 'B'], datasets: [{ label: 'S', data: [1, 3] }] },
  })

  it('draws a pie as a full disc', () => {
    const w = mount(ChartWidget, { props: data('pie') })
    expect(w.find('.admin-donut-widget').exists()).toBe(true)
    expect(w.find('.admin-donut-widget__svg circle').exists()).toBe(false)
  })

  it('draws a doughnut with its hole', () => {
    const w = mount(ChartWidget, { props: data('doughnut') })
    expect(w.find('.admin-donut-widget__svg circle').exists()).toBe(true)
  })

  it('maps a dataset colour name onto a token', () => {
    const w = mount(ChartWidget, {
      props: { data: { chartType: 'pie', labels: ['A'], datasets: [{ label: 'S', data: [1], color: 'green' }] } },
    })
    expect(w.find('.admin-donut-widget__svg path').attributes('fill')).toBe('var(--uid-color-success)')
  })
})
