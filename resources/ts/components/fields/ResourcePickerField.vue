<script setup lang="ts">
/**
 * ResourcePickerField — the `resource_picker` field (the backend's
 * Field\ResourcePicker): records of another resource, picked in a dialog.
 *
 * The value in the form state is the record's key, or with `multiple` an
 * ordered list of keys. The field resolves the keys into records — title,
 * subtitle, preview — through the target's search endpoint and shows them
 * with buttons to reorder and remove.
 *
 * The target's view permission gates the dialog: without it the field shows
 * what it holds and cannot change it.
 */
import { computed, ref, watch } from 'vue'
import { ArrowDown, ArrowUp, Plus, Replace, X } from 'lucide-vue-next'
import { UidButton, UidFormField, UidIcon } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { useManifestStore } from '../../stores/manifest'
import { useAuthStore } from '../../stores/auth'
import { trSafe as tr, tRaw } from '../../stores/i18n'
import type { FilterDef } from '../resource/FilterEditor'
import PickerItemView from './resourcePicker/PickerItemView.vue'
import ResourcePickerDialog from './resourcePicker/ResourcePickerDialog.vue'
import {
  fetchPickerItems,
  keysOf,
  sameKey,
  type PickerItem,
  type PickerKey,
  type PickerUpload,
} from './resourcePicker/pickerApi'

interface Props {
  name: string
  resource: string
  label?: string | null
  help?: string | null
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  multiple?: boolean
  maxItems?: number | null
  filters?: Record<string, unknown> | unknown[]
  perPage?: number
  layout?: 'grid' | 'list' | null
  dialogSize?: 'lg' | 'xl' | 'full'
  upload?: PickerUpload | null
  viewPermission?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  disabled: false,
  readonly: false,
  multiple: false,
  maxItems: null,
  filters: () => ({}),
  perPage: 24,
  layout: null,
  dialogSize: 'xl',
  upload: null,
  viewPermission: null,
})

const form = useFormState()
const manifest = useManifestStore()
const auth = useAuthStore()

const keys = computed<PickerKey[]>(() => keysOf(form.getField(props.name)))
const errorMsg = computed<string | undefined>(() => {
  const own = form.errors[props.name]?.[0]
  if (own) return own
  // A multiple picker's per-item errors arrive as `name.0`, `name.1`, …
  const nested = Object.keys(form.errors).find((k) => k.startsWith(`${props.name}.`))
  return nested ? form.errors[nested]?.[0] : undefined
})

const target = computed(() => manifest.getResource(props.resource))
const canView = computed<boolean>(() => (props.viewPermission ? auth.hasPermission(props.viewPermission) : true))
const canUpload = computed<boolean>(() => {
  const permission = props.upload?.permission
  return props.upload !== null && (!permission || auth.hasPermission(permission))
})
const isLocked = computed<boolean>(() => props.disabled || props.readonly)

// A PHP `[]` arrives as a list; only a map means filter values.
const fixedFilters = computed<Record<string, unknown>>(() =>
  Array.isArray(props.filters) ? {} : props.filters,
)
const availableFilters = computed<FilterDef[]>(() => (target.value?.filters ?? []) as unknown as FilterDef[])

/** Resolved records by key; a key missing here is shown as `#key` until it loads. */
const known = ref<Map<string, PickerItem>>(new Map())
const missing = ref<Set<string>>(new Set())

const items = computed<PickerItem[]>(() =>
  keys.value.map((k) => known.value.get(String(k)) ?? { id: k, title: `#${k}`, subtitle: null, preview: null }),
)

function remember(list: PickerItem[]): void {
  const next = new Map(known.value)
  for (const item of list) next.set(String(item.id), item)
  known.value = next
}

async function resolve(): Promise<void> {
  if (!canView.value) return
  const unknown = keys.value.filter((k) => !known.value.has(String(k)) && !missing.value.has(String(k)))
  if (unknown.length === 0) return
  try {
    const found = await fetchPickerItems(props.resource, unknown)
    remember(found)
    const gone = new Set(missing.value)
    for (const k of unknown) {
      if (!found.some((f) => sameKey(f.id, k))) gone.add(String(k))
    }
    missing.value = gone
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] picker records failed to load:', err)
  }
}

watch(keys, () => void resolve(), { immediate: true })

