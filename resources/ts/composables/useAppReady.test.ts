import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { setActivePinia, createPinia } from 'pinia'
import { useAppReady, BOOT_GATE_TIMEOUT_MS } from './useAppReady'
import { useManifestStore } from '../stores/manifest'
import { useMenuStore } from '../stores/menu'

/**
 * The shell's readiness gate: before it the page is not rendered at all —
 * otherwise one spends a quarter of a second looking at the wrong screen (the
 * HomePage placeholder) and an empty menu, and then everything is swapped out
 * at once.
 */
const Probe = defineComponent({
  setup() {
    const ready = useAppReady()
    return () => h('div', ready.value ? 'ready' : 'boot')
  },
})

describe('useAppReady', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('закрыт, пока манифест не разрешён', async () => {
    const wrapper = mount(Probe)
    expect(wrapper.text()).toBe('boot')
  })

  it('открывается, когда манифест разрешён и меню догрузилось', async () => {
    const manifest = useManifestStore()
    const wrapper = mount(Probe)

    manifest.bootResolved = true
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toBe('ready')
  })

  it('ждёт меню, даже если манифест уже пришёл', async () => {
    const manifest = useManifestStore()
    const menu = useMenuStore()
    menu.loading = true
    manifest.bootResolved = true

    const wrapper = mount(Probe)
    expect(wrapper.text()).toBe('boot')

    menu.loading = false
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toBe('ready')
  })

  it('открывается по таймауту — зависший запрос не держит страницу вечно', async () => {
    vi.useFakeTimers()
    const menu = useMenuStore()
    menu.loading = true

    const wrapper = mount(Probe)
    expect(wrapper.text()).toBe('boot')

    vi.advanceTimersByTime(BOOT_GATE_TIMEOUT_MS + 10)
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toBe('ready')
    vi.useRealTimers()
  })
})
