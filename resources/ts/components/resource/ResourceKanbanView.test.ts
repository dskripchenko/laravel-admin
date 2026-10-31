import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import { UidBoard, type BoardMove } from '@dskripchenko/ui'
import ResourceKanbanView from './ResourceKanbanView.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useResourceIndexStore } from '../../stores/resourceIndex'
import { adminToast } from '../../stores/toast'
import type { ResourceKanbanConfig } from '../../stores/manifest'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const config: ResourceKanbanConfig = {
  column: 'status',
  columns: null,
  lanes: null,
  lane_labels: null,
  limit: 2,
  order_by: 'position',
  saves_position: true,
  readonly: false,
  card: {},
}

const card = (id: number, value: string, extra: Record<string, unknown> = {}) => ({
  id, value, lane: null, title: `Card ${id}`, description: null, tags: [], assignee: null,
  due: null, overdue: false, locked: false, ...extra,
})

const boardPayload = () => ({
  success: true,
  payload: {
    limit: 2,
    lanes: null,
    columns: [
      { value: 'todo', label: 'To do', wip: null, total: 3, offset: 0, cards: [card(1, 'todo', { assignee: 'Ann', due: '2020-01-01', overdue: true, tags: ['ui'] }), card(2, 'todo')] },
      { value: 'done', label: 'Done', wip: 1, total: 2, offset: 0, cards: [card(3, 'done'), card(4, 'done')] },
    ],
  },
})

function mkRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: Stub },
      { path: '/r/tasks/:id', name: 'admin.resource.tasks.view', component: Stub },
    ],
  })
}

async function mountView(props: Record<string, unknown> = {}) {
  const router = mkRouter()
  await router.push('/')
  await router.isReady()
  const wrapper = mount(ResourceKanbanView, {
    props: { slug: 'tasks', config, canReorder: true, ...props },
    global: { plugins: [router] },
  })
  await flushPromises()
  return { wrapper, router }
}

const titles = (wrapper: ReturnType<typeof mount>, column: number): string[] =>
  wrapper.findAll('.uid-board__column')[column].findAll('.uid-board__card-title').map((n) => n.text())

