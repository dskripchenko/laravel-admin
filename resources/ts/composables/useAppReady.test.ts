import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { setActivePinia, createPinia } from 'pinia'
import { useAppReady, BOOT_GATE_TIMEOUT_MS } from './useAppReady'
import { useManifestStore } from '../stores/manifest'
import { useMenuStore } from '../stores/menu'

/**
 * Гейт готовности каркаса: до него страница не рендерится вовсе, иначе
 * пользователь четверть секунды смотрит на чужой экран (HomePage-заглушку)
 * и пустое меню, а потом всё скачком подменяется.
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
