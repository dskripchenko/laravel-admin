import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import TableWidget from './TableWidget.vue'
import { badgeLabel, badgeTone, formatCell } from '../resource/cellFormat'

describe('badge cells', () => {
  const meta = { colors: { draft: 'warning', live: 'green' }, labels: { draft: 'Черновик' } }

  it('formats a badge by its label and tones it by the raw value', () => {
    expect(formatCell('draft', 'badge', meta)).toBe('Черновик')
    expect(formatCell('live', 'badge', meta)).toBe('live')
    expect(badgeLabel(null, meta)).toBe('')
    expect(badgeTone('draft', meta)).toBe('warning')
    expect(badgeTone('live', meta)).toBe('success')
    expect(badgeTone('other', meta)).toBe('default')
  })

  it('TableWidget draws badge columns as UidBadge', () => {
    const wrapper = mount(TableWidget, {
      props: {
        columns: [
          { name: 'id', label: 'ID' },
          { name: 'status', label: 'Status', preset: 'badge', meta },
        ],
        rows: [{ id: 1, status: 'draft' }, { id: 2, status: 'live' }],
      },
    })
    const badges = wrapper.findAll('.uid-badge')
    expect(badges.map((b) => b.text())).toEqual(['Черновик', 'live'])
    expect(badges[0].classes().join(' ')).toContain('warning')
    expect(badges[1].classes().join(' ')).toContain('success')
  })
})
