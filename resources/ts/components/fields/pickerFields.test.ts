import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick, type Component } from 'vue'
import {
  UidCascader,
  UidCode,
  UidColorPicker,
  UidDateRangePicker,
  UidRadioGroup,
  UidRating,
  UidSelect,
  UidSlider,
  UidTimePicker,
  UidTreeSelect,
} from '@dskripchenko/ui'
import { provideFormState, type FormStateContext } from '../render/formState'
import MarkdownField from './MarkdownField.vue'
import CodeField from './CodeField.vue'
import ColorField from './ColorField.vue'
import SliderField from './SliderField.vue'
import RatingField from './RatingField.vue'
import RadioField from './RadioField.vue'
import TimeField from './TimeField.vue'
import DateRangeField from './DateRangeField.vue'
import TreeSelectField from './TreeSelectField.vue'
import CascaderField from './CascaderField.vue'
import MorphSwitcherField from './MorphSwitcherField.vue'
import LabelField from './LabelField.vue'
import HiddenField from './HiddenField.vue'
import GroupField from './GroupField.vue'
import { clearRegistry } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'

/**
 * The same contract as fields.test.ts: read state[name], write it through the
 * uid component's update:modelValue, show errors[name].
 */
function wrap(
  comp: Component,
  initial: Record<string, unknown>,
  props: Record<string, unknown>,
  errors: Record<string, string[]> = {},
) {
  let ctx!: FormStateContext
  const w = mount(
    defineComponent({
      setup() {
        ctx = provideFormState(initial, errors)
        return () => h(comp, props)
      },
    }),
    { attachTo: document.body },
  )
  return { w, ctx: () => ctx }
}

describe('MarkdownField', () => {
  it('edits the source and previews the rendered markdown', async () => {
    const state: Record<string, unknown> = { body: '# Hi' }
    const { w } = wrap(MarkdownField, state, { name: 'body', label: 'Body' })
    expect((w.find('textarea').element as HTMLTextAreaElement).value).toBe('# Hi')
    expect(w.find('.admin-markdown-field__preview').exists()).toBe(false)

    await w.find('[data-mode="split"]').trigger('click')
    expect(w.find('textarea').exists()).toBe(true)
    expect(w.find('.admin-markdown-field__preview').html()).toContain('<h1>Hi</h1>')

    await w.find('[data-mode="preview"]').trigger('click')
    expect(w.find('textarea').exists()).toBe(false)

    await w.find('[data-mode="write"]').trigger('click')
    await w.find('textarea').setValue('**x**')
    expect(state.body).toBe('**x**')
    w.unmount()
  })

  it('wraps the selection with the toolbar', async () => {
    const state: Record<string, unknown> = { body: 'hello world' }
    const { w } = wrap(MarkdownField, state, { name: 'body' })
    const ta = w.find('textarea').element as HTMLTextAreaElement
    ta.setSelectionRange(6, 11)
    await w.find('[data-action="bold"]').trigger('click')
    expect(state.body).toBe('hello **world**')
    w.unmount()
  })

  it('hides the preview and the toolbar on request, shows errors', () => {
    const { w } = wrap(MarkdownField, { body: '' }, { name: 'body', preview: false, toolbar: false }, { body: ['Required'] })
    expect(w.find('[data-mode]').exists()).toBe(false)
    expect(w.find('[data-action]').exists()).toBe(false)
    expect(w.text()).toContain('Required')
    w.unmount()
  })
})

