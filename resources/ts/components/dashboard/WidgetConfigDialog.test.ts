import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import WidgetConfigDialog from './WidgetConfigDialog.vue'

// BL-18: когда добавлять нечего — пустое состояние + disabled «Добавить».
vi.mock('./registry', () => ({
  listWidgets: () => [],
}))

const mountOpen = () =>
  mount(WidgetConfigDialog, {
    props: { open: true, mode: 'add' },
    global: { stubs: { teleport: true } },
  })

describe('WidgetConfigDialog — add empty state (BL-18)', () => {
  it('shows an empty-state message when no widget types are available', () => {
    const w = mountOpen()
    expect(w.find('.admin-dialog__empty').exists()).toBe(true)
    expect(w.text()).toContain('Нет виджетов для добавления')
  })

  it('disables the «Добавить» button with nothing selectable', () => {
    const w = mountOpen()
    const addBtn = w.findAll('button').find((b) => b.text() === 'Добавить')
    expect(addBtn).toBeTruthy()
    expect(addBtn!.attributes('disabled')).toBeDefined()
  })
})
