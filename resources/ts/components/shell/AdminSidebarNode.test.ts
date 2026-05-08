import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent, h } from 'vue'
import AdminSidebarNode from './AdminSidebarNode.vue'
import type { MenuItem } from '../../stores/menu'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = (currentPath = '/') => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/r/users', name: 'admin.resource.users.index', component: Stub },
      { path: '/r/users/active', name: 'admin.resource.users.active', component: Stub },
    ],
  })
  router.push(currentPath)
  return router
}

async function mountNode(props: { item: MenuItem; depth?: number; collapsed?: boolean }, currentPath = '/') {
  setActivePinia(createPinia())
  const router = mkRouter(currentPath)
  await router.push(currentPath)
  await router.isReady()
  return mount(AdminSidebarNode, {
    props,
    global: { plugins: [router] },
  })
}

describe('AdminSidebarNode', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders leaf as UidSidebarItem with label + url', async () => {
    const wrapper = await mountNode({
      item: { key: 'users', label: 'Users', url: '/r/users' },
    })
    expect(wrapper.text()).toContain('Users')
    expect(wrapper.find('.admin-sidebar-node--has-children').exists()).toBe(false)
  })

  it('renders parent with children — toggles open/close on chevron click', async () => {
    const item: MenuItem = {
      key: 'tools', label: 'Tools',
      children: [
        { key: 'contact', label: 'Contact', url: '/contact' },
        { key: 'status', label: 'Status', url: '/status' },
      ],
    }
    const wrapper = await mountNode({ item })
    expect(wrapper.find('.admin-sidebar-node--has-children').exists()).toBe(true)
    // По умолчанию закрыт (никто из children не active в '/').
    expect(wrapper.find('.admin-sidebar-node--open').exists()).toBe(false)
    expect(wrapper.text()).toContain('Tools')
    expect(wrapper.text()).not.toContain('Contact')

    await wrapper.find('button').trigger('click')
    expect(wrapper.find('.admin-sidebar-node--open').exists()).toBe(true)
    expect(wrapper.text()).toContain('Contact')
    expect(wrapper.text()).toContain('Status')
  })

  it('auto-opens when active route is inside children', async () => {
    const item: MenuItem = {
      key: 'users-group', label: 'Users group',
      children: [
        { key: 'users', label: 'All users', url: '/r/users' },
      ],
    }
    const wrapper = await mountNode({ item }, '/r/users')
    // Группа должна авто-раскрыться, потому что child active.
    expect(wrapper.find('.admin-sidebar-node--open').exists()).toBe(true)
    expect(wrapper.find('.admin-sidebar-node--active').exists()).toBe(true)
    expect(wrapper.text()).toContain('All users')
  })

  it('renders nested 3 levels deep recursively (auto-open via active)', async () => {
    // Auto-open работает по containsActive — поставим L3 на active route.
    const item: MenuItem = {
      key: 'l0', label: 'L0',
      children: [{
        key: 'l1', label: 'L1',
        children: [{
          key: 'l2', label: 'L2',
          children: [{ key: 'l3', label: 'L3', url: '/r/users' }],
        }],
      }],
    }
    const wrapper = await mountNode({ item }, '/r/users')
    // Все 3 уровня parent'ов должны быть авто-раскрыты, потому что L3 — active.
    expect(wrapper.text()).toContain('L0')
    expect(wrapper.text()).toContain('L1')
    expect(wrapper.text()).toContain('L2')
    expect(wrapper.text()).toContain('L3')
  })

  it('applies indent depth as CSS-var up to stripeAt-1', async () => {
    const wrapper = await mountNode({
      item: { key: 'leaf', label: 'Leaf', url: '/x' },
      depth: 1,
    })
    const root = wrapper.find('.admin-sidebar-node').element as HTMLElement
    expect(root.style.getPropertyValue('--admin-sidebar-indent')).toBe('14px')
  })

  it('switches to stripe-mode at depth >= stripeAt (default 3)', async () => {
    const wrapper = await mountNode({
      item: { key: 'leaf', label: 'Leaf', url: '/x' },
      depth: 3,
    })
    expect(wrapper.find('.admin-sidebar-node--stripe').exists()).toBe(true)
    const root = wrapper.find('.admin-sidebar-node').element as HTMLElement
    // Indent зафиксирован на stripeAt-1 = 2 → 28px.
    expect(root.style.getPropertyValue('--admin-sidebar-indent')).toBe('28px')
    // Stripe-alpha должна быть >0 (visible).
    const alpha = parseFloat(root.style.getPropertyValue('--admin-sidebar-stripe-alpha'))
    expect(alpha).toBeGreaterThan(0)
    expect(alpha).toBeLessThanOrEqual(1)
  })

  it('hides chevron and children in collapsed-sidebar mode', async () => {
    const item: MenuItem = {
      key: 'tools', label: 'Tools',
      children: [{ key: 'contact', label: 'Contact', url: '/contact' }],
    }
    const wrapper = await mountNode({ item, collapsed: true })
    // CSS .uid-pattern-sidebar--collapsed скрывает; здесь проверим что
    // внутри button нет chevron-элемента (он отрендерится только if !collapsed).
    expect(wrapper.find('.admin-sidebar-node__chev').exists()).toBe(false)
  })
})
