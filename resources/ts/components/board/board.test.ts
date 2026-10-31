import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import type { SeqBatch } from '@dskripchenko/board'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { createAdminBoardProvider } from './adminBoardProvider'
import { setBoardLoaders, setBoardRealtime, type RealtimeChannelLike, type RealtimeClientLike } from '../documents/loaders'
import BoardLayout from '../layouts/BoardLayout.vue'

const ok = (payload: unknown) => [200, { success: true, payload }] as const
const fail = (status: number, message = 'No') => [status, { success: false, payload: { errorKey: 'x', message } }] as const
const batch = (seq: number, client = 'other'): SeqBatch => ({ client, cseq: seq, seq, ops: [] })
const SNAPSHOT = { board: { version: 1, objects: {}, order: [] }, seq: 5 }

let mock: MockAdapter
const posts = (url: string) => mock.history.post.filter((r) => r.url === url).map((r) => JSON.parse(r.data ?? '{}'))

function setHidden(hidden: boolean): void {
  Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => (hidden ? 'hidden' : 'visible') })
}

beforeEach(() => {
  setActivePinia(createPinia())
  const c = createAdminClient({ baseURL: 'http://api.test' })
  setAdminClient(c)
  mock = new MockAdapter(c.raw)
  mock.onPost('/boards/1/snapshot').reply(...ok(SNAPSHOT))
  setHidden(false)
  vi.useFakeTimers()
})

afterEach(() => {
  vi.useRealTimers()
  mock.reset()
  clearAdminClient()
  setBoardLoaders(null)
  vi.restoreAllMocks()
})