describe('CodeField', () => {
  it('shows the language and a line number per line', () => {
    const { w } = wrap(CodeField, { src: 'a\nb\nc' }, { name: 'src', language: 'php', rows: 2 })
    expect(w.find('.admin-code-field__lang').text()).toBe('php')
    expect(w.find('.admin-code-field__gutter').text().split('\n').filter(Boolean)).toEqual(['1', '2', '3'])
    expect(w.find('textarea').attributes('wrap')).toBe('off')
    w.unmount()
  })

  it('indents with Tab and outdents with Shift+Tab', async () => {
    const state: Record<string, unknown> = { src: 'line' }
    const { w } = wrap(CodeField, state, { name: 'src', language: 'php' })
    const ta = w.find('textarea')
    ;(ta.element as HTMLTextAreaElement).setSelectionRange(0, 0)
    await ta.trigger('keydown', { key: 'Tab' })
    expect(state.src).toBe('    line')
    ;(ta.element as HTMLTextAreaElement).setSelectionRange(0, 8)
    await ta.trigger('keydown', { key: 'Tab', shiftKey: true })
    expect(state.src).toBe('line')
    w.unmount()
  })

  it('lets Tab leave the field after Escape', async () => {
    const state: Record<string, unknown> = { src: 'x' }
    const { w } = wrap(CodeField, state, { name: 'src' })
    const ta = w.find('textarea')
    await ta.trigger('keydown', { key: 'Escape' })
    await ta.trigger('keydown', { key: 'Tab' })
    expect(state.src).toBe('x')
    w.unmount()
  })

  it('renders read-only code with UidCode', () => {
    const { w } = wrap(CodeField, { src: 'echo 1;' }, { name: 'src', readonly: true, language: 'php' })
    expect(w.findComponent(UidCode).props('code')).toBe('echo 1;')
    expect(w.find('textarea').exists()).toBe(false)
    w.unmount()
  })
})

describe('ColorField', () => {
  it('converts the stored rgb to hex for the picker and back', async () => {
    const state: Record<string, unknown> = { tint: 'rgb(255, 0, 0)' }
    const { w } = wrap(ColorField, state, { name: 'tint', format: 'rgb', palette: ['#00f'] })
    const picker = w.findComponent(UidColorPicker)
    expect(picker.props('modelValue')).toBe('#ff0000')
    expect(picker.props('presets')).toEqual(['#0000ff'])
    picker.vm.$emit('update:modelValue', '#00ff0080')
    await nextTick()
    // Without withAlpha the alpha channel is dropped.
    expect(state.tint).toBe('rgb(0, 255, 0)')
    w.unmount()
  })

  it('keeps the alpha channel with withAlpha', () => {
    const state: Record<string, unknown> = { tint: null }
    const { w } = wrap(ColorField, state, { name: 'tint', format: 'hsl', withAlpha: true })
    w.findComponent(UidColorPicker).vm.$emit('update:modelValue', '#ff000080')
    expect(state.tint).toBe('hsla(0, 100%, 50%, 0.5)')
    w.unmount()
  })
})

describe('SliderField and RatingField', () => {
  it('slider passes min/max/step and draws marks', () => {
    const state: Record<string, unknown> = { vol: '30' }
    const { w } = wrap(SliderField, state, { name: 'vol', min: 0, max: 50, step: 5, marks: { 0: 'Off', 50: 'Max' } })
    const s = w.findComponent(UidSlider)
    expect(s.props()).toMatchObject({ modelValue: 30, min: 0, max: 50, step: 5 })
    expect(w.findAll('.admin-slider-field__mark').map((m) => m.text())).toEqual(['Off', 'Max'])
    s.vm.$emit('update:modelValue', 45)
    expect(state.vol).toBe(45)
    w.unmount()
  })

  it('rating maps count and half, writes the value, shows errors', () => {
    const state: Record<string, unknown> = { stars: 3 }
    const { w } = wrap(RatingField, state, { name: 'stars', count: 10, half: true, icon: 'heart' }, { stars: ['Too low'] })
    const r = w.findComponent(UidRating)
    expect(r.props()).toMatchObject({ modelValue: 3, max: 10, allowHalf: true })
    expect(r.props('icon')).toBeTruthy()
    expect(w.text()).toContain('Too low')
    r.vm.$emit('update:modelValue', 4.5)
    expect(state.stars).toBe(4.5)
    w.unmount()
  })
})

