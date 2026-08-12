import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RecentTableWidget from './RecentTableWidget.vue'

/**
 * The dashboard's "latest records" widget showed the date exactly as the
 * backend returned it — `2026-08-05T03:03:44.000000Z` — while the same
 * resource's list draws `05.08.2026 03:03:44`. The neighbouring TableWidget
 * ran its cells through formatCell; this one handed the strings to the table
 * untouched.
 *
 * RecentListWidget sends no column presets, so what is checked here is the
 * automatic detection of an ISO date, which is what the fix rests on.
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
