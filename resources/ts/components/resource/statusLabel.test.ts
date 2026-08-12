import { describe, it, expect } from 'vitest'
import { resolveStatusLabel } from './statusLabel'

/**
 * The status label in a form's header — the component's REAL function is what
 * is checked here.
 *
 * The header printed the RAW value (`active`) beside a fully translated form,
 * while the select two lines below showed "Active": the labels arrive in the
 * manifest already translated, and the header simply never looked at them.
 *
 * The logic was extracted into a pure function precisely so that it could be
 * checked: inside the SFC it could not be, which is how the defect shipped.
 */
const label = (nodes: unknown[], value: string | null): string | null =>
  resolveStatusLabel(nodes as never, value)

const nested = [
  {
    kind: 'layout',
    type: 'tabs',
    items: [
      { label: 'Main', items: [{ kind: 'field', name: 'name' }] },
      {
        label: 'Other',
        items: [
          {
            kind: 'field',
            name: 'status',
            // How it really is: the labels are in the attributes, the top-level key is empty.
            options: [],
            attributes: { options: { active: 'Активен', archived: 'Архивирован' } },
          },
        ],
      },
    ],
  },
]

describe('status label in the form header', () => {
  it('reads the label from the field, however deep it sits', () => {
    expect(label(nested, 'active')).toBe('Активен')
  })

  it('understands the list form of options too', () => {
    // Half the resources declare the labels as a list rather than a map; both are supported.
    const list = [
      {
        kind: 'field',
        name: 'status',
        attributes: { options: [{ value: 'suspended', label: 'Приостановлен' }] },
      },
    ]

    expect(label(list, 'suspended')).toBe('Приостановлен')
  })

  it('falls back to the raw value when the field declares no such option', () => {
    // A machine value beats an empty header.
    expect(label(nested, 'unknown-state')).toBe('unknown-state')
    expect(label([], 'active')).toBe('active')
  })

  it('shows nothing when the record has no status at all', () => {
    expect(label(nested, null)).toBeNull()
  })
})