describe('RadioField', () => {
  it('renders a radio per option, matches the stored type loosely', async () => {
    const state: Record<string, unknown> = { kind: '2' }
    const { w } = wrap(RadioField, state, {
      name: 'kind',
      label: 'Kind',
      inline: true,
      options: [{ value: 1, label: 'One' }, { value: 2, label: 'Two' }],
    })
    expect(w.findAll('input[type="radio"]')).toHaveLength(2)
    const group = w.findComponent(UidRadioGroup)
    expect(group.props('modelValue')).toBe(2)
    expect(group.props('direction')).toBe('horizontal')
    await w.findAll('input[type="radio"]')[0]?.setValue(true)
    expect(state.kind).toBe(1)
    w.unmount()
  })
})

describe('TimeField and DateRangeField', () => {
  it('time keeps the time part and honours seconds', () => {
    const state: Record<string, unknown> = { at: '2026-01-01 10:30:15' }
    const { w } = wrap(TimeField, state, { name: 'at', format: 'H:i:s', step: 15 })
    const t = w.findComponent(UidTimePicker)
    expect(t.props()).toMatchObject({ modelValue: '10:30:15', withSeconds: true, step: 15 })
    t.vm.$emit('update:modelValue', '11:00:00')
    expect(state.at).toBe('11:00:00')
    w.unmount()
  })

  it('date range maps {from,to} to the picker and back, with presets', async () => {
    const state: Record<string, unknown> = { period: { from: '2026-01-01', to: '2026-01-31' } }
    const { w } = wrap(DateRangeField, state, { name: 'period', presets: ['today', 'unknown'] }, { 'period.to': ['Bad end'] })
    const p = w.findComponent(UidDateRangePicker)
    expect(p.props('modelValue')).toEqual({ start: '2026-01-01', end: '2026-01-31' })
    expect(w.text()).toContain('Bad end')
    p.vm.$emit('update:modelValue', { start: '2026-02-01', end: '2026-02-03' })
    expect(state.period).toEqual({ from: '2026-02-01', to: '2026-02-03' })
    p.vm.$emit('update:modelValue', { start: null, end: null })
    expect(state.period).toBeNull()

    expect(w.findAll('[data-preset]')).toHaveLength(1)
    await w.find('[data-preset="today"]').trigger('click')
    const today = (state.period as { from: string; to: string })
    expect(today.from).toBe(today.to)
    expect(today.from).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    w.unmount()
  })
})

describe('TreeSelectField and CascaderField', () => {
  const tree = [
    { value: 1, label: 'Root', children: [{ value: 2, label: 'Leaf' }] },
  ]

  it('tree select converts the tree to nodes and keeps parents unselectable on request', () => {
    const state: Record<string, unknown> = { cat: '2' }
    const { w } = wrap(TreeSelectField, state, { name: 'cat', tree, selectableParents: false })
    const t = w.findComponent(UidTreeSelect)
    expect(t.props('nodes')).toEqual([
      { key: 1, label: 'Root', selectable: false, children: [{ key: 2, label: 'Leaf' }] },
    ])
    expect(t.props('modelValue')).toBe(2)
    t.vm.$emit('update:modelValue', 1)
    expect(state.cat).toBe(1)
    w.unmount()
  })

  it('tree select is multiple when checkable', () => {
    const { w } = wrap(TreeSelectField, { cat: 2 }, { name: 'cat', tree, checkable: true })
    const t = w.findComponent(UidTreeSelect)
    expect(t.props('multiple')).toBe(true)
    expect(t.props('modelValue')).toEqual([2])
    w.unmount()
  })

  it('cascader takes the tree, or the first level options', () => {
    const state: Record<string, unknown> = { loc: ['1', '2'] }
    const { w } = wrap(CascaderField, state, {
      name: 'loc',
      levels: [{ key: 'country', label: 'Country', options: tree }, { key: 'city', label: 'City' }],
    })
    const c = w.findComponent(UidCascader)
    expect(c.props('options')).toHaveLength(1)
    expect(c.props('modelValue')).toEqual([1, 2])
    expect(c.props('placeholder')).toBe('Country / City')
    c.vm.$emit('update:modelValue', [])
    expect(state.loc).toBeNull()
    w.unmount()
  })
})