function write(next: PickerKey[]): void {
  form.setField(props.name, props.multiple ? next : (next[0] ?? null))
}

function remove(index: number): void {
  write(keys.value.filter((_, i) => i !== index))
}

function move(index: number, delta: number): void {
  const to = index + delta
  if (to < 0 || to >= keys.value.length) return
  const next = [...keys.value]
  ;[next[index], next[to]] = [next[to], next[index]]
  write(next)
}

const dialogOpen = ref(false)

function onConfirm(picked: PickerItem[]): void {
  remember(picked)
  write(picked.map((i) => i.id))
}

const dialogTitle = computed<string>(() =>
  target.value?.label ? tRaw('Выбор: :resource', { resource: target.value.label }) : tr('Выбор записи'),
)
const pickLabel = computed<string>(() => {
  if (!props.multiple) return keys.value.length > 0 ? tr('Заменить') : tr('Выбрать')
  return tr('Выбрать')
})
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="isLocked"
  >
    <div class="admin-resource-picker" :data-testid="`resource-picker-${name}`">
      <ul v-if="items.length > 0" class="admin-resource-picker__list">
        <li
          v-for="(item, index) in items"
          :key="String(item.id)"
          class="admin-resource-picker__item"
          :class="{ 'admin-resource-picker__item--missing': missing.has(String(item.id)) }"
        >
          <PickerItemView :item="item" />
          <span v-if="!isLocked" class="admin-resource-picker__actions">
            <template v-if="multiple && items.length > 1">
              <UidButton
                variant="ghost"
                size="sm"
                :disabled="index === 0"
                :aria-label="tr('Выше')"
                @click="move(index, -1)"
              >
                <UidIcon :icon="ArrowUp" :size="14" />
              </UidButton>
              <UidButton
                variant="ghost"
                size="sm"
                :disabled="index === items.length - 1"
                :aria-label="tr('Ниже')"
                @click="move(index, 1)"
              >
                <UidIcon :icon="ArrowDown" :size="14" />
              </UidButton>
            </template>
            <UidButton
              variant="ghost"
              size="sm"
              :aria-label="tr('Убрать')"
              :data-testid="`resource-picker-remove-${item.id}`"
              @click="remove(index)"
            >
              <UidIcon :icon="X" :size="14" />
            </UidButton>
          </span>
        </li>
      </ul>
      <p v-else class="admin-resource-picker__empty">{{ tr('Ничего не выбрано') }}</p>

      <div v-if="!isLocked" class="admin-resource-picker__bar">
        <UidButton
          variant="secondary"
          size="sm"
          :disabled="!canView"
          data-testid="resource-picker-open"
          @click="dialogOpen = true"
        >
          <template #prepend><UidIcon :icon="!multiple && items.length > 0 ? Replace : Plus" :size="14" /></template>
          {{ pickLabel }}
        </UidButton>
        <span v-if="!canView" class="admin-resource-picker__note">
          {{ tr('Нет доступа к списку записей') }}
        </span>
      </div>
    </div>

    <ResourcePickerDialog
      v-if="canView && !isLocked"
      v-model:open="dialogOpen"
      :resource="resource"
      :title="dialogTitle"
      :multiple="multiple"
      :max-items="maxItems"
      :filters="fixedFilters"
      :available-filters="availableFilters"
      :per-page="perPage"
      :layout="layout"
      :size="dialogSize"
      :selected="items"
      :upload="canUpload ? upload : null"
      @confirm="onConfirm"
    />
  </UidFormField>
</template>

<style>
.admin-resource-picker {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm, 8px);
}
.admin-resource-picker__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-xs, 4px);
}
.admin-resource-picker__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--uid-space-sm, 8px);
  padding: var(--uid-space-xs, 4px) var(--uid-space-sm, 8px);
  border: 1px solid var(--uid-color-border, #e5e7eb);
  border-radius: var(--uid-radius-md, 6px);
}
.admin-resource-picker__item--missing {
  opacity: 0.6;
}
.admin-resource-picker__actions {
  display: inline-flex;
  flex: none;
}
.admin-resource-picker__empty,
.admin-resource-picker__note {
  margin: 0;
  font-size: var(--uid-font-size-sm, 13px);
  color: var(--uid-color-text-secondary, #62686f);
}
.admin-resource-picker__bar {
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm, 8px);
}
</style>
