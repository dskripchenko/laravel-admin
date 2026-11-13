/**
 * A link with `target: 'drawer'`: the list (filtered, scoped) or the screen
 * opens in the side panel over the page, and the page's address stays.
 */
import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import AdminLinkDrawer from './AdminLinkDrawer.vue'
import AdminTableCell from '../resource/AdminTableCell.vue'
import { useLinkDrawerStore } from '../../stores/linkDrawer'
import { useActionRunner } from '../../composables/useActionRunner'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useManifestStore } from '../../stores/manifest'
import { useAuthStore } from '../../stores/auth'

const Stub = defineComponent({ name: 'Stub', render: () => h('div', 'page') })

function mkRouter(): Router {
  return createRouter({
    history: createMemoryHistory('/admin'),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/contracts/1', name: 'contract', component: Stub },
      { path: '/r/claims', name: 'admin.resource.claims.index', component: Stub, meta: { kind: 'resource', slug: 'claims', title: 'Обращения', permissions: ['admin.claims.view'] } },
      { path: '/screens/client', name: 'admin.screen.client', component: Stub, meta: { kind: 'screen', slug: 'client', title: 'Всё по клиенту', permissions: ['admin.client'], permissionMode: 'all' } },
      { path: '/profile', name: 'admin.profile', component: Stub },
    ],
  })
}

function seed(): void {
  useManifestStore().manifest = {
    version: 'v1',
    locale: 'ru',
    resources: [{
      slug: 'claims',
      label: 'Обращения',
      permissions: { view: 'admin.claims.view' },
      fields: [],
      columns: [{ type: 'text', key: 'subject', label: 'Тема' }],
      filters: [{ name: 'phone', label: 'Телефон', type: 'input' }],
      actions: [],
      searchable: [],
      with: [],
      features: {},
    }],
    screens: [],
    settings: [],
    dashboards: [],
    plugins: [],
    permissions: [],
  }
}

function grant(permissions: string[]): void {
  const auth = useAuthStore()
  auth.permissions = permissions as never
}

describe('the link drawer', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    seed()
  })
  afterEach(() => {
    mock.reset()
    clearAdminClient()
    document.body.innerHTML = ''
  })

  async function mountDrawer(router: Router) {
    await router.push('/contracts/1')
    await router.isReady()
    return mount(AdminLinkDrawer, { global: { plugins: [router] }, attachTo: document.body })
  }

  it('opens a filtered, scoped list without touching the address', async () => {
    grant(['admin.claims.view'])
    mock.onPost('/claims/search').reply(200, { success: true, payload: { data: [{ id: 1, subject: 'Возврат' }], meta: { page: 1, per_page: 20, total: 1, last_page: 1 } } })
    const router = mkRouter()
    await mountDrawer(router)
    useLinkDrawerStore().open('/admin/r/claims?filter[phone]=79001234567&scope=open', { title: 'Обращения клиента' })
    await flushPromises()

    const panel = document.querySelector('[data-testid="link-drawer"]')
    expect(panel?.getAttribute('data-kind')).toBe('resource')
    const body = JSON.parse(mock.history.post[0].data)
    expect(body.filter ?? body.filters).toEqual({ phone: '79001234567' })
    expect(body.scope).toBe('open')
    expect(router.currentRoute.value.fullPath).toBe('/contracts/1')
    expect(document.body.textContent).toContain('Возврат')
  })

  it('says which key is missing instead of loading', async () => {
    grant([])
    const router = mkRouter()
    await mountDrawer(router)
    useLinkDrawerStore().open('/admin/screens/client?phone=7900')
    await flushPromises()
    expect(document.querySelector('[data-testid="state-forbidden"]')?.textContent).toContain('admin.client')
    expect(mock.history.get).toHaveLength(0)
  })

  it('opens anything else as a page', async () => {
    const router = mkRouter()
    await mountDrawer(router)
    useLinkDrawerStore().open('/admin/profile')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/profile')
    expect(useLinkDrawerStore().url).toBeNull()
  })

  it('«Открыть страницей» goes to the list with its query', async () => {
    grant(['admin.claims.view'])
    mock.onPost('/claims/search').reply(200, { success: true, payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } } })
    const router = mkRouter()
    await mountDrawer(router)
    useLinkDrawerStore().open('/admin/r/claims?filter[phone]=7900')
    await flushPromises()
    ;(document.querySelector('[data-testid="link-drawer-page"]') as HTMLElement).click()
    await flushPromises()
    expect(router.currentRoute.value.fullPath).toBe('/r/claims?filter[phone]=7900')
  })
})

describe('what opens the drawer', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('a link action with target drawer', async () => {
    const router = mkRouter()
    await router.push('/contracts/1')
    let runner: ReturnType<typeof useActionRunner> | null = null
    mount(defineComponent({ setup() { runner = useActionRunner({ execute: async () => undefined }); return () => h('div') } }), { global: { plugins: [router] } })
    await runner!.run({ name: 'claims', label: 'Обращения · 2', type: 'link', position: [], attributes: { href: '/admin/r/claims?q=x', target: 'drawer', drawerSize: 'lg' } } as never)
    const store = useLinkDrawerStore()
    expect(store.url).toBe('/admin/r/claims?q=x')
    expect(store.title).toBe('Обращения · 2')
    expect(store.size).toBe('lg')
  })

  it('a link column with target drawer', async () => {
    const router = mkRouter()
    const w = mount(AdminTableCell, {
      props: { value: 'Обращения', preset: 'link', meta: { template: '/admin/r/claims?filter[contract]={id}', target: 'drawer' }, row: { id: 7 } },
      global: { plugins: [router] },
    })
    await w.get('[data-testid="cell-link"]').trigger('click')
    expect(useLinkDrawerStore().url).toBe('/admin/r/claims?filter[contract]=7')
  })
})