describe('MorphSwitcherField', () => {
  it('lists the types, then that type\'s records; a type change clears the id', async () => {
    const state: Record<string, unknown> = { subject: { type: 'post', id: '7' } }
    const { w } = wrap(MorphSwitcherField, state, {
      name: 'subject',
      morphTypes: {
        post: { options: [{ value: 7, label: 'Hello' }] },
        user: { options: [{ value: 1, label: 'Alice' }] },
      },
    })
    const [typeSel, idSel] = w.findAllComponents(UidSelect)
    expect(typeSel?.props('options')).toEqual([
      { value: 'post', label: 'post' },
      { value: 'user', label: 'user' },
    ])
    expect(idSel?.props('modelValue')).toBe(7)
    typeSel?.vm.$emit('update:modelValue', 'user')
    await nextTick()
    expect(state.subject).toEqual({ type: 'user', id: null })
    expect(w.findAllComponents(UidSelect)[1]?.props('options')).toEqual([{ value: 1, label: 'Alice' }])
    w.findAllComponents(UidSelect)[1]?.vm.$emit('update:modelValue', 1)
    expect(state.subject).toEqual({ type: 'user', id: 1 })
    w.unmount()
  })
})

describe('LabelField and HiddenField', () => {
  it('label shows static text, no input', () => {
    const { w } = wrap(LabelField, { note: 'From state' }, { name: 'note', label: 'Note' })
    expect(w.find('input').exists()).toBe(false)
    expect(w.find('.admin-label-field').text()).toBe('From state')
    w.unmount()
    const { w: w2 } = wrap(LabelField, {}, { name: 'note', value: 'Explicit' })
    expect(w2.text()).toContain('Explicit')
    w2.unmount()
  })

  it('hidden draws nothing visible and keeps or seeds the value', () => {
    const state: Record<string, unknown> = { token: 'abc' }
    const { w } = wrap(HiddenField, state, { name: 'token', value: 'zzz' })
    expect(w.find('input').attributes('type')).toBe('hidden')
    expect(state.token).toBe('abc')
    w.unmount()

    const empty: Record<string, unknown> = {}
    const { w: w2 } = wrap(HiddenField, empty, { name: 'token', value: 'seeded' })
    expect(empty.token).toBe('seeded')
    w2.unmount()
  })
})

describe('GroupField', () => {
  it('nests the children under the group name and maps their errors', async () => {
    clearRegistry()
    registerBuiltinComponents()
    const state: Record<string, unknown> = { address: { city: 'Moscow' } }
    const { w, ctx } = wrap(
      GroupField,
      state,
      {
        name: 'address',
        label: 'Address',
        layout: 'columns',
        fields: [
          { type: 'input', name: 'city', label: 'City' },
          { type: 'input', name: 'street', label: 'Street' },
        ],
      },
      { 'address.street': ['Street is required'] },
    )
    expect(w.find('legend').text()).toBe('Address')
    expect(w.find('.admin-group-field--columns').exists()).toBe(true)
    const inputs = w.findAll('input')
    expect((inputs[0]?.element as HTMLInputElement).value).toBe('Moscow')
    expect(w.text()).toContain('Street is required')

    await inputs[1]?.setValue('Tverskaya')
    expect(state.address).toEqual({ city: 'Moscow', street: 'Tverskaya' })
    // Editing the child clears its prefixed error in the parent form.
    expect(ctx().errors['address.street']).toBeUndefined()
    w.unmount()
  })

  it('collapses behind an accordion', () => {
    const { w } = wrap(GroupField, { address: null }, {
      name: 'address',
      label: 'Address',
      collapsed: true,
      fields: [{ type: 'input', name: 'city' }],
    })
    expect(w.find('.uid-accordion-item__body').attributes('hidden')).toBeDefined()
    w.unmount()
  })
})
