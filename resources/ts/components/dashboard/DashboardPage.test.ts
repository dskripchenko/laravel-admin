import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import DashboardPage from './DashboardPage.vue'
import { useManifestStore } from '../../stores/manifest'
import { clearWidgetRegistry } from './registry'
import { registerBuiltinWidgets } from './builtin'

describe('DashboardPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    clearWidgetRegistry()
    registerBuiltinWidgets()
  })

  it('renders title from props', () => {
    const w = mount(DashboardPage, {
      props: { title: 'Аналитика', widgets: [] },
    })
    expect(w.find('.admin-page__title').text()).toBe('Аналитика')
  })

  it('renders widgets from prop with span styles', () => {
    const w = mount(DashboardPage, {
      props: {
        title: 'D',
        widgets: [
          { type: 'stat', span: 3, title: 'Total', value: 100 },
          { type: 'stat', span: 9, title: 'Wide', value: 50 },
        ],
      },
    })
    const cells = w.findAll('.admin-dashboard__cell')
    expect(cells).toHaveLength(2)
    expect(cells[0].attributes('style')).toContain('span 3')
    expect(cells[1].attributes('style')).toContain('span 9')
  })

  it('default span = 12', () => {
    const w = mount(DashboardPage, {
      props: {
        widgets: [{ type: 'stat', title: 'X', value: 1 }],
      },
    })
    expect(w.find('.admin-dashboard__cell').attributes('style')).toContain('span 12')
  })

  it('span clamped to [1, 12]', () => {
    const w = mount(DashboardPage, {
      props: {
        widgets: [
          { type: 'stat', span: 99, title: 'X' },
          { type: 'stat', span: 0, title: 'Y' },
        ],
      },
    })
    const cells = w.findAll('.admin-dashboard__cell')
    expect(cells[0].attributes('style')).toContain('span 12')
    expect(cells[1].attributes('style')).toContain('span 1')
  })

  it('reads dashboard from manifest by slug', () => {
    const manifest = useManifestStore()
    manifest.manifest = {
      version: 'v1',
      locale: 'ru',
      resources: [],
      screens: [],
      settings: [],
      dashboards: [
        {
          slug: 'main',
          label: 'Главный',
          widgets: [{ type: 'stat', span: 4, title: 'Total', value: 42 }],
        },
      ] as never,
      plugins: [],
      permissions: [],
    }
    const w = mount(DashboardPage, { props: { slug: 'main' } })
    expect(w.find('.admin-page__title').text()).toBe('Главный')
    expect(w.findAll('.admin-dashboard__cell')).toHaveLength(1)
  })

  function withDashboard(extra: Record<string, unknown>): void {
    const manifest = useManifestStore()
    manifest.manifest = {
      version: 'v1',
      locale: 'ru',
      resources: [],
      screens: [],
      settings: [],
      dashboards: [{ slug: 'main', label: 'Главный', widgets: [], ...extra }] as never,
      plugins: [],
      permissions: [],
    }
  }

  it('hides the period switcher when the dashboard offers no periods', () => {
    withDashboard({ periods: [] })
    const w = mount(DashboardPage, { props: { slug: 'main' } })
    expect(w.find('[data-testid="dash-period"]').exists()).toBe(false)
  })

  it('shows the dashboard\'s own periods and starts from its default', () => {
    withDashboard({ periods: ['7d', '14d'], period: '14d' })
    const w = mount(DashboardPage, { props: { slug: 'main' } })
    const trigger = w.find('[data-testid="dash-period"]')
    expect(trigger.exists()).toBe(true)
    expect(trigger.text()).toContain('14')
  })

  it('keeps the standard switcher for a page built from props', () => {
    const w = mount(DashboardPage, { props: { widgets: [] } })
    expect(w.find('[data-testid="dash-period"]').text()).toContain('30')
  })

  it('renders UnknownWidget for missing widget type', () => {
    clearWidgetRegistry()
    const w = mount(DashboardPage, {
      props: { widgets: [{ type: 'mystery' }] },
    })
    expect(w.text()).toContain('mystery')
  })

  it('renders Stat widget value', () => {
    const w = mount(DashboardPage, {
      props: {
        widgets: [{ type: 'stat', title: 'Posts', value: 123 }],
      },
    })
    expect(w.text()).toContain('Posts')
    expect(w.text()).toContain('123')
  })
})

describe('edit-mode draft seeding (первый save без persisted layout)', () => {
  it('seedDraft заполняет пустой draft и не трогает непустой', async () => {
    const { createPinia, setActivePinia } = await import('pinia')
    setActivePinia(createPinia())
    const { useDashboardStore } = await import('../../stores/dashboard')
    const store = useDashboardStore()

    store.seedDraft([{ slug: 'stat-clients', size: 6, type: 'stats' }])
    expect(store.draft.length).toBe(1)
    expect(store.draft[0].slug).toBe('stat-clients')

    // Seeding again does not overwrite an existing draft.
    store.seedDraft([{ slug: 'other', size: 12 }])
    expect(store.draft.length).toBe(1)
    expect(store.draft[0].slug).toBe('stat-clients')
  })
})
