import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import WidgetConfigDialog from './WidgetConfigDialog.vue'

// When there is nothing left to add: the empty state and a disabled "Add".
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

describe('WidgetConfigDialog — restore hidden widgets (BL-18)', () => {
  it('без скрытых виджетов показывает «нечего добавлять»', () => {
    const w = mountOpen()
    expect(w.text()).toContain('Нечего добавлять')
  })

  it('показывает скрытые виджеты и эмитит restore + close по клику', async () => {
    const w = mount(WidgetConfigDialog, {
      props: {
        open: true,
        mode: 'add',
        restorable: [{ slug: 'printable.stat.templates', title: 'Шаблоны' }],
      },
      global: { stubs: { teleport: true } },
    })
    const btn = w.find('[data-testid="restore-widget-printable.stat.templates"]')
    expect(btn.exists()).toBe(true)
    expect(btn.text()).toContain('Шаблоны')
    await btn.trigger('click')
    expect(w.emitted('restore')).toEqual([['printable.stat.templates']])
    expect(w.emitted('close')).toBeTruthy()
  })
})
