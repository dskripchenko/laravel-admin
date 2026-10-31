import { describe, it, expect, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { defineComponent, h } from 'vue'
import WidgetConfigDialog from './WidgetConfigDialog.vue'
import AddWidgetDialog from './AddWidgetDialog.vue'
import {
  clearWidgetRegistry,
  describeWidget,
  getWidgetDescription,
  listAddableWidgets,
  registerWidget,
} from './registry'
import { registerBuiltinWidgets } from './builtin'
import { buildWidgetConfig, initialValues, type WidgetConfigValues } from './widgetConfig'
import { useManifestStore, type AdminManifest } from '../../stores/manifest'
import { useAuthStore } from '../../stores/auth'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

/** The configs WidgetConfigDialog built before the descriptions, by its old hard-coded switch. */
const LEGACY_DEFAULTS: Record<string, Record<string, unknown>> = {
  markdown: { title: 'T', content: '# Новая заметка\n\nТекст…' },
  stat: { title: 'T', stats: [{ label: 'LABEL', value: '0' }] },
  stats: { title: 'T', stats: [{ label: 'LABEL', value: '0' }] },
  gauge: { title: 'T', value: 50, min: 0, max: 100 },
  chart: { title: 'T', labels: ['Янв', 'Фев', 'Мар', 'Апр'], datasets: [{ label: 'T', data: [10, 20, 15, 30] }], type: 'bar' },
  'bar-chart': { title: 'T', labels: ['Янв', 'Фев', 'Мар', 'Апр'], datasets: [{ label: 'T', data: [10, 20, 15, 30] }], type: 'bar' },
  'donut-chart': { title: 'T', labels: ['Янв', 'Фев', 'Мар', 'Апр'], datasets: [{ label: 'T', data: [10, 20, 15, 30] }], type: 'doughnut' },
  'recent-table': { title: 'T', resource: '', limit: 5 },
  recent_list: { title: 'T', resource: '', limit: 5 },
  'recent-list': { title: 'T', resource: '', limit: 5 },
  heatmap: { title: 'T' },
  table: { title: 'T' },
  // iframe gained its address field.
  iframe: { title: 'T', src: '' },
}

/** Saved configs of user-added widgets, as layouts written before hold them. */
const STORED: Array<{ type: string; config: Record<string, unknown> }> = [
  { type: 'markdown', config: { title: 'Notes', content: '## Hello' } },
  { type: 'stat', config: { title: 'Users', stats: [{ label: 'TOTAL', value: '42' }] } },
  { type: 'gauge', config: { title: 'Load', value: 73, min: 0, max: 100 } },
  { type: 'donut-chart', config: { title: 'Mix', labels: ['A', 'B'], datasets: [{ label: 'Mix', data: [1, 2] }], type: 'doughnut' } },
  { type: 'recent_list', config: { title: 'Latest', resource: 'articles', limit: 8 } },
]

function build(type: string, values: WidgetConfigValues, title = 'T'): Record<string, unknown> {
  return buildWidgetConfig(getWidgetDescription(type), values, { type, title })
}

describe('widget descriptions — the built-in types keep their config shapes', () => {
  beforeEach(() => {
    clearWidgetRegistry()
    registerBuiltinWidgets()
  })

  it('builds the same default config as the old dialog for every existing type', () => {
    for (const [type, expected] of Object.entries(LEGACY_DEFAULTS)) {
      const values = initialValues(getWidgetDescription(type), null)
      expect(build(type, values), type).toEqual(expected)
    }
  })

  it('reads a stored config back into the form and rebuilds it unchanged', () => {
    for (const { type, config } of STORED) {
      const description = getWidgetDescription(type)
      const values = initialValues(description, config)
      expect(build(type, values, config.title as string), type).toEqual(config)
    }
  })

  it('offers the new document types with their own configs', () => {
    expect(build('diagram', initialValues(getWidgetDescription('diagram'), null))).toEqual({
      title: 'T', syntax: null, content: 'flowchart LR\n  A[Start] --> B[Finish]',
    })
    expect(build('diagram', { syntax: 'dbml', content: 'Table a {}' })).toEqual({ title: 'T', syntax: 'dbml', content: 'Table a {}' })
    expect(build('bpmn', { notation: 'dmn', from: 'url', url: ' /files/a.dmn ', content: 'x' }))
      .toEqual({ title: 'T', notation: 'dmn', url: '/files/a.dmn' })
    expect(build('bpmn', { notation: 'bpmn', from: 'xml', url: '', content: '<definitions/>' }))
      .toEqual({ title: 'T', notation: 'bpmn', content: '<definitions/>' })
    expect(build('kanban', { mode: 'resource', resource: 'tasks', search: ' x ', editable: true, content: 'ignored' }))
      .toEqual({ title: 'T', mode: 'resource', resource: 'tasks', search: 'x', editable: true })
    expect(build('kanban', { mode: 'markup', resource: 'tasks', content: '## A' }))
      .toEqual({ title: 'T', mode: 'markup', content: '## A' })
    expect(build('calendar', { ...initialValues(getWidgetDescription('calendar'), null), resource: 'events' }))
      .toEqual({ title: 'T', mode: 'resource', resource: 'events', search: '', editable: false, initialView: 'agenda' })
  })

  it('lists every registered component as addable unless its description says otherwise', () => {
    expect(listAddableWidgets()).toContain('markdown')
    expect(listAddableWidgets()).toContain('kanban')
    describeWidget('heatmap', { addable: false })
    expect(listAddableWidgets()).not.toContain('heatmap')
  })

  it('offers an aliased type once, and not a widget whose data only the server gives', () => {
    const addable = listAddableWidgets()
    expect(addable).toContain('recent-table')
    expect(addable).not.toContain('recent_list')
    expect(addable).not.toContain('recent-list')
    expect(addable).not.toContain('table')
    expect(addable).toContain('iframe')
    // The aliases still read a saved config.
    expect(getWidgetDescription('recent_list')?.fields?.map((f) => f.key)).toEqual(['resource', 'limit'])
  })

  it('keeps a description the host gave before the built-ins registered', () => {
    clearWidgetRegistry()
    describeWidget('markdown', { label: 'Mine' })
    registerBuiltinWidgets()
    expect(getWidgetDescription('markdown')?.label).toBe('Mine')
  })
})

describe('WidgetConfigDialog — driven by the descriptions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    clearWidgetRegistry()
    registerBuiltinWidgets()
  })

  const mountDialog = (props: Record<string, unknown>) =>
    mount(WidgetConfigDialog, { props: { open: false, ...props }, global: { stubs: { teleport: true } } })

  it('shows a host-registered type in AddWidgetDialog with its own form, and builds its config', async () => {
    registerWidget('weather', Stub, {
      label: 'Weather',
      defaultSize: 3,
      fields: [
        { kind: 'text', key: 'city', label: 'City', required: true },
        { kind: 'number', key: 'days', label: 'Days', default: 3 },
      ],
    })
    const w = mount(AddWidgetDialog, { props: { open: false }, global: { stubs: { teleport: true } } })
    await w.setProps({ open: true })

    const card = w.find('[data-testid="widget-type-weather"]')
    expect(card.exists()).toBe(true)
    expect(card.text()).toContain('Weather')
    await card.trigger('click')

    const city = w.find('[data-testid="widget-config-city"] input')
    expect(city.exists()).toBe(true)
    await w.findAll('input').find((i) => i.attributes('placeholder') === 'Название виджета')!.setValue('Forecast')
    // The required city is empty: no submit yet.
    const add = () => w.findAll('button').find((b) => b.text() === 'Добавить')!
    expect(add().attributes('disabled')).toBeDefined()
    await city.setValue('Moscow')
    await add().trigger('click')

    const [[item]] = w.emitted('add') as Array<[Record<string, unknown>]>
    expect(item.type).toBe('weather')
    expect(item.size).toBe(3)
    expect(item.config).toEqual({ title: 'Forecast', city: 'Moscow', days: 3 })
  })

  it('opens a widget of a stored layout with its values and saves the same config', async () => {
    const stored = STORED[1]
    const w = mountDialog({ mode: 'configure', item: { slug: 'custom.stat.1', type: stored.type, size: 4, config: stored.config } })
    await w.setProps({ open: true })

    const inputs = w.findAll('[data-testid^="widget-config-"] input')
    expect(inputs.map((i) => (i.element as HTMLInputElement).value)).toEqual(['TOTAL', '42'])
    await w.findAll('button').find((b) => b.text() === 'Сохранить')!.trigger('click')
    expect(w.emitted('save')).toEqual([[{ size: 4, type: 'stat', config: stored.config }]])
  })

  it('offers only the resources that have a kanban view and that the user may view', async () => {
    useManifestStore().manifest = {
      version: '1',
      locale: 'en',
      resources: [
        { slug: 'tasks', label: 'Tasks', permissions: { view: 'admin.tasks.view' }, kanban: { column: 'status' } },
        { slug: 'secret', label: 'Secret', permissions: { view: 'admin.secret.view' }, kanban: { column: 'status' } },
        { slug: 'users', label: 'Users', permissions: { view: 'admin.users.view' }, kanban: null },
      ],
      screens: [],
      settings: [],
      dashboards: [],
      plugins: [],
      permissions: [],
    } as unknown as AdminManifest
    useAuthStore().permissions = ['admin.tasks.view', 'admin.users.view']

    const w = mountDialog({ mode: 'add' })
    await w.setProps({ open: true })
    await w.find('[data-testid="widget-type-kanban"]').trigger('click')
    await flushPromises()

    const select = w.findComponent({ name: 'UidSelect' })
    const labels = (w.findAllComponents({ name: 'UidSelect' })
      .map((s) => s.props('options') as Array<{ label: string }>)
      .find((opts) => opts.some((o) => o.label === 'Tasks')) ?? []).map((o) => o.label)
    expect(select.exists()).toBe(true)
    expect(labels).toEqual(['Tasks'])
  })
})
