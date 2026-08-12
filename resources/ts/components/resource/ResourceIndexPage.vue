<script setup lang="ts">
/**
 * ResourceIndexPage — the list screen of one resource from the manifest.
 * Laid out after docs/design_handoff_laravel_admin/screens-shell.jsx
 * (Resource List).
 *
 * Composition:
 *   - page header (title + count + the cluster of actions)
 *   - filter bar with search and chips
 *   - bulk toolbar, which replaces the filter bar once something is selected
 *   - UidTable fed by manifest.columns
 *   - states: loading (UidSkeleton ×8) / empty (UidEmptyState) / error
 *   - UidPagination at the bottom
 *
 * The host renders the page through the router; the resource slug arrives in
 * props or from route.params.
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
const tr = (s: string): string => i18n.tr(s)

/**
 * A string with placeholders: the key is the whole phrase, the values are
 * named.
 *
 * A sentence glued together from a template literal cannot be translated at
 * all — the translator receives a fragment with no beginning and no end. Here
 * the key stays whole and the numbers and names are substituted, so another
 * language may order the words differently.
 */
const tRaw = (s: string, replace: Record<string, string | number>): string => i18n.t(s, replace)
/**
 * A local wrapper over t() that falls back to the source string.
 *
 * This is what makes a gradual sweep possible: while bootstrap.translations is
 * empty the UI shows the source text, and once the host publishes its language
 * bag the translations take over.
 */
const tt = (key: string, fallback: string, replace?: Record<string, string | number>): string =>
  i18n.has(key) ? i18n.t(key, replace) : fallback

interface Props {
  /** Resource slug: users, articles and so on. */
  slug: string
  /** Page title. Taken from the manifest when not given. */
  title?: string | null
  /** The line under the title, e.g. "Content analytics". */
  subtitle?: string | null
  /** Text of the primary “Create” button. */
  createLabel?: string
  /** Router route name behind the “Create” action. */
  createRouteName?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  title: null,
  subtitle: null,
  createLabel: undefined,
  createRouteName: null,
})

const emit = defineEmits<{
  /** A bulk action fired with the current set of ids. */
  'bulk-action': [action: string, ids: Array<string | number>]
  /** Row click — the host decides: push edit, open view. */
  'row-click': [row: Record<string, unknown>]
  /** Header action: import — the host attaches the CSV/JSON upload handler. */
  'import': []
  /** Header more-menu action — any key from additional-items. */
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
 * Derives the create route name. The fallback is the standard pattern from
 * router/builder.ts (`admin.resource.{slug}.create`). The "Create" button is
 * rendered only when that route really exists — a read-only resource has none,
 * because the backend does not grant the create permission.
 */
const resolvedCreateRouteName = computed<string | null>(() => {
  if (props.createRouteName !== null) {
    return router.hasRoute(props.createRouteName) ? props.createRouteName : null
  }
  const candidate = `admin.resource.${props.slug}.create`
  return router.hasRoute(candidate) ? candidate : null
})

/**
 * Header actions from manifest.actions — `Resource->actions()` on the backend.
 * Every node carries {key, label, icon?, confirm?, …} and renders in the
 * more-menu. A click calls onCustomAction, which POSTs to
 * /{slug}/action/{key}.
 */
interface HeaderAction {
  key: string
  label: string
  confirm?: string
  icon?: string
  /** true when the action operates on selected rows (row or bulk position). */
  needsSelection: boolean
  destructive?: boolean
}
const allActions = computed<HeaderAction[]>(() => {
  const raw = (resourceMeta.value?.actions ?? []) as Array<Record<string, unknown>>
  return raw
    .map((a) => {
      const position = Array.isArray(a.position) ? (a.position as string[]) : []
      return {
        key: String(a.key ?? a.name ?? ''),
        label: String(a.label ?? a.name ?? a.key ?? ''),
        confirm: typeof a.confirm === 'string' ? a.confirm : undefined,
        icon: typeof a.icon === 'string' ? a.icon : undefined,
        // Row and bulk actions apply to the selected records — provisioning,
        // suspend, drop and so on. With nothing selected there is nothing to
        // run them against.
        needsSelection: position.includes('row') || position.includes('bulk'),
        destructive: Boolean(a.destructive),
      }
    })
    .filter((a) => a.key !== '' && a.label !== '')
})
// Global actions — the ones needing no selection — live in the ⋯ menu;
// selection actions live in the bulk toolbar, which appears once rows are
// picked.
const headerActions = computed<HeaderAction[]>(() => allActions.value.filter((a) => !a.needsSelection))
const selectionActions = computed<HeaderAction[]>(() => allActions.value.filter((a) => a.needsSelection))

async function onCustomAction(action: HeaderAction): Promise<void> {
  // Selection actions require a selection — a guard, even though their
  // buttons only exist in the bulk toolbar; global ones run without it.
  if (action.needsSelection && !index.hasSelection) {
    adminToast.error(tr('Сначала выберите записи.'))
    return
  }
  if (action.confirm && !window.confirm(action.confirm)) return
  emit('header-action', action.key)
  try {
    nav.start()
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    // The backend contract: POST /{slug}/action with {key, ids[], payload?}.
    // The action is resolved by name through `Resource->actions()`.
    const result = await client.post<{ affected?: number; message?: string }>(
      `/${props.slug}/action`,
      {
        key: action.key,
        ids: [...index.selection],
      },
    )
    if (action.needsSelection) index.clearSelection()
    await index.load().catch(() => undefined)
    adminToast.success(
      result?.message ?? tRaw('Действие «:action» применено к :count записям.', { action: action.label, count: result?.affected ?? 0 }),
    )
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] header-action failed:', err)
    adminToast.error(tRaw('Не удалось выполнить действие «:action».', { action: action.label }))
  } finally {
    nav.end()
  }
}

