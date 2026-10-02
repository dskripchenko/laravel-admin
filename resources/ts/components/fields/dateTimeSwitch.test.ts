import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { UidDatePicker, UidSwitch, UidTimePicker } from '@dskripchenko/ui'
import { provideFormState, type FormStateContext } from '../render/formState'
import DateField from './DateField.vue'
import SwitchField from './SwitchField.vue'
import { formatHasSeconds, joinDateTime, splitDateTime } from './support/dateTime'

function mountField(comp: unknown, initial: Record<string, unknown>, props: Record<string, unknown>) {
  let ctx: FormStateContext | null = null
  const w = mount(
    defineComponent({
      setup() {
        ctx = provideFormState(initial)
        return () => h(comp as never, props)
      },
    }),
  )
  return { w, ctx: ctx! }
}

describe('date-time helpers', () => {
  it('splits stored values, ISO casts included', () => {
    expect(splitDateTime('2026-05-01 10:30:15')).toEqual({ date: '2026-05-01', time: '10:30:15' })
    expect(splitDateTime('2026-05-01T09:05:00.000000Z')).toEqual({ date: '2026-05-01', time: '09:05:00' })
    expect(splitDateTime('2026-05-01')).toEqual({ date: '2026-05-01', time: null })
    expect(splitDateTime(null)).toEqual({ date: null, time: null })
    expect(splitDateTime('garbage')).toEqual({ date: null, time: null })
  })

  it('joins by the PHP format', () => {
    expect(joinDateTime('2026-05-01', '10:30', 'Y-m-d H:i:s')).toBe('2026-05-01 10:30:00')
    expect(joinDateTime('2026-05-01', '10:30:15', 'Y-m-d H:i')).toBe('2026-05-01 10:30')
    expect(joinDateTime('2026-05-01', '7:05', 'Y-m-d\\TH:i:s')).toBe('2026-05-01T07:05:00')
    expect(joinDateTime('2026-05-01', null, null)).toBe('2026-05-01 00:00:00')
    expect(joinDateTime(null, '10:30', null)).toBeNull()
    expect(formatHasSeconds('Y-m-d H:i')).toBe(false)
    expect(formatHasSeconds(null)).toBe(true)
  })
})

describe('DateField withTime', () => {
  it('renders a date picker alone without withTime', () => {
    const { w } = mountField(DateField, { d: '2026-05-01' }, { name: 'd' })
    expect(w.findComponent(UidDatePicker).exists()).toBe(true)
    expect(w.findComponent(UidTimePicker).exists()).toBe(false)
  })

  it('renders a date and a time picker, split from the stored value', () => {
    const { w } = mountField(DateField, { d: '2026-05-01 10:30:00' }, { name: 'd', withTime: true, format: 'Y-m-d H:i:s' })
    expect(w.findComponent(UidDatePicker).props('modelValue')).toBe('2026-05-01')
    expect(w.findComponent(UidTimePicker).props('modelValue')).toBe('10:30:00')
    expect(w.findComponent(UidTimePicker).props('withSeconds')).toBe(true)
  })

  it('keeps the time when the date changes, and the date when the time does', async () => {
    const { w, ctx } = mountField(DateField, { d: '2026-05-01 10:30:00' }, { name: 'd', withTime: true, format: 'Y-m-d H:i:s' })
    w.findComponent(UidDatePicker).vm.$emit('update:modelValue', '2026-06-15')
    expect(ctx.getField('d')).toBe('2026-06-15 10:30:00')
    await w.vm.$nextTick()
    w.findComponent(UidTimePicker).vm.$emit('update:modelValue', '18:45:00')
    expect(ctx.getField('d')).toBe('2026-06-15 18:45:00')
  })

  it('holds a time picked before the date, and clears with the date', async () => {
    const { w, ctx } = mountField(DateField, { d: null }, { name: 'd', withTime: true, format: 'Y-m-d H:i' })
    w.findComponent(UidTimePicker).vm.$emit('update:modelValue', '08:15')
    expect(ctx.getField('d')).toBeNull()
    await w.vm.$nextTick()
    w.findComponent(UidDatePicker).vm.$emit('update:modelValue', '2026-07-01')
    expect(ctx.getField('d')).toBe('2026-07-01 08:15')
    await w.vm.$nextTick()
    w.findComponent(UidDatePicker).vm.$emit('update:modelValue', null)
    expect(ctx.getField('d')).toBeNull()
  })
})

describe('SwitchField', () => {
  it('is a UidSwitch bound to the form', () => {
    const { w, ctx } = mountField(SwitchField, { on: false }, { name: 'on', label: 'Active' })
    const sw = w.findComponent(UidSwitch)
    expect(sw.props('label')).toBe('Active')
    sw.vm.$emit('update:modelValue', true)
    expect(ctx.getField('on')).toBe(true)
  })

  it('shows the on/off labels by state, with the field label above', async () => {
    const { w, ctx } = mountField(SwitchField, { on: false }, { name: 'on', label: 'Status', onLabel: 'Включено', offLabel: 'Выключено' })
    expect(w.findComponent(UidSwitch).props('label')).toBe('Выключено')
    expect(w.text()).toContain('Status')
    ctx.setField('on', true)
    await w.vm.$nextTick()
    expect(w.findComponent(UidSwitch).props('label')).toBe('Включено')
  })
})
