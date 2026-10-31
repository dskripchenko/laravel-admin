/**
 * The descriptions of the built-in widget types for the add and configure
 * dialogs — see widgetConfig.ts. The configs they build are the ones the
 * dialogs built before the descriptions existed, key for key, so the layouts
 * saved then keep rendering and keep opening in the configure dialog.
 */
import {
  AppWindow,
  CalendarDays,
  ChartColumn,
  ChartPie,
  FileText,
  Gauge,
  Grid3x3,
  Hash,
  LayoutGrid,
  List,
  Network,
  SquareKanban,
  Table2,
  Workflow,
} from 'lucide-vue-next'
import { trSafe as tr } from '../../stores/i18n'
import type {
  WidgetConfigField,
  WidgetConfigValues,
  WidgetTypeDescription,
} from './widgetConfig'
import { resourcesWithView } from './widgetConfig'

const str = (v: unknown): string => (v === null || v === undefined ? '' : String(v))

/* ------------------------------------------------------------------ existing types */

const markdown: WidgetTypeDescription = {
  label: () => tr('Markdown — заметка / текст'),
  icon: FileText,
  fields: [
    {
      kind: 'textarea',
      key: 'content',
      label: () => tr('Markdown содержимое'),
      rows: 6,
      default: () => `# ${tr('Новая заметка')}\n\n${tr('Текст…')}`,
    },
  ],
  build: (v) => ({ content: str(v.content) }),
}

const statFields: WidgetConfigField[] = [
  { kind: 'text', key: 'label', label: () => tr('Метка'), default: 'LABEL' },
  { kind: 'text', key: 'value', label: () => tr('Значение'), default: '0' },
]
const statRead = (cfg: Record<string, unknown>): WidgetConfigValues => {
  const first = (cfg.stats as Array<Record<string, unknown>> | undefined)?.[0]
  return first ? { label: str(first.label), value: str(first.value) } : {}
}
const statBuild = (v: WidgetConfigValues): Record<string, unknown> => ({
  stats: [{ label: str(v.label), value: str(v.value) }],
})

const gauge: WidgetTypeDescription = {
  label: () => tr('Gauge — шкала со значением'),
  icon: Gauge,
  fields: [{ kind: 'number', key: 'value', label: () => tr('Значение (0..100)'), default: 50 }],
  read: (cfg) => ({ value: String((cfg.value as number | undefined) ?? 50) }),
  build: (v) => ({ value: Number(v.value) || 0, min: 0, max: 100 }),
}

const chartFields: WidgetConfigField[] = [
  {
    kind: 'text',
    key: 'labels',
    label: () => tr('Метки (через запятую)'),
    placeholder: () => tr('Янв, Фев, Мар'),
    default: () => tr('Янв, Фев, Мар, Апр'),
  },
  {
    kind: 'text',
    key: 'values',
    label: () => tr('Значения (через запятую)'),
    placeholder: '10, 20, 15',
    default: '10, 20, 15, 30',
  },
]
const chartRead = (cfg: Record<string, unknown>): WidgetConfigValues => {
  const out: WidgetConfigValues = {}
  const labels = cfg.labels as string[] | undefined
  const datasets = cfg.datasets as Array<{ data?: number[] }> | undefined
  if (labels && labels.length > 0) out.labels = labels.join(', ')
  if (datasets && datasets[0]?.data) out.values = datasets[0].data.join(', ')
  return out
}
const chartBuild = (v: WidgetConfigValues, ctx: { type: string; title: string }): Record<string, unknown> => ({
  labels: str(v.labels).split(',').map((s) => s.trim()).filter(Boolean),
  datasets: [{
    label: ctx.title,
    data: str(v.values).split(',').map((s) => Number(s.trim())).filter((n) => !Number.isNaN(n)),
  }],
  type: ctx.type === 'donut-chart' ? 'doughnut' : 'bar',
})
const chart = (label: () => string, icon = ChartColumn): WidgetTypeDescription => ({
  label,
  icon,
  fields: chartFields,
  read: chartRead,
  build: chartBuild,
})

const recent: WidgetTypeDescription = {
  label: () => tr('Recent list — последние записи'),
  icon: List,
  fields: [
    { kind: 'text', key: 'resource', label: () => tr('Slug ресурса'), placeholder: 'articles', default: '' },
    { kind: 'number', key: 'limit', label: () => tr('Лимит'), default: 5 },
  ],
  read: (cfg) => ({ resource: (cfg.resource as string | undefined) ?? '', limit: String(cfg.limit ?? 5) }),
  build: (v) => ({ resource: str(v.resource), limit: Number(v.limit) || 5 }),
}