describe('adminBoardProvider', () => {
  it('loads the snapshot through the panel client and reports online', async () => {
    const p = createAdminBoardProvider({ endpoint: '/boards/1' })
    const statuses: string[] = []
    p.status!.on((s) => statuses.push(s))
    const snap = await p.load()
    expect(snap).toEqual(SNAPSHOT)
    expect(statuses).toEqual(['connecting', 'online'])
    p.close!()
  })

  it('sends batches one at a time, in order, retrying a failed one with back-off', async () => {
    mock.onPost('/boards/1/batches').replyOnce(500).onPost('/boards/1/batches').reply(...ok({ seq: 6 }))
    const p = createAdminBoardProvider({ endpoint: '/boards/1' })
    await p.load()
    p.send({ client: 'me', cseq: 1, ops: [] })
    p.send({ client: 'me', cseq: 2, ops: [] })
    await vi.advanceTimersByTimeAsync(0)
    expect(posts('/boards/1/batches').map((b) => b.cseq)).toEqual([1])
    await vi.advanceTimersByTimeAsync(1000)
    expect(posts('/boards/1/batches').map((b) => b.cseq)).toEqual([1, 1, 2])
    p.close!()
  })

  it('does not retry a refused batch and reports it', async () => {
    mock.onPost('/boards/1/batches').reply(...fail(403, 'Read only'))
    const rejected = vi.fn()
    const p = createAdminBoardProvider({ endpoint: '/boards/1', onRejected: rejected })
    await p.load()
    p.send({ client: 'me', cseq: 1, ops: [] })
    await vi.advanceTimersByTimeAsync(5000)
    expect(posts('/boards/1/batches')).toHaveLength(1)
    expect(rejected).toHaveBeenCalledTimes(1)
    p.close!()
  })

  it('sends nothing when read-only', async () => {
    const p = createAdminBoardProvider({ endpoint: '/boards/1', readonly: true })
    await p.load()
    p.send({ client: 'me', cseq: 1, ops: [] })
    await vi.advanceTimersByTimeAsync(100)
    expect(posts('/boards/1/batches')).toHaveLength(0)
    p.close!()
  })

  it('polls after the last batch and hands batches on in the server order; a hidden tab does not poll', async () => {
    mock.onPost('/boards/1/poll').replyOnce(...ok({ batches: [batch(7), batch(6)] })).onPost('/boards/1/poll').reply(...ok({ batches: [] }))
    const p = createAdminBoardProvider({ endpoint: '/boards/1', pollInterval: 1000 })
    const got: number[] = []
    p.onBatch((b) => got.push(b.seq))
    await p.load()
    await vi.advanceTimersByTimeAsync(1000)
    expect(posts('/boards/1/poll')).toEqual([{ after: 5 }])
    expect(got).toEqual([6, 7])
    await vi.advanceTimersByTimeAsync(1000)
    expect(posts('/boards/1/poll')[1]).toEqual({ after: 7 })

    setHidden(true)
    await vi.advanceTimersByTimeAsync(5000)
    expect(posts('/boards/1/poll')).toHaveLength(2)
    setHidden(false)
    document.dispatchEvent(new Event('visibilitychange'))
    await vi.advanceTimersByTimeAsync(0)
    expect(posts('/boards/1/poll')).toHaveLength(3)
    p.close!()
  })

  it('asks for a reload on 410 and stops polling', async () => {
    mock.onPost('/boards/1/poll').reply(...fail(410, 'Too old'))
    const reload = vi.fn()
    const p = createAdminBoardProvider({ endpoint: '/boards/1', pollInterval: 1000, onReload: reload })
    await p.load()
    await vi.advanceTimersByTimeAsync(1000)
    expect(reload).toHaveBeenCalledTimes(1)
    await vi.advanceTimersByTimeAsync(5000)
    expect(posts('/boards/1/poll')).toHaveLength(1)
    p.close!()
  })

  it('sends presence at most 10 times a second, the latest position last', async () => {
    mock.onPost('/boards/1/presence').reply(...ok({ presence: [{ client: 'other', name: 'Ann' }] }))
    const p = createAdminBoardProvider({ endpoint: '/boards/1' })
    const seen = vi.fn()
    p.presence!.on(seen)
    await p.load()
    p.presence!.send({ client: 'me', cursor: { x: 1, y: 1 } })
    p.presence!.send({ client: 'me', cursor: { x: 2, y: 2 } })
    p.presence!.send({ client: 'me', cursor: { x: 3, y: 3 } })
    await vi.advanceTimersByTimeAsync(0)
    expect(posts('/boards/1/presence').map((x) => x.cursor.x)).toEqual([1])
    await vi.advanceTimersByTimeAsync(100)
    expect(posts('/boards/1/presence').map((x) => x.cursor.x)).toEqual([1, 3])
    expect(seen).toHaveBeenCalledWith([{ client: 'other', name: 'Ann' }])
    p.close!()
  })

  it('follows the realtime channel when the client is registered, catching up through poll', async () => {
    mock.onPost('/boards/1/poll').reply(...ok({ batches: [batch(6)] }))
    const handlers: Record<string, (data: unknown, meta: { seq?: number }) => void> = {}
    let subscribed: () => void = () => {}
    const channel: RealtimeChannelLike = {
      on: (event, handler) => ((handlers[event] = handler), () => {}),
      onSubscribed: (handler) => ((subscribed = handler), () => {}),
      onResync: () => () => {},
      unsubscribe: vi.fn(),
    }
    const created: { options: unknown; channel: string | null } = { options: null, channel: null }
    class FakeClient implements RealtimeClientLike {
      constructor(options: unknown) {
        created.options = options
      }
      connect = vi.fn(async () => {})
      close = vi.fn()
      private(name: string) {
        created.channel = name
        return channel
      }
      on() {
        return () => {}
      }
    }
    setBoardRealtime(FakeClient)
    const p = createAdminBoardProvider({ endpoint: '/boards/1', pollInterval: 1000, realtime: { ticketUrl: '/realtime/ticket', channel: 'admin-board.1' } })
    const got: number[] = []
    p.onBatch((b) => got.push(b.seq))
    await p.load()
    await vi.advanceTimersByTimeAsync(0)
    expect(created.channel).toBe('admin-board.1')
    expect(created.options).toMatchObject({ ticketUrl: '/realtime/ticket', credentials: 'same-origin' })

    // A (re)subscription catches up over HTTP; no periodic polling after that.
    subscribed()
    await vi.advanceTimersByTimeAsync(0)
    expect(got).toEqual([6])
    handlers.batch!(batch(7), { seq: 1 })
    expect(got).toEqual([6, 7])
    await vi.advanceTimersByTimeAsync(5000)
    expect(posts('/boards/1/poll')).toHaveLength(1)
    p.close!()
    expect(channel.unsubscribe).toHaveBeenCalled()
  })

  it('polls, warning once, when realtime is configured but no client is registered', async () => {
    mock.onPost('/boards/1/poll').reply(...ok({ batches: [] }))
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    const p = createAdminBoardProvider({ endpoint: '/boards/1', pollInterval: 1000, realtime: { ticketUrl: '/t', channel: 'admin-board.1' } })
    await p.load()
    await vi.advanceTimersByTimeAsync(1000)
    expect(posts('/boards/1/poll')).toHaveLength(1)
    expect(warn.mock.calls.length).toBeLessThanOrEqual(1)
    p.close!()
  })
})

describe('BoardLayout', () => {
  it('mounts the editor with the panel provider, read-only as told', async () => {
    vi.useRealTimers()
    const Editor = defineComponent({
      name: 'BoardEditorStub',
      props: ['provider', 'readonly', 'user', 'locale'],
      setup: () => () => h('div', { class: 'board-stub' }),
    })
    setBoardLoaders({ board: async () => ({ BoardEditor: Editor }) as never })
    const wrapper = mount(BoardLayout, {
      props: { endpoint: '/boards/1', readonly: true, user: { id: '7', name: 'Anna', color: null } },
    })
    await flushPromises()
    const editor = wrapper.findComponent(Editor)
    expect(editor.exists()).toBe(true)
    expect(editor.props('readonly')).toBe(true)
    expect(editor.props('user')).toEqual({ id: '7', name: 'Anna' })
    expect(typeof (editor.props('provider') as { load: unknown }).load).toBe('function')
    wrapper.unmount()
  })
})
