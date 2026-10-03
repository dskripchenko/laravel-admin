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
import { confirmDialog } from '../../composables/useConfirm'

vi.mock('../../composables/useConfirm', () => ({
  confirmDialog: vi.fn(async () => true),
  deleteWording: () => ({ title: 'Удаление', confirmLabel: 'Удалить', destructive: true }),
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
      columns: [
        { name: 'title', label: 'Title', editable: { field: 'title', validation: [], as: 'text', options: {} } },
        { name: 'status', label: 'Status', editable: { field: 'status', validation: [], as: 'select', options: { draft: 'Draft', done: 'Done' } } },
        { name: 'note', label: 'Note', editable: null },
      ],
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

const three = {
  success: true,
  payload: {
    data: [
      { id: 1, title: 'One', status: 'draft', note: 'a' },
      { id: 2, title: 'Two', status: 'done', note: 'b' },
      { id: 3, title: 'Three', status: 'draft', note: 'c' },
    ],
    meta: { page: 1, per_page: 100, total: 3, last_page: 1 },
  },
}

describe('EmbeddedResourceTable on the UidTable API', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    seed()
    vi.mocked(confirmDialog).mockClear()
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
    vi.restoreAllMocks()
  })

  const mountTable = async (features = { create: true, delete: true, bulkDelete: true }) => {
    const wrapper = mount(EmbeddedResourceTable, {
      props: { resource: 'items', foreign_key: 'parent_id', features },
      attachTo: document.body,
    })
    await flushPromises()
    return wrapper
  }
  const rowCheckboxes = (w: Awaited<ReturnType<typeof mountTable>>) =>
    w.findAll('tbody .uid-table__td--select input[type="checkbox"]')
  const bodyRows = (w: Awaited<ReturnType<typeof mountTable>>) => w.findAll('tbody tr.uid-table__row')

  it('loads the children by the foreign key and renders the manifest columns', async () => {
    mock.onPost('/items/search').reply(200, three)
    const w = await mountTable()
    expect(JSON.parse(mock.history.post[0].data)).toMatchObject({ filters: { parent_id: 5 } })
    expect(bodyRows(w)).toHaveLength(3)
    expect(w.text()).toContain('Two')
    w.unmount()
  })

  it('selects rows through the kit selection model and shows the bulk button', async () => {
    mock.onPost('/items/search').reply(200, three)
    const w = await mountTable()
    expect(w.find('[data-testid="embedded-bulk-delete"]').exists()).toBe(false)
    await rowCheckboxes(w)[0].setValue(true)
    await rowCheckboxes(w)[2].setValue(true)
    expect(w.findAll('tr.uid-table__row--selected')).toHaveLength(2)
    expect(w.find('[data-testid="embedded-bulk-delete"]').text()).toContain('(2)')
    // The header checkbox selects every row, and again clears them.
    const all = w.find('thead input[type="checkbox"]')
    await all.setValue(true)
    expect(w.find('[data-testid="embedded-bulk-delete"]').text()).toContain('(3)')
    await all.setValue(false)
    expect(w.find('[data-testid="embedded-bulk-delete"]').exists()).toBe(false)
    w.unmount()
  })

  it('has no selection column without bulkDelete', async () => {
    mock.onPost('/items/search').reply(200, three)
    const w = await mountTable({ create: false, delete: true, bulkDelete: false })
    expect(rowCheckboxes(w)).toHaveLength(0)
    w.unmount()
  })

  it('bulk-deletes the selected rows, one /delete per id, and reloads', async () => {
    mock.onPost('/items/search').replyOnce(200, three).onPost('/items/search').reply(200, {
      success: true,
      payload: { data: [{ id: 2, title: 'Two' }], meta: { page: 1, per_page: 100, total: 1, last_page: 1 } },
    })
    mock.onPost('/items/delete').reply(200, { success: true, payload: {} })
    const w = await mountTable()
    await rowCheckboxes(w)[0].setValue(true)
    await rowCheckboxes(w)[2].setValue(true)
    await w.find('[data-testid="embedded-bulk-delete"]').trigger('click')
    await flushPromises()
    expect(confirmDialog).toHaveBeenCalledTimes(1)
    expect(vi.mocked(confirmDialog).mock.calls[0]![0]).toMatchObject({ confirmLabel: 'Удалить', destructive: true })
    const deleted = mock.history.post.filter((r) => r.url === '/items/delete').map((r) => JSON.parse(r.data).id)
    expect(deleted.sort()).toEqual([1, 3])
    expect(bodyRows(w)).toHaveLength(1)
    expect(w.find('[data-testid="embedded-bulk-delete"]').exists()).toBe(false)
    w.unmount()
  })

  it('a failed row in a bulk delete stays selected and the reason is shown', async () => {
    const toast = vi.spyOn(adminToast, 'error')
    mock.onPost('/items/search').reply(200, three)
    mock.onPost('/items/delete', { id: 1 }).reply(200, { success: true, payload: {} })
    mock.onPost('/items/delete', { id: 3 }).reply(403, { success: false, payload: { message: 'Locked.' } })
    const w = await mountTable()
    await rowCheckboxes(w)[0].setValue(true)
    await rowCheckboxes(w)[2].setValue(true)
    await w.find('[data-testid="embedded-bulk-delete"]').trigger('click')
    await flushPromises()
    expect(toast).toHaveBeenCalledWith('Locked.')
    expect(w.find('[data-testid="embedded-bulk-delete"]').text()).toContain('(1)')
    w.unmount()
  })

  it('deletes one row through its delete button in the actions column', async () => {
    mock.onPost('/items/search').reply(200, three)
    mock.onPost('/items/delete').reply(200, { success: true, payload: {} })
    const w = await mountTable()
    const buttons = w.findAll('[data-testid="embedded-row-delete"]')
    expect(buttons).toHaveLength(3)
    await buttons[1].trigger('click')
    await flushPromises()
    expect(JSON.parse(mock.history.post.find((r) => r.url === '/items/delete')!.data)).toEqual({ id: 2 })
    expect(bodyRows(w)).toHaveLength(2)
    expect(w.text()).not.toContain('Two')
    w.unmount()
  })

  it('keeps the row when the delete is not confirmed', async () => {
    vi.mocked(confirmDialog).mockResolvedValueOnce(false)
    mock.onPost('/items/search').reply(200, three)
    const w = await mountTable()
    await w.findAll('[data-testid="embedded-row-delete"]')[0].trigger('click')
    await flushPromises()
    expect(mock.history.post.some((r) => r.url === '/items/delete')).toBe(false)
    expect(bodyRows(w)).toHaveLength(3)
    w.unmount()
  })

  it('has no actions column without the delete feature', async () => {
    mock.onPost('/items/search').reply(200, three)
    const w = await mountTable({ create: true, delete: false, bulkDelete: false })
    expect(w.findAll('[data-testid="embedded-row-delete"]')).toHaveLength(0)
    expect(w.findAll('thead th')).toHaveLength(3)
    w.unmount()
  })

  it('quick-add posts the draft with the foreign key and reloads', async () => {
    mock.onPost('/items/search').replyOnce(200, three).onPost('/items/search').reply(200, {
      success: true,
      payload: {
        data: [...three.payload.data, { id: 4, title: 'Four', status: 'done', note: '' }],
        meta: { page: 1, per_page: 100, total: 4, last_page: 1 },
      },
    })
    mock.onPost('/items/create').reply(200, { success: true, payload: { id: 4 } })
    const w = await mountTable()
    await w.find('[data-testid="embedded-add"]').trigger('click')
    const draft = w.find('[data-testid="embedded-draft"]')
    expect(draft.exists()).toBe(true)
    await draft.find('input[name="title"]').setValue('Four')
    await w.find('[data-testid="embedded-draft-commit"]').trigger('click')
    await flushPromises()
    const body = JSON.parse(mock.history.post.find((r) => r.url === '/items/create')!.data)
    expect(body).toMatchObject({ parent_id: 5, title: 'Four' })
    expect(w.find('[data-testid="embedded-draft"]').exists()).toBe(false)
    expect(bodyRows(w)).toHaveLength(4)
    w.unmount()
  })

  it('edits an editable cell inline through /inlineUpdate', async () => {
    mock.onPost('/items/search').reply(200, three)
    mock.onPost('/items/inlineUpdate').reply(200, { success: true, payload: {} })
    const w = await mountTable()
    const cell = bodyRows(w)[0].find('.admin-inline-edit--editable')
    expect(cell.exists()).toBe(true)
    await cell.trigger('click')
    await flushPromises()
    const input = bodyRows(w)[0].find('input.admin-inline-edit__input')
    await input.setValue('Uno')
    await input.trigger('keydown', { key: 'Enter' })
    await flushPromises()
    expect(JSON.parse(mock.history.post.find((r) => r.url === '/items/inlineUpdate')!.data))
      .toEqual({ id: 1, column: 'title', value: 'Uno' })
    expect(bodyRows(w)[0].text()).toContain('Uno')
    // A column without `editable` is read-only.
    expect(bodyRows(w)[0].findAll('.admin-inline-edit--editable')).toHaveLength(2)
    w.unmount()
  })
})
