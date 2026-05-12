<script setup lang="ts">
/**
 * AdminFilterToolbar — двухстрочный toolbar над таблицей Resource'а:
 *
 *   Row 1 (filter bar): search | active-chips, inactive-buttons | + Filter | Сбросить
 *   Row 2 (action bar): Группировать | Колонки | Сохранить
 *
 * Конфигурируется через manifest:
 *   - filters[] — Resource->filters() (type/options/multiple/icon/...)
 *   - columns[] — Resource->columns() (key/label/groupable/...)
 *   - searchable[] — список колонок поиска (placeholder подставляется
 *     автоматически: "Поиск по {label}…")
 *
 * UI-фолбэк: для каждого filter-type (options / date_range / switcher /
 * input / select_from_model / trashed) рендерим универсальный popover-
 * редактор, чтобы Resource без custom-UI всё равно получил рабочий toolbar.
 */
import { computed, ref, watch } from 'vue'
import {
  Bookmark,
  Check,
  Columns,
  LayoutGrid,
  Plus,
  RotateCcw,
  Search,
  X,
} from 'lucide-vue-next'
import { UidButton, UidIcon, UidMenu } from '@dskripchenko/ui'
import { resolveIcon } from '../shell/iconRegistry'
import { FilterEditor, type FilterDef, type FilterOption } from './FilterEditor'

interface ColumnDef {
  key?: string
  name?: string
  label?: string
  groupable?: boolean
  hidden?: boolean
}

interface Props {
  search: string
  searchPlaceholder?: string
  filters: FilterDef[]
  values: Record<string, unknown>
  columns: ColumnDef[]
  /** Group-by column key (если выбран). */
  groupBy?: string | null
  /** Visibility map для column-toggle. true = видна. */
  columnVisibility?: Record<string, boolean>
  /** Показывать ли "Сохранить" (saved-views) — host управляет наличием feature. */
  enableSavedViews?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  searchPlaceholder: 'Поиск…',
  groupBy: null,
  columnVisibility: () => ({}),
  enableSavedViews: true,
})

const emit = defineEmits<{
  'update:search': [value: string]
  'apply-filter': [name: string, value: unknown]
  'reset': []
  'group-by': [column: string | null]
  'columns-visibility': [next: Record<string, boolean>]
  'save-view': [label: string]
}>()

// === Search local state (commit on Enter / blur) ===
const localSearch = ref<string>(props.search)
watch(
  () => props.search,
  (v) => {
    localSearch.value = v
  },
)
function commitSearch(): void {
  if (localSearch.value !== props.search) emit('update:search', localSearch.value)
}

// === Active / inactive filter partitioning ===
function hasValue(v: unknown): boolean {
  if (v === null || v === undefined || v === '') return false
  if (Array.isArray(v) && v.length === 0) return false
  if (typeof v === 'object' && !Array.isArray(v)) {
    const obj = v as Record<string, unknown>
    return Object.values(obj).some((x) => x !== null && x !== '' && x !== undefined)
  }
  return true
}

const activeFilters = computed(() =>
  props.filters.filter((f) => hasValue(props.values[f.name])),
)
const inactiveFilters = computed(() =>
  props.filters.filter((f) => !hasValue(props.values[f.name])),
)

const VISIBLE_LIMIT = 4
const revealed = ref<Set<string>>(new Set())
const inactiveVisible = computed(() =>
  inactiveFilters.value.filter(
    (f, i) => revealed.value.has(f.name) || i < VISIBLE_LIMIT,
  ),
)
const inactiveHidden = computed(() =>
  inactiveFilters.value.filter(
    (f, i) => !revealed.value.has(f.name) && i >= VISIBLE_LIMIT,
  ),
)
function reveal(name: string): void {
  const next = new Set(revealed.value)
  next.add(name)
  revealed.value = next
}

const hasActiveAnything = computed<boolean>(
  () => props.search !== '' || activeFilters.value.length > 0,
)

