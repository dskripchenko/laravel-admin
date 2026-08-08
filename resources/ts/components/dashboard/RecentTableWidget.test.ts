import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RecentTableWidget from './RecentTableWidget.vue'

/**
 * Виджет «последние записи» на дашборде показывал дату ровно так, как её
 * отдал бэкенд — `2026-08-05T03:03:44.000000Z`, — тогда как список того же
 * ресурса рисует `05.08.2026 03:03:44`. Соседний TableWidget гонял ячейки
 * через formatCell, а этот отдавал строки в таблицу как есть.
 *
 * RecentListWidget не шлёт preset'ы колонок, поэтому проверяем именно
 * автоопределение ISO-даты — на него фикс и опирается.
 */
describe('RecentTableWidget', () => {
  const mountWidget = (rows: Record<string, unknown>[]) =>
    mount(RecentTableWidget, {
      props: {
        columns: [
          { column: 'id', label: 'ID' },
          { column: 'status', label: 'Статус' },
          { column: 'created_at', label: 'Создан' },
        ],
        rows,
      },
      global: { mocks: { $route: {} }, stubs: { RouterLink: true } },
    })

  it('приводит ISO-дату к человеческому виду без preset колонки', () => {
    const wrapper = mountWidget([
      { id: 222, status: 'done', created_at: '2026-08-05T03:03:44.000000Z' },
    ])

    expect(wrapper.text()).toContain('05.08.2026')
    expect(wrapper.text()).not.toContain('2026-08-05T03:03:44')
  })

  it('не трогает значения, которые датой не являются', () => {
    const wrapper = mountWidget([{ id: 7, status: 'done', created_at: null }])

    expect(wrapper.text()).toContain('7')
    expect(wrapper.text()).toContain('done')
  })
})
