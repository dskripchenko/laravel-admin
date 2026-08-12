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

/**
 * Stands in for matchMedia: jsdom does not implement it, and it is what the
 * shell uses to decide whether it faces a sidebar or a sliding drawer.
 */
function stubMatchMedia(matches: boolean) {
  const listeners: ((e: MediaQueryListEvent) => void)[] = []
  const mql = {
    matches,
    media: '',
    addEventListener: (_: string, cb: (e: MediaQueryListEvent) => void) => listeners.push(cb),
    removeEventListener: () => undefined,
  }
  Object.defineProperty(window, 'matchMedia', { writable: true, configurable: true, value: () => mql })

  return {
    /** Crossing the threshold: the screen rotating, or another device. */
    change: (next: boolean) => {
      mql.matches = next
      listeners.forEach((cb) => cb({ matches: next } as MediaQueryListEvent))
    },
  }
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

describe('AdminShell — узкий экран (шторка)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('на узком экране шторка стартует закрытой', async () => {
    stubMatchMedia(true)
    const w = await mountShell()

    // Otherwise the panel opens on a phone with the menu over the whole screen
    // and the collapse button underneath that very drawer — nothing left to
    // close it with.
    expect(w.emitted('update:collapsed')?.at(-1)).toEqual([true])
  })

  it('на широком экране сайдбар остаётся раскрытым', async () => {
    stubMatchMedia(false)
    const w = await mountShell()

    expect(w.emitted('update:collapsed')).toBeUndefined()
  })

  it('переход по меню закрывает шторку, а на десктопе ничего не трогает', async () => {
    stubMatchMedia(true)
    const w = await mountShell()
    const router = w.vm.$router

    await router.push('/profile')
    await w.vm.$nextTick()

    // A drawer on top of the page just opened would have to be closed by hand
    // on every navigation.
    expect(w.emitted('update:collapsed')?.at(-1)).toEqual([true])

    const wide = stubMatchMedia(false)
    const desktop = await mountShell()
    await desktop.vm.$router.push('/profile')
    await desktop.vm.$nextTick()
    expect(desktop.emitted('update:collapsed')).toBeUndefined()
    wide.change(true)
    await desktop.vm.$nextTick()

    // Crossing the threshold is an event too: the screen narrowed, the sidebar must go.
    expect(desktop.emitted('update:collapsed')?.at(-1)).toEqual([true])
  })
})
