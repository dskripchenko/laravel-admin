<script setup lang="ts">
/**
 * EmbeddedResourceTable — компактная таблица другого Resource'а, встроенная
 * во вкладку edit-page родителя. Соответствует backend layout-типу
 * `'admin.resource-table'` (см. `core/src/Layout/ResourceTable.php`).
 *
 * Resolution:
 *  - manifest колонки/permissions/editable — из useManifestStore.
 *  - parent record (для FK) — из useResourceFormStore.
 *  - данные — POST `/{resource}/search` с filter `{[foreign_key]: parentId}`.
 *
 * Возможности (props.features):
 *  - inline-edit ячеек (всегда — определяется per-column в manifest)
 *  - quick-add: пустая строка-draft + commit (POST /create с FK auto-fill)
 *  - per-row delete (иконка корзины)
 *  - bulk delete (выделение + toolbar)
 */
import { computed, onMounted, ref, watch } from 'vue'
import { Plus, Trash2, Check, X } from 'lucide-vue-next'
import {
  UidButton,
  UidEmptyState,
  UidErrorState,
  UidIcon,
  UidSkeleton,
  UidTable,
  type UidTableColumn,
} from '@dskripchenko/ui'
import InlineEditCell from '../resource/InlineEditCell.vue'
import { useManifestStore } from '../../stores/manifest'
import { useResourceFormStore } from '../../stores/resourceForm'
import { getAdminClient } from '../../stores/registry'
import { adminToast } from '../../stores/toast'

interface Features {
  create?: boolean
  delete?: boolean
  bulkDelete?: boolean
}

interface Props {
  resource: string
  foreign_key: string
  parent_field?: string
  hide_columns?: string[]
  features?: Features
}

const props = withDefaults(defineProps<Props>(), {
  parent_field: 'id',
  hide_columns: () => [],
  features: () => ({ create: false, delete: false, bulkDelete: false }),
})

interface SearchResponse {
  data: Array<Record<string, unknown>>
  meta: { total: number; page: number; per_page: number; last_page: number }
}

interface EditableMeta {
  field: string
  validation: unknown[]
  as: 'text' | 'number' | 'select' | 'date' | 'textarea' | 'switcher'
  options: Record<string | number, string>
}

const manifest = useManifestStore()
const parentForm = useResourceFormStore()
const childMeta = computed(() => manifest.getResource(props.resource))

const items = ref<Array<Record<string, unknown>>>([])
const loading = ref(false)
const error = ref<Error | null>(null)
const selection = ref<Set<string | number>>(new Set())
const draft = ref<Record<string, unknown> | null>(null)

const parentId = computed<string | number | null>(() => {
  const v = (parentForm.state as Record<string, unknown>)[props.parent_field]
  if (v === null || v === undefined) return null
  return v as string | number
})

const visibleColumns = computed<Array<Record<string, unknown>>>(() => {
  const cols = (childMeta.value?.columns ?? []) as Array<Record<string, unknown>>
  return cols.filter((c) => {
    const name = String(c.name ?? '')
    return !props.hide_columns.includes(name)
  })
})

const tableColumns = computed<UidTableColumn[]>(() =>
  visibleColumns.value.map((c) => ({
    key: String(c.name ?? ''),
    label: String(c.label ?? c.name ?? ''),
    sortable: Boolean(c.sortable),
    align: (c.align as 'left' | 'center' | 'right' | undefined) ?? 'left',
    width: c.width as string | undefined,
  })),
)

const canCreate = computed(() => props.features.create && Boolean(childMeta.value?.permissions?.create))
const canDelete = computed(() => props.features.delete && Boolean(childMeta.value?.permissions?.delete))
const canBulkDelete = computed(() => props.features.bulkDelete && Boolean(childMeta.value?.permissions?.delete))

function editableMeta(colName: string): EditableMeta | null {
  for (const c of visibleColumns.value) {
    if (String(c.name ?? '') === colName) {
      const editable = c.editable as EditableMeta | null | undefined
      return editable ?? null
    }
  }
  return null
}

