<script setup lang="ts">
/**
 * The picker dialog: the target resource's records with its search, filters
 * and pagination, chosen by a click.
 *
 * The toolbar is the resource index's own AdminFilterToolbar, fed from the
 * target's manifest; the field's fixed filters are sent with every search and
 * hidden from the toolbar. The selection is kept across pages and searches, in
 * the order the records were picked. A single picker replaces its selection
 * on a click and confirms on a double click.
 *
 * With `upload` the dialog also takes a new file: it is sent to the upload
 * endpoint and the record it creates gets selected.
 */
import { computed, ref, watch } from 'vue'
import { Check, Upload } from 'lucide-vue-next'
import {
  UidButton,
  UidCard,
  UidEmptyState,
  UidErrorState,
  UidIcon,
  UidModal,
  UidPagination,
  UidSkeleton,
} from '@dskripchenko/ui'
import AdminFilterToolbar from '../../resource/AdminFilterToolbar.vue'
import type { FilterDef } from '../../resource/FilterEditor'
import PickerItemView from './PickerItemView.vue'
import {
  fetchPickerItems,
  sameKey,
  searchPicker,
  uploadForPicker,
  type PickerItem,
  type PickerUpload,
} from './pickerApi'
import { adminToast } from '../../../stores/toast'
import { trSafe as tr, tRaw } from '../../../stores/i18n'

interface Props {
  open: boolean
  resource: string
  title?: string
  multiple?: boolean
  maxItems?: number | null
  /** Fixed filter values, keyed by the target's filter names. */
  filters?: Record<string, unknown>
  /** The target's filters from its manifest, for the toolbar. */
  availableFilters?: FilterDef[]
  searchable?: boolean
  perPage?: number
  layout?: 'grid' | 'list' | null
  size?: 'lg' | 'xl' | 'full'
  /** What is picked when the dialog opens. */
  selected?: PickerItem[]
  /** The upload endpoint, when the user may upload. */
  upload?: PickerUpload | null
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  multiple: false,
  maxItems: null,
  filters: () => ({}),
  availableFilters: () => [],
  searchable: true,
  perPage: 24,
  layout: null,
  size: 'xl',
  selected: () => [],
  upload: null,
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: [items: PickerItem[]]
}>()

const items = ref<PickerItem[]>([])
const total = ref(0)
const page = ref(1)
const search = ref('')
const userFilters = ref<Record<string, unknown>>({})
const loading = ref(false)
const error = ref<Error | null>(null)
const selection = ref<PickerItem[]>([])
const uploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const toolbarFilters = computed<FilterDef[]>(() =>
  props.availableFilters.filter((f) => !(f.name in props.filters)),
)

const effectiveLayout = computed<'grid' | 'list'>(() => {
  if (props.layout) return props.layout
  return items.value.some((i) => i.preview !== null) ? 'grid' : 'list'
})

const limitReached = computed<boolean>(() =>
  props.multiple && props.maxItems !== null && selection.value.length >= props.maxItems,
)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const res = await searchPicker(props.resource, {
      page: page.value,
      perPage: props.perPage,
      q: search.value,
      filters: { ...userFilters.value, ...props.filters },
    })
    items.value = res.items
    total.value = res.total
  } catch (err) {
    error.value = err instanceof Error ? err : new Error(String(err))
    items.value = []
  } finally {
    loading.value = false
  }
}

watch(
  () => props.open,
  (open) => {
    if (!open) return
    selection.value = [...props.selected]
    page.value = 1
    void load()
  },
  { immediate: true },
)

function onSearch(value: string): void {
  search.value = value
  page.value = 1
  void load()
}

function onFilter(name: string, value: unknown): void {
  const next = { ...userFilters.value }
  if (value === null || value === undefined || value === '') delete next[name]
  else next[name] = value
  userFilters.value = next
  page.value = 1
  void load()
}

function onReset(): void {
  search.value = ''
  userFilters.value = {}
  page.value = 1
  void load()
}

function onPage(next: number): void {
  page.value = next
  void load()
}

const isSelected = (item: PickerItem): boolean => selection.value.some((s) => sameKey(s.id, item.id))

function toggle(item: PickerItem): void {
  if (!props.multiple) {
    selection.value = isSelected(item) ? [] : [item]
    return
  }
  if (isSelected(item)) {
    selection.value = selection.value.filter((s) => !sameKey(s.id, item.id))
  } else if (!limitReached.value) {
    selection.value = [...selection.value, item]
  }
}

function pickAndConfirm(item: PickerItem): void {
  if (props.multiple) return
  selection.value = [item]
  confirm()
}

function close(): void {
  emit('update:open', false)
}

function confirm(): void {
  emit('confirm', [...selection.value])
  close()
}

function chooseFile(): void {
  fileInput.value?.click()
}

