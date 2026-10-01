import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, createWebHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import { setAdminClient, clearAdminClient } from '../stores/registry'
import { createAdminClient } from '../api/client'
import {
  modalSizeOf,
  needsSelection,
  normalizeAction,
  normalizeActions,
  selectionAllows,
  toRouterPath,
  useActionRunner,
  type ActionExecutor,
  type ActionRunner,
  type AdminAction,
} from './useActionRunner'
import AdminActionDialogs from '../components/actions/AdminActionDialogs.vue'
import AdminActionButton from '../components/actions/AdminActionButton.vue'
import { registerBuiltinComponents } from '../components/render/builtin'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

function mkRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: Stub },
      { path: '/r/posts', name: 'posts', component: Stub },
      { path: '/:pathMatch(.*)*', name: 'notFound', component: Stub },
    ],
  })
}

const act = (raw: Record<string, unknown>): AdminAction => normalizeAction({
  kind: 'action',
  name: 'act',
  label: 'Act',
  type: 'button',
  position: ['command_bar'],
  attributes: {},
  ...raw,
})!

/** Mounts a host component owning a runner, with the dialogs rendered. */
async function mountRunner(executor: ActionExecutor, router: Router = mkRouter()) {
  let runner!: ActionRunner
  const Host = defineComponent({
    setup() {
      runner = useActionRunner(executor)
      return () => h(AdminActionDialogs, { runner })
    },
  })
  await router.push('/')
  await router.isReady()
  const wrapper = mount(Host, { global: { plugins: [router] }, attachTo: document.body })
  return { wrapper, runner: () => runner, router }
}

const $ = (testid: string): HTMLElement | null =>
  document.body.querySelector(`[data-testid="${testid}"]`)

describe('normalizeAction', () => {
  it('keeps an object confirm as is and wraps a legacy string one', () => {
    expect(act({ confirm: { message: 'Sure?', title: 'Heads up' } }).confirm)
      .toEqual({ message: 'Sure?', title: 'Heads up', confirmLabel: undefined, cancelLabel: undefined })
    expect(act({ confirm: 'Sure?' }).confirm).toEqual({ message: 'Sure?' })
    expect(act({ confirm: null }).confirm).toBeNull()
  })

  it('normalizes the nested items of a dropdown', () => {
    const d = act({ type: 'dropdown', items: [{ name: 'a', label: 'A', type: 'link' }, { label: '' }] })
    expect(d.items.map((i) => i.name)).toEqual(['a'])
    expect(normalizeActions(undefined)).toEqual([])
  })

  it('needsSelection: bulk always, standalone never, otherwise by position', () => {
    expect(needsSelection(act({}))).toBe(false)
    expect(needsSelection(act({ position: ['header'] }))).toBe(false)
    expect(needsSelection(act({ position: ['row'] }))).toBe(true)
    expect(needsSelection(act({ position: ['row'], attributes: { standalone: true } }))).toBe(false)
    expect(needsSelection(act({ type: 'bulk', position: ['command_bar'] }))).toBe(true)
    expect(needsSelection(act({ type: 'bulk', attributes: { standalone: true } }))).toBe(true)
  })

  it('maps the modal sizes, full included', () => {
    expect(modalSizeOf('full')).toBe('full')
    expect(modalSizeOf('fullscreen')).toBe('full')
    expect(modalSizeOf('extra')).toBe('xl')
    expect(modalSizeOf(undefined)).toBe('md')
  })

  it('checks requiresAtLeast / requiresAtMost', () => {
    const a = act({ type: 'bulk', attributes: { requiresAtLeast: 2, requiresAtMost: 3 } })
    expect(selectionAllows(a, 1)).toBe(false)
    expect(selectionAllows(a, 2)).toBe(true)
    expect(selectionAllows(a, 4)).toBe(false)
  })
})

describe('toRouterPath', () => {
  it('strips the router base and rejects external and unmatched paths', () => {
    const router = createRouter({
      history: createWebHistory('/admin'),
      routes: [
        { path: '/r/posts', component: Stub },
        { path: '/:pathMatch(.*)*', component: Stub },
      ],
    })
    expect(toRouterPath(router, '/admin/r/posts/create')).toBe('/r/posts/create')
    expect(toRouterPath(router, '/r/posts')).toBe('/r/posts')
    expect(toRouterPath(router, '/storage/file.pdf')).toBeNull()
    expect(toRouterPath(router, 'https://example.com')).toBeNull()
    expect(toRouterPath(router, '//cdn.example.com/x')).toBeNull()
  })
})

