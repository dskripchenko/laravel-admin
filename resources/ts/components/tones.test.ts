import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { UidBadge, UidGauge, UidHeatmapMatrix } from '@dskripchenko/ui'
import { badgeVariant, resolveTone, TONE_NAMES } from './tones'
import { badgeTone } from './resource/cellFormat'
import { toneColor } from './dashboard/toneColor'
import StatWidget from './dashboard/StatWidget.vue'
import GaugeWidget from './dashboard/GaugeWidget.vue'
import HeatmapWidget from './dashboard/HeatmapWidget.vue'
import BadgeEntry from './infolist/BadgeEntry.vue'

/**
 * One vocabulary for every renderer that takes a colour name: a name that
 * works on a badge works on a stat card, a chart, a gauge and a heatmap.
 */
describe('tone vocabulary', () => {
  it('resolves tone names and colour words, case-insensitively', () => {
    expect(resolveTone('amber')).toBe('warning')
    expect(resolveTone('Gray')).toBe('neutral')
    expect(resolveTone('grey')).toBe('neutral')
    expect(resolveTone('green')).toBe('success')
    expect(resolveTone('red')).toBe('danger')
    expect(resolveTone('blue')).toBe('info')
    expect(resolveTone('primary')).toBe('primary')
    expect(resolveTone('#ff0000')).toBeNull()
    expect(resolveTone(null)).toBeNull()
  })

  it('gives every name a colour in every renderer', () => {
    for (const name of TONE_NAMES) {
      expect(toneColor(name)).toMatch(/^var\(--uid-/)
      expect(['info', 'success', 'warning', 'danger', 'default']).toContain(badgeVariant(name))
    }
  })

  it('table badges take amber and gray', () => {
    const meta = { colors: { pending: 'amber', archived: 'gray', live: 'primary' } }
    expect(badgeTone('pending', meta)).toBe('warning')
    expect(badgeTone('archived', meta)).toBe('default')
    expect(badgeTone('live', meta)).toBe('info')
  })

  it('infolist badges take colour words, not only variant names', () => {
    const w = mount(BadgeEntry, { props: { value: 'draft', colors: { draft: 'amber' } } })
    expect(w.findComponent(UidBadge).props('variant')).toBe('warning')
    const g = mount(BadgeEntry, { props: { value: 'old', map: { old: 'grey' } } })
    expect(g.findComponent(UidBadge).props('variant')).toBe('default')
  })

  it('stat cards take amber, gray and grey', () => {
    const w = mount(StatWidget, {
      props: {
        title: 'Shop',
        stats: [
          { label: 'Pending', value: 3, color: 'amber' },
          { label: 'Archived', value: 9, color: 'gray' },
          { label: 'Other', value: 1, color: 'grey' },
          { label: 'Plain', value: 2 },
        ],
      },
    })
    const stats = w.findAll('.uid-stat')
    expect(stats[0]!.classes()).toContain('uid-stat--warning')
    expect(stats[1]!.classes()).toContain('admin-stat--neutral')
    expect(stats[2]!.classes()).toContain('admin-stat--neutral')
    expect(stats[3]!.classes()).toContain('uid-stat--primary')
    expect(stats[3]!.classes()).not.toContain('admin-stat--neutral')
  })

  it('a gauge takes a colour word as its tone', () => {
    const amber = mount(GaugeWidget, { props: { value: 10, tone: 'amber' } })
    expect(amber.findComponent(UidGauge).props('tone')).toBe('warning')
    const gray = mount(GaugeWidget, { props: { value: 10, tone: 'gray' } })
    expect(gray.findComponent(UidGauge).props('color')).toBe('var(--uid-color-zinc-400)')
    const plain = mount(GaugeWidget, { props: { value: 10 } })
    expect(plain.findComponent(UidGauge).props('tone')).toBe('primary')
  })

  it('a heatmap takes a colour word, and keeps the kit scale names', () => {
    const amber = mount(HeatmapWidget, { props: { rows: ['a'], cols: ['b'], matrix: [[1]], colorScale: 'amber' } })
    expect(amber.findComponent(UidHeatmapMatrix).props('colorScale')).toBe('var(--uid-color-warning)')
    const viridis = mount(HeatmapWidget, { props: { rows: ['a'], cols: ['b'], matrix: [[1]], colorScale: 'viridis' } })
    expect(viridis.findComponent(UidHeatmapMatrix).props('colorScale')).toBe('viridis')
    const hex = mount(HeatmapWidget, { props: { rows: ['a'], cols: ['b'], matrix: [[1]], colorScale: '#123456' } })
    expect(hex.findComponent(UidHeatmapMatrix).props('colorScale')).toBe('#123456')
  })
})