const bulkDeleting = ref(false)
/** Bulk delete of the selected rows — a /delete per id, one after another. */
async function onBulkDelete(): Promise<void> {
  const ids = [...index.selection]
  if (ids.length === 0) return
  if (!window.confirm(tRaw('Удалить выбранные записи (:count)?', { count: ids.length }))) return
  bulkDeleting.value = true
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    let ok = 0
    for (const id of ids) {
      try {
        await client.post(`/${props.slug}/delete`, { id })
        ok++
      } catch {
        /* skip the ones that failed, carry on with the rest */
      }
    }
    index.clearSelection()
    await index.load().catch(() => undefined)
    if (ok === ids.length) {
      adminToast.success(tRaw('Удалено записей: :count.', { count: ok }))
    } else {
      adminToast.error(tRaw('Удалено :ok из :total; часть не удалена.', { ok, total: ids.length }))
    }
  } finally {
    bulkDeleting.value = false
  }
}

/**
 * Export — POST /{slug}/export?format=csv|json|xlsx|pdf. The backend's
 * ExporterRegistry resolves the format; the response is a blob, downloaded
 * through an `<a download>`.
 *
 * Which formats are available depends on the composer packages installed:
 *   - csv / json — always, no dependencies
 *   - xlsx       — openspout/openspout
 *   - pdf        — mpdf/mpdf or dompdf/dompdf
 */
async function onExport(format: string = 'csv'): Promise<void> {
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
    adminToast.error(tRaw('Не удалось экспортировать данные в формате :format.', { format: format.toUpperCase() }))
  } finally {
    nav.end()
  }
}

/**
 * Import — a file picker, then a multipart POST to /{slug}/import. On success
 * the list is reloaded; when the backend does not support it, an alert.
 */
