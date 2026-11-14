import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import StatWidget from './StatWidget.vue'
import ListWidget from './ListWidget.vue'
import ActionsWidget from './ActionsWidget.vue'
import WidgetRenderer from './WidgetRenderer.vue'
import AdminTableCell from '../resource/AdminTableCell.vue'
import { formatCell, cellTone, cellValue, progressPercent } from '../resource/cellFormat'
import { clearWidgetRegistry } from './registry'
import { registerBuiltinWidgets } from './builtin'

const Stub = defineComponent({ render: () => h('div') })

async function mkRouter(): Promise<Router> {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:p(.*)*', component: Stub }],
  })
  await router.push('/')
  await router.isReady()
  return router
}

beforeEach(() => {
  setActivePinia(createPinia())
  clearWidgetRegistry()
  registerBuiltinWidgets()
})

describe('StatWidget: a share in percentage points (1.75)', () => {
  it('computes the change from the previous period in p.p., not per cent', () => {
    const w = mount(StatWidget, {
      props: {
        title: 'Итоги',
        stats: [
          { label: 'Сбои', value: 0.6, suffix: '%', precision: 1, share: true, previous: 0.8 },
          { label: 'Договоров', value: 120, previous: 100 },
        ],
      },
    })
    const stats = w.findAll('.uid-stat')
    expect(stats[0]!.text()).toMatch(/0[.,]2 п\.п\./)
    expect(stats[0]!.text()).not.toContain('25')
    // The plain number keeps its change in per cent.
    expect(stats[1]!.text()).toContain('20%')
  })

  it('shows an explicit trend in p.p. when its unit says so', () => {
    const w = mount(StatWidget, {
      props: { title: 'Итоги', stats: [{ label: 'Сбои', value: 0.6, change: { delta: 0.2, direction: 'down', unit: 'pp' } }, { label: 'B', value: 1 }] },
    })
    expect(w.findAll('.uid-stat')[0]!.text()).toMatch(/0[.,]2 п\.п\./)
  })
})

describe('StatWidget: compact, tiles and a list (1.75)', () => {
  it('puts the numbers in one row inside one card, the list under them', async () => {
    const router = await mkRouter()
    const w = mount(StatWidget, {
      props: {
        title: 'Отложенные процессы',
        layout: 'compact',
        stats: [
          { label: 'В очереди', value: 142, color: 'info' },
          { label: 'Сбои за сутки', value: 3, color: 'danger', url: '/r/processes?status=error' },
          { label: 'Зависли', value: 1, color: 'warning' },
        ],
        items: [{ label: 'Выпуск полиса ВЗР', secondary: '14:12', badge: 'ошибка КИАС', tone: 'danger', url: '/r/processes/15', key: 'p15' }],
      },
      global: { plugins: [router] },
    })
    expect(w.find('[data-testid="widget-stats-compact"]').exists()).toBe(true)
    expect(w.findAll('.uid-stat')).toHaveLength(0)
    const items = w.findAll('.admin-stats-compact__item')
    expect(items).toHaveLength(3)
    expect(items[0]!.find('.admin-stats-compact__label').text()).toBe('В очереди')
    expect(items[0]!.find('.admin-stats-compact__value').text()).toBe('142')
    // Only an alerting tone tints the number.
    expect(items[0]!.find('.admin-stats-compact__value').classes()).not.toContain('admin-stats-value--info')
    expect(items[1]!.find('.admin-stats-compact__value').classes()).toContain('admin-stats-value--danger')
    expect(items[1]!.attributes('href')).toBe('/r/processes?status=error')
    expect(w.find('.admin-stats-compact__row').attributes('style')).toContain('--admin-stats-count: 3')
    const row = w.find('[data-testid="list-item-p15"]')
    expect(row.attributes('href')).toBe('/r/processes/15')
    expect(row.text()).toContain('ошибка КИАС')
    expect(row.find('.uid-badge').exists()).toBe(true)
  })

  it('becomes compact when it carries rows, and shows the trend as text', () => {
    const w = mount(StatWidget, {
      props: {
        title: 'Итоги',
        stats: [{ label: 'Сбои', value: 0.6, share: true, previous: 0.8 }],
        items: [],
        emptyText: 'Сбоев нет',
      },
    })
    expect(w.find('[data-testid="widget-stats-compact"]').exists()).toBe(true)
    expect(w.find('[data-testid="stat-trend"]').text()).toMatch(/^▼ 0[.,]2 п\.п\.$/)
    expect(w.find('[data-testid="stat-trend"]').classes()).toContain('admin-stats-trend--down')
    expect(w.find('[data-testid="widget-list-empty"]').text()).toBe('Сбоев нет')
  })

  it('draws tiles with the hint as a badge of its tone', async () => {
    const router = await mkRouter()
    const w = mount(StatWidget, {
      props: {
        title: 'Очереди',
        layout: 'tiles',
        stats: [
          { label: 'Обращения', value: 12, color: 'danger', hint: 'старейшее 26 ч', hintTone: 'danger', url: '/screens/q' },
          { label: 'Заявки', value: 31, hint: '3 ч' },
        ],
      },
      global: { plugins: [router] },
    })
    const tiles = w.findAll('.admin-stats-tile')
    expect(tiles).toHaveLength(2)
    expect(tiles[0]!.attributes('href')).toBe('/screens/q')
    expect(tiles[0]!.find('.admin-stats-tile__value').text()).toBe('12')
    const badge = tiles[0]!.find('[data-testid="stat-hint"]')
    expect(badge.classes()).toContain('uid-badge')
    expect(badge.text()).toBe('старейшее 26 ч')
    // Without a tone the hint stays plain text.
    expect(tiles[1]!.find('[data-testid="stat-hint"]').classes()).toContain('admin-stats-widget__hint')
  })

  it('keeps the cards as they were without a layout or rows', () => {
    const w = mount(StatWidget, { props: { title: 'A', stats: [{ label: 'X', value: 1 }, { label: 'Y', value: 2 }] } })
    expect(w.findAll('.uid-stat')).toHaveLength(2)
    expect(w.find('.admin-stats-widget__title').text()).toBe('A')
  })
})

