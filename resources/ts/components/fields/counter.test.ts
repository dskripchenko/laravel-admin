import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { setActivePinia, createPinia } from 'pinia'
import { provideFormState } from '../render/formState'
import TextField from './TextField.vue'
import TextAreaField from './TextAreaField.vue'
import StatWidget from '../dashboard/StatWidget.vue'

function wrap(comp: unknown, value: string, props: Record<string, unknown>) {
  return mount(defineComponent({
    setup() {
      provideFormState({ title: value })
      return () => h(comp as never, { name: 'title', ...props })
    },
  }))
}

describe('character counter', () => {
  it('counts against the limit in the hint and caps the input', async () => {
    const w = wrap(TextField, 'Новый офис в Казани', { counter: true, maxlength: 120, help: 'Заголовок' })
    expect(w.text()).toContain('Заголовок · 19 из 120 символов')
    expect(w.get('input').attributes('maxlength')).toBe('120')
    await w.get('input').setValue('Офис')
    expect(w.text()).toContain('4 из 120 символов')
  })

  it('counts without a limit in a textarea', () => {
    const w = wrap(TextAreaField, 'abc', { counter: true })
    expect(w.text()).toContain('Символов: 3')
  })

  it('stays out of the way without counter()', () => {
    expect(wrap(TextField, 'abc', {}).text()).not.toContain('символ')
  })
})

describe('stat hint', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('puts a line under the number', () => {
    const single = mount(StatWidget, { props: { title: 'Обращения', stats: [{ label: 'Обращения', value: 12, hint: 'старейшее 26 ч' }] } })
    expect(single.get('[data-testid="stat-hint"]').text()).toBe('старейшее 26 ч')
    const many = mount(StatWidget, { props: { title: 'Очереди', stats: [{ label: 'A', value: 1, hint: 'h1' }, { label: 'B', value: 2 }] } })
    expect(many.findAll('[data-testid="stat-hint"]').map((x) => x.text())).toEqual(['h1'])
  })
})
