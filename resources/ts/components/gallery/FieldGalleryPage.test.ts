import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import FieldGalleryPage from './FieldGalleryPage.vue'
import { clearRegistry } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'

describe('FieldGalleryPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    clearRegistry()
    registerBuiltinComponents()
  })

  it('renders header', () => {
    const w = mount(FieldGalleryPage)
    expect(w.find('.admin-page__title').text()).toBe('Field Gallery')
  })

  it('groups demos by category (Текстовые / Выбор / Дата/время)', () => {
    const w = mount(FieldGalleryPage)
    const titles = w.findAll('.admin-gallery__group-title').map((t) => t.text())
    expect(titles).toContain('Текстовые')
    expect(titles).toContain('Выбор')
    expect(titles).toContain('Дата/время')
  })

  it('renders demo card with type badge + description', () => {
    const w = mount(FieldGalleryPage)
    const cards = w.findAll('.admin-gallery__card')
    expect(cards.length).toBeGreaterThanOrEqual(6)
    const text = w.text()
    expect(text).toContain('type: text')
    expect(text).toContain('type: textarea')
    expect(text).toContain('type: number')
    expect(text).toContain('type: select')
    expect(text).toContain('type: checkbox')
    expect(text).toContain('type: date')
  })

  it('renders Field components — input для text demo', () => {
    const w = mount(FieldGalleryPage)
    const inputs = w.findAll('input')
    // text + email/password types + number + checkbox + date — все рендерят input
    expect(inputs.length).toBeGreaterThan(2)
  })

  it('renders textarea для textarea demo', () => {
    const w = mount(FieldGalleryPage)
    expect(w.find('textarea').exists()).toBe(true)
  })

  it('shows initial values from demo state', () => {
    const w = mount(FieldGalleryPage)
    const inputs = w.findAll('input').map((i) => (i.element as HTMLInputElement).value)
    // Один из text-input'ов содержит «Hello World»
    expect(inputs).toContain('Hello World')
  })
})
