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

  it('shows moon icon in light theme', () => {
    const theme = useThemeStore()
    theme.applyLocal('light')
    const wrapper = mount(ThemeToggle)
    expect(wrapper.find('[data-icon="moon"]').exists()).toBe(true)
  })

  it('shows sun icon in dark theme', () => {
    const theme = useThemeStore()
    theme.available = ['light', 'dark']
    theme.applyLocal('dark')
    const wrapper = mount(ThemeToggle)
    expect(wrapper.find('[data-icon="sun"]').exists()).toBe(true)
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
    await new Promise((r) => setTimeout(r, 10))
    expect(theme.current).toBe('dark')
  })

  it('aria-pressed reflects dark state', () => {
    const theme = useThemeStore()
    theme.applyLocal('dark')
    const wrapper = mount(ThemeToggle)
    expect(wrapper.find('button').attributes('aria-pressed')).toBe('true')
  })
})
