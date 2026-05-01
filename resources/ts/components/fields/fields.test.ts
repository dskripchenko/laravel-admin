import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, type Component } from 'vue'
import { provideFormState } from '../render/formState'
import TextField from './TextField.vue'
import TextAreaField from './TextAreaField.vue'
import NumberField from './NumberField.vue'
import SelectField from './SelectField.vue'
import CheckboxField from './CheckboxField.vue'
import DateField from './DateField.vue'

const wrap = (
  comp: Component,
  initial: Record<string, unknown>,
  props: Record<string, unknown>,
) =>
  mount(
    defineComponent({
      setup() {
        provideFormState(initial)
        return () => h(comp, props)
      },
    }),
  )

describe('TextField', () => {
  it('renders value, label, required marker', () => {
    const w = wrap(
      TextField,
      { title: 'Hi' },
      { name: 'title', label: 'Title', required: true },
    )
    expect((w.find('input').element as HTMLInputElement).value).toBe('Hi')
    expect(w.find('.admin-field__required').exists()).toBe(true)
  })

  it('updates form on input', async () => {
    const initial = { title: '' }
    const w = wrap(TextField, initial, { name: 'title' })
    await w.find('input').setValue('NEW')
    expect(initial.title).toBe('NEW')
  })

  it('inputType=email passes through', () => {
    const w = wrap(TextField, {}, { name: 'e', inputType: 'email' })
    expect(w.find('input').attributes('type')).toBe('email')
  })
})

describe('NumberField', () => {
  it('coerces empty to null', async () => {
    const initial: Record<string, unknown> = { x: 5 }
    const w = wrap(NumberField, initial, { name: 'x' })
    await w.find('input').setValue('')
    expect(initial.x).toBeNull()
  })

  it('coerces numeric string to number', async () => {
    const initial: Record<string, unknown> = { x: 0 }
    const w = wrap(NumberField, initial, { name: 'x' })
    await w.find('input').setValue('42')
    expect(initial.x).toBe(42)
  })

  it('rejects NaN', async () => {
    const initial: Record<string, unknown> = { x: 0 }
    const w = wrap(NumberField, initial, { name: 'x' })
    const el = w.find('input').element as HTMLInputElement
    el.value = 'not-a-number'
    await w.find('input').trigger('input')
    expect(initial.x).toBeNull()
  })
})

describe('SelectField', () => {
  it('renders all options', () => {
    const w = wrap(SelectField, { x: 'a' }, {
      name: 'x',
      options: [
        { value: 'a', label: 'A' },
        { value: 'b', label: 'B' },
      ],
    })
    expect(w.findAll('option')).toHaveLength(2)
  })

  it('sets value on change', async () => {
    const initial: Record<string, unknown> = { x: 'a' }
    const w = wrap(SelectField, initial, {
      name: 'x',
      options: [
        { value: 'a', label: 'A' },
        { value: 'b', label: 'B' },
      ],
    })
    await w.find('select').setValue('b')
    expect(initial.x).toBe('b')
  })

  it('multiple — array of values', async () => {
    const initial: Record<string, unknown> = { x: [] }
    const w = wrap(SelectField, initial, {
      name: 'x',
      multiple: true,
      options: [
        { value: 'a', label: 'A' },
        { value: 'b', label: 'B' },
        { value: 'c', label: 'C' },
      ],
    })
    const select = w.find('select').element as HTMLSelectElement
    select.options[0].selected = true
    select.options[2].selected = true
    await w.find('select').trigger('change')
    expect(initial.x).toEqual(['a', 'c'])
  })
})

describe('CheckboxField', () => {
  it('toggles boolean', async () => {
    const initial: Record<string, unknown> = { x: false }
    const w = wrap(CheckboxField, initial, { name: 'x', inlineLabel: 'Active' })
    await w.find('input').setValue(true)
    expect(initial.x).toBe(true)
    await w.find('input').setValue(false)
    expect(initial.x).toBe(false)
  })
})

describe('TextAreaField', () => {
  it('binds value', async () => {
    const initial: Record<string, unknown> = { x: '' }
    const w = wrap(TextAreaField, initial, { name: 'x', rows: 6 })
    expect(w.find('textarea').attributes('rows')).toBe('6')
    await w.find('textarea').setValue('hello')
    expect(initial.x).toBe('hello')
  })
})

describe('DateField', () => {
  it('binds date value with type', async () => {
    const initial: Record<string, unknown> = { x: '' }
    const w = wrap(DateField, initial, { name: 'x', inputType: 'date' })
    expect(w.find('input').attributes('type')).toBe('date')
    await w.find('input').setValue('2026-05-01')
    expect(initial.x).toBe('2026-05-01')
  })

  it('empty input → null', async () => {
    const initial: Record<string, unknown> = { x: '2025-01-01' }
    const w = wrap(DateField, initial, { name: 'x' })
    await w.find('input').setValue('')
    expect(initial.x).toBeNull()
  })
})

describe('Field error rendering', () => {
  it('shows error message and invalid class', async () => {
    const Captured = defineComponent({
      setup(_, { expose }) {
        const ctx = provideFormState({ name: '' })
        expose({ ctx })
        return () => h(TextField, { name: 'name', label: 'Name' })
      },
    })
    const w = mount(Captured)
    const captured = w.vm as unknown as { ctx: { setError: (n: string, m: string[]) => void } }
    captured.ctx.setError('name', ['Обязательное поле'])
    await w.vm.$nextTick()
    expect(w.find('.admin-field--invalid').exists()).toBe(true)
    expect(w.find('.admin-field__error').text()).toBe('Обязательное поле')
  })
})
