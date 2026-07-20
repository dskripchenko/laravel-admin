<script setup lang="ts">
/**
 * ResourceIndexPage — list-screen для одного Resource'а из manifest'а.
 * Архитектура по docs/design_handoff_laravel_admin/screens-shell.jsx (Resource List).
 *
 * Композиция:
 *   - Page header (title + count + actions cluster)
 *   - Filter bar с search + chips
 *   - Bulk toolbar (заменяет filter bar при selectedCount > 0)
 *   - UidTable с manifest.columns
 *   - States: loading (UidSkeleton ×8) / empty (UidEmptyState) / error (UidErrorState)
 *   - UidPagination внизу
 *
 * Host рендерит page через router. resource-slug приходит из props (либо
 * из route.params).
 */
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  Bookmark,
  ChevronDown,
  Eye,
  GripVertical,
  MoreHorizontal,
  Pencil,
  Plus,
  RotateCcw,
  Trash2,
  Upload,
} from 'lucide-vue-next'
import {
  UidButton,
  UidEmptyState,
  UidErrorState,
  UidIcon,
  UidMenu,
  UidMenuItem,
  UidPagination,
  UidSkeleton,
  UidTable,
  type UidTableColumn,
} from '@dskripchenko/ui'
import { useResourceIndexStore } from '../../stores/resourceIndex'
import { useManifestStore } from '../../stores/manifest'
import { useNavigationStore } from '../../stores/navigation'
import { formatCell, type CellMeta } from './cellFormat'
import AdminFilterToolbar from './AdminFilterToolbar.vue'
import InlineEditCell from './InlineEditCell.vue'
import ResourceTreePage from './ResourceTreePage.vue'
import { adminToast } from '../../stores/toast'
import { useI18nStore } from '../../stores/i18n'

const i18n = useI18nStore()
/**
 * Локальная обёртка над t() с graceful fallback на ru-string.
 * Это позволяет постепенный sweep: пока bootstrap.translations не наполнен,
 * UI показывает ru-defaults; когда host публикует lang-bag — подменяется
 * на переводы.
 */
const tt = (key: string, fallback: string, replace?: Record<string, string | number>): string =>
  i18n.has(key) ? i18n.t(key, replace) : fallback

interface Props {
  /** Slug ресурса (users/articles/etc). */
  slug: string
  /** Заголовок страницы. Если не задан — берётся из manifest'а. */
  title?: string | null
  /** Подпись под заголовком (например "Аналитика контента"). */
  subtitle?: string | null
  /** Текст primary-кнопки «Создать». */
  createLabel?: string
  /** Имя router-route для action «Создать». */
  createRouteName?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  title: null,
  subtitle: null,
  createLabel: 'Создать',
  createRouteName: null,
})

const emit = defineEmits<{
  /** Bulk-action triggered с текущим Set'ом id. */
  'bulk-action': [action: string, ids: Array<string | number>]
  /** Click на row — host решает (push edit / open view). */
  'row-click': [row: Record<string, unknown>]
  /** Header-action: import — host вешает обработчик загрузки CSV/JSON. */
  'import': []
  /** Header more-menu action — произвольный ключ из additional-items. */
  'header-action': [action: string]
}>()

const index = useResourceIndexStore()
const manifest = useManifestStore()
const nav = useNavigationStore()
const router = useRouter()

const resourceMeta = computed(() => manifest.getResource(props.slug))

const displayTitle = computed(
  () => props.title ?? resourceMeta.value?.label ?? props.slug,
)

/**
 * Auto-derive create route name. Резервный вариант — стандартный pattern
 * из router/builder.ts (`admin.resource.{slug}.create`). Кнопка "Создать"
 * рендерится если route действительно зарегистрирован — у read-only
 * Resource'а его не будет (backend не отдаст create permission).
 */
const resolvedCreateRouteName = computed<string | null>(() => {
  if (props.createRouteName !== null) {
    return router.hasRoute(props.createRouteName) ? props.createRouteName : null
  }
  const candidate = `admin.resource.${props.slug}.create`
  return router.hasRoute(candidate) ? candidate : null
})

/**
 * Header actions из manifest.actions — Resource->actions() в backend.
 * Каждый node имеет {key, label, icon?, confirm?, ...}. Рендерятся в
 * more-menu (...). По клику вызываем onCustomAction → POST на
 * /{slug}/action/{key}.
 */
interface HeaderAction {
  key: string
  label: string
  confirm?: string
  icon?: string
}
const headerActions = computed<HeaderAction[]>(() => {
  const raw = (resourceMeta.value?.actions ?? []) as Array<Record<string, unknown>>
  return raw
    .map((a) => ({
      key: String(a.key ?? a.name ?? ''),
      label: String(a.label ?? a.name ?? a.key ?? ''),
      confirm: typeof a.confirm === 'string' ? a.confirm : undefined,
      icon: typeof a.icon === 'string' ? a.icon : undefined,
    }))
    .filter((a) => a.key !== '' && a.label !== '')
})

async function onCustomAction(action: HeaderAction): Promise<void> {
  if (action.confirm && !window.confirm(action.confirm)) return
  emit('header-action', action.key)
  try {
    nav.start()
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    // Backend контракт: POST /{slug}/action body {key, ids[], payload?}.
    // Action резолвится через Resource->actions() по name.
    const result = await client.post<{ affected?: number; message?: string }>(
      `/${props.slug}/action`,
      {
        key: action.key,
        ids: [...index.selection],
      },
    )
    await index.load().catch(() => undefined)
    adminToast.success(
      result?.message ?? `Action «${action.label}» применён к ${result?.affected ?? 0} записям.`,
    )
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] header-action failed:', err)
    adminToast.error(`Не удалось выполнить action «${action.label}».`)
  } finally {
    nav.end()
  }
}

/**
 * Export — POST /{slug}/export?format=csv|json|xlsx|pdf. Backend
 * ExporterRegistry резолвит format. Ответ blob, скачиваем через <a download>.
 *
 * Доступные форматы зависят от установленных composer-пакетов:
 *   - csv / json — всегда (без deps)
 *   - xlsx       — openspout/openspout
 *   - pdf        — mpdf/mpdf или dompdf/dompdf
 */