describe('useActionRunner', () => {
  let mock: MockAdapter
  let wrapper: VueWrapper | null = null

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    registerBuiltinComponents()
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    mock.reset()
    clearAdminClient()
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('shows an object confirm in a dialog and runs only after OK', async () => {
    const execute = vi.fn().mockResolvedValue({})
    const m = await mountRunner({ execute })
    wrapper = m.wrapper
    const action = act({ confirm: { message: 'Delete everything?', title: 'Careful' } })

    const first = m.runner().run(action)
    await flushPromises()
    expect($('action-confirm-message')?.textContent).toContain('Delete everything?')
    expect(document.body.textContent).toContain('Careful')
    $('action-confirm-cancel')!.click()
    await first
    expect(execute).not.toHaveBeenCalled()

    const second = m.runner().run(action)
    await flushPromises()
    $('action-confirm-ok')!.click()
    await second
    expect(execute).toHaveBeenCalledTimes(1)
  })

  it('ModalAction opens its form, sends the values as payload and shows 422 inline', async () => {
    const { ValidationError } = await import('../api/errors')
    const execute = vi.fn()
      .mockRejectedValueOnce(new ValidationError({
        errorKey: 'validation',
        message: 'invalid',
        messages: { 'payload.reason': ['The reason is too short.'] },
      }))
      .mockResolvedValueOnce({ affected: 1 })
    const onSuccess = vi.fn()
    const m = await mountRunner({ execute, onSuccess })
    wrapper = m.wrapper
    const action = act({
      type: 'modal',
      name: 'notify',
      attributes: {
        method: 'notify',
        modalSize: 'large',
        submitLabel: 'Send',
        fields: [{ kind: 'field', type: 'input', name: 'reason', label: 'Reason', defaultValue: 'x' }],
      },
    })

    await m.runner().run(action)
    await flushPromises()
    expect($('action-modal')).not.toBeNull()
    expect(m.runner().modalState.size).toBe('lg')
    const input = document.body.querySelector('[data-testid="action-form"] input') as HTMLInputElement
    expect(input.value).toBe('x')
    input.value = 'ok'
    input.dispatchEvent(new Event('input'))
    await flushPromises()

    $('action-modal-submit')!.click()
    await flushPromises()
    expect(execute).toHaveBeenLastCalledWith(action, { reason: 'ok' }, undefined)
    // The form stays open with the error next to the field.
    expect($('action-modal')).not.toBeNull()
    expect(document.body.textContent).toContain('The reason is too short.')

    $('action-modal-submit')!.click()
    await flushPromises()
    expect(execute).toHaveBeenCalledTimes(2)
    expect(onSuccess).toHaveBeenCalledWith(action, { affected: 1 }, undefined)
    expect(m.runner().modalState.open).toBe(false)
  })

  it('keeps the ids of a run until its modal form is submitted', async () => {
    const execute = vi.fn(async () => ({ affected: 1 }))
    const onSuccess = vi.fn()
    const m = await mountRunner({ execute, onSuccess, ids: () => [1, 2, 3] })
    wrapper = m.wrapper
    const action = act({ type: 'modal', position: ['row'], attributes: { method: 'notify', fields: [] } })

    await m.runner().run(action, { ids: [7] })
    await flushPromises()
    $('action-modal-submit')!.click()
    await flushPromises()
    expect(execute).toHaveBeenLastCalledWith(action, {}, { ids: [7] })
    expect(onSuccess).toHaveBeenCalledWith(action, { affected: 1 }, { ids: [7] })
  })

  it("checks a row run's own ids against requiresAtMost, not the selection", async () => {
    const execute = vi.fn(async () => ({}))
    const m = await mountRunner({ execute, ids: () => [1, 2, 3] })
    wrapper = m.wrapper
    const action = act({ type: 'bulk', position: ['bulk', 'row'], attributes: { requiresAtMost: 1 } })
    expect(await m.runner().run(action, { ids: [7] })).toBe(true)
    expect(execute).toHaveBeenLastCalledWith(action, undefined, { ids: [7] })
    expect(await m.runner().run(action)).toBe(false)
  })

  it('AsyncAction starts a delayed process and polls it to the end', async () => {
    vi.useFakeTimers()
    const refresh = vi.fn()
    let runBody: Record<string, unknown> | null = null
    mock.onPost('/delayed/run').reply((config) => {
      runBody = JSON.parse(config.data)
      return [200, { success: true, payload: { uuid: 'u-1', status: 'new' } }]
    })
    const statuses = [
      { status: 'wait', progress: 40 },
      { status: 'done', progress: 100, data: { message: 'Exported 10 rows' } },
    ]
    const polled: string[] = []
    mock.onGet(/\/delayed\/status/).reply((config) => {
      polled.push(String(config.url))
      return [200, { success: true, payload: { uuid: 'u-1', ...statuses.shift() } }]
    })
    const m = await mountRunner({ execute: vi.fn(), ids: () => [7, 8], refresh })
    wrapper = m.wrapper
    const action = act({
      type: 'async',
      position: ['bulk'],
      attributes: {
        handler: { entity: 'App\\Jobs\\Export', method: 'run' },
        params: { format: 'csv' },
        pollInterval: 1,
      },
    })

    const done = m.runner().run(action)
    await vi.advanceTimersByTimeAsync(0)
    expect(runBody).toEqual({
      entity: 'App\\Jobs\\Export',
      method: 'run',
      params: { format: 'csv', ids: [7, 8] },
    })
    expect($('action-async')).not.toBeNull()

    await vi.advanceTimersByTimeAsync(1000)
    expect(m.runner().asyncState.progress).toBe(40)
    await vi.advanceTimersByTimeAsync(1000)
    await done
    expect(polled).toEqual(['/delayed/status?uuid=u-1', '/delayed/status?uuid=u-1'])
    expect(m.runner().asyncState.finished).toBe(true)
    expect(m.runner().asyncState.progress).toBe(100)
    expect(refresh).toHaveBeenCalled()
  })

  it('AsyncAction reports a failed process', async () => {
    vi.useFakeTimers()
    mock.onPost('/delayed/run').reply(200, { success: true, payload: { uuid: 'u-2', status: 'new' } })
    mock.onGet(/\/delayed\/status/).reply(200, {
      success: true,
      payload: { uuid: 'u-2', status: 'error', progress: 10, error: 'Disk full' },
    })
    const refresh = vi.fn()
    const m = await mountRunner({ execute: vi.fn(), refresh })
    wrapper = m.wrapper
    const done = m.runner().run(act({
      type: 'async',
      attributes: { handler: { entity: 'E', method: 'm' } },
    }))
    await vi.advanceTimersByTimeAsync(2000)
    await done
    expect(m.runner().asyncState.error).toBe('Disk full')
    expect(refresh).not.toHaveBeenCalled()
  })

  it('Link goes through the router inside the panel and leaves it otherwise', async () => {
    const m = await mountRunner({ execute: vi.fn() })
    wrapper = m.wrapper
    const push = vi.spyOn(m.router, 'push')
    await m.runner().run(act({ type: 'link', attributes: { href: '/r/posts' } }))
    expect(push).toHaveBeenCalledWith('/r/posts')

    const open = vi.spyOn(window, 'open').mockReturnValue(null)
    await m.runner().run(act({ type: 'link', attributes: { href: 'https://example.com', target: '_blank' } }))
    expect(open).toHaveBeenCalledWith('https://example.com', '_blank', 'noopener')
  })

  it('a bulk action outside its selection bounds does not run', async () => {
    const execute = vi.fn()
    const m = await mountRunner({ execute, ids: () => [1] })
    wrapper = m.wrapper
    await m.runner().run(act({ type: 'bulk', position: ['bulk'], attributes: { requiresAtLeast: 2 } }))
    expect(execute).not.toHaveBeenCalled()
  })
})