function clearFilter(name: string): void {
  emit('apply-filter', name, null)
}

// === Filter chip label formatting ===
function chipText(f: FilterDef): string {
  const v = props.values[f.name]
  if (f.options && f.options.length > 0) {
    const arr = Array.isArray(v) ? v : [v]
    const labels = arr.map(
      (val) => f.options?.find((o) => String(o.value) === String(val))?.label ?? String(val),
    )
    return `${f.label}: ${labels.join(', ')}`
  }
  if (f.type === 'date_range' && v && typeof v === 'object') {
    const r = v as { from?: string; to?: string }
    return `${f.label}: ${r.from ?? ''} – ${r.to ?? ''}`
  }
  if (f.type === 'switcher') {
    return `${f.label}: ${v ? 'да' : 'нет'}`
  }
  return `${f.label}: ${String(v)}`
}

// === Multi-select working-copy (буфер до Apply) ===
const draftValues = ref<Record<string, unknown>>({})
function getDraft(name: string): unknown {
  if (!(name in draftValues.value)) {
    draftValues.value[name] = props.values[name] ?? null
  }
  return draftValues.value[name]
}
function setDraft(name: string, value: unknown): void {
  draftValues.value = { ...draftValues.value, [name]: value }
}
function applyDraft(name: string, close: () => void): void {
  emit('apply-filter', name, draftValues.value[name] ?? null)
  close()
}
function toggleOption(name: string, value: FilterOption['value'], multiple: boolean): void {
  if (!multiple) {
    setDraft(name, value)
    return
  }
  const cur = getDraft(name)
  const arr = Array.isArray(cur) ? [...(cur as Array<string | number>)] : []
  const idx = arr.findIndex((x) => String(x) === String(value))
  if (idx >= 0) arr.splice(idx, 1)
  else arr.push(value)
  setDraft(name, arr)
}
function isOptionChecked(name: string, value: FilterOption['value']): boolean {
  const cur = getDraft(name)
  if (Array.isArray(cur)) return cur.some((x) => String(x) === String(value))
  return cur !== null && cur !== undefined && String(cur) === String(value)
}

// === Group-by + Columns + Saved-view ===
const groupableColumns = computed<ColumnDef[]>(() =>
  props.columns.filter((c) => c.groupable === true && (c.key ?? c.name)),
)
function setGroupBy(col: string | null, close: () => void): void {
  emit('group-by', col)
  close()
}

function colKey(c: ColumnDef): string {
  return String(c.key ?? c.name ?? '')
}
function colLabel(c: ColumnDef): string {
  return String(c.label ?? c.name ?? c.key ?? '')
}
function isVisible(c: ColumnDef): boolean {
  const k = colKey(c)
  if (k in props.columnVisibility) return props.columnVisibility[k]
  return true
}
function toggleVisibility(c: ColumnDef): void {
  const k = colKey(c)
  emit('columns-visibility', { ...props.columnVisibility, [k]: !isVisible(c) })
}

const newViewLabel = ref<string>('')
function saveView(close: () => void): void {
  if (newViewLabel.value.trim() === '') return
  emit('save-view', newViewLabel.value.trim())
  newViewLabel.value = ''
  close()
}

function iconFor(name: string | null | undefined) {
  return resolveIcon(name) ?? null
}
</script>

