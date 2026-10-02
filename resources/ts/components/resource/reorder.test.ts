import { describe, expect, it } from 'vitest'
import { applyPositions, buildReorderPayload, canDragReorder } from './reorder'

describe('reorder contract', () => {
  it('sends the rows in their new order and the page offset', () => {
    expect(buildReorderPayload([3, 1, 2], 1, 25)).toEqual({ ids: [3, 1, 2], offset: 0 })
    expect(buildReorderPayload(['b', 'a'], 3, 20)).toEqual({ ids: ['b', 'a'], offset: 40 })
  })

  it('guards against a missing page or page size', () => {
    expect(buildReorderPayload([1], Number.NaN, 0)).toEqual({ ids: [1], offset: 0 })
  })

  it('allows dragging only in the manual order', () => {
    expect(canDragReorder(true, 'position', null, null)).toBe(true)
    expect(canDragReorder(true, 'position', 'position', 'asc')).toBe(true)
    expect(canDragReorder(true, 'position', 'position', 'desc')).toBe(false)
    expect(canDragReorder(true, 'position', 'title', 'asc')).toBe(false)
    expect(canDragReorder(true, 'position', 'title', null)).toBe(true)
    expect(canDragReorder(false, 'position', null, null)).toBe(false)
  })

  it('writes the answered positions into the rows that carry the column', () => {
    const rows = [{ id: 1, position: 0 }, { id: 2, position: 1 }, { id: 3 }]
    const next = applyPositions(rows, { 1: 1, 2: 0, 3: 2 }, 'position', (r) => r.id as number)
    expect(next).toEqual([{ id: 1, position: 1 }, { id: 2, position: 0 }, { id: 3 }])
    expect(applyPositions(rows, undefined, 'position', (r) => r.id as number)).toBe(rows)
  })
})
