import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import FieldRenderer from '../render/FieldRenderer.vue'
import { provideFormState, type FormStateContext } from '../render/formState'
import { clearRegistry } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'
import { provideRecord } from '../infolist/recordContext'
import ResourcePickerEntry from '../infolist/ResourcePickerEntry.vue'
import { createAdminClient } from '../../api/client'
import { clearAdminClient, setAdminClient } from '../../stores/registry'
import { useAuthStore } from '../../stores/auth'
import { useManifestStore, type AdminManifest } from '../../stores/manifest'

const PHOTOS = [
  { id: 1, name: 'Sunset', _picker: { id: 1, title: 'Sunset', subtitle: 'sunset.jpg', preview: '/t/1.jpg' } },
  { id: 2, name: 'Harbour', _picker: { id: 2, title: 'Harbour', subtitle: null, preview: '/t/2.jpg' } },
  { id: 3, name: 'Forest', _picker: { id: 3, title: 'Forest', subtitle: null, preview: null } },
]

let ctx: FormStateContext | null = null
let mock: MockAdapter
let searches: Array<Record<string, unknown>> = []

const Wrapper = defineComponent({
  props: {
    initial: { type: Object, default: () => ({}) },
    node: { type: Object, required: true },
  },
  setup(props) {
    ctx = provideFormState(props.initial as Record<string, unknown>)
    return () => h(FieldRenderer, { node: props.node as { type: string; name: string } })
  },
})

function node(attributes: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    kind: 'field',
    type: 'resource_picker',
    name: 'cover_id',
    label: 'Обложка',
    attributes: { resource: 'photos', viewPermission: 'admin.photos.view', multiple: false, ...attributes },
  }
}

function mountField(initial: Record<string, unknown>, attributes: Record<string, unknown> = {}) {
  return mount(Wrapper, {
    props: { initial, node: node(attributes) },
    global: { stubs: { teleport: true } },
  })
}

async function openDialog(wrapper: ReturnType<typeof mountField>): Promise<void> {
  await wrapper.find('[data-testid="resource-picker-open"]').trigger('click')
  await flushPromises()
}

const lastSearch = (): Record<string, unknown> => searches[searches.length - 1]

beforeEach(() => {
  setActivePinia(createPinia())
  const client = createAdminClient({ baseURL: 'http://api.test' })
  setAdminClient(client)
  mock = new MockAdapter(client.raw)
  searches = []
  mock.onPost('/photos/search').reply((config) => {
    const body = JSON.parse(String(config.data)) as Record<string, unknown>
    searches.push(body)
    const ids = body.ids as number[] | undefined
    const data = ids ? PHOTOS.filter((p) => ids.includes(p.id)) : PHOTOS
    return [200, { success: true, payload: { data, meta: { page: 1, per_page: 24, total: data.length, last_page: 1 } } }]
  })

  useAuthStore().permissions = ['admin.photos.view']
  useManifestStore().manifest = {
    version: '1',
    locale: 'ru',
    resources: [{
      slug: 'photos',
      label: 'Фото',
      permissions: {},
      fields: [],
      columns: [],
      filters: [
        { name: 'album', label: 'Альбом', type: 'input' },
        { name: 'kind', label: 'Тип', type: 'options', options: [] },
      ],
      actions: [],
      searchable: ['name'],
      with: [],
      features: {},
    }],
    screens: [],
    settings: [],
    dashboards: [],
    plugins: [],
    permissions: [],
  } as AdminManifest

  clearRegistry()
  registerBuiltinComponents()
  ctx = null
})

afterEach(() => {
  mock.reset()
  clearAdminClient()
})

