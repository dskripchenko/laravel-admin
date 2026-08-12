import { describe, it, expect } from 'vitest'
import { resolveStatusLabel } from './statusLabel'

/**
 * Подпись статуса в шапке формы — проверяется НАСТОЯЩАЯ функция компонента.
 *
 * Шапка печатала СЫРОЕ значение (`active`) рядом с полностью русской формой,
 * хотя select двумя строками ниже показывал «Активен»: подписи приходят в
 * манифесте уже переведёнными, шапка просто не заглядывала в них.
 *
 * Логика вынесена сюда как чистая функция ровно затем, чтобы её можно было
 * проверить: внутри SFC она проверяться не могла — так дефект и уехал.
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
            // Как на самом деле: подписи в attributes, верхний ключ пуст.
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
    // Половина ресурсов объявляет подписи списком, а не картой — поддержаны оба.
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
    // Лучше машинное значение, чем пустая шапка.
    expect(label(nested, 'unknown-state')).toBe('unknown-state')
    expect(label([], 'active')).toBe('active')
  })

  it('shows nothing when the record has no status at all', () => {
    expect(label(nested, null)).toBeNull()
  })
})
