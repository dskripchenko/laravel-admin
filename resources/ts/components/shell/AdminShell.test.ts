import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent, h } from 'vue'
import AdminShell from './AdminShell.vue'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/profile', name: 'admin.profile', component: Stub },
    ],
  })

async function mountShell(props: Record<string, unknown> = {}) {
  const router = mkRouter()
  router.push('/')
  await router.isReady()
  return mount(AdminShell, {
    props,
    global: { plugins: [router] },
  })
}

describe('AdminShell — branding (BL-12)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders the configured copyright in the footer', async () => {
    const w = await mountShell({ brand: { name: 'Печать', copyright: '© 2026 Printable' } })
    expect(w.find('.admin-main-footer__copyright').text()).toBe('© 2026 Printable')
  })

  it('passes the brand name into the sidebar brand-row', async () => {
    const w = await mountShell({ brand: { name: 'Печать' } })
    expect(w.text()).toContain('Печать')
  })

  it('no copyright element when brand has none', async () => {
    const w = await mountShell({ brand: { name: 'X' } })
    expect(w.find('.admin-main-footer__copyright').exists()).toBe(false)
  })
})
