<script setup lang="ts">
/**
 * EmbeddedResourceTable — a compact table of another resource, embedded into a
 * tab of the parent's edit page. It matches the backend layout type
 * `'admin.resource-table'`; see `core/src/Layout/ResourceTable.php`.
 *
 * Where things come from:
 *  - the columns, permissions and editability from the manifest, through
 *    useManifestStore.
 *  - the parent record, for the foreign key, from useResourceFormStore.
 *  - the data from POST `/{resource}/search`, filtered by
 *    `{[foreign_key]: parentId}`.
 *
 * What it can do, through props.features:
 *  - edit cells inline — always available, decided per column in the manifest
 *  - quick-add: an empty draft row that commits through POST /create, with the
 *    foreign key filled in
 *  - delete a row, through the bin icon
 *  - delete in bulk, through the selection and the toolbar
 */
import { computed, onMounted, ref, watch } from 'vue'
import { Plus, Trash2, Check, X } from 'lucide-vue-next'
import {
  UidButton,
  UidEmptyState,
  UidErrorState,
  UidFormField,
  UidInput,
  UidSelect,
  UidSkeleton,
  UidSwitch,
  UidTable,
  UidTextarea,
  type UidTableColumn,
} from '@dskripchenko/ui'
import InlineEditCell from '../resource/InlineEditCell.vue'
import AdminTableCell from '../resource/AdminTableCell.vue'
import type { CellMeta } from '../resource/cellFormat'
import { useManifestStore } from '../../stores/manifest'
import { useResourceFormStore } from '../../stores/resourceForm'
import { getAdminClient } from '../../stores/registry'
import { adminToast } from '../../stores/toast'
import { apiErrorMessage } from '../../api/errors'
import { trSafe as tr, tRaw } from '../../stores/i18n'
import { confirmDialog, deleteWording } from '../../composables/useConfirm'

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

type InputKind = 'text' | 'number' | 'select' | 'date' | 'textarea' | 'switcher'

interface EditableMeta {
  field: string
  validation: unknown[]
  as: InputKind
  options: Record<string | number, string>
}

/** The trailing column with the row's delete button; UidTable fills it through its slot. */
const ACTIONS_KEY = '__actions'

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

/** The manifest's column key: `name` from TableColumn, `key` from older manifests. */
function columnKey(c: Record<string, unknown>): string {
  return String(c.key ?? c.name ?? '')
}

const visibleColumns = computed<Array<Record<string, unknown>>>(() => {
  const cols = (childMeta.value?.columns ?? []) as Array<Record<string, unknown>>
  return cols.filter((c) => {
    const name = columnKey(c)
    // No column switcher here: a defaultHidden() column stays hidden.
    return name !== '' && !props.hide_columns.includes(name) && (c.defaultHidden !== true || c.cantHide === true)
  })
})

/** The data columns, without the actions column: the draft form shows these. */
const dataColumns = computed<UidTableColumn[]>(() =>
  visibleColumns.value.map((c) => ({
    key: columnKey(c),
    label: String(c.label ?? c.name ?? ''),
    align: (c.align as 'left' | 'center' | 'right' | undefined) ?? 'left',
    width: typeof c.width === 'string' ? c.width : undefined,
  })),
)

const canCreate = computed(() => Boolean(props.features.create) && Boolean(childMeta.value?.permissions?.create))
const canDelete = computed(() => Boolean(props.features.delete) && Boolean(childMeta.value?.permissions?.delete))
const canBulkDelete = computed(() => Boolean(props.features.bulkDelete) && Boolean(childMeta.value?.permissions?.delete))

const tableColumns = computed<UidTableColumn[]>(() =>
  canDelete.value
    ? [...dataColumns.value, { key: ACTIONS_KEY, label: '', align: 'right', width: '48px' }]
    : dataColumns.value,
)

function editableMeta(colName: string): EditableMeta | null {
  const c = visibleColumns.value.find((col) => columnKey(col) === colName)
  const editable = c?.editable as Partial<EditableMeta> | null | undefined
  if (!editable) return null
  return {
    field: editable.field ?? colName,
    validation: editable.validation ?? [],
    as: editable.as ?? 'text',
    options: editable.options ?? {},
  }
}

function inputKind(colName: string): InputKind {
  return editableMeta(colName)?.as ?? 'text'
}

