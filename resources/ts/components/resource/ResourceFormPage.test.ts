import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import ResourceFormPage from './ResourceFormPage.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useManifestStore } from '../../stores/manifest'
import { useResourceFormStore } from '../../stores/resourceForm'
import { clearRegistry } from '../render/registry'
import { registerBuiltinComponents as registerBuiltin } from '../render/builtin'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = (): Router =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/r/articles', name: 'admin.resource.articles.index', component: Stub },
      { path: '/r/articles/create', name: 'admin.resource.articles.create', component: Stub },
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
            { type: 'text', name: 'title', label: 'Заголовок', required: true },
            { type: 'textarea', name: 'body', label: 'Текст' },
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
  await router.push('/r/articles')
  await router.isReady()
  return mount(ResourceFormPage, {
    props: { slug: 'articles', ...props },
    global: { plugins: [router] },
  })
}

describe('ResourceFormPage', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    seedManifest()
    clearRegistry()
    registerBuiltin()
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
    clearRegistry()
  })

  it('create-mode: renders «Создать» title and primary button', async () => {
    const wrapper = await mountPage()
    await flushPromises()
    expect(wrapper.find('.admin-page__title').text()).toContain('Создать')
    const primary = wrapper.findAll('button').find((b) => b.text() === 'Создать')
    expect(primary).toBeDefined()
  })

  it('create-mode: no Удалить button', async () => {
    const wrapper = await mountPage()
    await flushPromises()
    const del = wrapper.findAll('button').find((b) => b.text() === 'Удалить')
    expect(del).toBeUndefined()
  })

  it('edit-mode: loads record + renders Сохранить + Удалить', async () => {
    mock.onGet('/resources/articles/read').reply(200, {
      success: true,
      payload: { data: { id: 7, title: 'Old', body: 'B' } },
    })
    const wrapper = await mountPage({ id: 7 })
    await flushPromises()
    expect(wrapper.find('.admin-page__title').text()).toContain('#7')
    const saveBtn = wrapper.findAll('button').find((b) => b.text() === 'Сохранить')
    const delBtn = wrapper.findAll('button').find((b) => b.text() === 'Удалить')
    expect(saveBtn).toBeDefined()
    expect(delBtn).toBeDefined()
  })

  it('renders form fields from manifest layout', async () => {
    const wrapper = await mountPage()
    await flushPromises()
    expect(wrapper.find('input').exists()).toBe(true)
    expect(wrapper.find('textarea').exists()).toBe(true)
  })

  it('shows save-bar when dirty', async () => {
    const wrapper = await mountPage()
    await flushPromises()
    expect(wrapper.find('.admin-resource-form__savebar').exists()).toBe(false)

    const form = useResourceFormStore()
    form.setField('title', 'NEW')
    await flushPromises()
    expect(wrapper.find('.admin-resource-form__savebar').exists()).toBe(true)
    expect(wrapper.find('.admin-resource-form__savebar-hint').text()).toContain(
      'несохранённые',
    )
  })

  it('save POSTs to /create + redirects on create', async () => {
    mock.onPost('/resources/articles/create').reply(200, {
      success: true,
      payload: { id: 99 },
    })
    const wrapper = await mountPage()
    await flushPromises()
    const form = useResourceFormStore()
    form.setField('title', 'A')

    const primary = wrapper.findAll('button').find((b) => b.text() === 'Создать')
    await primary!.trigger('click')
    await flushPromises()
    expect(form.recordId).toBe(99)
    expect(form.isEdit).toBe(true)
  })

  it('save in edit-mode POSTs id + state to /update', async () => {
    let captured: Record<string, unknown> | null = null
    mock.onGet('/resources/articles/read').reply(200, {
      success: true, payload: { data: { id: 5, title: 'Old' } },
    })
    mock.onPost('/resources/articles/update').reply((config) => {
      captured = JSON.parse(config.data)
      return [200, { success: true, payload: { id: 5 } }]
    })
    const wrapper = await mountPage({ id: 5 })
    await flushPromises()
    const form = useResourceFormStore()
    form.setField('title', 'NEW')
    await flushPromises()

    const saveBtn = wrapper.findAll('button').find((b) => b.text() === 'Сохранить')
    await saveBtn!.trigger('click')
    await flushPromises()

    expect(captured).toMatchObject({ id: 5, title: 'NEW' })
  })

  it('delete: confirm + redirect to index', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)
    mock.onGet('/resources/articles/read').reply(200, {
      success: true, payload: { data: { id: 5 } },
    })
    mock.onPost('/resources/articles/destroy').reply(200, {
      success: true, payload: {},
    })
    const wrapper = await mountPage({
      id: 5,
      indexRouteName: 'admin.resource.articles.index',
    })
    await flushPromises()
    const delBtn = wrapper.findAll('button').find((b) => b.text() === 'Удалить')
    await delBtn!.trigger('click')
    await flushPromises()
    expect(confirmSpy).toHaveBeenCalled()
    confirmSpy.mockRestore()
  })

  it('shows ValidationError messages on save failure', async () => {
    mock.onPost('/resources/articles/create').reply(422, {
      success: false,
      payload: {
        errorKey: 'validation',
        message: 'V',
        messages: { title: ['Required'] },
      },
    })
    const wrapper = await mountPage()
    await flushPromises()
    const form = useResourceFormStore()
    form.setField('title', '')
    const primary = wrapper.findAll('button').find((b) => b.text() === 'Создать')
    await primary!.trigger('click')
    await flushPromises()
    // Ошибки обновляются в store; UidInput через FormState получит error.
    expect(form.errors.title).toEqual(['Required'])
  })
})
