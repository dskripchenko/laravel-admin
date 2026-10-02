import { describe, it, expect } from 'vitest'
import { resolveIcon } from './iconRegistry'

describe('iconRegistry', () => {
  it.each([
    'dollar-sign', 'shopping-cart', 'package', 'file-text', 'users', 'settings', 'layout-dashboard',
    'book-open', 'book', 'chart-bar', 'bar-chart-3', 'bell', 'newspaper', 'user-cog', 'folder-tree',
    'building-2', 'credit-card', 'cube', 'globe', 'key', 'shield', 'tag', 'store', 'image',
  ])('resolves %s', (name) => {
    expect(resolveIcon(name)).not.toBeNull()
  })

  it('answers null for an unknown name', () => {
    expect(resolveIcon('no-such-icon')).toBeNull()
  })
})
