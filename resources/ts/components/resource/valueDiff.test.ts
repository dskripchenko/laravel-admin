import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { diffTokens, foldSame, jsonLines, valueDiff } from './valueDiff'
import AuditValueDiff from './AuditValueDiff.vue'

describe('valueDiff', () => {
  it('leaves a short value inline', () => {
    expect(valueDiff('Лечение коронавируса', 'Лечение COVID-19').mode).toBe('inline')
    expect(valueDiff(60, 20).mode).toBe('inline')
  })

  it('aligns tokens', () => {
    expect(diffTokens(['a', 'b', 'c'], ['a', 'x', 'c'])).toEqual([
      { kind: 'same', text: 'a' }, { kind: 'del', text: 'b' }, { kind: 'add', text: 'x' }, { kind: 'same', text: 'c' },
    ])
  })

  it('shows only the changed lines of a long text, the rest folded', () => {
    const before = ['один', 'два', 'три', 'четыре', 'пять', 'шесть'].join('\n')
    const after = ['один', 'два', 'три', 'четыре', 'ПЯТЬ', 'шесть'].join('\n')
    const d = valueDiff(before, after)
    expect(d.mode).toBe('lines')
    expect(d.added).toBe(1)
    expect(d.removed).toBe(1)
    expect(d.rows[0]).toEqual({ kind: 'fold', count: 3 })
    expect(d.rows.slice(1)).toEqual([
      { kind: 'same', text: 'четыре' }, { kind: 'del', text: 'пять' }, { kind: 'add', text: 'ПЯТЬ' }, { kind: 'same', text: 'шесть' },
    ])
  })

  it('compares a long single line word by word', () => {
    const before = 'Покрываются расходы на лечение в стационаре при подтверждённом диагнозе, включая анализы и консультации врачей.'
    const after = 'Покрываются расходы на лечение в стационаре и амбулаторно при подтверждённом диагнозе, включая анализы и консультации врачей.'
    const d = valueDiff(before, after)
    expect(d.mode).toBe('words')
    expect(d.rows.filter((r) => r.kind === 'add').map((r) => (r as { text: string }).text).join('')).toBe('и амбулаторно ')
  })

  it('lays a JSON value out by paths, keys sorted', () => {
    expect(jsonLines({ b: 1, a: { x: [true] } })).toEqual(['a.x[0]: true', 'b: 1'])
    const d = valueDiff('{"programs":["Премиум"],"order":60}', { order: 60, programs: ['Стандарт', 'Премиум'] })
    expect(d.mode).toBe('lines')
    expect(d.rows.filter((r) => r.kind !== 'same' && r.kind !== 'fold')).toEqual([
      { kind: 'del', text: 'programs[0]: "Премиум"' },
      { kind: 'add', text: 'programs[0]: "Стандарт"' },
      { kind: 'add', text: 'programs[1]: "Премиум"' },
    ])
  })

  it('folds only long unchanged runs', () => {
    expect(foldSame([{ kind: 'same', text: 'a' }, { kind: 'add', text: 'b' }], 1)).toHaveLength(2)
  })
})

describe('AuditValueDiff', () => {
  it('marks the removed and added lines and unfolds the rest', async () => {
    const before = Array.from({ length: 8 }, (_, i) => `line ${i}`).join('\n')
    const after = before.replace('line 6', 'line six')
    const w = mount(AuditValueDiff, { props: { before, after } })
    expect(w.findAll('.admin-value-diff__line--del')).toHaveLength(1)
    expect(w.find('.admin-value-diff__line--del').text()).toContain('line 6')
    expect(w.find('.admin-value-diff__line--add').text()).toContain('line six')
    expect(w.text()).toContain('строк без изменений')
    await w.get('[data-testid="value-diff-fold"]').trigger('click')
    expect(w.find('[data-testid="value-diff-fold"]').exists()).toBe(false)
    expect(w.findAll('.admin-value-diff__line')).toHaveLength(9)
  })
})
