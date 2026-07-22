import { describe, it, expect, beforeEach } from 'vitest'
import { defineComponent } from 'vue'
import {
  registerField,
  registerLayout,
  getField,
  getLayout,
  hasField,
  hasLayout,
  listFields,
  listLayouts,
  clearRegistry,
  registerComponents,
} from './registry'

const Stub = defineComponent({ name: 'Stub', template: '<div />' })
const Stub2 = defineComponent({ name: 'Stub2', template: '<span />' })

describe('component registry', () => {
  beforeEach(() => {
    clearRegistry()
  })

  it('register/get field', () => {
    expect(getField('text')).toBeNull()
    registerField('text', Stub)
    expect(hasField('text')).toBe(true)
    expect(getField('text')).toBe(Stub)
  })

  it('register/get layout', () => {
    registerLayout('rows', Stub)
    expect(hasLayout('rows')).toBe(true)
    expect(getLayout('rows')).toBe(Stub)
  })

  it('overwrites existing registration', () => {
    registerField('text', Stub)
    registerField('text', Stub2)
    expect(getField('text')).toBe(Stub2)
  })

  it('list returns sorted-ish keys (insertion order)', () => {
    registerField('a', Stub)
    registerField('b', Stub)
    expect(listFields()).toEqual(['a', 'b'])
  })

  it('clearRegistry wipes both', () => {
    registerField('x', Stub)
    registerLayout('y', Stub)
    clearRegistry()
    expect(listFields()).toEqual([])
    expect(listLayouts()).toEqual([])
  })

  it('registerComponents bundle', () => {
    registerComponents({
      fields: { text: Stub, email: Stub2 },
      layouts: { rows: Stub },
    })
    expect(getField('text')).toBe(Stub)
    expect(getField('email')).toBe(Stub2)
    expect(getLayout('rows')).toBe(Stub)
  })

  it('builtin bundle registers expected types', async () => {
    const { registerBuiltinComponents } = await import('./builtin')
    registerBuiltinComponents()
    for (const t of ['text', 'email', 'textarea', 'number', 'select', 'checkbox', 'date']) {
      expect(hasField(t)).toBe(true)
    }
    for (const t of ['rows', 'columns', 'section', 'tabs', 'block']) {
      expect(hasLayout(t)).toBe(true)
    }
  })

  it('builtin bundle covers backend snake_case fieldType() strings', async () => {
    const { registerBuiltinComponents } = await import('./builtin')
    registerBuiltinComponents()
    // Backend Field::fieldType() — snake_case (см. core/src/Field/*.php);
    // регрессия: были зарегистрированы только dash-алиасы и relation_select
    // рендерился UnknownField-заглушкой.
    for (const t of ['relation_select', 'morph_switcher', 'tree_select', 'date_range', 'color']) {
      expect(hasField(t)).toBe(true)
    }
  })
})

describe('host registrations are not clobbered by builtins', () => {
  beforeEach(() => clearRegistry())

  it('registerBuiltinComponents keeps a pre-registered host field', async () => {
    const HostStub = defineComponent({ name: 'HostRepeater', template: '<i/>' })
    registerField('repeater', HostStub)
    const { registerBuiltinComponents } = await import('./builtin')
    registerBuiltinComponents()
    expect(getField('repeater')).toBe(HostStub)
    // не-переопределённые типы регистрируются как обычно
    expect(hasField('text')).toBe(true)
  })
})
