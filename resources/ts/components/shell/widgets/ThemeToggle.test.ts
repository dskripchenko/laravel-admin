import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import ThemeToggle from './ThemeToggle.vue'
import { setAdminClient, clearAdminClient } from '../../../stores/registry'
import { createAdminClient } from '../../../api/client'
import { useThemeStore } from '../../../stores/theme'

describe('ThemeToggle', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('shows sun in light, moon in dark', async () => {
    const theme = useThemeStore()
    theme.applyLocal('light')
    const wrapper = mount(ThemeToggle)
    expect(wrapper.text()).toContain('☀')

    theme.applyLocal('dark')
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('☾')
  })

  it('toggles theme on click', async () => {
    const theme = useThemeStore()
    theme.available = ['light', 'dark']
    theme.applyLocal('light')

    mock.onPost('/system/setTheme').reply(200, {
      success: true, payload: { theme: 'dark' },
    })

    const wrapper = mount(ThemeToggle)
    await wrapper.find('button').trigger('click')
    await wrapper.vm.$nextTick()
    expect(theme.current).toBe('dark')
  })
})