describe('ResourceKanbanView', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    const index = useResourceIndexStore()
    index.setSlug('tasks')
    index.search = 'abc'
    index.filters = { priority: 'high' }
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
    vi.restoreAllMocks()
  })

  it('renders the columns and cards with totals, WIP, assignee and overdue due date', async () => {
    mock.onPost('/tasks/board').reply(200, boardPayload())
    const { wrapper } = await mountView()

    // The list's search and filters go with the board request.
    expect(JSON.parse(mock.history.post[0].data)).toEqual({ q: 'abc', filters: { priority: 'high' } })
    expect(wrapper.findAll('.uid-board__column')).toHaveLength(2)
    expect(titles(wrapper, 0)).toEqual(['Card 1', 'Card 2'])
    const counts = wrapper.findAll('[data-testid="kanban-count"]').map((n) => n.text())
    expect(counts).toEqual(['3', '2/1'])
    expect(wrapper.findAll('.admin-resource-kanban__count--over')).toHaveLength(1)
    expect(wrapper.text()).toContain('Ann')
    expect(wrapper.find('[data-testid="kanban-due"]').classes()).toContain('admin-resource-kanban__due--overdue')
  })

  it('fetches the next page of one column with "more"', async () => {
    mock.onPost('/tasks/board').replyOnce(200, boardPayload())
    mock.onPost('/tasks/board').replyOnce(200, {
      success: true,
      payload: { limit: 2, lanes: null, columns: [{ value: 'todo', label: 'To do', wip: null, total: 3, offset: 2, cards: [card(5, 'todo')] }] },
    })
    const { wrapper } = await mountView()

    const more = wrapper.findAll('[data-testid="kanban-more"]')
    expect(more).toHaveLength(1)
    await more[0].trigger('click')
    await flushPromises()

    expect(JSON.parse(mock.history.post[1].data)).toMatchObject({ column: 'todo', offset: 2 })
    expect(titles(wrapper, 0)).toEqual(['Card 1', 'Card 2', 'Card 5'])
    expect(wrapper.findAll('[data-testid="kanban-more"]')).toHaveLength(0)
  })

  it('moves a card at once and saves it through boardMove', async () => {
    mock.onPost('/tasks/board').reply(200, boardPayload())
    mock.onPost('/tasks/boardMove').reply(200, { success: true, payload: { card: card(2, 'done', { title: 'Saved' }) } })
    const { wrapper } = await mountView()

    const move: BoardMove = {
      card: { key: 2, title: 'Card 2' }, from: 'todo', to: 'done', beforeKey: 4, afterKey: 3, lane: null,
    }
    wrapper.findComponent(UidBoard).vm.$emit('move', move)
    await flushPromises()

    expect(JSON.parse(mock.history.post[1].data)).toEqual({
      q: 'abc', filters: { priority: 'high' }, id: 2, value: 'done', position: 1,
    })
    expect(titles(wrapper, 0)).toEqual(['Card 1'])
    expect(titles(wrapper, 1)).toEqual(['Card 3', 'Saved', 'Card 4'])
    expect(wrapper.findAll('[data-testid="kanban-count"]').map((n) => n.text())).toEqual(['2', '3/1'])
  })

  it('rolls the board back and shows the reason when the move is refused', async () => {
    mock.onPost('/tasks/board').reply(200, boardPayload())
    mock.onPost('/tasks/boardMove').reply(403, { success: false, payload: { errorKey: 'forbidden', message: 'Locked' } })
    const toast = vi.spyOn(adminToast, 'error')
    const { wrapper } = await mountView()

    wrapper.findComponent(UidBoard).vm.$emit('move', {
      card: { key: 1, title: 'Card 1' }, from: 'todo', to: 'done', beforeKey: null, afterKey: null, lane: null,
    } satisfies BoardMove)
    await flushPromises()

    expect(titles(wrapper, 0)).toEqual(['Card 1', 'Card 2'])
    expect(titles(wrapper, 1)).toEqual(['Card 3', 'Card 4'])
    expect(toast).toHaveBeenCalledWith('Locked')
  })

  it('sends no position when the user may not reorder', async () => {
    mock.onPost('/tasks/board').reply(200, boardPayload())
    mock.onPost('/tasks/boardMove').reply(200, { success: true, payload: { card: card(1, 'done') } })
    const { wrapper } = await mountView({ canReorder: false })

    wrapper.findComponent(UidBoard).vm.$emit('move', {
      card: { key: 1, title: 'Card 1' }, from: 'todo', to: 'done', beforeKey: null, afterKey: null, lane: null,
    } satisfies BoardMove)
    await flushPromises()

    expect(JSON.parse(mock.history.post[1].data)).not.toHaveProperty('position')
  })

  it('is read-only: the board gets readonly and a move sends nothing', async () => {
    mock.onPost('/tasks/board').reply(200, boardPayload())
    const { wrapper } = await mountView({ readonly: true })

    expect(wrapper.findComponent(UidBoard).props('readonly')).toBe(true)
    expect(wrapper.find('.uid-board__card').attributes('draggable')).toBe('false')
    wrapper.findComponent(UidBoard).vm.$emit('move', {
      card: { key: 1, title: 'Card 1' }, from: 'todo', to: 'done', beforeKey: null, afterKey: null, lane: null,
    } satisfies BoardMove)
    await flushPromises()
    expect(mock.history.post.filter((r) => r.url === '/tasks/boardMove')).toHaveLength(0)
  })

  it('keeps a locked card in its column and opens the record on click', async () => {
    const payload = boardPayload()
    payload.payload.columns[0].cards[0].locked = true
    mock.onPost('/tasks/board').reply(200, payload)
    const { wrapper, router } = await mountView()

    const board = wrapper.findComponent(UidBoard)
    const columns = board.props('columns') as Array<{ cards: Array<{ key: number; targets?: unknown }> }>
    expect(columns[0].cards[0].targets).toEqual(['todo'])
    expect(columns[0].cards[1].targets).toBeUndefined()

    await wrapper.find('.uid-board__card').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.fullPath).toBe('/r/tasks/1')
  })

  it('reloads when the search changes', async () => {
    mock.onPost('/tasks/board').reply(200, boardPayload())
    await mountView()
    useResourceIndexStore().search = 'other'
    await flushPromises()

    expect(mock.history.post).toHaveLength(2)
    expect(JSON.parse(mock.history.post[1].data).q).toBe('other')
  })
})