<template>
  <div class="admin-toolbar">
    <!-- Row 1: search + filters -->
    <div class="admin-toolbar__row admin-toolbar__row--filters">
      <label class="admin-toolbar__search">
        <UidIcon :icon="Search" :size="14" class="admin-toolbar__search-icon" />
        <input
          v-model="localSearch"
          type="search"
          :placeholder="searchPlaceholder"
          class="admin-toolbar__search-input"
          @keydown.enter="commitSearch"
          @blur="commitSearch"
        />
      </label>

      <span v-if="filters.length > 0" class="admin-toolbar__divider" />

      <!-- Active filter chips: filled style + X для сброса -->
      <UidMenu
        v-for="f in activeFilters"
        :key="`active-${f.name}`"
      >
        <template #trigger>
          <button
            type="button"
            class="admin-toolbar__chip admin-toolbar__chip--active"
          >
            <UidIcon
              v-if="iconFor(f.icon) !== null"
              :icon="(iconFor(f.icon) as never)"
              :size="14"
            />
            <span class="admin-toolbar__chip-text">{{ chipText(f) }}</span>
            <span
              role="button"
              tabindex="0"
              aria-label="Сбросить фильтр"
              class="admin-toolbar__chip-x"
              @click.stop="clearFilter(f.name)"
              @keydown.enter.stop="clearFilter(f.name)"
            >
              <UidIcon :icon="X" :size="12" />
            </span>
          </button>
        </template>
        <div
          class="admin-toolbar__popover"
          @keydown.stop
        >
          <FilterEditor
            :filter="f"
            :draft="(getDraft(f.name) as never)"
            :is-checked="(v) => isOptionChecked(f.name, v)"
            @toggle-option="(v) => toggleOption(f.name, v, f.multiple ?? false)"
            @set-draft="(v) => setDraft(f.name, v)"
          />
          <div class="admin-toolbar__popover-actions">
            <UidButton size="sm" variant="ghost" @click="setDraft(f.name, null)">
              Очистить
            </UidButton>
            <UidButton
              size="sm"
              variant="primary"
              @click="applyDraft(f.name, () => undefined)"
            >
              Применить
            </UidButton>
          </div>
        </div>
      </UidMenu>

      <!-- Inactive filter buttons: outlined, открывают popover -->
      <UidMenu
        v-for="f in inactiveVisible"
        :key="`inactive-${f.name}`"
      >
        <template #trigger>
          <button
            type="button"
            class="admin-toolbar__chip"
          >
            <UidIcon
              v-if="iconFor(f.icon) !== null"
              :icon="(iconFor(f.icon) as never)"
              :size="14"
            />
            <span class="admin-toolbar__chip-text">{{ f.label }}</span>
          </button>
        </template>
        <div
          class="admin-toolbar__popover"
          @keydown.stop
        >
          <FilterEditor
            :filter="f"
            :draft="(getDraft(f.name) as never)"
            :is-checked="(v) => isOptionChecked(f.name, v)"
            @toggle-option="(v) => toggleOption(f.name, v, f.multiple ?? false)"
            @set-draft="(v) => setDraft(f.name, v)"
          />
          <div class="admin-toolbar__popover-actions">
            <UidButton size="sm" variant="ghost" @click="setDraft(f.name, null)">
              Очистить
            </UidButton>
            <UidButton
              size="sm"
              variant="primary"
              @click="applyDraft(f.name, () => undefined)"
            >
              Применить
            </UidButton>
          </div>
        </div>
      </UidMenu>

      <!-- + Filter dropdown с скрытыми filter'ами -->
      <UidMenu v-if="inactiveHidden.length > 0">
        <template #trigger>
          <button type="button" class="admin-toolbar__chip">
            <UidIcon :icon="Plus" :size="14" />
            <span class="admin-toolbar__chip-text">Filter</span>
          </button>
        </template>
        <div class="admin-toolbar__popover admin-toolbar__popover--list" @keydown.stop>
          <button
            v-for="f in inactiveHidden"
            :key="f.name"
            type="button"
            role="menuitem"
            class="admin-toolbar__list-item"
            @click="reveal(f.name)"
          >
            <UidIcon
              v-if="iconFor(f.icon) !== null"
              :icon="(iconFor(f.icon) as never)"
              :size="14"
            />
            {{ f.label }}
          </button>
        </div>
      </UidMenu>

      <span class="admin-toolbar__spacer" />

      <UidButton
        v-if="hasActiveAnything"
        variant="ghost"
        size="md"
        @click="emit('reset')"
      >
        <template #prepend><UidIcon :icon="RotateCcw" :size="14" /></template>
        Сбросить
      </UidButton>

      <!-- Спейсер: отодвигает actions вправо в этой же строке -->
      <span
        v-if="groupableColumns.length > 0 || columns.length > 0 || enableSavedViews"
        class="admin-toolbar__spacer admin-toolbar__spacer--actions"
      />

      <!-- Inline-actions (группировать / колонки / сохранить) -->
      <!-- Группировать -->
      <UidMenu v-if="groupableColumns.length > 0">
        <template #trigger>
          <button type="button" class="admin-toolbar__chip">
            <UidIcon :icon="LayoutGrid" :size="14" />
            <span class="admin-toolbar__chip-text">
              {{ groupBy
                ? `Группа: ${colLabel(columns.find((c) => colKey(c) === groupBy) ?? {})}`
                : 'Группировать' }}
            </span>
          </button>
        </template>
        <div class="admin-toolbar__popover admin-toolbar__popover--list" @keydown.stop>
          <button
            type="button"
            role="menuitem"
            class="admin-toolbar__list-item"
            @click="setGroupBy(null, () => undefined)"
          >
            <UidIcon v-if="!groupBy" :icon="Check" :size="14" />
            <span>Без группировки</span>
          </button>
          <button
            v-for="c in groupableColumns"
            :key="colKey(c)"
            type="button"
            role="menuitem"
            class="admin-toolbar__list-item"
            @click="setGroupBy(colKey(c), () => undefined)"
          >
            <UidIcon v-if="groupBy === colKey(c)" :icon="Check" :size="14" />
            <span>{{ colLabel(c) }}</span>
          </button>
        </div>
      </UidMenu>

      <!-- Колонки -->
      <UidMenu v-if="columns.length > 0">
        <template #trigger>
          <button type="button" class="admin-toolbar__chip">
            <UidIcon :icon="Columns" :size="14" />
            <span class="admin-toolbar__chip-text">Колонки</span>
          </button>
        </template>
        <div class="admin-toolbar__popover admin-toolbar__popover--list" @keydown.stop>
          <label
            v-for="c in columns.filter((col) => colKey(col) !== '')"
            :key="colKey(c)"
            class="admin-toolbar__list-item admin-toolbar__list-item--checkbox"
          >
            <input
              type="checkbox"
              :checked="isVisible(c)"
              @change="toggleVisibility(c)"
            />
            <span>{{ colLabel(c) }}</span>
          </label>
        </div>
      </UidMenu>

      <!-- Сохранить (saved views) -->
      <UidMenu v-if="enableSavedViews">
        <template #trigger>
          <button type="button" class="admin-toolbar__chip">
            <UidIcon :icon="Bookmark" :size="14" />
            <span class="admin-toolbar__chip-text">Сохранить</span>
          </button>
        </template>
        <div class="admin-toolbar__popover" @keydown.stop>
          <input
            v-model="newViewLabel"
            type="text"
            placeholder="Название view"
            class="admin-toolbar__input"
            @keydown.enter="saveView(() => undefined)"
          />
          <div class="admin-toolbar__popover-actions">
            <UidButton
              size="sm"
              variant="primary"
              :disabled="newViewLabel.trim() === ''"
              @click="saveView(() => undefined)"
            >
              Сохранить view
            </UidButton>
          </div>
        </div>
      </UidMenu>
    </div>
  </div>
