import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { setActivePinia, createPinia } from 'pinia'
import { provideFormState } from '../render/formState'
import GeneratedField from './GeneratedField.vue'

const wrap = (initial: Record<string, unknown>, props: Record<string, unknown>) =>
  mount(
    defineComponent({
      setup() {
        provideFormState(initial)
        return () => h(GeneratedField, props as never)
      },
    }),
  )

describe('GeneratedField', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('автогенерит значение при монтировании, если пусто', async () => {
    const w = wrap({ token: '' }, { name: 'token', length: 16 })
    await w.vm.$nextTick()
    const value = (w.find('input').element as HTMLInputElement).value
    expect(value).toHaveLength(16)
    expect(value).toMatch(/^[a-zA-Z0-9]+$/)
  })

  it('НЕ перегенерирует существующее значение (edit-режим)', () => {
    const w = wrap({ token: 'existing-token' }, { name: 'token' })
    expect((w.find('input').element as HTMLInputElement).value).toBe('existing-token')
  })

  it('не автогенерит при autogenerate=false', () => {
    const w = wrap({ token: '' }, { name: 'token', autogenerate: false })
    expect((w.find('input').element as HTMLInputElement).value).toBe('')
  })

  it('кнопка перегенерирует значение', async () => {
    const w = wrap({ token: '' }, { name: 'token', length: 20 })
    const before = (w.find('input').element as HTMLInputElement).value
    await w.find('button').trigger('click')
    const after = (w.find('input').element as HTMLInputElement).value
    expect(after).toHaveLength(20)
    expect(after).not.toBe(before)
  })

  it('уважает кастомный charset', async () => {
    const w = wrap({ token: '' }, { name: 'token', length: 40, charset: 'ab' })
    await w.vm.$nextTick()
    const value = (w.find('input').element as HTMLInputElement).value
    expect(value).toMatch(/^[ab]{40}$/)
  })
})

describe('GeneratedField — гонка с сидированием формы', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('регенерит после того как сидирование затёрло значение (create-форма)', async () => {
    let ctx!: ReturnType<typeof provideFormState>
    const w = mount(
      defineComponent({
        setup() {
          ctx = provideFormState({ token: '' })
          return () => h(GeneratedField, { name: 'token', length: 12 } as never)
        },
      }),
    )
    await w.vm.$nextTick()
    // эмулируем prepareCreate: стор затирает state пустыми default'ами
    ctx.setField('token', '')
    await w.vm.$nextTick()
    expect((w.find('input').element as HTMLInputElement).value).toHaveLength(12)
  })
})
