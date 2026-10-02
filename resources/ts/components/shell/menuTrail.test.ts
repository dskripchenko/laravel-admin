import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h, type ComputedRef } from 'vue'
import AdminSidebar from './AdminSidebar.vue'
import { findMenuTrail, type RouteLike } from './menuTrail'
import { useBreadcrumbs, type Crumb } from './breadcrumbs'
import { useMenuStore, type MenuItem } from '../../stores/menu'

/**
 * One rule for "which menu item is this page": the sidebar and the
 * breadcrumbs used to answer it each on their own, and with `/r/orders`
 * linked from two branches they disagreed.
 */
const at = (name: string, path: string): RouteLike => ({ name, path, params: {}, meta: {} })

const orders = (key: string): MenuItem => ({
  key, label: 'Заказы', url: '/r/orders', routeName: 'admin.resource.orders.index',
})

// The demo's shape: the showcase (declared first) links to orders four levels
// deep, the Shop section holds them as its own entry.
const demoMenu = (): MenuItem[] => [
  {
    key: 'showcase', label: 'Витрина', children: [
      {
        key: 'nested', label: 'Вложенное меню', children: [
          { key: 'nested-shop', label: 'L2 · Магазин', children: [orders('resource.orders')] },
        ],
      },
    ],
  },
  { key: 'shop', label: 'Магазин', children: [orders('resource.orders'), { key: 'products', label: 'Товары', url: '/r/products', routeName: 'admin.resource.products.index' }] },
]

describe('findMenuTrail — the shared rule', () => {
  it('the most specific match wins: exact over prefix, longer prefix over shorter', () => {
    const items: MenuItem[] = [
      { key: 'all', label: 'All', url: '/screens' },
      { key: 'reports', label: 'Reports', url: '/screens/reports' },
      { key: 'exact', label: 'Exact', url: '/screens/reports/daily' },
    ]
    expect(findMenuTrail(items, at('x', '/screens/reports/daily')).map((i) => i.key)).toEqual(['exact'])
    expect(findMenuTrail(items, at('x', '/screens/reports/weekly')).map((i) => i.key)).toEqual(['reports'])
  })

  it('on a tie the canonical node — the one with the route name — beats a copied url', () => {
    const items: MenuItem[] = [
      { key: 'shortcut', label: 'Orders', url: '/r/orders' },
      { key: 'resource.orders', label: 'Orders', url: '/r/orders', routeName: 'admin.resource.orders.index' },
    ]
    expect(findMenuTrail(items, at('admin.resource.orders.index', '/r/orders')).map((i) => i.key))
      .toEqual(['resource.orders'])
  })

  it('then the shallowest: the home section, not a shortcut deep in another branch', () => {
    const trail = findMenuTrail(demoMenu(), at('admin.resource.orders.index', '/r/orders'))
    expect(trail.map((i) => i.key)).toEqual(['shop', 'resource.orders'])
  })

  it('a record page follows the same rule as its list', () => {
    const trail = findMenuTrail(demoMenu(), at('admin.resource.orders.view', '/r/orders/5'))
    expect(trail.map((i) => i.key)).toEqual(['shop', 'resource.orders'])
  })

  it('then the first declared', () => {
    const items: MenuItem[] = [
      { key: 'a', label: 'A', children: [orders('first')] },
      { key: 'b', label: 'B', children: [orders('second')] },
    ]
    expect(findMenuTrail(items, at('admin.resource.orders.index', '/r/orders')).map((i) => i.key))
      .toEqual(['a', 'first'])
  })
})

describe('the sidebar and the breadcrumbs agree', () => {
  const Stub = defineComponent({ render: () => h('div') })
  let router: Router

  beforeEach(async () => {
    setActivePinia(createPinia())
    useMenuStore().setItems(demoMenu())
    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', name: 'admin.home', component: Stub },
        { path: '/r/orders', name: 'admin.resource.orders.index', component: Stub, meta: { kind: 'resource', slug: 'orders', title: 'Заказы' } },
        { path: '/r/products', name: 'admin.resource.products.index', component: Stub, meta: { kind: 'resource', slug: 'products', title: 'Товары' } },
      ],
    })
    await router.push('/r/orders')
    await router.isReady()
  })

  it('opens only the branch the breadcrumbs name', async () => {
    let crumbs: ComputedRef<Crumb[]> | null = null
    const Probe = defineComponent({
      setup() {
        crumbs = useBreadcrumbs()
        return () => h('div')
      },
    })
    mount(Probe, { global: { plugins: [router] } })
    const sidebar = mount(AdminSidebar, { global: { plugins: [router] } })

    expect(crumbs!.value.map((c) => c.label)).toEqual(['Магазин', 'Заказы'])

    // Exactly one active leaf, and the open branches are its parents alone.
    const activeLeaves = sidebar.findAll('.admin-sidebar-node--active:not(.admin-sidebar-node--has-children)')
    expect(activeLeaves).toHaveLength(1)
    const openGroups = sidebar.findAll('.admin-sidebar-node--has-children.admin-sidebar-node--active')
    expect(openGroups.map((g) => g.find('.admin-sidebar-node__group').text())).toEqual(['Магазин'])
  })
})
