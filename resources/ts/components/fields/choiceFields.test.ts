import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick, type Component } from 'vue'
import { UidCheckbox, UidCheckboxGroup, UidDatePicker, UidSelect, UidSlider } from '@dskripchenko/ui'
import { provideFormState, type FormStateContext } from '../render/formState'
import SelectField from './SelectField.vue'
import CheckboxField from './CheckboxField.vue'
import SliderField from './SliderField.vue'
import DateField from './DateField.vue'

/** Mounts a field over a form state and hands the state back. */
function wrap(comp: Component, initial: Record<string, unknown>, props: Record<string, unknown>) {
  let ctx: FormStateContext | null = null
  const w = mount(
    defineComponent({
      setup() {
        ctx = provideFormState(initial)
        return () => h(comp, props)
      },
    }),
  )
  return { w, ctx: ctx! }
}

const options = [
  { value: 1, label: 'One' },
  { value: 2, label: 'Two' },
  { value: 3, label: 'Three' },
]

describe('SelectField multiple()', () => {
  it('stays a single-value UidSelect without multiple', () => {
    const { w } = wrap(SelectField, { x: 1 }, { name: 'x', options })
    const select = w.findComponent(UidSelect)
    expect(select.exists()).toBe(true)
    expect(select.props('multiple')).toBe(false)
    expect(select.props('modelValue')).toBe(1)
  })

  it('takes a list, matching the option values however the state spells them', () => {
    const { w } = wrap(SelectField, { x: ['2', 3] }, { name: 'x', options, multiple: true })
    const select = w.findComponent(UidSelect)
    expect(select.exists()).toBe(true)
    expect(select.props('multiple')).toBe(true)
    expect(select.props('modelValue')).toEqual([2, 3])
  })

  it('reads a JSON string a cast left behind', () => {
    const { w } = wrap(SelectField, { x: '[1]' }, { name: 'x', options, multiple: true })
    expect(w.findComponent(UidSelect).props('modelValue')).toEqual([1])
  })

  it('writes the list back', async () => {
    const { w, ctx } = wrap(SelectField, { x: null }, { name: 'x', options, multiple: true })
    w.findComponent(UidSelect).vm.$emit('update:modelValue', [1, 3])
    expect(ctx.getField('x')).toEqual([1, 3])
    w.findComponent(UidSelect).vm.$emit('update:modelValue', null)
    expect(ctx.getField('x')).toEqual([])
  })
})

describe('CheckboxField with options()', () => {
  it('is a group of checkboxes, the checked ones from the list', () => {
    const { w } = wrap(CheckboxField, { x: [2] }, { name: 'x', label: 'Numbers', options })
    const boxes = w.findAllComponents(UidCheckbox)
    expect(boxes.map((b) => b.props('label'))).toEqual(['One', 'Two', 'Three'])
    expect(boxes.map((b) => b.props('modelValue'))).toEqual([false, true, false])
    expect(w.findComponent(UidCheckboxGroup).props('modelValue')).toEqual([2])
    expect(w.find('[role="group"]').exists()).toBe(true)
    expect(w.text()).toContain('Numbers')
  })

  it('toggles an option, keeping the options order and value types', async () => {
    const { w, ctx } = wrap(CheckboxField, { x: ['3'] }, { name: 'x', options })
    w.findAllComponents(UidCheckbox)[0]!.vm.$emit('update:modelValue', true)
    expect(ctx.getField('x')).toEqual([1, 3])
    await nextTick()
    w.findAllComponents(UidCheckbox)[2]!.vm.$emit('update:modelValue', false)
    expect(ctx.getField('x')).toEqual([1])
  })

  it('lays the group out in a row with inline()', () => {
    const { w } = wrap(CheckboxField, { x: [] }, { name: 'x', options, inline: true })
    expect(w.findComponent(UidCheckboxGroup).props('direction')).toBe('horizontal')
  })

  it('is one boolean checkbox without options', () => {
    const { w } = wrap(CheckboxField, { x: true }, { name: 'x', inlineLabel: 'Active' })
    const boxes = w.findAllComponents(UidCheckbox)
    expect(boxes).toHaveLength(1)
    expect(boxes[0]!.props('modelValue')).toBe(true)
  })
})

describe('SliderField', () => {
  it('shows its caption once, through the form field', () => {
    const { w } = wrap(SliderField, { x: 5 }, { name: 'x', label: 'Volume', min: 0, max: 10 })
    const slider = w.findComponent(UidSlider)
    expect(slider.props('label')).toBeUndefined()
    expect(w.text().split('Volume')).toHaveLength(2)
    // The handle still has an accessible name.
    expect(slider.props('ariaLabel')).toBe('Volume')
    expect(w.find('input[type="range"]').attributes('aria-label')).toBe('Volume')
  })
})

describe('DateField', () => {
  it('gives the picker a real date', () => {
    const { w } = wrap(DateField, { x: '2026-10-01' }, { name: 'x' })
    expect(w.findComponent(UidDatePicker).props('modelValue')).toBe('2026-10-01')
  })

  it('takes the date part of a datetime', () => {
    const { w } = wrap(DateField, { x: '2026-10-01 12:30:00' }, { name: 'x' })
    expect(w.findComponent(UidDatePicker).props('modelValue')).toBe('2026-10-01')
  })

  it('leaves the picker empty for a value that is no date, not NaN.NaN.NaN', () => {
    for (const bad of ['soon', '2026-13-45', 'abc-de-fg']) {
      const { w } = wrap(DateField, { x: bad }, { name: 'x' })
      expect(w.findComponent(UidDatePicker).props('modelValue')).toBeNull()
      expect(w.html()).not.toContain('NaN')
    }
  })
})