describe('AdminActionButton', () => {
  it('renders a dropdown as a menu and emits its nested actions', async () => {
    const dropdown = act({
      type: 'dropdown',
      name: 'more',
      label: 'More',
      items: [
        { name: 'restore', label: 'Restore', type: 'button', attributes: { method: 'restore' } },
        { name: 'audit', label: 'Audit', type: 'link', attributes: { href: '/audit' } },
      ],
    })
    const wrapper = mount(AdminActionButton, { props: { action: dropdown }, attachTo: document.body })
    await wrapper.find('[data-testid="action-more"]').trigger('click')
    await flushPromises()
    const item = document.body.querySelector('[data-testid="action-audit"]') as HTMLElement
    expect(item).not.toBeNull()
    item.click()
    await flushPromises()
    const emitted = wrapper.emitted('run') as Array<[AdminAction]>
    expect(emitted[0][0].name).toBe('audit')
    wrapper.unmount()
  })

  it('renders a nested dropdown as a submenu', async () => {
    const dropdown = act({
      type: 'dropdown',
      name: 'more',
      label: 'More',
      items: [
        { name: 'restore', label: 'Restore', type: 'button', attributes: { method: 'restore' } },
        {
          name: 'export',
          label: 'Export',
          type: 'dropdown',
          items: [{ name: 'csv', label: 'CSV', type: 'button', attributes: { method: 'csv' } }],
        },
      ],
    })
    const wrapper = mount(AdminActionButton, { props: { action: dropdown }, attachTo: document.body })
    await wrapper.find('[data-testid="action-more"]').trigger('click')
    await flushPromises()
    const trigger = document.body.querySelector('.uid-submenu__trigger') as HTMLElement
    expect(trigger.textContent).toContain('Export')
    expect(document.body.querySelector('[data-testid="action-csv"]')).toBeNull()
    trigger.click()
    await flushPromises()
    ;(document.body.querySelector('[data-testid="action-csv"]') as HTMLElement).click()
    await flushPromises()
    const emitted = wrapper.emitted('run') as Array<[AdminAction]>
    expect(emitted[0][0].name).toBe('csv')
    wrapper.unmount()
  })
})
