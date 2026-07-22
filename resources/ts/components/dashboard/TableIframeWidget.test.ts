import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import TableWidget from './TableWidget.vue'
import IframeWidget from './IframeWidget.vue'

describe('TableWidget', () => {
  it('renders resource-format columns and formats cells by preset', () => {
    const wrapper = mount(TableWidget, {
      props: {
        title: 'Документы',
        columns: [
          { name: 'name', label: 'Название' },
          { name: 'done', label: 'Готов', preset: 'boolean' },
          { name: 'created_at', label: 'Создан', preset: 'date' },
        ],
        rows: [
          { name: 'Счёт', done: true, created_at: '2026-07-22 10:00:00' },
        ],
      },
    })
    expect(wrapper.text()).toContain('Документы')
    expect(wrapper.text()).toContain('Счёт')
    expect(wrapper.text()).toContain('Да')
    expect(wrapper.text()).toContain('22.07.2026')
  })

  it('shows empty text without rows', () => {
    const wrapper = mount(TableWidget, {
      props: { columns: [{ name: 'x', label: 'X' }], rows: [] },
    })
    expect(wrapper.text()).toContain('Нет данных')
  })
})

describe('IframeWidget', () => {
  it('renders sandboxed iframe with height', () => {
    const wrapper = mount(IframeWidget, {
      props: { title: 'Метрика', src: 'https://example.com/board', height: 480 },
    })
    const frame = wrapper.find('iframe')
    expect(frame.exists()).toBe(true)
    expect(frame.attributes('src')).toBe('https://example.com/board')
    expect(frame.attributes('sandbox')).toBe('allow-scripts allow-same-origin')
    expect(frame.attributes('style')).toContain('480px')
  })

  it('renders placeholder without src', () => {
    const wrapper = mount(IframeWidget, { props: {} })
    expect(wrapper.find('iframe').exists()).toBe(false)
    expect(wrapper.text()).toContain('Не задан src')
  })
})