</template>

<style>
.admin-toolbar {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-xs);
  padding: var(--uid-space-sm) var(--uid-space-md);
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-raised);
  border-radius: var(--uid-radius-lg) var(--uid-radius-lg) 0 0;
  border-bottom: 0;
  margin-top: var(--uid-space-md);
}

.admin-toolbar__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--uid-space-xs);
}

.admin-toolbar__divider {
  width: 1px;
  height: 22px;
  background: var(--uid-border-subtle);
  flex: none;
  margin: 0 var(--uid-space-2xs);
}

.admin-toolbar__spacer { flex: 1; }

/* Search input */
.admin-toolbar__search {
  position: relative;
  display: inline-flex;
  align-items: center;
  flex: 1 1 200px;
  max-width: 360px;
  height: 32px;
  padding: 0 var(--uid-space-sm);
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-base);
  border-radius: var(--uid-radius-md);
  gap: var(--uid-space-2xs);
}
.admin-toolbar__search:focus-within {
  border-color: var(--uid-accent);
  outline: 2px solid color-mix(in srgb, var(--uid-accent) 18%, transparent);
}
.admin-toolbar__search-icon { color: var(--uid-text-tertiary); flex: none; }
.admin-toolbar__search-input {
  flex: 1;
  height: 100%;
  border: 0;
  outline: none;
  background: transparent;
  font: inherit;
  font-size: 13px;
  color: var(--uid-text-primary);
}
.admin-toolbar__search-input::placeholder { color: var(--uid-text-tertiary); }

