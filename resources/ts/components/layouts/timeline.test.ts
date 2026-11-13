import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent, h } from 'vue'
import TimelineLayout from './TimelineLayout.vue'
import { provideRecord } from '../infolist/recordContext'
import { useLinkDrawerStore } from '../../stores/linkDrawer'

const types = [
  { key: 'mail', label: 'Письма', icon: 'mail', tone: 'info' },
  { key: 'kias', label: 'КИАС', icon: 'server' },
]
const events = [
  { id: 1, type: 'mail', title: 'Письмо с полисом', time: '2026-11-13T14:15:00Z' },
  { id: 2, type: 'kias', title: 'Выпуск полиса', time: '2026-11-13T14:12:00Z', tone: 'danger', details: 'Таймаут 30 с', url: '/admin/r/processes/41', linkLabel: 'Процесс #41', linkTarget: 'drawer' },
  { id: 3, type: 'mail', title: 'Письмо об оплате', time: 'вчера', details: [{ label: 'Кому', value: 'i***@mail.ru' }] },
]

function mountTimeline(props: Record<string, unknown>) {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:p(.*)*', component: { render: () => null } }] })
  return mount(TimelineLayout, { props: { types, ...props }, global: { plugins: [router] } })
}

describe('TimelineLayout', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('counts the kinds in the chips and filters by them', async () => {
    const w = mountTimeline({ events })
    expect(w.get('[data-testid="timeline-chip-all"]').text()).toBe('Все 3')
    expect(w.get('[data-testid="timeline-chip-mail"]').text()).toBe('Письма 2')
    expect(w.findAll('[data-testid^="timeline-event-"]')).toHaveLength(3)
    await w.get('[data-testid="timeline-chip-kias"]').trigger('click')
    expect(w.findAll('[data-testid^="timeline-event-"]').map((e) => e.attributes('data-type'))).toEqual(['kias'])
  })

  it('keeps the errors alone', async () => {
    const w = mountTimeline({ events })
    expect(w.get('[data-testid="timeline-only-errors"]').text()).toContain('1')
    await w.get('[data-testid="timeline-only-errors"]').trigger('click')
    expect(w.findAll('[data-testid^="timeline-event-"]')).toHaveLength(1)
    expect(w.text()).toContain('Выпуск полиса')
  })

  it('folds the details and opens a drawer link in the side panel', async () => {
    const w = mountTimeline({ events })
    const ev = w.get('[data-testid="timeline-event-2"]')
    expect(ev.find('[data-testid="timeline-details"]').exists()).toBe(false)
    await ev.get('[data-testid="timeline-details-toggle"]').trigger('click')
    expect(w.get('[data-testid="timeline-event-2"]').get('[data-testid="timeline-details"]').text()).toBe('Таймаут 30 с')
    await w.get('[data-testid="timeline-event-2"]').get('[data-testid="timeline-link"]').trigger('click')
    expect(useLinkDrawerStore().url).toBe('/admin/r/processes/41')
  })

  it('reads the events from the record and limits them', async () => {
    const w = mount(defineComponent({
      setup() {
        provideRecord({ feed: events })
        return () => h(TimelineLayout, { source: 'feed', types, limit: 2 })
      },
    }), { global: { plugins: [createRouter({ history: createMemoryHistory(), routes: [{ path: '/', component: { render: () => null } }] })] } })
    expect(w.findAll('[data-testid^="timeline-event-"]')).toHaveLength(2)
    await w.get('[data-testid="timeline-show-all"]').trigger('click')
    expect(w.findAll('[data-testid^="timeline-event-"]')).toHaveLength(3)
  })

  it('says so when there is nothing', () => {
    const w = mountTimeline({ events: [], emptyText: 'Событий по договору нет' })
    expect(w.get('[data-testid="timeline-empty"]').text()).toBe('Событий по договору нет')
  })
})