async function onFile(e: Event): Promise<void> {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || !props.upload) return
  uploading.value = true
  try {
    const id = await uploadForPicker(props.upload, file)
    page.value = 1
    await load()
    if (id !== null) {
      const created = items.value.find((i) => sameKey(i.id, id))
        ?? (await fetchPickerItems(props.resource, [id]))[0]
      if (created && !isSelected(created)) {
        if (!props.multiple) selection.value = [created]
        else if (!limitReached.value) selection.value = [...selection.value, created]
      }
    }
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] picker upload failed:', err)
    adminToast.error(tr('Не удалось загрузить файл.'))
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <UidModal
    :model-value="open"
    :size="size"
    :title="title || tr('Выбор записи')"
    @update:model-value="(v: boolean) => emit('update:open', v)"
  >
    <div class="admin-picker-dialog" data-testid="resource-picker-dialog">
      <div class="admin-picker-dialog__toolbar">
        <AdminFilterToolbar
          class="admin-picker-dialog__filters"
          :search="search"
          :filters="toolbarFilters"
          :values="userFilters"
          :columns="[]"
          :enable-saved-views="false"
          @update:search="onSearch"
          @apply-filter="onFilter"
          @reset="onReset"
        />
        <template v-if="upload">
          <UidButton
            variant="secondary"
            size="sm"
            :loading="uploading"
            data-testid="resource-picker-upload"
            @click="chooseFile"
          >
            <template #prepend><UidIcon :icon="Upload" :size="14" /></template>
            {{ tr('Загрузить') }}
          </UidButton>
          <input
            ref="fileInput"
            type="file"
            class="admin-picker-dialog__file"
            :accept="upload.accept ?? undefined"
            @change="onFile"
          />
        </template>
      </div>

      <div v-if="loading && items.length === 0" class="admin-picker-dialog__grid">
        <UidSkeleton v-for="n in 6" :key="n" height="120px" />
      </div>
      <UidErrorState
        v-else-if="error"
        :title="tr('Не удалось загрузить записи')"
        :description="error.message"
      >
        <template #actions>
          <UidButton variant="secondary" size="sm" @click="load">{{ tr('Повторить') }}</UidButton>
        </template>
      </UidErrorState>
      <UidEmptyState
        v-else-if="items.length === 0"
        :title="tr('Ничего не найдено')"
      />
      <div
        v-else
        class="admin-picker-dialog__items"
        :class="`admin-picker-dialog__items--${effectiveLayout}`"
        :aria-busy="loading"
      >
        <UidCard
          v-for="item in items"
          :key="String(item.id)"
          clickable
          padding="sm"
          class="admin-picker-dialog__item"
          :class="{
            'admin-picker-dialog__item--selected': isSelected(item),
            'admin-picker-dialog__item--blocked': !isSelected(item) && limitReached,
          }"
          :aria-pressed="isSelected(item)"
          :data-testid="`resource-picker-item-${item.id}`"
          @click="toggle(item)"
          @dblclick="pickAndConfirm(item)"
        >
          <PickerItemView :item="item" :variant="effectiveLayout === 'grid' ? 'tile' : 'row'" />
          <span v-if="isSelected(item)" class="admin-picker-dialog__check">
            <UidIcon :icon="Check" :size="14" />
            <template v-if="multiple">{{ selection.findIndex((s) => String(s.id) === String(item.id)) + 1 }}</template>
          </span>
        </UidCard>
      </div>

      <UidPagination
        v-if="total > perPage"
        class="admin-picker-dialog__pagination"
        :model-value="page"
        :total="total"
        :per-page="perPage"
        @update:model-value="onPage"
      />
    </div>

    <template #footer>
      <div class="admin-picker-dialog__footer">
        <span class="admin-picker-dialog__count">
          <template v-if="multiple && maxItems !== null">
            {{ tRaw('Выбрано: :count из :max', { count: selection.length, max: maxItems }) }}
          </template>
          <template v-else-if="multiple">
            {{ tRaw('Выбрано: :count', { count: selection.length }) }}
          </template>
        </span>
        <UidButton variant="ghost" @click="close">{{ tr('Отмена') }}</UidButton>
        <UidButton
          variant="primary"
          data-testid="resource-picker-confirm"
          :disabled="!multiple && selection.length === 0"
          @click="confirm"
        >
          {{ tr('Выбрать') }}
        </UidButton>
      </div>
    </template>
  </UidModal>
</template>

<style>
.admin-picker-dialog {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md, 12px);
  min-height: 320px;
}
.admin-picker-dialog__toolbar {
  display: flex;
  flex-wrap: wrap-reverse;
  align-items: flex-start;
  justify-content: flex-end;
  gap: var(--uid-space-sm, 8px);
}
.admin-picker-dialog__filters {
  /* On a narrow screen the upload button wraps above the toolbar. */
  flex: 1 1 320px;
  min-width: 0;
}
.admin-picker-dialog__file {
  display: none;
}
.admin-picker-dialog__items--grid,
.admin-picker-dialog__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: var(--uid-space-sm, 8px);
}
.admin-picker-dialog__items--list {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-xs, 4px);
}
.admin-picker-dialog__items[aria-busy='true'] {
  opacity: 0.6;
}
.admin-picker-dialog__item {
  position: relative;
  outline-offset: 2px;
}
.admin-picker-dialog__item--selected {
  border-color: var(--uid-color-primary, #2dd4bf);
  box-shadow: 0 0 0 1px var(--uid-color-primary, #2dd4bf);
  background: var(--uid-color-primary-subtle, #f0fdfa);
}
.admin-picker-dialog__item--blocked {
  opacity: 0.5;
  cursor: not-allowed;
}
.admin-picker-dialog__check {
  position: absolute;
  top: 6px;
  right: 6px;
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 2px 6px;
  border-radius: var(--uid-radius-full, 999px);
  background: var(--uid-color-primary, #2dd4bf);
  color: var(--uid-color-text-on-primary, #fff);
  font-size: var(--uid-font-size-xs, 12px);
}
.admin-picker-dialog__pagination {
  align-self: center;
}
.admin-picker-dialog__footer {
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm, 8px);
  width: 100%;
}
.admin-picker-dialog__count {
  flex: 1;
  font-size: var(--uid-font-size-sm, 13px);
  color: var(--uid-color-text-secondary, #62686f);
}
</style>