const importInput = ref<HTMLInputElement | null>(null)
function onImportClick(): void {
  emit('import')
  importInput.value?.click()
}
/**
 * The default import flow over Dskripchenko\LaravelAdmin\Import\ImportController:
 *   1. POST /import/upload (file + resource=slug) → uploadId
 *   2. POST /import/preview → auto-mapping (headers ↔ fields)
 *   3. POST /import/start (uploadId + mapping) → processId
 *   4. poll /import/status?processId=… until status is 'done' or 'failed'
 *
 * The auto-mapping matches CSV/JSON column names against the resource's own
 * fields. A host may intercept @import before this flow and open a wizard of
 * its own.
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

    // 2. Preview — this is where the auto-mapping happens
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
      adminToast.warning(tRaw('Импорт завершён с ошибками: :imported записей, :failed ошибок.', { imported, failed }))
    } else {
      adminToast.success(tRaw('Импортировано записей: :count.', { count: imported }))
    }
    await index.load().catch(() => undefined)
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] import failed:', err)
    adminToast.error(
      tr('Импорт не удался. Проверьте формат файла и поля ресурса либо обратитесь к администратору.'),
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
  // Poll every 800 ms, but no longer than 90 seconds — a crude guard against
  // looping forever.
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

/** The resource supports reordering when features.reorderable is true. */
const isReorderable = computed<boolean>(() => {
  const features = (resourceMeta.value?.features ?? {}) as Record<string, unknown>
  return features.reorderable === true
})

/** The resource supports creation unless features.creatable is false; true by default. */
const isCreatable = computed<boolean>(() => {
  const features = (resourceMeta.value?.features ?? {}) as Record<string, unknown>
  return features.creatable !== false
})

/** The resource supports editing unless features.editable is false — then the
 * edit and delete actions are hidden. */
const isEditable = computed<boolean>(() => {
  const features = (resourceMeta.value?.features ?? {}) as Record<string, unknown>
  return features.editable !== false
})

/**
 * Saved views — only when features.savedViews is on; off by default. Without
 * the flag the backend registers no routes, and asking for the list would land
 * in a 404.
 */
const hasSavedViews = computed<boolean>(() => {
  const features = (resourceMeta.value?.features ?? {}) as Record<string, unknown>
  return features.savedViews === true
})

/** Import is available only when features.importable is on; off by default. */
const isImportable = computed<boolean>(() => {
  const features = (resourceMeta.value?.features ?? {}) as Record<string, unknown>
  return features.importable === true
})

/** Export formats come from features.exportable: only the ones actually
 * supported are shown, otherwise the xlsx/pdf entries led nowhere. */
const exportFormats = computed<string[]>(() => {
  const features = (resourceMeta.value?.features ?? {}) as Record<string, unknown>
  const list = Array.isArray(features.exportable) ? (features.exportable as string[]) : ['csv']
  return list.filter((f) => typeof f === 'string' && f.length > 0)
})
const EXPORT_LABELS: Record<string, string> = {
  csv: 'CSV', json: 'JSON', xlsx: 'XLSX', pdf: 'PDF',
}

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
    // Apply the visibility state from the toolbar's "Columns" control: an
    // explicit false hides the column. Absent from the map means visible.
    .filter((c) => columnVisibility.value[c.key] !== false)
  // A reorderable resource gets a drag-handle column in front.
  const head: UidTableColumn[] = isReorderable.value
    ? [{
        key: REORDER_KEY,
        label: '',
        sortable: false,
        align: 'center',
        width: '32px',
      }]
    : []
  // The trailing column of per-row actions: view, edit, delete.
  // ResourceIndexPage always adds it; a host will be able to hide it through
  // useShowActions=false once that prop exists.
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