async function onExport(format: 'csv' | 'json' | 'xlsx' | 'pdf' = 'csv'): Promise<void> {
  emit('header-action', 'export')
  try {
    nav.start()
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    const blob = await client.post<Blob>(
      `/${props.slug}/export`,
      { format, filters: index.filters, search: index.search },
      { responseType: 'blob' as const },
    )
    const url = URL.createObjectURL(blob as unknown as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${props.slug}-${new Date().toISOString().slice(0, 10)}.${format}`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] export failed:', err)
    adminToast.error(`Не удалось экспортировать данные в формате ${format.toUpperCase()}.`)
  } finally {
    nav.end()
  }
}

/**
 * Import — file picker → POST multipart на /{slug}/import. После успеха
 * перезагружаем list. Если backend не поддерживает — alert.
 */
const importInput = ref<HTMLInputElement | null>(null)
function onImportClick(): void {
  emit('import')
  importInput.value?.click()
}
/**
 * Default import flow поверх Dskripchenko\LaravelAdmin\Import\ImportController:
 *   1. POST /import/upload (file + resource=slug) → uploadId
 *   2. POST /import/preview → auto-mapping (headers ↔ fields)
 *   3. POST /import/start (uploadId + mapping) → processId
 *   4. polling /import/status?processId=… до status === 'done' | 'failed'
 *
 * Auto-mapping автоматически связывает имена колонок CSV/JSON со своими
 * Resource-полями. Host может перехватить @import до этого flow и открыть
 * собственный wizard.
 */
async function onImportFileChange(e: Event): Promise<void> {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  try {
    nav.start()
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()

    // 1. Upload
    const uploadForm = new FormData()
    uploadForm.append('file', file)
    uploadForm.append('resource', props.slug)
    const uploaded = await client.post<{ upload_id: string }>(
      '/import/upload',
      uploadForm,
    )

    // 2. Preview (для auto-mapping)
    const preview = await client.post<{ mapping: Record<string, string> }>(
      '/import/preview',
      { resource: props.slug, upload_id: uploaded.upload_id },
    )

    // 3. Start
    const started = await client.post<{ process_id: string }>(
      '/import/start',
      {
        resource: props.slug,
        upload_id: uploaded.upload_id,
        mapping: preview.mapping,
      },
    )

    // 4. Poll status
    const finalStatus = await pollImportStatus(client, started.process_id)
    const imported = finalStatus.imported ?? 0
    const failed = finalStatus.failed ?? 0
    if (failed > 0) {
      adminToast.warning(`Импорт завершён с ошибками: ${imported} записей, ${failed} ошибок.`)
    } else {
      adminToast.success(`Импортировано записей: ${imported}.`)
    }
    await index.load().catch(() => undefined)
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] import failed:', err)
    adminToast.error(
      'Импорт не удался. Проверьте формат файла и поля ресурса либо обратитесь к администратору.',
    )
  } finally {
    input.value = ''
    nav.end()
  }
}

interface ImportStatus {
  status: 'pending' | 'running' | 'done' | 'failed'
  imported?: number
  failed?: number
}
async function pollImportStatus(
  client: { get<T>(url: string): Promise<T> },
  processId: string,
): Promise<ImportStatus> {
  // Polling каждые 800ms, но не дольше 90 секунд (сырая защита от вечного loop'а).
  const deadline = Date.now() + 90_000
  while (Date.now() < deadline) {
    await new Promise((resolve) => setTimeout(resolve, 800))
    const status = await client.get<ImportStatus>(
      `/import/status?process_id=${encodeURIComponent(processId)}`,
    )
    if (status.status === 'done' || status.status === 'failed') return status
  }
  return { status: 'failed', failed: 1 }
}

const ACTIONS_KEY = '__row_actions__'
const REORDER_KEY = '__row_reorder__'

/** Resource поддерживает reorder если features.reorderable=true. */
const isReorderable = computed<boolean>(() => {
  const features = (resourceMeta.value?.features ?? {}) as Record<string, unknown>
  return features.reorderable === true
})

/** Resource поддерживает create если features.creatable !== false (default true). */
const isCreatable = computed<boolean>(() => {
  const features = (resourceMeta.value?.features ?? {}) as Record<string, unknown>
  return features.creatable !== false
})

/** Resource поддерживает edit если features.editable !== false. Если false — скрываем edit/delete actions. */
const isEditable = computed<boolean>(() => {
  const features = (resourceMeta.value?.features ?? {}) as Record<string, unknown>
  return features.editable !== false
})

const columns = computed<UidTableColumn[]>(() => {
  const cols = resourceMeta.value?.columns ?? []
  const mapped = cols.map((c) => {
    const col = c as Record<string, unknown>
    return {
      key: String(col.key ?? col.name ?? ''),
      label: String(col.label ?? col.name ?? ''),
      sortable: Boolean(col.sortable),
      align: (col.align as 'left' | 'center' | 'right' | undefined) ?? 'left',
      width: typeof col.width === 'string' ? col.width : undefined,
    }
  })
    .filter((c) => c.key)
    // Применяем visibility-state из toolbar'а (Колонки): если явно false —
    // колонку не рендерим. По умолчанию (отсутствует в map) считаем visible.
    .filter((c) => columnVisibility.value[c.key] !== false)
  // Если Resource reorderable — впереди колонка с drag-handle.
  const head: UidTableColumn[] = isReorderable.value
    ? [{
        key: REORDER_KEY,
        label: '',
        sortable: false,
        align: 'center',
        width: '32px',
      }]
    : []
  // Хвостовая колонка с per-row actions (Просмотр / Редактировать / Удалить).
  // ResourceIndexPage добавляет её всегда — host может скрыть через
  // useShowActions=false (TODO prop).
  return [
    ...head,
    ...mapped,
    {
      key: ACTIONS_KEY,
      label: '',
      sortable: false,
      align: 'right',
      width: '120px',
    },
  ]
})

// Колоночная meta (preset / format / currency / editable / etc.) из manifest'а.
// Используется для formatCell (datetime → 'd.m.Y H:i:s', money → '{val} {ccy}')
// + InlineEditCell проверяет editable.
function columnIsEditable(key: string): boolean {
  const cols = resourceMeta.value?.columns ?? []
  for (const c of cols) {
    const col = c as Record<string, unknown>
    const k = String(col.key ?? col.name ?? '')
    if (k === key) {
      // Backend кладёт editable как объект {rules:...} или null.
      return col.editable !== null && col.editable !== undefined
    }
  }
  return false
}

interface EditableMeta {
  as: 'text' | 'number' | 'select' | 'date' | 'textarea' | 'switcher'
  options: Record<string | number, string>
}

function columnEditableMeta(key: string): EditableMeta {
  const cols = resourceMeta.value?.columns ?? []
  for (const c of cols) {
    const col = c as Record<string, unknown>
    const k = String(col.key ?? col.name ?? '')
    if (k === key) {
      const editable = (col.editable ?? {}) as Record<string, unknown>
      return {
        as: ((editable.as as EditableMeta['as']) ?? 'text'),
        options: (editable.options as Record<string | number, string> | undefined) ?? {},
      }
    }
  }
  return { as: 'text', options: {} }
}
const columnMeta = computed<Record<string, { preset?: string; meta: CellMeta }>>(() => {
  const cols = resourceMeta.value?.columns ?? []
  const result: Record<string, { preset?: string; meta: CellMeta }> = {}
  for (const c of cols) {
    const col = c as Record<string, unknown>
    const key = String(col.key ?? col.name ?? '')
    if (!key) continue
    result[key] = {
      preset: typeof col.preset === 'string' ? col.preset : undefined,
      meta: (col.meta as CellMeta) ?? {},
    }
  }
  return result
})

function renderCell(key: string, slotProps: unknown): string {
  // UidTable scoped-slot передаёт {row: actualRow}.
  const row = (slotProps as { row?: Record<string, unknown> } | undefined)?.row
  const value = row?.[key]
  const m = columnMeta.value[key]
  return formatCell(value, m?.preset, m?.meta ?? {})
}

function rowFromSlot(slotProps: unknown): Record<string, unknown> | undefined {
  return (slotProps as { row?: Record<string, unknown> } | undefined)?.row
}

/**
 * Показывать ли filter-toolbar:
 *   - если есть данные → всегда (search + фильтры внутри);
 *   - если данных нет, но есть активный search/filter → ДА (чтобы пользователь
 *     мог сбросить и снова увидеть items);
 *   - если данных нет и фильтры/поиск пустые → НЕТ (initial empty state).
 */
const hasActiveFilters = computed<boolean>(
  () => index.search !== '' || Object.keys(index.filters).length > 0,
)
const showFilterBar = computed<boolean>(
  () => !index.isEmpty || hasActiveFilters.value,
)

// Filter / column / view state — toolbar делегирует это сюда.
const manifestFilters = computed(
  () => (resourceMeta.value?.filters ?? []) as unknown as Array<Record<string, unknown>>,
)
const manifestColumns = computed(
  () => (resourceMeta.value?.columns ?? []) as unknown as Array<Record<string, unknown>>,
)
const searchPlaceholder = computed(() => {
  const label = (resourceMeta.value?.label ?? props.slug).toLowerCase()
  return `Поиск по ${label}…`
})

const groupByCol = ref<string | null>(null)
const columnVisibility = ref<Record<string, boolean>>({})

async function onFilterApply(name: string, value: unknown): Promise<void> {
  await index.setFilter(name, value)
}
async function onSearchUpdate(v: string): Promise<void> {
  await index.setSearch(v)
}
async function onResetFilters(): Promise<void> {
  index.search = ''
  await index.clearFilters()
}
async function onGroupBy(col: string | null): Promise<void> {
  groupByCol.value = col
  // Backend может или не может поддерживать group-by — пробрасываем
  // в search payload как `group_by`. Если не поддерживается — просто
  // игнорируется. Cast через unknown-bridge т.к. IndexParams strict.
  await index.load({ group_by: col } as unknown as Parameters<typeof index.load>[0])
}
function onColumnsVisibility(next: Record<string, boolean>): void {
  columnVisibility.value = next
}
/**
 * Saved views: state + load + apply/delete. Backend — SavedViewsController,
 * URL pattern /{slug}_views/{action}. Active view хранится локально и
 * подсвечивается в scope-dropdown'е.
 */
interface SavedViewItem {
  id: number
  name: string
  state: Record<string, unknown>
  is_default: boolean
  owned: boolean
}
const savedViews = ref<SavedViewItem[]>([])
const activeViewId = ref<number | null>(null)

async function loadSavedViews(): Promise<void> {
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    const result = await client.get<{ data: SavedViewItem[] }>(`/${props.slug}_views/list`)
    savedViews.value = result.data ?? []
  } catch {
    // Тихо — endpoint опционален. Возможно ресурс без permission или backend
    // не зарегистрировал views.
    savedViews.value = []
  }
}

async function onApplyView(view: SavedViewItem): Promise<void> {
  activeViewId.value = view.id
  const s = view.state as {
    search?: string
    filters?: Record<string, unknown>
    sort?: { key?: string | null; direction?: 'asc' | 'desc' | null }
    group_by?: string | null
    columns?: Record<string, boolean>
  }
  index.search = s.search ?? ''
  index.filters = { ...(s.filters ?? {}) }
  index.sortKey = s.sort?.key ?? null
  index.sortDirection = s.sort?.direction ?? null
  groupByCol.value = s.group_by ?? null
  columnVisibility.value = { ...(s.columns ?? {}) }
  index.meta.page = 1
  await loadWithProgress()
}

async function onResetView(): Promise<void> {
  activeViewId.value = null
  index.search = ''
  index.filters = {}
  index.sortKey = null
  index.sortDirection = null
  groupByCol.value = null
  columnVisibility.value = {}
  index.meta.page = 1
  await loadWithProgress()
}

async function onDeleteView(view: SavedViewItem, e?: MouseEvent): Promise<void> {
  e?.stopPropagation()
  if (!view.owned) return
  if (!window.confirm(`Удалить view «${view.name}»?`)) return
  try {
    nav.start()
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    await client.post(`/${props.slug}_views/delete`, { id: view.id })
    if (activeViewId.value === view.id) activeViewId.value = null
    await loadSavedViews()
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] delete-view failed:', err)
  } finally {
    nav.end()
  }
}

async function onSaveView(label: string): Promise<void> {
  try {
    nav.start()
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    // Backend: ResourceCompiler регистрирует controller key `{slug}_views`,
    // SavedViewsController::create — POST /{slug}_views/create.
    // Validation: {name: required string, state: required array, is_default?}
    const result = await client.post<{ view: SavedViewItem }>(
      `/${props.slug}_views/create`,
      {
        name: label,
        state: {
          search: index.search,
          filters: index.filters,
          sort: { key: index.sortKey, direction: index.sortDirection },
          group_by: groupByCol.value,
          columns: columnVisibility.value,
        },
      },
    )
    // Сразу актуализируем локальный список и помечаем view активным.
    if (result?.view) {
      activeViewId.value = result.view.id
    }
    await loadSavedViews()
    adminToast.success(tt('admin.resource.view_saved', 'Представление сохранено.'))
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] save-view failed:', err)
    adminToast.error(
      'Не удалось сохранить view. Возможно, недостаточно прав либо ресурс не зарегистрирован.',
    )
  } finally {
    nav.end()
  }
}

const totalLabel = computed(() => {
  const t = index.meta.total
  if (t === 0) return ''
  return `${index.items.length} из ${t} ${pluralRecords(t)}`
})

/**
 * Русская плюрализация для "записей". Backend локаль RU; для других локалей
 * host может пропатчить через slot `subtitle`.
 */
function pluralRecords(n: number): string {
  const mod10 = n % 10
  const mod100 = n % 100
  if (mod10 === 1 && mod100 !== 11) return 'запись'
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return 'записи'
  return 'записей'
}

/**
 * Live-status: timestamp последнего успешного load + "tick" каждые 30s,
 * чтобы текст "обновлено 1 мин назад" реально обновлялся без re-load.
 *
 * lastLoadedAt = null до первой удачи; после успешного store.load() ставим
 * Date.now(). Используем watch на index.loading: переход true → false без
 * error = успех.
 */
const lastLoadedAt = ref<number | null>(null)
const tick = ref<number>(0)
let tickTimer: ReturnType<typeof setInterval> | null = null

watch(
  () => index.loading,
  (isLoading, wasLoading) => {
    if (wasLoading && !isLoading && index.error === null) {
      lastLoadedAt.value = Date.now()
    }
  },
)

onMounted(() => {
  tickTimer = setInterval(() => {
    tick.value += 1
  }, 30_000)
})
onUnmounted(() => {
  if (tickTimer !== null) clearInterval(tickTimer)
})

const liveStatus = computed<string | null>(() => {
  // tick — dependency для re-compute. Без него computed замёрзнет на initial value.
  void tick.value
  if (lastLoadedAt.value === null) return null
  const diffSec = Math.floor((Date.now() - lastLoadedAt.value) / 1000)
  // Меньше минуты — данные «свежие», не отвлекаем индикатором.
  if (diffSec < 60) return null
  const min = Math.floor(diffSec / 60)
  if (min < 60) return `обновлено ${min} мин назад`
  const hr = Math.floor(min / 60)
  if (hr < 24) return `обновлено ${hr} ч назад`
  return 'обновлено более суток назад'
})

const scopeLabel = computed<string>(() => {
  // Активный view → его имя; иначе "Все {ресурс в lowercase}".
  if (activeViewId.value !== null) {
    const v = savedViews.value.find((x) => x.id === activeViewId.value)
    if (v) return v.name
  }
  const label = resourceMeta.value?.label ?? props.slug
  return `Все ${label.toLowerCase()}`
})

/**
 * Обёртка над index.load — увеличивает navigation pending counter
 * (top loading-bar) на время запроса. router.beforeEach уже инкрементирует
 * counter при start navigation; этот wrap держит bar до конца data-fetch'а.
 */
async function loadWithProgress(): Promise<void> {
  nav.start()
  try {
    await index.load()
  } catch {
    // silent; ошибка отображается в hasError state
  } finally {
    nav.end()
  }
}

/**
 * Mount-init: setSlug + load data.
 *
 * При SPA navigation от router.push первичный data-fetch также делается
 * в router.beforeResolve (см. createAdminApp.ts) — он держит navigation
 * в pending пока данные не пришли. Здесь load всё равно вызываем как
 * resilient fallback (direct page mount, page reload, test).
 */
onMounted(async () => {
  index.setSlug(props.slug)
  if (manifest.manifest === null) {
    await manifest.load().catch(() => undefined)
  }
  void loadSavedViews()
  await loadWithProgress()
})

watch(
  () => props.slug,
  async (next, prev) => {
    if (next === prev) return
    index.setSlug(next)
    activeViewId.value = null
    void loadSavedViews()
    await loadWithProgress()
  },
)

async function onSortKeyUpdate(key: string | null): Promise<void> {
  // UidTable управляет своим 3-режимным cycle; здесь только применяем итог.
  // sortDirection приходит отдельным событием — apply через setSort одним
  // вызовом (после nextTick — Vue batches событий).
  await nextTick()
  await index.setSort(key, index.sortDirection)
}

async function onSortDirUpdate(dir: 'asc' | 'desc' | null): Promise<void> {
  await nextTick()
  await index.setSort(index.sortKey, dir)
}

function onSelectionUpdate(next: Set<string | number>): void {
  index.selection = next
}

async function onPageChange(page: number): Promise<void> {
  await index.setPage(page)
}

function rowId(row: Record<string, unknown>): string | number | null {
  const v = row?.id ?? row?.key
  return typeof v === 'string' || typeof v === 'number' ? v : null
}

function inlineRowId(slotProps: unknown): string | number {
  return rowId(rowFromSlot(slotProps) ?? {}) ?? ''
}

function onRowClick(row: Record<string, unknown>): void {
  emit('row-click', row)
  // По умолчанию click по строке открывает view-screen записи.
  // Host может перехватить через @row-click и сделать e.preventDefault.
  const id = rowId(row)
  if (id !== null) {
    void router.push({ name: `admin.resource.${props.slug}.view`, params: { id: String(id) } })
  }
}

function onView(row: Record<string, unknown>, e?: MouseEvent): void {
  e?.stopPropagation()
  const id = rowId(row)
  if (id !== null) {
    void router.push({ name: `admin.resource.${props.slug}.view`, params: { id: String(id) } })
  }
}

function onEdit(row: Record<string, unknown>, e?: MouseEvent): void {
  e?.stopPropagation()
  const id = rowId(row)
  if (id !== null) {
    void router.push({ name: `admin.resource.${props.slug}.edit`, params: { id: String(id) } })
  }
}

async function onDelete(row: Record<string, unknown>, e?: MouseEvent): Promise<void> {
  e?.stopPropagation()
  const id = rowId(row)
  if (id === null) return
  if (!window.confirm(tt('admin.resource.delete_confirm', 'Удалить запись?'))) return
  try {
    nav.start()
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    await client.post(`/${props.slug}/delete`, { id })
    await index.load().catch(() => undefined)
    adminToast.success(tt('admin.resource.deleted', 'Запись удалена.'))
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] delete failed:', err)
    adminToast.error(tt('admin.resource.delete_failed', 'Не удалось удалить запись.'))
  } finally {
    nav.end()
  }
}

/** Soft-deleted rows имеют непустой `deleted_at`. */
function isTrashed(row: Record<string, unknown>): boolean {
  return row.deleted_at !== null && row.deleted_at !== undefined
}

async function onRestore(row: Record<string, unknown>, e?: MouseEvent): Promise<void> {
  e?.stopPropagation()
  const id = rowId(row)
  if (id === null) return
  try {
    nav.start()
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    await client.post(`/${props.slug}/restore`, { id })
    await index.load().catch(() => undefined)
    adminToast.success(tt('admin.resource.restored', 'Запись восстановлена.'))
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] restore failed:', err)
    adminToast.error(tt('admin.resource.restore_failed', 'Не удалось восстановить запись.'))
  } finally {
    nav.end()
  }
}

// === Row reorder (HTML5 drag + visual indicator) ===
const dragRowIdx = ref<number | null>(null)
const dragOverRowIdx = ref<number | null>(null)
const dragOverSide = ref<'before' | 'after'>('before')

function onRowDragStart(idx: number, e: DragEvent): void {
  if (!isReorderable.value || !e.dataTransfer) return
  const t = e.target as HTMLElement | null
  if (!t?.closest('[data-row-drag-handle="true"]')) {
    e.preventDefault()
    return
  }
  dragRowIdx.value = idx
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(idx))
}
function onRowDragOver(idx: number, e: DragEvent): void {
  if (!isReorderable.value || dragRowIdx.value === null) return
  e.preventDefault()
  // Определяем сторону по mid-Y current cell'а — drop-line будет либо
  // выше, либо ниже текущей строки.
  const target = e.currentTarget as HTMLElement | null
  if (target) {
    const rect = target.getBoundingClientRect()
    dragOverSide.value = e.clientY < rect.top + rect.height / 2 ? 'before' : 'after'
  }
  dragOverRowIdx.value = idx
}
function onRowDragEnd(): void {
  dragRowIdx.value = null
  dragOverRowIdx.value = null
}
async function onRowDrop(toIdx: number, e: DragEvent): Promise<void> {
  e.preventDefault()
  if (!isReorderable.value || dragRowIdx.value === null) return
  const fromIdx = dragRowIdx.value
  // adjusted = вставка после строки (insertion-index в уже-удалённом array).
  const adjusted = dragOverSide.value === 'after' ? toIdx + 1 : toIdx
  const finalIdx = adjusted > fromIdx ? adjusted - 1 : adjusted
  dragRowIdx.value = null
  dragOverRowIdx.value = null
  if (fromIdx === finalIdx) return
  // Локальный reorder для мгновенного отклика.
  const items = [...index.items]
  const [moved] = items.splice(fromIdx, 1)
  items.splice(finalIdx, 0, moved)
  index.items = items
  // Backend persistence: POST /{slug}/reorder body {ids: [orderedIds]}.
  try {
    nav.start()
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    const ids = items.map((r) => index.rowId(r))
    await client.post(`/${props.slug}/reorder`, { ids })
    adminToast.success(tt('admin.resource.reorder_saved', 'Порядок сохранён.'))
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] reorder failed:', err)
    adminToast.error(tt('admin.resource.reorder_failed', 'Не удалось сохранить порядок.'))
    await index.load().catch(() => undefined)
  } finally {
    nav.end()
  }
}

async function onForceDelete(row: Record<string, unknown>, e?: MouseEvent): Promise<void> {
  e?.stopPropagation()
  const id = rowId(row)
  if (id === null) return
  if (!window.confirm(tt('admin.resource.force_delete_confirm', 'Удалить запись НАВСЕГДА? Действие необратимо.'))) return
  try {
    nav.start()
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    await client.post(`/${props.slug}/forceDelete`, { id })
    await index.load().catch(() => undefined)
    adminToast.success(tt('admin.resource.force_deleted', 'Запись удалена навсегда.'))
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] force-delete failed:', err)
    adminToast.error(tt('admin.resource.force_delete_failed', 'Не удалось удалить запись навсегда.'))
  } finally {
    nav.end()
  }
}

function bulkAction(action: string): void {
  emit('bulk-action', action, [...index.selection])
}

async function retryLoad(): Promise<void> {
  await index.load().catch(() => undefined)
}
</script>

<template>
  <ResourceTreePage
    v-if="resourceMeta?.view_mode === 'tree'"
    :slug="slug"
    :title="title"
    :subtitle="subtitle"
  />
  <section v-else class="admin-page admin-resource-index">
    <!-- Header — следует docs/design_handoff_laravel_admin/screens-resource.jsx
         (Resource Index): title-row с live-status, под ним counter,
         справа — scope dropdown / more-menu / Import / Создать. -->
    <header class="admin-page__hd">
      <div class="admin-page__title-wrap">
        <div class="admin-page__title-row">
          <h1 class="admin-page__title">{{ displayTitle }}</h1>
          <span v-if="liveStatus" class="admin-page__live" role="status">
            <span class="admin-page__live-dot" aria-hidden="true" />
            {{ liveStatus }}
          </span>
        </div>
        <div v-if="subtitle || totalLabel" class="admin-page__count">
          <template v-if="subtitle">{{ subtitle }}</template>
          <template v-if="subtitle && totalLabel"> · </template>
          <template v-if="totalLabel">{{ totalLabel }}</template>
        </div>
      </div>
      <div class="admin-page__actions">
        <slot name="actions" />
        <UidMenu>
          <template #trigger>
            <UidButton variant="ghost" size="md" class="admin-page__scope">
              <template #prepend><UidIcon :icon="Bookmark" :size="14" /></template>
              {{ scopeLabel }}
              <template #append><UidIcon :icon="ChevronDown" :size="14" /></template>
            </UidButton>
          </template>
          <UidMenuItem @click="onResetView">
            Все {{ (resourceMeta?.label ?? slug).toLowerCase() }}
          </UidMenuItem>
          <UidMenuItem
            v-for="v in savedViews"
            :key="v.id"
            @click="onApplyView(v)"
          >
            <span class="admin-page__view-row">
              <span class="admin-page__view-name">
                {{ v.name }}
                <span v-if="v.is_default" class="admin-page__view-badge">default</span>
              </span>
              <button
                v-if="v.owned"
                type="button"
                class="admin-page__view-delete"
                aria-label="Удалить view"
                @click.stop="onDeleteView(v, $event)"
              >
                <UidIcon :icon="Trash2" :size="12" />
              </button>
            </span>
          </UidMenuItem>
        </UidMenu>
        <UidMenu>
          <template #trigger>
            <UidButton variant="ghost" size="md" aria-label="Действия" class="admin-page__more">
              <UidIcon :icon="MoreHorizontal" :size="16" />
            </UidButton>
          </template>
          <UidMenuItem @click="retryLoad">Обновить</UidMenuItem>
          <UidMenuItem @click="onExport('csv')">Экспорт CSV</UidMenuItem>
          <UidMenuItem @click="onExport('json')">Экспорт JSON</UidMenuItem>
          <UidMenuItem @click="onExport('xlsx')">Экспорт XLSX</UidMenuItem>
          <UidMenuItem @click="onExport('pdf')">Экспорт PDF</UidMenuItem>
          <!-- Кастомные действия от backend Resource->actions(). -->
          <UidMenuItem
            v-for="action in headerActions"
            :key="action.key"
            @click="onCustomAction(action)"
          >
            {{ action.label }}
          </UidMenuItem>
          <slot name="header-menu" />
        </UidMenu>
        <UidButton variant="secondary" size="md" @click="onImportClick">
          <template #prepend><UidIcon :icon="Upload" :size="14" /></template>
          Import
        </UidButton>
        <input
          ref="importInput"
          type="file"
          class="admin-page__import-input"
          accept=".csv,.json,.xlsx,.xls,application/json,text/csv"
          @change="onImportFileChange"
        />
        <UidButton
          v-if="resolvedCreateRouteName && isCreatable"
          variant="primary"
          size="md"
          @click="$router.push({ name: resolvedCreateRouteName })"
        >
          <template #prepend><UidIcon :icon="Plus" :size="14" /></template>
          {{ createLabel }}
        </UidButton>
      </div>
    </header>

    <!-- Bulk toolbar (selection > 0) ИЛИ filter bar -->
    <div v-if="index.hasSelection" class="admin-bulk-toolbar" role="toolbar">
      <span class="admin-bulk-toolbar__count">
        Выбрано <b>{{ index.selectedCount }}</b>
      </span>
      <span class="admin-bulk-toolbar__divider" />
      <UidButton size="sm" variant="ghost" @click="bulkAction('publish')">Опубликовать</UidButton>
      <UidButton size="sm" variant="ghost" @click="bulkAction('archive')">Архивировать</UidButton>
      <UidButton size="sm" variant="ghost" @click="bulkAction('export')">Экспорт</UidButton>
      <UidButton size="sm" variant="danger" @click="bulkAction('delete')">Удалить</UidButton>
      <span class="admin-bulk-toolbar__spacer" />
      <UidButton size="sm" variant="ghost" @click="index.clearSelection">
        Снять выделение
      </UidButton>
    </div>

    <AdminFilterToolbar
      v-else-if="showFilterBar"
      :search="index.search"
      :search-placeholder="searchPlaceholder"
      :filters="(manifestFilters as never)"
      :values="index.filters"
      :columns="(manifestColumns as never)"
      :group-by="groupByCol"
      :column-visibility="columnVisibility"
      @update:search="onSearchUpdate"
      @apply-filter="onFilterApply"
      @reset="onResetFilters"
      @group-by="onGroupBy"
      @columns-visibility="onColumnsVisibility"
      @save-view="onSaveView"
    >
      <template #extra>
        <slot name="filters" :filters="index.filters" :set-filter="index.setFilter" />
      </template>
    </AdminFilterToolbar>

    <!-- States -->
    <!-- Initial-loading стейт (между setSlug и первым успехом load).
         Показываем placeholder вместо UidTable — иначе сама таблица
         рендерит "Нет данных" empty state, что создаёт flicker
         "Нет данных → реальные строки" при navigation. -->
    <div
      v-if="index.loading && index.items.length === 0"
      class="admin-resource-index__loading"
    >
      <template v-if="index.slowLoading">
        <UidSkeleton v-for="i in 8" :key="i" height="40px" />
      </template>
    </div>
    <UidErrorState
      v-else-if="index.hasError"
      title="Не удалось загрузить данные"
      :description="index.error?.message ?? 'Попробуйте обновить страницу.'"
      class="admin-resource-index__state"
    >
      <template #actions>
        <UidButton variant="primary" @click="retryLoad">Обновить</UidButton>
      </template>
    </UidErrorState>
    <UidEmptyState
      v-else-if="index.isEmpty"
      title="Пока пусто"
      description="Создайте первую запись или измените фильтры."
      class="admin-resource-index__state"
    >
      <template #actions>
        <UidButton
          v-if="createRouteName && isCreatable"
          variant="primary"
          @click="$router.push({ name: createRouteName })"
        >
          {{ createLabel }}
        </UidButton>
      </template>
    </UidEmptyState>

    <!-- Таблица — UidTable native selection (UidTable.selectable + selection prop). -->
    <div v-else class="admin-resource-index__table">
      <UidTable
        :columns="columns"
        :data="index.items"
        :sort-key="index.sortKey"
        :sort-direction="index.sortDirection"
        selectable
        :selection="index.selection"
        :row-key="(row) => index.rowId(row)"
        @update:sort-key="onSortKeyUpdate"
        @update:sort-direction="onSortDirUpdate"
        @update:selection="onSelectionUpdate"
        @row-click="onRowClick"
      >
        <template
          v-for="col in columns"
          #[col.key]="slotProps"
          :key="col.key"
        >
          <!-- Колонка drag-handle для reorderable resource'а -->
          <span
            v-if="col.key === REORDER_KEY"
            :class="[
              'admin-resource-index__row-drag',
              {
                'admin-resource-index__row-drag--drop-before':
                  dragOverRowIdx !== null
                  && dragOverRowIdx === index.items.indexOf((rowFromSlot(slotProps) ?? {}) as Record<string, unknown>)
                  && dragOverSide === 'before',
                'admin-resource-index__row-drag--drop-after':
                  dragOverRowIdx !== null
                  && dragOverRowIdx === index.items.indexOf((rowFromSlot(slotProps) ?? {}) as Record<string, unknown>)
                  && dragOverSide === 'after',
                'admin-resource-index__row-drag--ghost':
                  dragRowIdx !== null
                  && dragRowIdx === index.items.indexOf((rowFromSlot(slotProps) ?? {}) as Record<string, unknown>),
              },
            ]"
            data-row-drag-handle="true"
            :draggable="isReorderable"
            title="Перетащить"
            @dragstart="(e: DragEvent) => onRowDragStart(
              index.items.indexOf((rowFromSlot(slotProps) ?? {}) as Record<string, unknown>),
              e,
            )"
            @dragover="(e: DragEvent) => onRowDragOver(
              index.items.indexOf((rowFromSlot(slotProps) ?? {}) as Record<string, unknown>),
              e,
            )"
            @dragend="onRowDragEnd"
            @drop="(e: DragEvent) => onRowDrop(
              index.items.indexOf((rowFromSlot(slotProps) ?? {}) as Record<string, unknown>),
              e,
            )"
          >
            <UidIcon :icon="GripVertical" :size="14" />
          </span>

          <!-- Колонка row-actions (View / Edit / Delete) — рендерим всегда последней. -->
          <div
            v-else-if="col.key === ACTIONS_KEY"
            class="admin-resource-index__row-actions"
          >
            <button
              type="button"
              class="admin-resource-index__row-action"
              title="Просмотр"
              @click.stop="onView(rowFromSlot(slotProps) ?? {}, $event)"
            >
              <UidIcon :icon="Eye" :size="16" />
            </button>
            <template v-if="!isTrashed(rowFromSlot(slotProps) ?? {})">
              <button
                v-if="isEditable"
                type="button"
                class="admin-resource-index__row-action"
                title="Редактировать"
                @click.stop="onEdit(rowFromSlot(slotProps) ?? {}, $event)"
              >
                <UidIcon :icon="Pencil" :size="16" />
              </button>
              <button
                v-if="isEditable"
                type="button"
                class="admin-resource-index__row-action admin-resource-index__row-action--danger"
                title="Удалить"
                @click.stop="onDelete(rowFromSlot(slotProps) ?? {}, $event)"
              >
                <UidIcon :icon="Trash2" :size="16" />
              </button>
            </template>
            <template v-else>
              <button
                type="button"
                class="admin-resource-index__row-action"
                title="Восстановить"
                @click.stop="onRestore(rowFromSlot(slotProps) ?? {}, $event)"
              >
                <UidIcon :icon="RotateCcw" :size="16" />
              </button>
              <button
                type="button"
                class="admin-resource-index__row-action admin-resource-index__row-action--danger"
                title="Удалить навсегда"
                @click.stop="onForceDelete(rowFromSlot(slotProps) ?? {}, $event)"
              >
                <UidIcon :icon="Trash2" :size="16" />
              </button>
            </template>
          </div>
          <slot
            v-else
            :name="`cell-${col.key}`"
            :row="rowFromSlot(slotProps)"
          >
            <!--
              Default cell renderer: значение в одну строку с ellipsis при
              переполнении. max-width задаёт «адекватную» ширину колонки
              через CSS-var --admin-cell-max-width (по умолчанию 320px).
              Полный текст доступен через native browser tooltip (title=).
              Для editable-колонок double-click открывает inline editor.
            -->
            <InlineEditCell
              v-if="columnIsEditable(col.key)"
              :resource-slug="slug"
              :row-id="inlineRowId(slotProps)"
              :column="col.key"
              :value="(rowFromSlot(slotProps) ?? {})[col.key]"
              :editable="true"
              :input-type="columnEditableMeta(col.key).as"
              :options="columnEditableMeta(col.key).options"
              :row-override="((rowFromSlot(slotProps) ?? {})._editable as Record<string, boolean> | undefined) ?? {}"
              @saved="(v) => {
                const r = rowFromSlot(slotProps)
                if (r) r[col.key] = v
              }"
            >
              <span class="admin-cell-truncate">{{ renderCell(col.key, slotProps) }}</span>
            </InlineEditCell>
            <span
              v-else
              class="admin-cell-truncate"
              :title="renderCell(col.key, slotProps)"
            >{{ renderCell(col.key, slotProps) }}</span>
          </slot>
        </template>
      </UidTable>
    </div>

    <!-- Pagination -->
    <footer
      v-if="!index.loading && !index.hasError && !index.isEmpty"
      class="admin-resource-index__footer"
    >
      <UidPagination
        :model-value="index.meta.page"
        :total="index.meta.total"
        :per-page="index.meta.per_page"
        @update:model-value="onPageChange"
      />
    </footer>
  </section>
</template>

<style>
.admin-resource-index__loading {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
  margin-top: var(--uid-space-md);
  /* min-height предотвращает layout-collapse при первом mount'е,
     когда slowLoading ещё false (быстрый запрос — placeholder пустой). */
  min-height: 320px;
}

/* Drag-handle для reorderable resource'а — первая колонка. */
.admin-resource-index__row-drag {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  cursor: grab;
  color: var(--uid-text-tertiary);
  border-radius: var(--uid-radius-sm);
}
.admin-resource-index__row-drag:hover {
  background: var(--uid-color-surface-hover, var(--uid-border-subtle));
  color: var(--uid-text-primary);
}
.admin-resource-index__row-drag:active { cursor: grabbing; }
/* Полупрозрачная исходная строка во время drag (приём из Notion/Linear). */
.admin-resource-index__row-drag--ghost { opacity: 0.4; }
/* Линия-индикатор drop'а — extends на всю ширину строки через ::before
   (положение absolute относительно td.admin-resource-index__row-drag,
   left:-9999 чтобы перекрыть ширину таблицы). */
.admin-resource-index__row-drag--drop-before::before,
.admin-resource-index__row-drag--drop-after::before {
  content: '';
  position: absolute;
  left: 0;
  right: -9999px;
  height: 2px;
  background: var(--uid-accent);
  pointer-events: none;
  z-index: 5;
}
.admin-resource-index__row-drag--drop-before::before { top: -1px; }
.admin-resource-index__row-drag--drop-after::before { bottom: -1px; }

/* Row actions: View / Edit / Delete иконки в последней колонке таблицы. */
.admin-resource-index__row-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--uid-space-2xs);
}
.admin-resource-index__row-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  padding: 0;
  border: 1px solid transparent;
  border-radius: var(--uid-radius-sm);
  background: transparent;
  color: var(--uid-text-secondary);
  cursor: pointer;
  transition: background var(--uid-duration-fast) var(--uid-ease-out),
    color var(--uid-duration-fast) var(--uid-ease-out),
    border-color var(--uid-duration-fast) var(--uid-ease-out);
}
.admin-resource-index__row-action:hover {
  background: var(--uid-color-surface-hover);
  color: var(--uid-text-primary);
  border-color: var(--uid-border-subtle);
}
.admin-resource-index__row-action--danger:hover {
  color: var(--uid-color-danger, #dc2626);
}
.admin-resource-index__state {
  margin-top: var(--uid-space-xl);
}
.admin-resource-index__table {
  /* table визуально приклеена к filter-bar / bulk-toolbar сверху —
     убираем gap, скругляем только нижние углы у table-wrap'а. */
  margin-top: 0;
}
.admin-resource-index__table .uid-table-wrap {
  border: 1px solid var(--uid-border-subtle);
  border-top: 0;
  border-radius: 0 0 var(--uid-radius-lg) var(--uid-radius-lg);
}

/*
 * Compact-mode table: уменьшаем padding td/th под --admin-row-h:32px и
 * font-size 13px. Оригинальный UidTable ставит padding:12px — для compact
 * это много. Переопределяем точечно внутри admin-resource-index'а чтобы
 * не задеть UidTable в других контекстах (storybook, custom widgets).
 */
.admin-resource-index__table .uid-table__td,
.admin-resource-index__table .uid-table__th {
  padding: 6px var(--uid-space-md);
  font-size: var(--admin-row-fs, 13px);
  height: var(--admin-row-h, 32px);
  line-height: 1.3;
}

/*
 * Cell truncate — 1 строка с ellipsis, max-width адекватный (320px по
 * умолчанию). Long-words переносятся внутри ограничения макс. до 3 строк
 * (line-clamp) на случай когда host переопределит white-space через slot.
 *
 * Tooltip с полным значением — нативный browser-tooltip через title=
 * атрибут (см. ResourceIndexPage.vue cell renderer).
 */
.admin-cell-truncate {
  display: inline-block;
  max-width: var(--admin-cell-max-width, 320px);
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.admin-cell-truncate--multi {
  display: -webkit-box;
  white-space: normal;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  word-break: break-word;
}
.admin-resource-index__footer {
  display: flex;
  justify-content: flex-end;
  padding: var(--uid-space-md) 0;
}

/* Filter bar */
.admin-filter-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--uid-space-sm);
  padding: var(--uid-space-sm) var(--uid-space-md);
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-raised);
  border-radius: var(--uid-radius-lg) var(--uid-radius-lg) 0 0;
  border-bottom: 0;
  margin-top: var(--uid-space-md);
}
.admin-filter-bar__search {
  flex: 1;
  min-width: 200px;
  max-width: 320px;
  height: 28px;
  padding: 0 var(--uid-space-sm);
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-base);
  border-radius: var(--uid-radius-md);
  color: var(--uid-text-primary);
  font-size: 13px;
  outline: none;
}
.admin-filter-bar__search:focus {
  border-color: var(--uid-accent);
  outline: 2px solid color-mix(in srgb, var(--uid-accent) 18%, transparent);
}
.admin-filter-bar__spacer { flex: 1; }

/* Bulk toolbar — dark zinc-900 surface, заменяет filter bar */
.admin-bulk-toolbar {
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm);
  padding: var(--uid-space-sm) var(--uid-space-md);
  background: var(--uid-color-zinc-900, #18181b);
  color: var(--uid-color-zinc-100, #f4f4f5);
  border-radius: var(--uid-radius-lg) var(--uid-radius-lg) 0 0;
  border: 1px solid var(--uid-color-zinc-900, #18181b);
  border-bottom: 0;
  margin-top: var(--uid-space-md);
}
.admin-bulk-toolbar__count { font-size: 13px; }
.admin-bulk-toolbar__count b { font-weight: var(--uid-font-weight-semibold); }
.admin-bulk-toolbar__divider {
  width: 1px;
  height: 20px;
  background: rgba(255, 255, 255, 0.15);
}
.admin-bulk-toolbar__spacer { flex: 1; }
.admin-bulk-toolbar .uid-button {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.15);
  color: var(--uid-color-zinc-100, #f4f4f5);
}
.admin-bulk-toolbar .uid-button:hover:not([disabled]) {
  background: rgba(255, 255, 255, 0.08);
}
.admin-bulk-toolbar .uid-button--danger {
  color: var(--uid-color-rose-400, #fb7185);
}

/* Selected row tint */
.admin-resource-index__row--selected {
  background: color-mix(in srgb, var(--uid-accent) 8%, transparent);
}
</style>
