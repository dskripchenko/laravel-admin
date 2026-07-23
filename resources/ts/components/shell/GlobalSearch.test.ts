import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { createAdminClient } from '../../api/client'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { useMenuStore } from '../../stores/menu'
import GlobalSearch from './GlobalSearch.vue'

const pushSpy = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushSpy }),
}))

const wait = (ms: number) => new Promise((r) => setTimeout(r, ms))

describe('GlobalSearch (⌘K палитра)', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
    pushSpy.mockClear()
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('shows a hint until 2+ characters are typed', async () => {
    const w = mount(GlobalSearch, { props: { modelValue: true }, global: { stubs: { teleport: true } } })
    expect(w.text()).toContain('минимум 2 символа')
  })

  it('filters menu sections client-side (nav group)', async () => {
    const menu = useMenuStore()
    menu.items = [
      { key: 'clients', label: 'Клиенты', url: '/r/clients' },
      { key: 'tpl', label: 'Шаблоны', url: '/r/templates' },
    ]
    mock.onGet('/system/search').reply(200, { success: true, payload: { query: 'клиент', groups: [] } })

    const w = mount(GlobalSearch, { props: { modelValue: true }, global: { stubs: { teleport: true } } })
    await w.find('input').setValue('клиент')
    await wait(300)
    await flushPromises()

    expect(w.text()).toContain('Разделы')
    expect(w.text()).toContain('Клиенты')
    expect(w.text()).not.toContain('Шаблоны')
  })

  it('renders server record results and navigates on click', async () => {
    mock.onGet('/system/search').reply(200, {
      success: true,
      payload: {
        query: 'ромашка',
        groups: [
          {
            slug: 'clients',
            label: 'Клиенты',
            icon: 'building-2',
            items: [{ id: 5, title: 'Ромашка', subtitle: 'romashka@x.io', url: '/r/clients/5' }],
            hasMore: false,
            moreUrl: '/r/clients',
          },
        ],
      },
    })

    const w = mount(GlobalSearch, { props: { modelValue: true }, global: { stubs: { teleport: true } } })
    await w.find('input').setValue('ромашка')
    await wait(300)
    await flushPromises()

    expect(w.text()).toContain('Ромашка')
    expect(w.text()).toContain('romashka@x.io')

    await w.find('.admin-search__item').trigger('click')
    expect(pushSpy).toHaveBeenCalledWith('/r/clients/5')
    expect(w.emitted('update:modelValue')?.at(-1)).toEqual([false])
  })

  it('Enter activates the first result', async () => {
    mock.onGet('/system/search').reply(200, {
      success: true,
      payload: {
        query: 'test',
        groups: [
          {
            slug: 'clients', label: 'Клиенты', icon: null,
            items: [{ id: 1, title: 'Test One', subtitle: null, url: '/r/clients/1' }],
            hasMore: false, moreUrl: '/r/clients',
          },
        ],
      },
    })

    const w = mount(GlobalSearch, { props: { modelValue: true }, global: { stubs: { teleport: true } } })
    await w.find('input').setValue('test')
    await wait(300)
    await flushPromises()

    await w.find('input').trigger('keydown', { key: 'Enter' })
    expect(pushSpy).toHaveBeenCalledWith('/r/clients/1')
  })
})
