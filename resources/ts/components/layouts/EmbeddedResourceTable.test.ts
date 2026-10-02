import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import EmbeddedResourceTable from './EmbeddedResourceTable.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useManifestStore } from '../../stores/manifest'
import { useResourceFormStore } from '../../stores/resourceForm'
import { adminToast } from '../../stores/toast'

vi.mock('../../composables/useConfirm', () => ({
  confirmDialog: vi.fn(async () => true),
}))

function seed(): void {
  const manifest = useManifestStore()
  manifest.manifest = {
    version: 'v1',
    locale: 'ru',
    resources: [{
      slug: 'items',
      label: 'Items',
      permissions: { view: 'v', create: 'c', delete: 'd' },
      fields: [],
      columns: [{ name: 'title', label: 'Title' }],
      filters: [],
      actions: [],
      searchable: [],
      with: [],
      features: {},
    }],
    screens: [],
    settings: [],
    dashboards: [],
    plugins: [],
    permissions: [],
  } as never
  ;(useResourceFormStore().state as Record<string, unknown>).id = 5
}

const rows = {
  success: true,
  payload: { data: [{ id: 1, title: 'One' }], meta: { page: 1, per_page: 100, total: 1, last_page: 1 } },
}

describe('EmbeddedResourceTable: a refused change shows the server reason', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    seed()
    mock.onPost('/items/search').reply(200, rows)
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
    vi.restoreAllMocks()
  })

  const mountTable = () => mount(EmbeddedResourceTable, {
    props: { resource: 'items', foreign_key: 'parent_id', features: { create: true, delete: true, bulkDelete: true } },
    attachTo: document.body,
  })

  it('a refused quick-add: the validation message, or the generic text without one', async () => {
    const toast = vi.spyOn(adminToast, 'error')
    mock.onPost('/items/create')
      .replyOnce(422, {
        success: false,
        payload: { errorKey: 'validation', message: 'invalid', messages: { title: ['The title is required.'] } },
      })
      .onPost('/items/create')
      .replyOnce(500)
    const wrapper = mountTable()
    await flushPromises()
    const add = wrapper.findAll('button').find((b) => b.text().includes('Добавить'))
    await add!.trigger('click')
    await flushPromises()
    const commit = () => wrapper.find('.admin-embedded-table__draft-actions button').trigger('click')
    await commit()
    await flushPromises()
    expect(toast).toHaveBeenLastCalledWith('The title is required.')
    await commit()
    await flushPromises()
    expect(toast).toHaveBeenLastCalledWith('Не удалось создать запись.')
    wrapper.unmount()
  })
})