describe('ListWidget (1.75)', () => {
  it('draws rows without a table header: caption, secondary text, toned badge, link', async () => {
    const router = await mkRouter()
    const w = mount(ListWidget, {
      props: {
        title: 'Очереди',
        items: [
          { label: 'Обращения', secondary: 'старейшее 26 ч', badge: 12, tone: 'danger', url: '/screens/q?tab=claims', key: 'claims' },
          { label: 'Заявки', badge: 31, tone: 'neutral' },
        ],
      },
      global: { plugins: [router] },
    })
    expect(w.find('thead').exists()).toBe(false)
    expect(w.find('.admin-widget__title').text()).toBe('Очереди')
    const first = w.find('[data-testid="list-item-claims"]')
    expect(first.attributes('href')).toBe('/screens/q?tab=claims')
    expect(first.find('.admin-list-rows__secondary').text()).toBe('старейшее 26 ч')
    expect(first.find('.uid-badge').text()).toBe('12')
    expect(first.find('.uid-badge').classes().join(' ')).toContain('danger')
    const second = w.findAll('[data-testid="list-item"]')
    expect(second).toHaveLength(1)
    expect(second[0]!.element.tagName).toBe('DIV')
  })

  it('shows the empty state', () => {
    const w = mount(ListWidget, { props: { title: 'Очереди', items: [] } })
    expect(w.find('[data-testid="widget-list-empty"]').text()).toBe('Нет данных')
    const own = mount(ListWidget, { props: { items: [], emptyText: 'Пусто' } })
    expect(own.find('[data-testid="widget-list-empty"]').text()).toBe('Пусто')
  })
})

describe('ActionsWidget (1.75)', () => {
  it('draws the buttons, the first one primary, and follows a click', async () => {
    const router = await mkRouter()
    const w = mount(ActionsWidget, {
      props: {
        title: 'Создать',
        items: [
          { key: 'resource.news', label: 'Новость', url: '/r/news/create', icon: 'newspaper' },
          { key: 'resource.posts', label: 'Статья блога', url: '/r/posts/create' },
        ],
      },
      global: { plugins: [router] },
    })
    const buttons = w.findAll('button')
    expect(buttons).toHaveLength(2)
    expect(buttons[0]!.attributes('data-testid')).toBe('widget-action-resource-news')
    expect(buttons[0]!.classes().join(' ')).toContain('primary')
    expect(buttons[1]!.classes().join(' ')).not.toContain('primary')
    await buttons[1]!.trigger('click')
    await new Promise((r) => setTimeout(r, 0))
    expect(router.currentRoute.value.fullPath).toBe('/r/posts/create')
  })

  it('says so when no action is available', () => {
    const w = mount(ActionsWidget, { props: { items: [] } })
    expect(w.find('[data-testid="widget-actions-empty"]').exists()).toBe(true)
  })
})

