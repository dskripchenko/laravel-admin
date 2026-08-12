import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import ResourceViewPage from './ResourceViewPage.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useManifestStore } from '../../stores/manifest'
import { clearInfolistRegistry } from '../infolist/registry'
import { registerBuiltinInfolistEntries } from '../infolist/builtin'
import { clearRegistry } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = (): Router =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/r/articles', name: 'admin.resource.articles.index', component: Stub },
      { path: '/r/articles/:id/edit', name: 'admin.resource.articles.edit', component: Stub },
    ],
  })

const seedManifest = () => {
  const manifest = useManifestStore()
  manifest.manifest = {
    version: 'v1',
    locale: 'ru',
    resources: [
      {
        slug: 'articles',
        label: 'Статьи',
        permissions: { view: 'admin.articles.view' },
        fields: [
          { type: 'rows', items: [
            { type: 'text', name: 'title', label: 'Заголовок' },
            { type: 'badge', name: 'status', label: 'Status', map: { published: 'success', draft: 'warning' } },
          ] },
        ],
        columns: [],
        filters: [],
        actions: [],
        searchable: [],
        with: [],
        features: {},
      },
    ],
    screens: [],
    settings: [],
    dashboards: [],
    plugins: [],
    permissions: [],
  }
}

async function mountPage(props: Record<string, unknown> = {}) {
  const router = mkRouter()
  await router.push('/')
  await router.isReady()
  return mount(ResourceViewPage, {
    props: { slug: 'articles', id: 7, ...props },
    global: { plugins: [router] },
  })
}

describe('ResourceViewPage', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    seedManifest()
    clearRegistry()
    clearInfolistRegistry()
    registerBuiltinComponents()
    registerBuiltinInfolistEntries()
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
    clearRegistry()
    clearInfolistRegistry()
  })

  it('renders title from record + UID label', async () => {
    mock.onGet('/articles/read').reply(200, {
      success: true, payload: { record: { id: 7, title: 'Old', status: 'published' } },
    })
    const wrapper = await mountPage()
    await flushPromises()
    // The title comes from record.title, which wins over "Resource: #id".
    expect(wrapper.find('.admin-page__title').text()).toBe('Old')
    // The UID label next to the status.
    expect(wrapper.find('.admin-resource-view__uid').text()).toContain('7')
    // The back link to the index, carrying the resource's label.
    expect(wrapper.find('.admin-resource-view__back').text()).toContain('Статьи')
  })

  it('renders Edit button и more-menu (Удалить открывается через триггер)', async () => {
    mock.onGet('/articles/read').reply(200, {
      success: true, payload: { record: { id: 7 } },
    })
    const wrapper = await mountPage()
    await flushPromises()
    // Edit is the primary button.
    expect(
      wrapper.findAll('button').map((b) => b.text()),
    ).toContain('Редактировать')
    // The trigger of the "more" menu is there.
    const triggers = wrapper.findAll('[aria-label="Действия"]')
    expect(triggers.length).toBe(1)
    // Opening the menu: UidMenu teleports into the body, so we look through document.
    await triggers[0].trigger('click')
    await flushPromises()
    expect(document.body.textContent ?? '').toContain('Удалить')
  })

  it('shows skeleton during load', async () => {
    mock.onGet('/articles/read').reply(() => new Promise(() => {}))
    const wrapper = await mountPage()
    await flushPromises()
    expect(wrapper.findAll('.admin-resource-view__loading > *').length).toBeGreaterThan(0)
  })

  it('renders infolist with text + badge entries from manifest', async () => {
    mock.onGet('/articles/read').reply(200, {
      success: true,
      payload: { record: { id: 7, title: 'Hello', status: 'published' } },
    })
    const wrapper = await mountPage()
    await flushPromises()
    expect(wrapper.text()).toContain('Hello')
    expect(wrapper.text()).toContain('published')
  })

  it('shows error alert on load failure', async () => {
    mock.onGet('/articles/read').networkError()
    const wrapper = await mountPage()
    await flushPromises()
    // form.error.message is either 'Network Error' or the template's fallback
    expect(wrapper.find('.uid-alert').exists()).toBe(true)
  })
})
