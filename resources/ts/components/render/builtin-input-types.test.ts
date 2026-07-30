import { describe, expect, it, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { clearRegistry, getField } from './registry'
import { registerBuiltinComponents } from './builtin'
import { provideFormState } from './formState'

/**
 * Поля password/email/url/tel рендерились обычным text-инпутом: секрет был
 * виден открытым текстом, а мобильная клавиатура не подстраивалась.
 */
describe('builtin: тип инпута текстовых полей', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    clearRegistry()
    registerBuiltinComponents()
  })

  const mountField = (type: string) => {
    const Component = getField(type)!

    return mount(
      {
        components: { Component },
        setup: () => {
          provideFormState({ secret: '' })

          return {}
        },
        template: '<Component name="secret" label="Секрет" />',
      },
      { global: { plugins: [createPinia()] } },
    )
  }

  it.each([
    ['password', 'password'],
    ['email', 'email'],
    ['url', 'url'],
    ['tel', 'tel'],
  ])('%s рендерится инпутом type=%s', (fieldType, expected) => {
    expect(mountField(fieldType).find('input').attributes('type')).toBe(expected)
  })

  it('обычный text остаётся текстом', () => {
    expect(mountField('text').find('input').attributes('type')).toBe('text')
  })
})