/* Chip / button (общая база для active/inactive) */
.admin-toolbar__chip {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-2xs);
  height: 32px;
  padding: 0 var(--uid-space-sm);
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-base);
  border-radius: var(--uid-radius-md);
  color: var(--uid-text-secondary);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background var(--uid-duration-fast, 120ms) var(--uid-ease-out, ease),
    border-color var(--uid-duration-fast, 120ms) var(--uid-ease-out, ease),
    color var(--uid-duration-fast, 120ms) var(--uid-ease-out, ease);
}
.admin-toolbar__chip:hover {
  background: var(--uid-color-surface-hover, var(--uid-border-subtle));
  color: var(--uid-text-primary);
}
.admin-toolbar__chip--active {
  background: color-mix(in srgb, var(--uid-accent) 12%, transparent);
  border-color: color-mix(in srgb, var(--uid-accent) 35%, transparent);
  color: var(--uid-accent);
}
.admin-toolbar__chip--active:hover {
  background: color-mix(in srgb, var(--uid-accent) 18%, transparent);
  color: var(--uid-accent);
}
.admin-toolbar__chip-text {
  white-space: nowrap;
  max-width: 240px;
  overflow: hidden;
  text-overflow: ellipsis;
}
.admin-toolbar__chip-x {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  cursor: pointer;
  margin-left: var(--uid-space-2xs);
}
.admin-toolbar__chip-x:hover {
  background: color-mix(in srgb, var(--uid-accent) 25%, transparent);
}

/* Popover content */
.admin-toolbar__popover {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
  padding: var(--uid-space-sm);
  min-width: 220px;
  max-width: 320px;
}
.admin-toolbar__popover--list {
  padding: var(--uid-space-2xs);
  gap: 0;
}
.admin-toolbar__popover-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--uid-space-2xs);
  border-top: 1px solid var(--uid-border-subtle);
  padding-top: var(--uid-space-sm);
}
.admin-toolbar__list-item {
  display: flex;
  align-items: center;
  gap: var(--uid-space-2xs);
  padding: var(--uid-space-2xs) var(--uid-space-sm);
  border-radius: var(--uid-radius-sm);
  cursor: pointer;
  background: transparent;
  border: 0;
  font-size: 13px;
  text-align: left;
  color: var(--uid-text-primary);
}
.admin-toolbar__list-item:hover { background: var(--uid-color-surface-hover, var(--uid-border-subtle)); }
.admin-toolbar__list-item--checkbox { cursor: pointer; }
.admin-toolbar__editor { display: flex; flex-direction: column; gap: var(--uid-space-2xs); }
.admin-toolbar__editor--dates { gap: var(--uid-space-xs); }
.admin-toolbar__input {
  height: 32px;
  padding: 0 var(--uid-space-sm);
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-base);
  border-radius: var(--uid-radius-md);
  color: var(--uid-text-primary);
  font-size: 13px;
  outline: none;
}
.admin-toolbar__input:focus {
  border-color: var(--uid-accent);
  outline: 2px solid color-mix(in srgb, var(--uid-accent) 18%, transparent);
}
</style>
