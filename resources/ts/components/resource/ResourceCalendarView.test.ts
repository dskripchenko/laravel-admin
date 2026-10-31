import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import { UidCalendar, type CalendarEvent } from '@dskripchenko/ui'
import ResourceCalendarView from './ResourceCalendarView.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useResourceIndexStore } from '../../stores/resourceIndex'
import { adminToast } from '../../stores/toast'
import type { ResourceCalendarConfig } from '../../stores/manifest'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const config: ResourceCalendarConfig = {
  start: 'starts_at',
  end: 'ends_at',
  date_only: false,
  all_day: 'all_day',
  title: 'name',
  color: 'priority',
  views: ['month', 'week'],
  initial_view: 'month',
  readonly: false,
}

const events = [
  { id: 1, title: 'Standup', start: '2026-10-10 09:00', end: '2026-10-10 09:30', all_day: false, color: 'danger', editable: true },
  { id: 2, title: 'Offsite', start: '2026-10-12', end: '2026-10-14', all_day: true, color: null, editable: true },
  { id: 3, title: 'Frozen', start: '2026-10-15 10:00', end: null, all_day: false, color: null, editable: false },
]

function mkRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: Stub },
      { path: '/r/tasks/create', name: 'admin.resource.tasks.create', component: Stub },
      { path: '/r/tasks/:id', name: 'admin.resource.tasks.view', component: Stub },
    ],
  })
}

async function mountView(props: Record<string, unknown> = {}) {
  const router = mkRouter()
  await router.push('/')
  await router.isReady()
  const wrapper = mount(ResourceCalendarView, {
    props: { slug: 'tasks', config, createRoute: 'admin.resource.tasks.create', ...props },
    global: { plugins: [router] },
  })
  await flushPromises()
  return { wrapper, router }
}

const shown = (wrapper: ReturnType<typeof mount>): CalendarEvent[] =>
  wrapper.findComponent(UidCalendar).props('events') as CalendarEvent[]