describe('ResourcePickerField', () => {
  it('resolves a held key into its record through the search endpoint', async () => {
    const wrapper = mountField({ cover_id: 2 })
    await flushPromises()

    expect(lastSearch()).toMatchObject({ picker: true, ids: [2] })
    expect(wrapper.text()).toContain('Harbour')
    expect(wrapper.find('img').attributes('src')).toBe('/t/2.jpg')
  })

  it('picks a single record: the value is its key', async () => {
    const wrapper = mountField({ cover_id: null })
    await openDialog(wrapper)

    expect(wrapper.find('[data-testid="resource-picker-dialog"]').exists()).toBe(true)
    expect(lastSearch()).toMatchObject({ picker: true, page: 1 })
    expect(lastSearch()).not.toHaveProperty('ids')

    await wrapper.find('[data-testid="resource-picker-item-1"]').trigger('click')
    await wrapper.find('[data-testid="resource-picker-item-3"]').trigger('click')
    expect(wrapper.find('[data-testid="resource-picker-item-3"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('[data-testid="resource-picker-item-1"]').attributes('aria-pressed')).toBe('false')

    await wrapper.find('[data-testid="resource-picker-confirm"]').trigger('click')
    await flushPromises()

    expect(ctx!.getField('cover_id')).toBe(3)
    expect(wrapper.text()).toContain('Forest')
  })

  it('confirms a single record on a double click', async () => {
    const wrapper = mountField({ cover_id: null })
    await openDialog(wrapper)

    await wrapper.find('[data-testid="resource-picker-item-2"]').trigger('dblclick')
    expect(ctx!.getField('cover_id')).toBe(2)
  })

  it('picks several records in the order they were clicked, then reorders and removes them', async () => {
    const wrapper = mountField({ cover_id: [] }, { multiple: true })
    await openDialog(wrapper)

    await wrapper.find('[data-testid="resource-picker-item-3"]').trigger('click')
    await wrapper.find('[data-testid="resource-picker-item-1"]').trigger('click')
    await wrapper.find('[data-testid="resource-picker-confirm"]').trigger('click')
    await flushPromises()
    expect(ctx!.getField('cover_id')).toEqual([3, 1])

    // Move the second record up.
    const up = wrapper.findAll('button[aria-label="Выше"]')
    await up[1].trigger('click')
    expect(ctx!.getField('cover_id')).toEqual([1, 3])

    await wrapper.find('[data-testid="resource-picker-remove-1"]').trigger('click')
    expect(ctx!.getField('cover_id')).toEqual([3])
  })

  it('keeps a multiple picker within maxItems', async () => {
    const wrapper = mountField({ cover_id: [] }, { multiple: true, maxItems: 2 })
    await openDialog(wrapper)

    for (const id of [1, 2, 3]) {
      await wrapper.find(`[data-testid="resource-picker-item-${id}"]`).trigger('click')
    }
    expect(wrapper.text()).toContain('Выбрано: 2 из 2')
    await wrapper.find('[data-testid="resource-picker-confirm"]').trigger('click')
    expect(ctx!.getField('cover_id')).toEqual([1, 2])
  })

  it('sends the fixed filters with every search and leaves them out of the toolbar', async () => {
    const wrapper = mountField({ cover_id: null }, { filters: { album: 'covers' } })
    await openDialog(wrapper)

    expect(lastSearch()).toMatchObject({ filters: { album: 'covers' } })
    const toolbar = wrapper.find('.admin-picker-dialog__toolbar').text()
    expect(toolbar).toContain('Тип')
    expect(toolbar).not.toContain('Альбом')
  })

  it('cannot open the dialog without the target view permission', async () => {
    useAuthStore().permissions = []
    const wrapper = mountField({ cover_id: 2 })
    await flushPromises()

    expect(searches).toHaveLength(0)
    expect(wrapper.text()).toContain('#2')
    expect(wrapper.find('[data-testid="resource-picker-open"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-testid="resource-picker-dialog"]').exists()).toBe(false)
  })

  it('uploads into the target and selects the new record', async () => {
    mock.onPost('/photos/upload').reply(200, { success: true, payload: { photo: { id: 3 } } })
    useAuthStore().permissions = ['admin.photos.view', 'admin.photos.create']
    const wrapper = mountField({ cover_id: null }, {
      upload: { url: '/photos/upload', permission: 'admin.photos.create', fileField: 'file', responseKey: 'photo', data: { album: 'covers' } },
    })
    await openDialog(wrapper)

    const input = wrapper.find('.admin-picker-dialog__file')
    const file = new File(['x'], 'x.png', { type: 'image/png' })
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    await flushPromises()

    const sent = mock.history.post.find((r) => r.url === '/photos/upload')
    expect(sent?.data).toBeInstanceOf(FormData)
    expect((sent?.data as FormData).get('album')).toBe('covers')
    expect(wrapper.find('[data-testid="resource-picker-item-3"]').attributes('aria-pressed')).toBe('true')

    await wrapper.find('[data-testid="resource-picker-confirm"]').trigger('click')
    expect(ctx!.getField('cover_id')).toBe(3)
  })

  it('hides the upload button without the upload permission', async () => {
    const wrapper = mountField({ cover_id: null }, {
      upload: { url: '/photos/upload', permission: 'admin.photos.create' },
    })
    await openDialog(wrapper)

    expect(wrapper.find('[data-testid="resource-picker-upload"]').exists()).toBe(false)
  })
})

describe('ResourcePickerEntry', () => {
  const Host = defineComponent({
    props: { record: { type: Object, required: true } },
    setup(props) {
      provideRecord(props.record as Record<string, unknown>)
      return () => h(ResourcePickerEntry, { name: 'gallery', resource: 'photos', viewPermission: 'admin.photos.view' })
    },
  })

  it('shows the picked records in the stored order, linked to their pages', async () => {
    const wrapper = mount(Host, {
      props: { record: { gallery: [3, 1] } },
      global: { stubs: { UidLink: { props: ['to'], template: '<a :href="to"><slot /></a>' } } },
    })
    await flushPromises()

    const links = wrapper.findAll('a')
    expect(links.map((a) => a.attributes('href'))).toEqual(['/r/photos/3', '/r/photos/1'])
    expect(wrapper.text()).toMatch(/Forest[\s\S]*Sunset/)
  })

  it('shows a placeholder when nothing is picked', () => {
    const wrapper = mount(Host, { props: { record: { gallery: null } } })
    expect(wrapper.text()).toBe('—')
  })
})
