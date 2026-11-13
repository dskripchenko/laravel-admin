import { describe, it, expect, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h, type ComputedRef } from 'vue'
import AdminSidebarNode from './AdminSidebarNode.vue'
import AdminTopBar from './AdminTopBar.vue'
import { useBreadcrumbs, type Crumb } from './breadcrumbs'
import { useMenuStore, type MenuItem } from '../../stores/menu'
import { useUserNavStore } from '../../stores/userNav'
import MockAdapter from 'axios-mock-adapter'
import { createAdminClient } from '../../api/client'
import { setAdminClient, clearAdminClient } from '../../stores/registry'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

function mkRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub, meta: { kind: 'system', title: 'Главная' } },
      { path: '/screens/applications', name: 'admin.screen.applications', component: Stub, meta: { kind: 'screen', slug: 'applications', title: 'Заявки' } },
      { path: '/r/claims', name: 'admin.resource.claims.index', component: Stub, meta: { kind: 'resource', slug: 'claims', title: 'Претензии' } },
      { path: '/r/claims/:id', name: 'admin.resource.claims.view', component: Stub, meta: { kind: 'resource', slug: 'claims', title: 'Претензии' } },
    ],
  })
}

const tree: MenuItem[] = [
  { key: 'section.pinned', label: 'Закреплено', kind: 'section', children: [
    { key: 'desk', label: 'Рабочий стол', url: '/' },
  ] },
  { key: 'clients', label: 'Клиенты', children: [
    { key: 'queues', label: 'Очереди', children: [
      { key: 'screen.applications', label: 'Заявки', url: '/screens/applications', routeName: 'admin.screen.applications' },
      { key: 'resource.claims', label: 'Претензии', url: '/r/claims', routeName: 'admin.resource.claims.index' },
    ] },
  ] },
]

async function crumbsAt(router: Router, path: string): Promise<Crumb[]> {
  let crumbs: ComputedRef<Crumb[]> | null = null
  const Probe = defineComponent({
    setup() {
      crumbs = useBreadcrumbs()
      return () => h('div')
    },
  })
  await router.push(path)
  await router.isReady()
  mount(Probe, { global: { plugins: [router] } })
  return crumbs!.value
}

describe('breadcrumbs: compact, sections, tabs, the list as it was (1.73)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    useMenuStore().setItems(tree)
  })

  it('full: every menu level; compact: the area, the screen and its tabs', async () => {
    const nav = useUserNavStore()
    nav.registerTab({ queryKey: 'tab', label: 'CRM', depth: 0 })
    nav.registerTab({ queryKey: 'crm-tab', label: 'Не переданы', depth: 1 })

    let crumbs = await crumbsAt(mkRouter(), '/screens/applications?tab=crm')
    expect(crumbs.map((c) => c.label)).toEqual(['Клиенты', 'Очереди', 'Заявки', 'CRM', 'Не переданы'])

    nav.configure({ breadcrumbs: 'compact' })
    crumbs = await crumbsAt(mkRouter(), '/screens/applications?tab=crm')
    expect(crumbs.map((c) => c.label)).toEqual(['Клиенты', 'Заявки', 'CRM', 'Не переданы'])
    expect(crumbs.at(-1)!.kind).toBe('tab')
  })

  it('a pinned item is not preceded by its section', async () => {
    const crumbs = await crumbsAt(mkRouter(), '/')
    expect(crumbs.map((c) => c.label)).toEqual(['Рабочий стол'])
  })

  it('a crumb leads back to the list with the filters it was left with', async () => {
    const nav = useUserNavStore()
    nav.configure({ breadcrumbs: 'compact' })
    nav.rememberQuery('/r/claims', { 'filter[status]': 'new', page: '2' })
    const crumbs = await crumbsAt(mkRouter(), '/r/claims/7')
    const list = crumbs.find((c) => c.label === 'Претензии')!
    expect(list.to).toEqual({ name: 'admin.resource.claims.index', query: { 'filter[status]': 'new', page: '2' } })
    expect(crumbs.at(-1)!.label).toBe('#7')
  })
})