describe('ResourceCalendarView', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    const index = useResourceIndexStore()
    index.setSlug('tasks')
    index.filters = { priority: 'high' }
    mock.onPost('/tasks/calendar').reply(200, { success: true, payload: { events, truncated: false } })
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
    vi.restoreAllMocks()
  })

  it('loads the visible range with the list filters and maps the events for the kit', async () => {
    const { wrapper } = await mountView()

    // UidCalendar emits its range on mount; the month grid is six weeks.
    const body = JSON.parse(mock.history.post[0].data)
    expect(body.filters).toEqual({ priority: 'high' })
    expect(body.from).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    expect(body.to > body.from).toBe(true)

    expect(shown(wrapper)).toEqual([
      { id: 1, title: 'Standup', date: '2026-10-10', editable: true, time: '09:00', endTime: '09:30', color: 'var(--uid-color-danger)' },
      { id: 2, title: 'Offsite', date: '2026-10-12', editable: true, endDate: '2026-10-14' },
      { id: 3, title: 'Frozen', date: '2026-10-15', editable: false, time: '10:00' },
    ])
  })

  it('loads again when the range changes, and not for the same range', async () => {
    const { wrapper } = await mountView()
    const calendar = wrapper.findComponent(UidCalendar)

    calendar.vm.$emit('range', { view: 'week', start: '2026-10-05', end: '2026-10-11' })
    await flushPromises()
    calendar.vm.$emit('range', { view: 'week', start: '2026-10-05', end: '2026-10-11' })
    await flushPromises()

    expect(mock.history.post).toHaveLength(2)
    expect(JSON.parse(mock.history.post[1].data)).toMatchObject({ from: '2026-10-05', to: '2026-10-11' })
  })

  it('moves a timed event at once and saves it through calendarMove', async () => {
    mock.onPost('/tasks/calendarMove').reply(200, {
      success: true,
      payload: { event: { ...events[0], start: '2026-10-11 10:00', end: '2026-10-11 10:30' } },
    })
    const { wrapper } = await mountView()

    wrapper.findComponent(UidCalendar).vm.$emit('event-move', {
      event: shown(wrapper)[0], date: '2026-10-11', time: '10:00', endTime: '10:30',
    })
    await flushPromises()

    const move = mock.history.post.find((r) => r.url === '/tasks/calendarMove')
    expect(JSON.parse(move!.data)).toEqual({ id: 1, start: '2026-10-11 10:00', end: '2026-10-11 10:30' })
    expect(shown(wrapper)[0]).toMatchObject({ date: '2026-10-11', time: '10:00', endTime: '10:30' })
  })

  it('sends whole days for an all-day event', async () => {
    mock.onPost('/tasks/calendarMove').reply(200, { success: true, payload: { event: null } })
    const { wrapper } = await mountView()

    wrapper.findComponent(UidCalendar).vm.$emit('event-move', {
      event: shown(wrapper)[1], date: '2026-10-19', endDate: '2026-10-21',
    })
    await flushPromises()

    const move = mock.history.post.find((r) => r.url === '/tasks/calendarMove')
    expect(JSON.parse(move!.data)).toEqual({ id: 2, start: '2026-10-19', end: '2026-10-21' })
  })

  it('rolls the event back and shows the reason when the move is refused', async () => {
    mock.onPost('/tasks/calendarMove').reply(422, {
      success: false,
      payload: { errorKey: 'validation', message: 'Invalid', messages: { end: ['The end must be after the start.'] } },
    })
    const toast = vi.spyOn(adminToast, 'error')
    const { wrapper } = await mountView()

    wrapper.findComponent(UidCalendar).vm.$emit('event-move', {
      event: shown(wrapper)[0], date: '2026-10-11', time: '10:00', endTime: '10:30',
    })
    await flushPromises()

    expect(shown(wrapper)[0]).toMatchObject({ date: '2026-10-10', time: '09:00', endTime: '09:30' })
    expect(toast).toHaveBeenCalledWith('The end must be after the start.')
  })

  it('hands the kit a locked event as not editable, and ignores a move of one', async () => {
    const { wrapper } = await mountView()
    // The kit does not drag or resize it; a move reported anyway is not sent.
    expect(shown(wrapper)[2]).toMatchObject({ editable: false })

    wrapper.findComponent(UidCalendar).vm.$emit('event-move', { event: shown(wrapper)[2], date: '2026-10-16', time: '10:00' })
    await flushPromises()

    expect(mock.history.post.filter((r) => r.url === '/tasks/calendarMove')).toHaveLength(0)
  })

  it('offers create on an empty slot only when the user may create', async () => {
    const { wrapper } = await mountView()
    expect(wrapper.findComponent(UidCalendar).props('selectable')).toBe(true)
  })

  it('is read-only: the calendar is not editable and a move sends nothing', async () => {
    const { wrapper } = await mountView({ readonly: true })

    expect(wrapper.findComponent(UidCalendar).props('editable')).toBe(false)
    wrapper.findComponent(UidCalendar).vm.$emit('event-move', { event: shown(wrapper)[0], date: '2026-10-11', time: '10:00' })
    await flushPromises()
    expect(mock.history.post.filter((r) => r.url === '/tasks/calendarMove')).toHaveLength(0)
  })

  it('opens the record on an event click and create with the start on an empty slot', async () => {
    const { wrapper, router } = await mountView()
    const calendar = wrapper.findComponent(UidCalendar)

    calendar.vm.$emit('event-click', shown(wrapper)[0])
    await flushPromises()
    expect(router.currentRoute.value.fullPath).toBe('/r/tasks/1')

    calendar.vm.$emit('slot-click', { date: '2026-10-20', time: '14:30' })
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('admin.resource.tasks.create')
    expect(router.currentRoute.value.query).toEqual({ starts_at: '2026-10-20 14:30:00' })

    await router.push('/')
    calendar.vm.$emit('date-click', '2026-10-21')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ starts_at: '2026-10-21 00:00:00' })
  })

  it('prefills a date column with the date alone, and offers no create without the route', async () => {
    const { wrapper, router } = await mountView({ config: { ...config, date_only: true } })
    wrapper.findComponent(UidCalendar).vm.$emit('date-click', '2026-10-21')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ starts_at: '2026-10-21' })

    const other = await mountView({ createRoute: null })
    other.wrapper.findComponent(UidCalendar).vm.$emit('date-click', '2026-10-21')
    await flushPromises()
    expect(other.router.currentRoute.value.fullPath).toBe('/')
  })
})
