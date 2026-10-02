import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h, nextTick, type PropType } from 'vue'
import ListenerLayout from './ListenerLayout.vue'
import { provideFormState, type FormStateContext } from '../render/formState'
import { provideListenerEndpoint } from '../render/listenerContext'
import { clearRegistry, hasLayout } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import type { AdminClient } from '../../api/client'
import { ValidationError } from '../../api/errors'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

interface Answer {
  state?: Record<string, unknown>
  layouts?: LayoutNode[]
}

type Post = (url: string, data: Record<string, unknown>, config?: { signal?: AbortSignal }) => Promise<Answer>

let post: ReturnType<typeof vi.fn<Post>>
let form: FormStateContext
let wrapper: VueWrapper | null = null

function field(name: string, label = name): LayoutNode {
  return { kind: 'field', type: 'text', name, label } as LayoutNode
}

function mountListener(
  initial: Record<string, unknown>,
  props: Record<string, unknown> = {},
  extra?: () => Record<string, unknown>,
): VueWrapper {
  const Host = defineComponent({
    props: { listenerProps: { type: Object as PropType<Record<string, unknown>>, required: true } },
    setup(p) {
      form = provideFormState(initial)
      provideListenerEndpoint({ url: () => '/contact/listener', extra })
      return () => h(ListenerLayout, p.listenerProps as never)
    },
  })
  wrapper = mount(Host, {
    props: {
      listenerProps: { id: 'cities', listen: ['country'], items: [field('city', 'City')], primed: true, ...props },
    },
  })
  return wrapper
}

beforeEach(() => {
  vi.useFakeTimers()
  clearRegistry()
  registerBuiltinComponents()
  post = vi.fn<Post>()
  setAdminClient({ post } as unknown as AdminClient)
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  clearAdminClient()
  vi.useRealTimers()
})

describe('ListenerLayout', () => {
  it('is registered under the type the backend emits', () => {
    expect(hasLayout('listener')).toBe(true)
  })

  it('debounces changes of the watched fields into one request', async () => {
    post.mockResolvedValue({ state: {}, layouts: [field('city', 'Город')] })
    mountListener({ country: 'de', city: null })

    form.setField('country', 'r')
    await nextTick()
    vi.advanceTimersByTime(100)
    form.setField('country', 'ru')
    await nextTick()
    vi.advanceTimersByTime(299)
    expect(post).not.toHaveBeenCalled()

    vi.advanceTimersByTime(1)
    expect(post).toHaveBeenCalledTimes(1)
    const [url, body] = post.mock.calls[0]
    expect(url).toBe('/contact/listener')
    expect(body).toEqual({ listener: 'cities', state: { country: 'ru', city: null } })
  })

  it('ignores changes of fields it does not watch', async () => {
    mountListener({ country: 'de', note: '' })
    form.setField('note', 'hello')
    await nextTick()
    vi.advanceTimersByTime(1000)
    expect(post).not.toHaveBeenCalled()
  })

  it('swaps its children for the ones that came back', async () => {
    post.mockResolvedValue({ state: {}, layouts: [field('city', 'Город'), field('district', 'Район')] })
    const w = mountListener({ country: 'de' })
    expect(w.findAll('label').map((l) => l.text())).toEqual(['City'])

    form.setField('country', 'ru')
    await nextTick()
    vi.advanceTimersByTime(300)
    await flushPromises()

    expect(w.text()).toContain('Город')
    expect(w.text()).toContain('Район')
  })

  it('merges the state patch without touching a field edited meanwhile', async () => {
    let resolve!: (a: Answer) => void
    post.mockImplementation(() => new Promise<Answer>((r) => { resolve = r }))
    mountListener({ price: 2, quantity: 1, total: 2, note: 'a' }, { listen: ['price', 'quantity'] })

    form.setField('quantity', 3)
    await nextTick()
    vi.advanceTimersByTime(300)
    // The person types into another field while the request is out.
    form.setField('note', 'typed')

    resolve({ state: { total: 6, note: 'server' }, layouts: [field('total')] })
    await flushPromises()

    expect(form.state.total).toBe(6)
    expect(form.state.note).toBe('typed')
  })

  it('does not trigger itself when the patch changes a watched field', async () => {
    post.mockResolvedValue({ state: { country: 'RU' }, layouts: [] })
    mountListener({ country: 'de' })

    form.setField('country', 'ru')
    await nextTick()
    vi.advanceTimersByTime(300)
    await flushPromises()
    expect(form.state.country).toBe('RU')

    vi.advanceTimersByTime(1000)
    expect(post).toHaveBeenCalledTimes(1)
  })

  it('cancels a stale request and drops its answer', async () => {
    const resolvers: Array<(a: Answer) => void> = []
    const signals: AbortSignal[] = []
    post.mockImplementation((_u, _b, config) => {
      if (config?.signal) signals.push(config.signal)
      return new Promise<Answer>((r) => resolvers.push(r))
    })
    const w = mountListener({ country: 'de' })

    form.setField('country', 'ru')
    await nextTick()
    vi.advanceTimersByTime(300)
    form.setField('country', 'fr')
    await nextTick()
    vi.advanceTimersByTime(300)

    expect(post).toHaveBeenCalledTimes(2)
    expect(signals[0].aborted).toBe(true)

    resolvers[1]({ state: {}, layouts: [field('city', 'Paris')] })
    await flushPromises()
    resolvers[0]({ state: {}, layouts: [field('city', 'Moscow')] })
    await flushPromises()

    expect(w.text()).toContain('Paris')
    expect(w.text()).not.toContain('Moscow')
  })

  it('shows a loading state while the request is out', async () => {
    let resolve!: (a: Answer) => void
    post.mockImplementation(() => new Promise<Answer>((r) => { resolve = r }))
    const w = mountListener({ country: 'de' })

    form.setField('country', 'ru')
    await nextTick()
    vi.advanceTimersByTime(300)
    await nextTick()
    expect(w.find('.admin-listener').attributes('aria-busy')).toBe('true')

    resolve({ state: {}, layouts: [] })
    await flushPromises()
    expect(w.find('.admin-listener').attributes('aria-busy')).toBe('false')
  })

  it('puts validation errors on the fields', async () => {
    post.mockRejectedValue(new ValidationError({ errorKey: 'validation', message: 'bad', messages: { country: ['Unknown'] } }))
    mountListener({ country: 'de' })

    form.setField('country', 'xx')
    await nextTick()
    vi.advanceTimersByTime(300)
    await flushPromises()

    expect(form.errors.country).toEqual(['Unknown'])
  })

  it('syncs an unprimed listener once on mount without changing values', async () => {
    post.mockResolvedValue({ state: { city: 'msk' }, layouts: [field('city', 'Moscow')] })
    const w = mountListener({ country: 'ru', city: null }, { primed: false }, () => ({ context: 'update', id: 5 }))
    await flushPromises()

    expect(post).toHaveBeenCalledTimes(1)
    expect(post.mock.calls[0][1]).toMatchObject({ context: 'update', id: 5, listener: 'cities' })
    expect(w.text()).toContain('Moscow')
    expect(form.state.city).toBeNull()
  })

  it('does not ask on mount when the watched fields are empty', async () => {
    mountListener({ country: null }, { primed: false })
    await flushPromises()
    expect(post).not.toHaveBeenCalled()
  })
})
