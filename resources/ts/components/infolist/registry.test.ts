import { describe, it, expect, beforeEach } from 'vitest'
import { defineComponent } from 'vue'
import {
  registerInfolistEntry,
  registerInfolistEntries,
  getInfolistEntry,
  hasInfolistEntry,
  listInfolistEntries,
  clearInfolistRegistry,
} from './registry'
import { registerBuiltinInfolistEntries } from './builtin'

const Stub = defineComponent({ name: 'Stub', template: '<div/>' })

describe('infolist registry', () => {
  beforeEach(() => clearInfolistRegistry())

  it('register/get entry', () => {
    expect(getInfolistEntry('text')).toBeNull()
    registerInfolistEntry('text', Stub)
    expect(hasInfolistEntry('text')).toBe(true)
    expect(getInfolistEntry('text')).toBe(Stub)
  })

  it('registerInfolistEntries bundle', () => {
    registerInfolistEntries({ a: Stub, b: Stub })
    expect(listInfolistEntries()).toEqual(['a', 'b'])
  })

  it('overwrites existing', () => {
    const Stub2 = defineComponent({ name: 'Stub2', template: '<span/>' })
    registerInfolistEntry('x', Stub)
    registerInfolistEntry('x', Stub2)
    expect(getInfolistEntry('x')).toBe(Stub2)
  })

  it('clearInfolistRegistry wipes all', () => {
    registerInfolistEntry('x', Stub)
    clearInfolistRegistry()
    expect(listInfolistEntries()).toEqual([])
  })

  it('registerBuiltinInfolistEntries registers expected types', () => {
    registerBuiltinInfolistEntries()
    for (const t of ['text', 'badge', 'icon', 'keyvalue', 'key-value']) {
      expect(hasInfolistEntry(t)).toBe(true)
    }
  })
})
