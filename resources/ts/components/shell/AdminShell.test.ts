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
 * Подменяет matchMedia: jsdom его не реализует, а именно по нему shell решает,
 * сайдбар перед ним или выдвижная шторка.
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
    /** Пересечь порог: имитируем поворот экрана или смену устройства. */
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

    // Иначе панель на телефоне открывается меню поверх всего экрана, а кнопка
    // сворачивания оказывается под самой шторкой — закрыть её нечем.
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

    // Шторка поверх только что открытой страницы означала бы, что её надо
    // закрывать руками при каждой навигации.
    expect(w.emitted('update:collapsed')?.at(-1)).toEqual([true])

    const wide = stubMatchMedia(false)
    const desktop = await mountShell()
    await desktop.vm.$router.push('/profile')
    await desktop.vm.$nextTick()
    expect(desktop.emitted('update:collapsed')).toBeUndefined()
    wide.change(true)
    await desktop.vm.$nextTick()

    // Пересечение порога — тоже событие: экран сузился, сайдбар обязан уйти.
    expect(desktop.emitted('update:collapsed')?.at(-1)).toEqual([true])
  })
})