describe('Widget header links (1.75)', () => {
  it('shows the links in the header of a widget that draws one', async () => {
    const router = await mkRouter()
    const w = mount(WidgetRenderer, {
      props: {
        node: {
          type: 'table',
          title: 'Продажи по продуктам',
          headerLinks: [{ label: 'Отчёт целиком', url: '/screens/sales', key: 'report' }, { label: 'Внешний', url: 'https://example.com/r' }],
          data: { columns: [{ name: 'a', label: 'A' }], rows: [] },
        },
      },
      global: { plugins: [router] },
    })
    await w.vm.$nextTick()
    const header = w.find('.admin-widget__hd')
    const report = header.find('[data-testid="widget-header-link-report"]')
    expect(report.text()).toBe('Отчёт целиком')
    expect(report.attributes('href')).toBe('/screens/sales')
    const external = header.find('[data-testid="widget-header-link"]')
    expect(external.attributes('target')).toBe('_blank')
    // Shown once, and not passed down as an attribute.
    expect(w.findAll('[data-testid="widget-header-link-report"]')).toHaveLength(1)
    expect(w.html()).not.toContain('headerlinks')
  })

  it('puts them in the corner of a widget without a header', async () => {
    const router = await mkRouter()
    const w = mount(WidgetRenderer, {
      props: {
        node: { type: 'stats', headerLinks: [{ label: 'Журнал', url: '/r/log', key: 'log' }], data: { stats: [{ label: 'A', value: 1 }] } },
      },
      global: { plugins: [router] },
    })
    await w.vm.$nextTick()
    expect(w.find('.admin-widget-linked__links [data-testid="widget-header-link-log"]').exists()).toBe(true)
  })

  it('shows the links of a titled stats section in its header', async () => {
    const router = await mkRouter()
    const w = mount(WidgetRenderer, {
      props: {
        node: {
          type: 'stats',
          title: 'Итоги периода',
          headerLinks: [{ label: 'Продажи', url: '/screens/sales', key: 'sales' }],
          data: { stats: [{ label: 'A', value: 1 }, { label: 'B', value: 2 }] },
        },
      },
      global: { plugins: [router] },
    })
    await w.vm.$nextTick()
    expect(w.find('.admin-widget__hd [data-testid="widget-header-link-sales"]').exists()).toBe(true)
    expect(w.find('.admin-widget-linked__links').exists()).toBe(false)
  })
})

describe('Table cells: progress and tone (1.75)', () => {
  it('formats a progress value as a per cent of its max', () => {
    expect(progressPercent(42, {})).toBe(42)
    expect(progressPercent(0.25, { max: 1 })).toBe(25)
    expect(progressPercent('x', {})).toBeNull()
    expect(formatCell(42, 'progress', {})).toBe('42 %')
    expect(formatCell(0.256, 'progress', { max: 1, decimals: 1 })).toMatch(/^25[.,]6 %$/)
  })

  it('draws a bar with its caption', () => {
    const w = mount(AdminTableCell, { props: { value: 33.3, preset: 'progress', meta: { max: 100, decimals: 0 } } })
    const bar = w.find('[data-testid="cell-progress"]')
    expect(bar.attributes('role')).toBe('progressbar')
    expect(bar.find('.admin-cell-progress__fill').attributes('style')).toContain('width: 33.3%')
    expect(bar.find('.admin-cell-progress__label').text()).toBe('33 %')
  })

  it('clamps the bar and colours it by the row tone', () => {
    const w = mount(AdminTableCell, {
      props: { value: 150, preset: 'progress', meta: {}, row: { __tones: { share: 'danger' } }, column: 'share' },
    })
    const style = w.find('.admin-cell-progress__fill').attributes('style')
    expect(style).toContain('width: 100%')
    expect(style).toContain('--uid-color-danger')
  })

  it('reads a cell tone from the row or from a {value, tone} cell', () => {
    expect(cellTone(5, { __tones: { change: 'success' } }, 'change')).toBe('success')
    expect(cellTone(5, { __tones: { change: 'red' } }, 'change')).toBe('danger')
    expect(cellTone({ value: '−4%', tone: 'danger' }, null, null)).toBe('danger')
    expect(cellTone(5, {}, 'change')).toBeNull()
    expect(cellValue({ value: 3, tone: 'info' })).toBe(3)
    // A JSON value with other keys is not a toned cell.
    expect(cellValue({ value: 3, tone: 'info', extra: 1 })).toEqual({ value: 3, tone: 'info', extra: 1 })
  })

  it('colours the text of a toned cell, and a badge by the tone', () => {
    const text = mount(AdminTableCell, { props: { value: '+18%', row: { __tones: { change: 'success' } }, column: 'change' } })
    expect(text.find('span').classes()).toContain('admin-cell--tone-success')
    expect(text.text()).toBe('+18%')
    const own = mount(AdminTableCell, { props: { value: { value: '−4%', tone: 'danger' } } })
    expect(own.find('span').classes()).toContain('admin-cell--tone-danger')
    expect(own.text()).toBe('−4%')
    const badge = mount(AdminTableCell, {
      props: { value: 'up', preset: 'badge', meta: { colors: { up: 'neutral' } }, row: { __tones: { change: 'success' } }, column: 'change' },
    })
    expect(badge.find('.uid-badge').classes().join(' ')).toContain('success')
  })
})
