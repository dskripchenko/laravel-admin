import { describe, expect, it } from 'vitest'
import { formatColor, parseColor, toHex } from './color'
import { cascaderLabels, findLabelPath, normalizeTree, optionLabel } from './tree'
import { readDateRange } from './dateRange'

describe('colour conversions', () => {
  it('parses hex, short hex, rgb and hsl', () => {
    expect(parseColor('#ff8000')).toEqual({ r: 255, g: 128, b: 0, a: 1 })
    expect(parseColor('#f80')).toEqual({ r: 255, g: 136, b: 0, a: 1 })
    expect(parseColor('rgba(10, 20, 30, 0.5)')).toEqual({ r: 10, g: 20, b: 30, a: 0.5 })
    expect(toHex('hsl(0, 100%, 50%)')).toBe('#ff0000')
    expect(parseColor('not a colour')).toBeNull()
  })

  it('formats into each storage format', () => {
    const c = { r: 255, g: 0, b: 0, a: 1 }
    expect(formatColor(c, 'hex')).toBe('#ff0000')
    expect(formatColor(c, 'rgb')).toBe('rgb(255, 0, 0)')
    expect(formatColor(c, 'hsl')).toBe('hsl(0, 100%, 50%)')
    expect(formatColor({ ...c, a: 0.5 }, 'rgb')).toBe('rgba(255, 0, 0, 0.5)')
    expect(formatColor({ ...c, a: 0.5 }, 'hex')).toBe('#ff000080')
  })
})

describe('option trees', () => {
  const tree = normalizeTree([
    { value: 1, label: 'Russia', children: [{ value: 2, label: 'Moscow' }] },
    { value: 'x', label: 'Other' },
    { label: 'no value — dropped' },
  ])

  it('normalizes and looks up label paths', () => {
    expect(tree).toHaveLength(2)
    expect(findLabelPath(tree, '2')).toEqual(['Russia', 'Moscow'])
    expect(findLabelPath(tree, 99)).toBeNull()
    expect(cascaderLabels(tree, [1, 2])).toEqual(['Russia', 'Moscow'])
    expect(cascaderLabels(tree, [1, 7])).toEqual(['Russia', '7'])
  })

  it('finds an option label in a list or a map', () => {
    expect(optionLabel([{ value: 'a', label: 'A' }], 'a')).toBe('A')
    expect(optionLabel({ a: 'A' }, 'a')).toBe('A')
    expect(optionLabel([], 'z')).toBe('z')
  })
})

describe('readDateRange', () => {
  it('reads {from,to}, {start,end} and pairs', () => {
    expect(readDateRange({ from: '2026-01-01', to: '2026-01-31T00:00:00Z' })).toEqual({ start: '2026-01-01', end: '2026-01-31' })
    expect(readDateRange({ start: '2026-02-01', end: null })).toEqual({ start: '2026-02-01', end: null })
    expect(readDateRange(['2026-03-01', '2026-03-02'])).toEqual({ start: '2026-03-01', end: '2026-03-02' })
    expect(readDateRange(null)).toEqual({ start: null, end: null })
  })
})