/* ------------------------------------------------------------------ documents */

const DIAGRAM_SYNTAXES: Array<[string, string]> = [
  ['mermaid', 'Mermaid'],
  ['plantuml', 'PlantUML'],
  ['dot', 'Graphviz DOT'],
  ['d2', 'D2'],
  ['dbml', 'DBML'],
  ['structurizr', 'Structurizr'],
  ['wardley', 'Wardley map'],
]

const diagram: WidgetTypeDescription = {
  label: () => tr('Диаграмма — Mermaid, PlantUML, DOT и другие'),
  icon: Network,
  defaultSize: 6,
  defaultRowSpan: 3,
  fields: [
    {
      kind: 'select',
      key: 'syntax',
      label: () => tr('Нотация'),
      default: 'auto',
      options: [
        { value: 'auto', label: () => tr('Определить по тексту') },
        ...DIAGRAM_SYNTAXES.map(([value, label]) => ({ value, label: () => label })),
      ],
    },
    {
      kind: 'code',
      key: 'content',
      label: () => tr('Исходный текст диаграммы'),
      rows: 10,
      required: true,
      default: 'flowchart LR\n  A[Start] --> B[Finish]',
    },
  ],
  read: (cfg) => ({ syntax: str(cfg.syntax) || 'auto', content: str(cfg.content) }),
  build: (v) => ({ syntax: v.syntax === 'auto' || !v.syntax ? null : str(v.syntax), content: str(v.content) }),
}

const bpmn: WidgetTypeDescription = {
  label: () => tr('BPMN / DMN — модель процесса или решений'),
  icon: Workflow,
  defaultSize: 6,
  defaultRowSpan: 3,
  fields: [
    {
      kind: 'select',
      key: 'notation',
      label: () => tr('Нотация'),
      default: 'bpmn',
      options: [
        { value: 'bpmn', label: () => tr('BPMN — процесс') },
        { value: 'dmn', label: () => tr('DMN — решения') },
      ],
    },
    {
      kind: 'select',
      key: 'from',
      label: () => tr('Источник'),
      default: 'url',
      options: [
        { value: 'url', label: () => tr('Адрес файла на этом сайте') },
        { value: 'xml', label: () => tr('XML целиком') },
      ],
    },
    {
      kind: 'text',
      key: 'url',
      label: () => tr('Адрес'),
      placeholder: '/files/order.bpmn',
      required: true,
      visible: (v) => v.from !== 'xml',
    },
    {
      kind: 'code',
      key: 'content',
      label: () => tr('XML модели'),
      rows: 10,
      required: true,
      visible: (v) => v.from === 'xml',
    },
  ],
  read: (cfg) => ({
    notation: cfg.notation === 'dmn' ? 'dmn' : 'bpmn',
    from: cfg.url ? 'url' : (cfg.content ? 'xml' : 'url'),
    url: str(cfg.url),
    content: str(cfg.content),
  }),
  build: (v) => (v.from === 'xml'
    ? { notation: str(v.notation) || 'bpmn', content: str(v.content) }
    : { notation: str(v.notation) || 'bpmn', url: str(v.url).trim() }),
}

/* ------------------------------------------------------------------ kanban and calendar */

const SAMPLE_BOARD = '## To do\n- First card #tag\n\n## Doing\n\n## Done'
const SAMPLE_CALENDAR = 'every mon 10:00-10:30 Planning\nevery fri 16:00-17:00 Review'

/** The source fields shared by the kanban and calendar widgets. */
function sourceFields(view: 'kanban' | 'calendar', sample: string): WidgetConfigField[] {
  const byResource = (v: WidgetConfigValues): boolean => v.mode !== 'markup'
  return [
    {
      kind: 'select',
      key: 'mode',
      label: () => tr('Источник'),
      default: 'resource',
      options: [
        { value: 'resource', label: () => tr('Записи ресурса') },
        { value: 'markup', label: () => tr('Разметка') },
      ],
    },
    {
      kind: 'select',
      key: 'resource',
      label: () => tr('Ресурс'),
      required: true,
      visible: byResource,
      options: (ctx) => resourcesWithView(ctx, view),
      help: view === 'kanban'
        ? () => tr('Ресурсы с видом «канбан», которые вам доступны.')
        : () => tr('Ресурсы с видом «календарь», которые вам доступны.'),
    },
    {
      kind: 'text',
      key: 'search',
      label: () => tr('Поиск'),
      placeholder: () => tr('Необязательно'),
      visible: byResource,
    },
    {
      kind: 'switch',
      key: 'editable',
      label: () => tr('Разрешить перетаскивание, если есть право на изменение'),
      visible: byResource,
    },
    {
      kind: 'code',
      key: 'content',
      label: () => tr('Разметка'),
      rows: 10,
      required: true,
      default: sample,
      visible: (v) => v.mode === 'markup',
    },
  ]
}

