import { describe, it, expect, beforeEach } from 'vitest'
import { defineComponent } from 'vue'
import {
  registerWidget,
  registerWidgets,
  getWidget,
  hasWidget,
  listWidgets,
  clearWidgetRegistry,
} from './registry'
import { registerBuiltinWidgets } from './builtin'

const Stub = defineComponent({ name: 'Stub', template: '<div/>' })

describe('dashboard widget registry', () => {
  beforeEach(() => clearWidgetRegistry())

  it('register/get widget', () => {
    expect(getWidget('stat')).toBeNull()
    registerWidget('stat', Stub)
    expect(hasWidget('stat')).toBe(true)
    expect(getWidget('stat')).toBe(Stub)
  })

  it('registerWidgets bundle', () => {
    registerWidgets({ a: Stub, b: Stub })
    expect(listWidgets()).toEqual(['a', 'b'])
  })

  it('clearWidgetRegistry wipes all', () => {
    registerWidget('x', Stub)
    clearWidgetRegistry()
    expect(listWidgets()).toEqual([])
  })

  it('builtin widgets register all 7 types', () => {
    registerBuiltinWidgets()
    for (const t of ['stat', 'bar-chart', 'donut-chart', 'recent-table', 'heatmap', 'gauge', 'markdown']) {
      expect(hasWidget(t)).toBe(true)
    }
  })
})