// Per-column metadata from the manifest: preset, format, currency, editable
// and so on. formatCell uses it (datetime → 'd.m.Y H:i:s', money →
// '{val} {ccy}'), and InlineEditCell checks `editable`.
function columnIsEditable(key: string): boolean {
  const cols = resourceMeta.value?.columns ?? []
  for (const c of cols) {
    const col = c as Record<string, unknown>
    const k = String(col.key ?? col.name ?? '')
    if (k === key) {
      // The backend sends `editable` either as an object {rules: …} or null.
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
  // The UidTable scoped slot passes {row: actualRow}.
  const row = (slotProps as { row?: Record<string, unknown> } | undefined)?.row
  const value = row?.[key]
  const m = columnMeta.value[key]
  return formatCell(value, m?.preset, m?.meta ?? {})
}

function rowFromSlot(slotProps: unknown): Record<string, unknown> | undefined {
  return (slotProps as { row?: Record<string, unknown> } | undefined)?.row
}

/** A link column — preset 'link', see TableColumn::asLink. */
function columnIsLink(key: string): boolean {
  return columnMeta.value[key]?.preset === 'link'
}

/**
 * Resolves the href of a link column: the template from meta.template, with
 * `{field}` standing for a field of the row and `:value` for the cell's own
 * value. Empty when the field is missing or null — then no link is rendered
 * and the plain text remains.
 */
function linkHref(key: string, slotProps: unknown): string {
  const row = rowFromSlot(slotProps) ?? {}
  const tpl = (columnMeta.value[key]?.meta?.template as string | undefined) ?? ''
  if (!tpl) return ''
  const href = tpl
    .replace(/\{(\w+)\}/g, (_m, f: string) => String(row[f] ?? ''))
    .replace(/:value/g, String(row[key] ?? ''))
  // Unresolved placeholders left, or nothing at all — then there is no link
  return href.includes('{') || href === '' ? '' : href
}

function linkTarget(key: string): string | undefined {
  return (columnMeta.value[key]?.meta?.target as string | undefined) ?? undefined
}

/**
 * Whether to show the filter toolbar:
 *   - there is data → always, since search and filters live inside it;
 *   - no data but a search or filter is active → YES, so that the visitor can
 *     clear it and see the items again;
 *   - no data and nothing applied → NO, this is the initial empty state.
 */
const hasActiveFilters = computed<boolean>(
  () => index.search !== '' || Object.keys(index.filters).length > 0,
)
const showFilterBar = computed<boolean>(
  () => !index.isEmpty || hasActiveFilters.value,
)

// Filter, column and view state — the toolbar delegates all of it here.
const manifestFilters = computed(
  () => (resourceMeta.value?.filters ?? []) as unknown as Array<Record<string, unknown>>,
)
const manifestColumns = computed(
  () => (resourceMeta.value?.columns ?? []) as unknown as Array<Record<string, unknown>>,
)
const searchPlaceholder = computed(() => {
  const label = (resourceMeta.value?.label ?? props.slug).toLowerCase()
  return tRaw('Поиск по :label…', { label })
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
  // The backend may or may not support grouping — we pass it in the search
  // payload as `group_by`, and an unsupported backend simply ignores it. The
  // cast goes through `unknown` because IndexParams is strict.
  await index.load({ group_by: col } as unknown as Parameters<typeof index.load>[0])
}
function onColumnsVisibility(next: Record<string, boolean>): void {
  columnVisibility.value = next
}
/**
 * Saved views: state, loading, applying and deleting. The backend is
 * SavedViewsController with the URL pattern /{slug}_views/{action}. The active
 * view is kept locally and highlighted in the scope dropdown.
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
  if (!hasSavedViews.value) {
    savedViews.value = []
    return
  }
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    const result = await client.get<{ data: SavedViewItem[] }>(`/${props.slug}_views/list`)
    savedViews.value = result.data ?? []
  } catch {
    // Silently: the endpoint is optional. The resource may lack the
    // permission, or the backend may not have registered views at all.
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
  if (!window.confirm(tRaw('Удалить представление «:name»?', { name: view.name }))) return
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
    // Backend: ResourceCompiler registers the controller key `{slug}_views`,
    // and SavedViewsController::create answers POST /{slug}_views/create.
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
    // Refresh the local list right away and mark the view as active.
    if (result?.view) {
      activeViewId.value = result.view.id
    }
    await loadSavedViews()
    adminToast.success(tt('admin.resource.view_saved', 'Представление сохранено.'))
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] save-view failed:', err)
    adminToast.error(
      tr('Не удалось сохранить view. Возможно, недостаточно прав либо ресурс не зарегистрирован.'),
    )
  } finally {
    nav.end()
  }
}

const totalLabel = computed(() => {
  const t = index.meta.total
  if (t === 0) return ''
  return tRaw(':shown из :total :word', { shown: index.items.length, total: t, word: pluralRecords(t) })
})

/**
 * Russian pluralisation of "records". The source language is Russian; for
 * other locales the three forms are translated separately and collapse
 * correctly, and a host can override the whole line through the `subtitle`
 * slot.
 */
function pluralRecords(n: number): string {
  const mod10 = n % 10
  const mod100 = n % 100
  if (mod10 === 1 && mod100 !== 11) return tr('запись')
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return tr('записи')
  return tr('записей')
}

/**
 * Live status: the timestamp of the last successful load plus a tick every 30
 * seconds, so that "updated a minute ago" actually moves without a reload.
 *
 * `lastLoadedAt` stays null until the first success, then holds Date.now().
 * Success is detected by watching index.loading: a true → false transition
 * with no error.
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
  // `tick` is a dependency of this computed. Without it the value would
  // freeze at whatever it was first.
  void tick.value
  if (lastLoadedAt.value === null) return null
  const diffSec = Math.floor((Date.now() - lastLoadedAt.value) / 1000)
  // Under a minute the data is fresh — no need to distract with an indicator.
  if (diffSec < 60) return null
  const min = Math.floor(diffSec / 60)
  if (min < 60) return tr('обновлено :min мин назад').replace(':min', String(min))
  const hr = Math.floor(min / 60)
  if (hr < 24) return tr('обновлено :hr ч назад').replace(':hr', String(hr))
  return tr('обновлено более суток назад')
})

const scopeLabel = computed<string>(() => {
  // An active view shows its own name; otherwise "All {resource, lowercased}".
  if (activeViewId.value !== null) {
    const v = savedViews.value.find((x) => x.id === activeViewId.value)
    if (v) return v.name
  }
  const label = resourceMeta.value?.label ?? props.slug
  return `${tr('Все')} ${label.toLowerCase()}`
})

/**
 * A wrapper over index.load that raises the navigation pending counter — the
 * top loading bar — for the duration of the request. router.beforeEach already
 * increments it when navigation starts; this wrapper keeps the bar up until
 * the data has actually arrived.
 */
async function loadWithProgress(): Promise<void> {
  nav.start()
  try {
    await index.load()
  } catch {
    // Silent: the error surfaces through the hasError state.
  } finally {
    nav.end()
  }
}

/**
 * Mount-time init: set the slug and load the data.
 *
 * On SPA navigation from router.push the first fetch also happens in
 * router.beforeResolve (see createAdminApp.ts), which keeps the navigation
 * pending until the data arrives. The load here stays as a resilient fallback:
 * a direct mount, a page reload, a test.
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
  // UidTable runs its own three-state cycle; here we only apply the outcome.
  // sortDirection arrives as a separate event, so setSort is called once,
  // after nextTick, since Vue batches the events.
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
  // By default a row click opens the record's view screen. A host can
  // intercept it through @row-click and call preventDefault.
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

/** Soft-deleted rows carry a non-empty `deleted_at`. */
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
  // The side is decided by the mid-Y of the current cell: the drop line goes
  // either above or below the row.
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
  // `adjusted` is the insertion index after the row, counted in the array
  // the dragged item has already been removed from.
  const adjusted = dragOverSide.value === 'after' ? toIdx + 1 : toIdx
  const finalIdx = adjusted > fromIdx ? adjusted - 1 : adjusted
  dragRowIdx.value = null
  dragOverRowIdx.value = null
  if (fromIdx === finalIdx) return
  // Reorder locally first, so the response is instant.
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
        <UidMenu v-if="hasSavedViews">
          <template #trigger>
            <UidButton variant="ghost" size="md" class="admin-page__scope">
              <template #prepend><UidIcon :icon="Bookmark" :size="14" /></template>
              {{ scopeLabel }}
              <template #append><UidIcon :icon="ChevronDown" :size="14" /></template>
            </UidButton>
          </template>
          <UidMenuItem @click="onResetView">
            {{ tr('Все') }} {{ (resourceMeta?.label ?? slug).toLowerCase() }}
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
                :aria-label="tr('Удалить view')"
                @click.stop="onDeleteView(v, $event)"
              >
                <UidIcon :icon="Trash2" :size="12" />
              </button>
            </span>
          </UidMenuItem>
        </UidMenu>
        <UidMenu>
          <template #trigger>
            <UidButton variant="ghost" size="md" :aria-label="tr('Действия')" class="admin-page__more">
              <UidIcon :icon="MoreHorizontal" :size="16" />
            </UidButton>
          </template>
          <UidMenuItem @click="retryLoad">{{ tr('Обновить') }}</UidMenuItem>
          <UidMenuItem
            v-for="fmt in exportFormats"
            :key="fmt"
            @click="onExport(fmt)"
          >
            {{ tr('Экспорт') }} {{ EXPORT_LABELS[fmt] ?? fmt.toUpperCase() }}
          </UidMenuItem>
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
        <UidButton v-if="isImportable" variant="secondary" size="md" @click="onImportClick">
          <template #prepend><UidIcon :icon="Upload" :size="14" /></template>
          {{ tr('Импорт') }}
        </UidButton>
        <input
          ref="importInput"
          type="file"
          class="admin-page__import-input"
          accept=".csv,.json,.xlsx,.xls,application/json,text/csv"
          @change="onImportFileChange"
        />
        <UidButton data-testid="resource-create"
          v-if="resolvedCreateRouteName && isCreatable"
          variant="primary"
          size="md"
          @click="$router.push({ name: resolvedCreateRouteName })"
        >
          <template #prepend><UidIcon :icon="Plus" :size="14" /></template>
          {{ createLabel ?? tr('Создать') }}
        </UidButton>
      </div>
    </header>

    <!-- Bulk toolbar (selection > 0) ИЛИ filter bar. Действия ресурса
         (provision/suspend/drop …) доступны ТОЛЬКО здесь — без выбора
         строк запустить массовое действие нельзя (BL-26). -->
    <div v-if="index.hasSelection" class="admin-bulk-toolbar" data-testid="bulk-bar" role="toolbar">
      <span class="admin-bulk-toolbar__count">
        {{ tr('Выбрано') }} <b>{{ index.selectedCount }}</b>
      </span>
      <span class="admin-bulk-toolbar__divider" />
      <UidButton
        v-for="action in selectionActions"
        :key="action.key"
        size="sm"
        :variant="action.destructive ? 'danger' : 'ghost'"
        @click="onCustomAction(action)"
      >
        {{ action.label }}
      </UidButton>
      <UidButton size="sm" variant="ghost" @click="onExport('csv')">{{ tr('Экспорт') }}</UidButton>
      <UidButton
        v-if="isEditable"
        size="sm"
        variant="danger"
        :loading="bulkDeleting"
        @click="onBulkDelete"
      >
        {{ tr('Удалить') }}
      </UidButton>
      <span class="admin-bulk-toolbar__spacer" />
      <UidButton size="sm" variant="ghost" @click="index.clearSelection">
        {{ tr('Снять выделение') }}
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
      :enable-saved-views="hasSavedViews"
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
      :title="tr('Не удалось загрузить данные')"
      :description="index.error?.message ?? tr('Попробуйте обновить страницу.')"
      class="admin-resource-index__state"
    >
      <template #actions>
        <UidButton variant="primary" @click="retryLoad">{{ tr('Обновить') }}</UidButton>
      </template>
    </UidErrorState>
    <UidEmptyState
      v-else-if="index.isEmpty"
      :title="tr('Пока пусто')"
      :description="tr('Создайте первую запись или измените фильтры.')"
      class="admin-resource-index__state"
    >
      <template #actions>
        <UidButton
          v-if="createRouteName && isCreatable"
          variant="primary"
          @click="$router.push({ name: createRouteName })"
        >
          {{ createLabel ?? tr('Создать') }}
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
            :title="tr('Перетащить')"
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
              :title="tr('Просмотр')"
              @click.stop="onView(rowFromSlot(slotProps) ?? {}, $event)"
            >
              <UidIcon :icon="Eye" :size="16" />
            </button>
            <template v-if="!isTrashed(rowFromSlot(slotProps) ?? {})">
              <button
                v-if="isEditable"
                type="button"
                class="admin-resource-index__row-action"
                :title="tr('Редактировать')"
                @click.stop="onEdit(rowFromSlot(slotProps) ?? {}, $event)"
              >
                <UidIcon :icon="Pencil" :size="16" />
              </button>
              <button
                v-if="isEditable"
                type="button"
                class="admin-resource-index__row-action admin-resource-index__row-action--danger"
                :title="tr('Удалить')"
                @click.stop="onDelete(rowFromSlot(slotProps) ?? {}, $event)"
              >
                <UidIcon :icon="Trash2" :size="16" />
              </button>
            </template>
            <template v-else>
              <button
                type="button"
                class="admin-resource-index__row-action"
                :title="tr('Восстановить')"
                @click.stop="onRestore(rowFromSlot(slotProps) ?? {}, $event)"
              >
                <UidIcon :icon="RotateCcw" :size="16" />
              </button>
              <button
                type="button"
                class="admin-resource-index__row-action admin-resource-index__row-action--danger"
                :title="tr('Удалить навсегда')"
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
            <a
              v-else-if="columnIsLink(col.key) && linkHref(col.key, slotProps)"
              class="admin-cell-truncate admin-cell-link"
              :href="linkHref(col.key, slotProps)"
              :target="linkTarget(col.key)"
              rel="noopener"
              :title="renderCell(col.key, slotProps)"
            >{{ renderCell(col.key, slotProps) }}</a>
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
  /* min-height keeps the layout from collapsing on the first mount,
     когда slowLoading ещё false (быстрый запрос — placeholder пустой). */
  min-height: 320px;
}

/* Drag handle of a reorderable resource — the first column. */
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
/* The source row goes translucent while dragging — borrowed from Notion and Linear. */
.admin-resource-index__row-drag--ghost { opacity: 0.4; }
/* The drop indicator line, stretched across the row through ::before
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

/* Row actions: the view, edit and delete icons in the last column. */
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
  /* The table is visually glued to the filter bar or bulk toolbar above it —
     убираем gap, скругляем только нижние углы у table-wrap'а. */
  margin-top: 0;
}
.admin-resource-index__table .uid-table-wrap {
  border: 1px solid var(--uid-border-subtle);
  border-top: 0;
  border-radius: 0 0 var(--uid-radius-lg) var(--uid-radius-lg);
}

/*
 * Compact table mode: the td/th padding is reduced to fit --admin-row-h:32px
 * and a 13px font. The stock UidTable uses padding:12px, which is too much
 * here. The override is scoped to admin-resource-index so that UidTable stays
 * untouched elsewhere — storybook, custom widgets.
 */
.admin-resource-index__table .uid-table__td,
.admin-resource-index__table .uid-table__th {
  padding: 6px var(--uid-space-md);
  font-size: var(--admin-row-fs, 13px);
  height: var(--admin-row-h, 32px);
  line-height: 1.3;
}

/*
 * Cell truncation: one line with an ellipsis and a sane max-width, 320px by
 * default. Long words wrap within that limit up to three lines (line-clamp),
 * in case a host overrides white-space through a slot.
 *
 * The full value is shown by the native browser tooltip through the `title`
 * attribute — see the cell renderer above.
 */
.admin-cell-truncate {
  display: inline-block;
  max-width: var(--admin-cell-max-width, 320px);
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.admin-cell-link {
  color: var(--uid-accent, #2563eb);
  text-decoration: none;
  cursor: pointer;
}
.admin-cell-link:hover {
  text-decoration: underline;
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

/* Bulk toolbar — a dark zinc-900 surface that replaces the filter bar */
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
