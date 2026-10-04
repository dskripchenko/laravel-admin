import { describe, it, expect } from 'vitest'
import { SquarePen, ShoppingCart, Trash2 } from 'lucide-vue-next'
import { normalizeIconName, resolveIcon } from './iconRegistry'

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

  // The names a host reaches for first, from lucide.dev — old spellings
  // (edit, more-horizontal, alert-triangle) next to the current ones.
  it.each([
    'edit', 'edit-2', 'edit-3', 'square-pen', 'pencil', 'trash', 'trash-2', 'plus', 'minus', 'eye',
    'eye-off', 'search', 'settings', 'user', 'users', 'mail', 'calendar', 'clock', 'download', 'upload',
    'file', 'file-text', 'folder', 'image', 'link', 'lock', 'unlock', 'log-in', 'log-out', 'check', 'x',
    'chevron-down', 'chevron-up', 'chevron-left', 'chevron-right', 'arrow-left', 'arrow-right',
    'arrow-up', 'arrow-down', 'refresh-cw', 'filter', 'star', 'heart', 'home', 'bell', 'shield', 'tag',
    'package', 'shopping-cart', 'box', 'copy', 'external-link', 'more-horizontal', 'more-vertical',
    'alert-triangle', 'alert-circle', 'info', 'help-circle', 'globe', 'map-pin', 'phone', 'credit-card',
    'dollar-sign', 'bar-chart', 'pie-chart', 'activity', 'database', 'server', 'code', 'terminal',
    'layers', 'grid', 'list', 'menu', 'save', 'send', 'share', 'share-2', 'printer', 'archive',
    'bookmark', 'flag', 'zap', 'cpu', 'key',
  ])('resolves the common Lucide name %s', (name) => {
    expect(resolveIcon(name)).not.toBeNull()
  })

  it('maps edit onto the pen-in-a-square icon', () => {
    expect(resolveIcon('edit')).toBe(SquarePen)
  })

  it('reads a name written another way', () => {
    expect(normalizeIconName('ShoppingCart')).toBe('shopping-cart')
    expect(normalizeIconName(' shopping_cart ')).toBe('shopping-cart')
    expect(normalizeIconName('lucide-shopping-cart')).toBe('shopping-cart')
    expect(resolveIcon('ShoppingCart')).toBe(ShoppingCart)
    expect(resolveIcon('Trash2')).toBe(Trash2)
  })
})