describe('AdminSidebarNode: folded groups and counters (1.73)', () => {
  async function mountNode(item: MenuItem) {
    const router = mkRouter()
    await router.push('/')
    await router.isReady()
    return mount(AdminSidebarNode, { props: { item }, global: { plugins: [router] } })
  }

  beforeEach(() => {
    setActivePinia(createPinia())
  })

  const group = (extra: Partial<MenuItem> = {}): MenuItem => ({
    key: 'dicts', label: 'Справочники', ...extra, children: [
      { key: 'a', label: 'A', url: '/r/a', badge: 120, badgeTone: 'danger' },
      { key: 'b', label: 'B', url: '/r/b', badge: 3 },
    ],
  })

  it('starts by the node, then the panel default, then the user own choice', async () => {
    expect((await mountNode(group())).find('[aria-expanded]').attributes('aria-expanded')).toBe('false')
    useUserNavStore().configure({ groupsExpanded: true })
    expect((await mountNode(group())).find('[aria-expanded]').attributes('aria-expanded')).toBe('true')
    expect((await mountNode(group({ collapsed: true }))).find('[aria-expanded]').attributes('aria-expanded')).toBe('false')
    useUserNavStore().apply({ groups: { dicts: true } })
    expect((await mountNode(group({ collapsed: true }))).find('[aria-expanded]').attributes('aria-expanded')).toBe('true')
  })

  it('a folded group shows the sum of its counters, red when one is; unfolded — none', async () => {
    const w = await mountNode(group({ sumBadges: true, collapsed: true }))
    const badge = w.find('[data-testid="menu-badge-dicts"]')
    expect(badge.text()).toBe('99+')
    expect(badge.classes()).toContain('admin-sidebar-node__badge--danger')

    await w.find('[aria-expanded]').trigger('click')
    expect(w.find('[data-testid="menu-badge-dicts"]').exists()).toBe(false)
    expect(w.find('.admin-sidebar-node__leaf--danger').exists()).toBe(true)
    expect(w.text()).toContain('99+')
    expect(useUserNavStore().groupOpen('dicts')).toBe(true)
  })
})

describe('AdminTopBar: the star and «+ Create» (1.73)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('names the favourite with the screen and its tab; offers the create links', async () => {
    const router = mkRouter()
    await router.push('/screens/applications?tab=crm')
    await router.isReady()
    useMenuStore().quickCreate = [{ key: 'resource.news', label: 'Новость', url: '/r/news/create' }]
    const w = mount(AdminTopBar, {
      props: { breadcrumbs: [{ label: 'Клиенты' }, { label: 'Заявки', to: '/screens/applications' }, { label: 'CRM', kind: 'tab' }] },
      global: { plugins: [router], stubs: { StatusIndicators: true, NotificationBell: true, ThemeToggle: true, LocaleSwitcher: true, UserMenu: true } },
    })
    await flushPromises()
    const star = w.find('[data-testid="favorite-toggle"]')
    expect(star.exists()).toBe(true)
    expect(star.attributes('aria-pressed')).toBe('false')
    expect(w.find('[data-testid="quick-create"]').exists()).toBe(true)

    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    const mock = new MockAdapter(client.raw)
    let sent: unknown = null
    mock.onPost('/navigation/favorite').reply((config) => {
      sent = JSON.parse(config.data)
      return [200, { success: true, payload: { favorites: [{ url: '/screens/applications?tab=crm', label: 'Заявки · CRM' }], recent: [], groups: {} } }]
    })
    await star.trigger('click')
    await flushPromises()
    expect(sent).toEqual({ url: '/screens/applications?tab=crm', label: 'Заявки · CRM', icon: null })
    clearAdminClient()
    expect(w.find('[data-testid="favorite-toggle"]').attributes('aria-pressed')).toBe('true')
  })
})
