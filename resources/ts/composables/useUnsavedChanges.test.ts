import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, RouterView } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import ScreenPage from '../components/ScreenPage.vue'
import { changedFields } from './useUnsavedChanges'
import { setAdminClient, clearAdminClient } from '../stores/registry'
import { createAdminClient } from '../api/client'
import { registerBuiltinComponents } from '../components/render/builtin'
import { confirmState, resolveConfirmDialog } from './useConfirm'

const field = (name: string, label: string) => ({ kind: 'field', type: 'input', name, label })
const layout = [{
  kind: 'layout',
  type: 'tabs',
  items: [
    { key: 'general', label: 'Общие', items: [field('site.name', 'Название')] },
    { key: 'captcha', label: 'Капча', items: [{ kind: 'layout', type: 'block', title: 'Ключи', items: [field('captcha.key', 'Ключ сервера')] }] },
  ],
}]

describe('changedFields', () => {
  it('names the changed fields by their tab and block', () => {
    expect(changedFields(layout, { 'site.name': 'A', 'captcha.key': 'new' }, { 'site.name': 'A', 'captcha.key': 'old' }))
      .toEqual([{ name: 'captcha.key', label: 'Ключ сервера', path: ['Капча', 'Ключи', 'Ключ сервера'] }])
    // An empty string and a missing value are the same nothing.
    expect(changedFields(layout, { 'site.name': '' }, {})).toEqual([])
  })
})

describe('ScreenPage savebar', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    registerBuiltinComponents()
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    mock.onGet('/settings/state').reply(200, {
      success: true,
      payload: { state: { 'site.name': 'Сайт', 'captcha.key': 'old' }, name: 'Настройки', description: null, layout, command_bar: [], permissions: [], etag: 'x', save_method: 'save' },
    })
  })
  afterEach(() => {
    mock.reset()
    clearAdminClient()
    document.body.innerHTML = ''
  })

  async function mountPage() {
    const Page = defineComponent({ render: () => h(ScreenPage, { slug: 'settings' }) })
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/settings', component: Page }, { path: '/elsewhere', component: { render: () => h('div', 'elsewhere') } }],
    })
    await router.push('/settings')
    await router.isReady()
    const w = mount(defineComponent({ render: () => h(RouterView) }), { global: { plugins: [router] }, attachTo: document.body })
    await flushPromises()
    return { w, router }
  }

  it('lists the changes, marks the field, discards them', async () => {
    const { w } = await mountPage()
    expect(w.find('[data-testid="screen-savebar"]').exists()).toBe(false)
    await w.get('[data-testid="tab-captcha"]').trigger('click')
    await w.get('[data-testid="field-captcha-key"] input').setValue('new')
    expect(w.get('[data-testid="screen-savebar-count"]').text()).toBe('1 несохранённое изменение')
    expect(w.get('[data-testid="screen-savebar-fields"]').text()).toContain('Капча › Ключи › Ключ сервера')
    expect(w.get('[data-testid="field-captcha-key"]').attributes('data-changed')).toBe('true')
    await w.get('[data-testid="screen-savebar-discard"]').trigger('click')
    expect(w.find('[data-testid="screen-savebar"]').exists()).toBe(false)
    expect((w.get('[data-testid="field-captcha-key"] input').element as HTMLInputElement).value).toBe('old')
  })

  it('saves through the screen\'s method and asks before leaving with changes', async () => {
    mock.onPost('/settings/runMethod').reply(200, { success: true, payload: { message: 'Сохранено' } })
    const { w, router } = await mountPage()
    await w.get('[data-testid="field-site-name"] input').setValue('Новый')
    const leaving = router.push('/elsewhere')
    await flushPromises()
    expect(confirmState.open).toBe(true)
    resolveConfirmDialog(false)
    await leaving
    expect(router.currentRoute.value.path).toBe('/settings')

    await w.get('[data-testid="screen-savebar-save"]').trigger('click')
    await flushPromises()
    expect(JSON.parse(mock.history.post[0].data)).toMatchObject({ method: 'save', payload: { 'site.name': 'Новый' } })
    expect(w.find('[data-testid="screen-savebar"]').exists()).toBe(false)
    await router.push('/elsewhere')
    expect(router.currentRoute.value.path).toBe('/elsewhere')
  })
})
