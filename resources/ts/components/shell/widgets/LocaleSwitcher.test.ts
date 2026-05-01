import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import LocaleSwitcher from './LocaleSwitcher.vue'
import { setAdminClient, clearAdminClient } from '../../../stores/registry'
import { createAdminClient } from '../../../api/client'
import { useLocaleStore } from '../../../stores/locale'

describe('LocaleSwitcher', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
    const locale = useLocaleStore()
    locale.available = ['ru', 'en', 'de']
    locale.applyLocal('ru')
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('renders all available locales', () => {
    const wrapper = mount(LocaleSwitcher)
    const options = wrapper.findAll('option').map((o) => o.text())
    expect(options).toEqual(['RU', 'EN', 'DE'])
  })

  it('selects current locale', () => {
    const wrapper = mount(LocaleSwitcher)
    const select = wrapper.find('select')
    expect((select.element as HTMLSelectElement).value).toBe('ru')
  })

  it('changes locale on select', async () => {
    mock.onPost('/system/setLocale').reply(200, {
      success: true, payload: { locale: 'en' },
    })
    const wrapper = mount(LocaleSwitcher)
    await wrapper.find('select').setValue('en')
    await wrapper.vm.$nextTick()
    const locale = useLocaleStore()
    expect(locale.current).toBe('en')
  })
})
