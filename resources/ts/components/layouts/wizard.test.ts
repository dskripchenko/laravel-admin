import { describe, it, expect } from 'vitest'
import { collectFields, normalizeRules, validateStep, validateValue } from './wizard'

describe('wizard helpers', () => {
  it('normalizes lists and pipe strings', () => {
    expect(normalizeRules('required|email')).toEqual(['required', 'email'])
    expect(normalizeRules(['required', 'max:5', 3])).toEqual(['required', 'max:5'])
    expect(normalizeRules(undefined)).toEqual([])
  })

  it('collects the fields through nested layouts, tabs and accordion sections', () => {
    const fields = collectFields([
      { kind: 'layout', type: 'columns', items: [{ kind: 'field', type: 'input', name: 'a', required: true }] },
      { kind: 'layout', type: 'tabs', items: [{ label: 'T', items: [{ kind: 'field', type: 'input', name: 'b' }] }] },
      { kind: 'layout', type: 'accordion', sections: [{ title: 'S', children: [{ kind: 'field', type: 'input', name: 'c' }] }] },
      { kind: 'field', type: 'repeater', name: 'rows', fields: [{ kind: 'field', name: 'inner' }] },
    ])
    expect(fields.map((f) => f.name)).toEqual(['a', 'b', 'c', 'rows'])
    expect(fields[0]!.rules).toEqual(['required'])
  })

  it('checks the common rules and leaves the rest to the server', () => {
    expect(validateValue('', ['required'], 'Name')).toHaveLength(1)
    expect(validateValue('', ['email'], 'Email')).toEqual([])
    expect(validateValue('x', ['email'], 'Email')).toHaveLength(1)
    expect(validateValue('abc', ['max:2'], 'Code')).toHaveLength(1)
    expect(validateValue('5', ['numeric', 'min:10'], 'Qty')).toHaveLength(1)
    expect(validateValue('1.5', ['integer'], 'Qty')).toHaveLength(1)
    expect(validateValue('c', ['in:a,b'], 'Kind')).toHaveLength(1)
    expect(validateValue('anything', ['unique:users,email', 'regex:/x/'], 'X')).toEqual([])
  })

  it('merges the step rules into the field rules', () => {
    const errors = validateStep(
      [{ name: 'email', type: 'input', label: 'Email', rules: [] }],
      { email: ['required'], extra: 'required' },
      () => '',
    )
    expect(Object.keys(errors)).toEqual(['email', 'extra'])
  })
})