function selectOptions(colName: string): Array<{ value: string; label: string }> {
  return Object.entries(editableMeta(colName)?.options ?? {}).map(([value, label]) => ({ value, label: String(label) }))
}

async function load(): Promise<void> {
  if (parentId.value === null) {
    items.value = []
    selection.value = new Set()
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
    // A row that is gone is no longer selected.
    const present = new Set(items.value.map(rowId))
    selection.value = new Set([...selection.value].filter((id) => present.has(id)))
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

/** UidTable passes `{ row }` to a column slot. */
function rowFromSlot(slotProps: unknown): Record<string, unknown> {
  return (slotProps as { row?: Record<string, unknown> } | undefined)?.row ?? {}
}

function onSelectionUpdate(next: Set<string | number>): void {
  selection.value = new Set(next)
}

async function deleteRow(row: Record<string, unknown>): Promise<void> {
  const id = rowId(row)
  if (!(await confirmDialog({ ...deleteWording(), message: tr('Удалить строку?') }))) return
  try {
    await getAdminClient().post(`/${props.resource}/delete`, { id })
    items.value = items.value.filter((r) => rowId(r) !== id)
    const next = new Set(selection.value)
    next.delete(id)
    selection.value = next
  } catch (err) {
    adminToast.error(apiErrorMessage(err, tr('Не удалось удалить строку.')))
  }
}

async function bulkDelete(): Promise<void> {
  if (selection.value.size === 0) return
  if (!(await confirmDialog({ ...deleteWording(), message: tRaw('Удалить :count строк?', { count: selection.value.size }) }))) return
  const ids = [...selection.value]
  // There is no bulk endpoint: one /delete per id, one after another, as on
  // the list page. A row that failed stays selected.
  const failed: Array<string | number> = []
  let firstError: unknown = null
  for (const id of ids) {
    try {
      await getAdminClient().post(`/${props.resource}/delete`, { id })
    } catch (err) {
      failed.push(id)
      firstError ??= err
    }
  }
  selection.value = new Set(failed)
  if (failed.length > 0) {
    adminToast.error(apiErrorMessage(firstError, tr('Не удалось удалить часть строк.')))
  }
  await load()
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
  } catch (err) {
    adminToast.error(apiErrorMessage(err, tr('Не удалось создать запись.')))
  }
}

function draftValue(col: string): unknown {
  return draft.value?.[col] ?? null
}

function updateDraftField(col: string, value: unknown): void {
  if (draft.value === null) return
  draft.value = { ...draft.value, [col]: value }
}

function columnDef(col: string): Record<string, unknown> {
  return visibleColumns.value.find((c) => columnKey(c) === col) ?? {}
}
function columnPreset(col: string): string | null {
  const p = columnDef(col).preset
  return typeof p === 'string' ? p : null
}
function columnCellMeta(col: string): CellMeta {
  return (columnDef(col).meta as CellMeta | undefined) ?? {}
}
</script>

<template>
  <div class="admin-embedded-table">
    <div v-if="canCreate || (canBulkDelete && selection.size > 0)" class="admin-embedded-table__toolbar">
      <UidButton
        v-if="canCreate"
        variant="primary"
        size="sm"
        :icon="Plus"
        :disabled="draft !== null"
        data-testid="embedded-add"
        @click="startDraft"
      >
        {{ tr('Добавить') }}
      </UidButton>
      <UidButton
        v-if="canBulkDelete && selection.size > 0"
        variant="danger"
        size="sm"
        :icon="Trash2"
        data-testid="embedded-bulk-delete"
        @click="bulkDelete"
      >
        {{ tRaw('Удалить выбранные (:count)', { count: selection.size }) }}
      </UidButton>
    </div>

    <UidSkeleton v-if="loading && items.length === 0" />
    <UidErrorState
      v-else-if="error"
      :title="tr('Не удалось загрузить данные')"
      :description="error.message"
    >
      <template #actions>
        <UidButton variant="primary" @click="load">{{ tr('Обновить') }}</UidButton>
      </template>
    </UidErrorState>
    <UidEmptyState
      v-else-if="!loading && items.length === 0 && draft === null"
      :title="tr('Пока пусто')"
      :description="canCreate ? tr('Нажмите «Добавить», чтобы создать первую запись.') : tr('Здесь пока нет записей.')"
    />
    <UidTable
      v-else
      :columns="tableColumns"
      :data="items"
      :selectable="canBulkDelete"
      :selection="selection"
      row-key="id"
      @update:selection="onSelectionUpdate"
    >
      <template
        v-for="col in tableColumns"
        :key="col.key"
        #[col.key]="slotProps"
      >
        <UidButton
          v-if="col.key === ACTIONS_KEY"
          variant="ghost"
          size="sm"
          :icon="Trash2"
          class="admin-embedded-table__row-delete"
          :aria-label="tr('Удалить')"
          :title="tr('Удалить')"
          data-testid="embedded-row-delete"
          @click="deleteRow(rowFromSlot(slotProps))"
        />
        <InlineEditCell
          v-else-if="editableMeta(col.key)"
          :resource-slug="resource"
          :row-id="rowId(rowFromSlot(slotProps))"
          :column="col.key"
          :value="rowFromSlot(slotProps)[col.key]"
          :editable="true"
          :input-type="inputKind(col.key)"
          :options="editableMeta(col.key)!.options"
          :row-override="(rowFromSlot(slotProps)._editable as Record<string, boolean> | undefined) ?? {}"
          @saved="(v) => { rowFromSlot(slotProps)[col.key] = v }"
        >
          <AdminTableCell
            :value="rowFromSlot(slotProps)[col.key]"
            :preset="columnPreset(col.key)"
            :meta="columnCellMeta(col.key)"
            :row="rowFromSlot(slotProps)"
          />
        </InlineEditCell>
        <AdminTableCell
          v-else
          :value="rowFromSlot(slotProps)[col.key]"
          :preset="columnPreset(col.key)"
          :meta="columnCellMeta(col.key)"
          :row="rowFromSlot(slotProps)"
        />
      </template>
    </UidTable>

    <!-- The quick-add draft: a small form under the table, since UidTable has
         no slot for an extra row. -->
    <div v-if="draft !== null" class="admin-embedded-table__draft" data-testid="embedded-draft">
      <div class="admin-embedded-table__draft-cells">
        <UidFormField
          v-for="col in dataColumns"
          :key="col.key"
          :label="col.label"
          class="admin-embedded-table__draft-cell"
        >
          <UidSelect
            v-if="inputKind(col.key) === 'select'"
            :model-value="(draftValue(col.key) as string | null)"
            :options="selectOptions(col.key)"
            clearable
            size="sm"
            @update:model-value="(v) => updateDraftField(col.key, v)"
          />
          <UidSwitch
            v-else-if="inputKind(col.key) === 'switcher'"
            :model-value="Boolean(draftValue(col.key))"
            size="sm"
            @update:model-value="(v) => updateDraftField(col.key, v)"
          />
          <UidTextarea
            v-else-if="inputKind(col.key) === 'textarea'"
            :model-value="String(draftValue(col.key) ?? '')"
            :rows="2"
            size="sm"
            @update:model-value="(v) => updateDraftField(col.key, v)"
          />
          <UidInput
            v-else
            :model-value="String(draftValue(col.key) ?? '')"
            :type="inputKind(col.key) === 'number' ? 'number' : inputKind(col.key) === 'date' ? 'date' : 'text'"
            :name="col.key"
            size="sm"
            @update:model-value="(v) => updateDraftField(col.key, v)"
          />
        </UidFormField>
      </div>
      <div class="admin-embedded-table__draft-actions">
        <UidButton variant="primary" size="sm" :icon="Check" data-testid="embedded-draft-commit" @click="commitDraft">
          {{ tr('Создать') }}
        </UidButton>
        <UidButton variant="ghost" size="sm" :icon="X" @click="cancelDraft">
          {{ tr('Отмена') }}
        </UidButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-embedded-table {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
}
.admin-embedded-table__toolbar {
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm);
}
.admin-embedded-table__row-delete:hover {
  color: var(--uid-color-danger, #dc2626);
}
.admin-embedded-table__draft {
  background: var(--uid-color-bg-subtle);
  border: 1px dashed var(--uid-color-border, #e5e7eb);
  border-radius: var(--uid-radius-md, 8px);
  padding: var(--uid-space-md);
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
}
.admin-embedded-table__draft-cells {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: var(--uid-space-sm);
}
.admin-embedded-table__draft-actions {
  display: flex;
  gap: var(--uid-space-sm);
}
</style>