async function load(): Promise<void> {
  if (parentId.value === null) {
    items.value = []
    return
  }
  loading.value = true
  error.value = null
  try {
    const client = getAdminClient()
    const res = await client.post<SearchResponse>(`/${props.resource}/search`, {
      page: 1,
      per_page: 100,
      filters: { [props.foreign_key]: parentId.value },
    })
    items.value = res.data
  } catch (err) {
    error.value = err instanceof Error ? err : new Error(String(err))
    items.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(parentId, load)

function rowId(row: Record<string, unknown>): string | number {
  return (row.id ?? '') as string | number
}

function rowFromSlot(slotProps: unknown): Record<string, unknown> | undefined {
  return (slotProps as { row?: Record<string, unknown> } | undefined)?.row
}

function toggleSelection(row: Record<string, unknown>): void {
  const id = rowId(row)
  const next = new Set(selection.value)
  if (next.has(id)) next.delete(id); else next.add(id)
  selection.value = next
}

function toggleSelectAll(): void {
  if (selection.value.size === items.value.length) {
    selection.value = new Set()
  } else {
    selection.value = new Set(items.value.map(rowId))
  }
}

async function deleteRow(row: Record<string, unknown>): Promise<void> {
  const id = rowId(row)
  if (!confirm('Удалить строку?')) return
  try {
    await getAdminClient().post(`/${props.resource}/delete`, { id })
    items.value = items.value.filter((r) => rowId(r) !== id)
    selection.value.delete(id)
    selection.value = new Set(selection.value)
  } catch {
    adminToast.error('Не удалось удалить строку.')
  }
}

async function bulkDelete(): Promise<void> {
  if (selection.value.size === 0) return
  if (!confirm(`Удалить ${selection.value.size} строк?`)) return
  const ids = [...selection.value]
  try {
    // Параллельное удаление: backend пока без bulk endpoint; шлём по одному.
    await Promise.all(ids.map((id) => getAdminClient().post(`/${props.resource}/delete`, { id })))
    selection.value = new Set()
    await load()
  } catch {
    adminToast.error('Не удалось удалить часть строк.')
    await load()
  }
}

function startDraft(): void {
  if (!canCreate.value || draft.value !== null) return
  const initial: Record<string, unknown> = {}
  if (parentId.value !== null) initial[props.foreign_key] = parentId.value
  draft.value = initial
}

function cancelDraft(): void {
  draft.value = null
}

async function commitDraft(): Promise<void> {
  if (draft.value === null) return
  try {
    await getAdminClient().post(`/${props.resource}/create`, draft.value)
    draft.value = null
    await load()
  } catch {
    adminToast.error('Не удалось создать запись.')
  }
}

function updateDraftField(col: string, value: unknown): void {
  if (draft.value === null) return
  draft.value = { ...draft.value, [col]: value }
}

function getCellDisplay(row: Record<string, unknown>, col: string): string {
  const v = row[col]
  if (v === null || v === undefined) return ''
  return String(v)
}
</script>

<template>
  <div class="admin-embedded-table">
    <div class="admin-embedded-table__toolbar">
      <UidButton
        v-if="canCreate"
        variant="primary"
        size="sm"
        :disabled="draft !== null"
        @click="startDraft"
      >
        <UidIcon :icon="Plus" /> Добавить
      </UidButton>
      <UidButton
        v-if="canBulkDelete && selection.size > 0"
        variant="danger"
        size="sm"
        @click="bulkDelete"
      >
        <UidIcon :icon="Trash2" /> Удалить выбранные ({{ selection.size }})
      </UidButton>
    </div>

    <UidSkeleton v-if="loading && items.length === 0" />
    <UidErrorState v-else-if="error" :message="error.message" @retry="load" />
    <UidEmptyState
      v-else-if="!loading && items.length === 0 && draft === null"
      title="Нет данных"
      hint="Нажмите «Добавить», чтобы создать первую запись."
    />
    <UidTable
      v-else
      :columns="tableColumns"
      :data="items"
      :selectable="canBulkDelete"
      :selection="selection"
      row-key="id"
      @select-row="toggleSelection"
      @select-all="toggleSelectAll"
    >
      <template
        v-for="col in tableColumns"
        :key="col.key"
        #[col.key]="slotProps"
      >
        <template v-if="rowFromSlot(slotProps) && editableMeta(col.key)">
          <InlineEditCell
            :resource-slug="resource"
            :row-id="rowId(rowFromSlot(slotProps)!)"
            :column="col.key"
            :value="rowFromSlot(slotProps)![col.key]"
            :editable="true"
            :input-type="editableMeta(col.key)!.as"
            :options="editableMeta(col.key)!.options"
            :row-override="(rowFromSlot(slotProps)!._editable as Record<string, boolean> | undefined) ?? {}"
            @saved="(v) => { const r = rowFromSlot(slotProps); if (r) r[col.key] = v }"
          >
            <span>{{ getCellDisplay(rowFromSlot(slotProps)!, col.key) }}</span>
          </InlineEditCell>
        </template>
        <span v-else>{{ getCellDisplay(rowFromSlot(slotProps) ?? {}, col.key) }}</span>
      </template>

      <template v-if="canDelete" #actions="slotProps">
        <button
          type="button"
          class="admin-embedded-table__row-delete"
          aria-label="Удалить"
          @click="rowFromSlot(slotProps) && deleteRow(rowFromSlot(slotProps)!)"
        >
          <UidIcon :icon="Trash2" :size="14" />
        </button>
      </template>
    </UidTable>

    <!-- Draft row (для quick-add) — рендерим отдельной мини-формой под таблицей,
         т.к. UidTable не имеет slot'а для prepend-row. UX-простой. -->
    <div v-if="draft !== null" class="admin-embedded-table__draft">
      <div class="admin-embedded-table__draft-cells">
        <div
          v-for="col in tableColumns"
          :key="col.key"
          class="admin-embedded-table__draft-cell"
        >
          <label class="admin-embedded-table__draft-label">{{ col.label }}</label>
          <select
            v-if="editableMeta(col.key)?.as === 'select'"
            class="admin-embedded-table__draft-input"
            :value="(draft[col.key] as string) ?? ''"
            @change="(e) => updateDraftField(col.key, (e.target as HTMLSelectElement).value)"
          >
            <option value=""></option>
            <option
              v-for="(label, value) in editableMeta(col.key)!.options"
              :key="value"
              :value="value"
            >{{ label }}</option>
          </select>
          <input
            v-else
            class="admin-embedded-table__draft-input"
            :type="editableMeta(col.key)?.as === 'number' ? 'number' : editableMeta(col.key)?.as === 'date' ? 'date' : 'text'"
            :value="(draft[col.key] as string) ?? ''"
            @input="(e) => updateDraftField(col.key, (e.target as HTMLInputElement).value)"
          />
        </div>
      </div>
      <div class="admin-embedded-table__draft-actions">
        <UidButton variant="primary" size="sm" @click="commitDraft">
          <UidIcon :icon="Check" /> Создать
        </UidButton>
        <UidButton variant="ghost" size="sm" @click="cancelDraft">
          <UidIcon :icon="X" /> Отмена
        </UidButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-embedded-table {
  display: flex;
  flex-direction: column;
  gap: var(--uid-spacing-sm, 12px);
}
.admin-embedded-table__toolbar {
  display: flex;
  align-items: center;
  gap: var(--uid-spacing-sm, 8px);
}
.admin-embedded-table__row-delete {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--uid-color-text-secondary, #62686f);
  padding: 4px;
  border-radius: var(--uid-radius-sm, 4px);
}
.admin-embedded-table__row-delete:hover {
  background: var(--uid-color-surface-2, #f3f4f6);
  color: var(--uid-color-danger, #dc2626);
}
.admin-embedded-table__draft {
  background: var(--uid-color-surface-2, #f9fafb);
  border: 1px dashed var(--uid-color-border, #e5e7eb);
  border-radius: var(--uid-radius-md, 8px);
  padding: var(--uid-spacing-md, 16px);
  display: flex;
  flex-direction: column;
  gap: var(--uid-spacing-sm, 12px);
}
.admin-embedded-table__draft-cells {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: var(--uid-spacing-sm, 12px);
}
.admin-embedded-table__draft-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.admin-embedded-table__draft-label {
  font-size: var(--uid-font-size-sm, 12px);
  color: var(--uid-color-text-secondary, #62686f);
}
.admin-embedded-table__draft-input {
  height: 32px;
  padding: 0 8px;
  border: 1px solid var(--uid-color-border, #e5e7eb);
  border-radius: var(--uid-radius-sm, 4px);
  font: inherit;
  font-size: 13px;
}
.admin-embedded-table__draft-actions {
  display: flex;
  gap: var(--uid-spacing-sm, 8px);
}
</style>