function readSource(cfg: Record<string, unknown>): WidgetConfigValues {
  const mode = cfg.mode === 'markup' || (!cfg.resource && cfg.content) ? 'markup' : 'resource'
  const out: WidgetConfigValues = {
    mode,
    resource: str(cfg.resource),
    search: str(cfg.search),
    editable: cfg.editable === true,
  }
  if (typeof cfg.content === 'string') out.content = cfg.content
  return out
}

function buildSource(v: WidgetConfigValues): Record<string, unknown> {
  if (v.mode === 'markup') return { mode: 'markup', content: str(v.content) }
  return {
    mode: 'resource',
    resource: str(v.resource),
    search: str(v.search).trim(),
    editable: v.editable === true,
  }
}

const kanban: WidgetTypeDescription = {
  label: () => tr('Канбан — доска ресурса или разметка'),
  icon: SquareKanban,
  defaultSize: 12,
  defaultRowSpan: 3,
  fields: sourceFields('kanban', SAMPLE_BOARD),
  read: readSource,
  build: buildSource,
}

const CALENDAR_VIEWS: Array<[string, () => string]> = [
  ['agenda', () => tr('Повестка')],
  ['day', () => tr('День')],
  ['week', () => tr('Неделя')],
  ['month', () => tr('Месяц')],
  ['year', () => tr('Год')],
]

const calendar: WidgetTypeDescription = {
  label: () => tr('Календарь — события ресурса или разметка'),
  icon: CalendarDays,
  defaultSize: 6,
  defaultRowSpan: 3,
  fields: [
    ...sourceFields('calendar', SAMPLE_CALENDAR),
    {
      kind: 'select',
      key: 'initialView',
      label: () => tr('Вид'),
      default: 'agenda',
      options: CALENDAR_VIEWS.map(([value, label]) => ({ value, label })),
    },
  ],
  read: (cfg) => ({ ...readSource(cfg), initialView: str(cfg.initialView) || 'agenda' }),
  build: (v) => ({ ...buildSource(v), initialView: str(v.initialView) || 'agenda' }),
}

/**
 * The built-in descriptions by type. Aliases of one widget share one.
 */
export function builtinWidgetDescriptions(): Record<string, WidgetTypeDescription> {
  const stat: WidgetTypeDescription = {
    label: () => tr('Stat — карточка с числом'),
    icon: Hash,
    fields: statFields,
    read: statRead,
    build: statBuild,
  }
  const stats: WidgetTypeDescription = { ...stat, label: () => tr('Stats overview — несколько метрик'), icon: LayoutGrid }
  return {
    stat,
    stats,
    chart: chart(() => tr('Chart — линия / столбцы / донат')),
    'bar-chart': chart(() => tr('Bar chart — гистограмма')),
    'donut-chart': chart(() => tr('Donut — кольцевая диаграмма'), ChartPie),
    'recent-table': recent,
    // Aliases of the same widget: their saved config still works; the dialog offers it once.
    recent_list: { ...recent, addable: false },
    'recent-list': { ...recent, addable: false },
    // Its rows come from the server's TableWidget only: nothing for a user to set.
    table: { label: () => tr('Таблица'), icon: Table2, addable: false },
    iframe: {
      label: () => tr('Встроенная страница — iframe'),
      icon: AppWindow,
      fields: [
        { kind: 'text', key: 'src', label: () => tr('Адрес страницы'), placeholder: 'https://', required: true, default: '' },
      ],
      read: (cfg) => ({ src: (cfg.src as string | undefined) ?? '' }),
      build: (v) => ({ src: str(v.src) }),
    },
    heatmap: { label: () => tr('Heatmap — тепловая карта'), icon: Grid3x3 },
    gauge,
    markdown,
    diagram,
    bpmn,
    kanban,
    calendar,
    // A board the package creates: built on the server, not offered to add.
    board: { label: () => tr('Доска — совместная работа'), icon: SquareKanban, addable: false },
  }
}
