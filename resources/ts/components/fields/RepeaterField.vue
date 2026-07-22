<script setup lang="ts">
/**
 * RepeaterField — list<Record> из повторяющихся групп под-полей (backend
 * Field\Repeater, fieldType 'repeater'). Каждый item редактируется во
 * вложенной под-форме (NestedFieldsGroup — собственный form-state).
 */
import { computed, ref, watch } from 'vue'
import { ChevronDown, ChevronUp, Plus, X } from 'lucide-vue-next'
import { UidButton, UidCard, UidIcon } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import type { FieldNode } from '../render/FieldRenderer.vue'
import NestedFieldsGroup from './NestedFieldsGroup.vue'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  fields?: FieldNode[]
  minItems?: number | null
  maxItems?: number | null
  addable?: boolean
  removable?: boolean
  reorderable?: boolean
  defaultItem?: Record<string, unknown>
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  fields: () => [],
  minItems: null,
  maxItems: null,
  addable: true,
  removable: true,
  reorderable: true,
  defaultItem: () => ({}),
})

const form = useFormState()

function fromState(): Record<string, unknown>[] {
  const v = form.getField(props.name)
  return Array.isArray(v) ? (v as Record<string, unknown>[]).map((it) => ({ ...it })) : []
}

const items = ref<Record<string, unknown>[]>(fromState())

watch(
  () => form.getField(props.name),
  (next) => {
    if (JSON.stringify(next ?? []) !== JSON.stringify(items.value)) {
      items.value = fromState()
    }
  },
)

function sync(): void {
  form.setField(props.name, items.value.map((it) => ({ ...it })))
}

function updateItem(idx: number, value: Record<string, unknown>): void {
  items.value[idx] = value
  sync()
}

const canAdd = computed(() =>
  props.addable && (props.maxItems === null || items.value.length < props.maxItems),
)

function addItem(): void {
  items.value.push({ ...props.defaultItem })
  sync()
}

const canRemove = computed(() =>
  props.removable && (props.minItems === null || items.value.length > props.minItems),
)

function removeItem(idx: number): void {
  items.value.splice(idx, 1)
  sync()
}

function move(idx: number, dir: -1 | 1): void {
  const target = idx + dir
  if (target < 0 || target >= items.value.length) return
  const copy = [...items.value]
  ;[copy[idx], copy[target]] = [copy[target], copy[idx]]
  items.value = copy
  sync()
}

const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])
</script>

<template>
  <div class="uid-form-field admin-repeater" :class="{ 'uid-form-field--error': !!errorMsg }">
    <label v-if="label" class="uid-form-field__label">
      {{ label }}<span v-if="required" class="uid-form-field__required" aria-hidden="true">*</span>
    </label>

    <UidCard
      v-for="(item, idx) in items"
      :key="idx"
      padding="md"
      class="admin-repeater__item"
    >
      <header class="admin-repeater__item-hd">
        <span class="admin-repeater__item-no">#{{ idx + 1 }}</span>
        <span class="admin-repeater__item-actions">
          <UidButton
            v-if="reorderable"
            variant="ghost" size="sm" :disabled="idx === 0"
            aria-label="Выше" @click="move(idx, -1)"
          ><UidIcon :icon="ChevronUp" :size="14" /></UidButton>
          <UidButton
            v-if="reorderable"
            variant="ghost" size="sm" :disabled="idx === items.length - 1"
            aria-label="Ниже" @click="move(idx, 1)"
          ><UidIcon :icon="ChevronDown" :size="14" /></UidButton>
          <UidButton
            v-if="canRemove"
            variant="ghost" size="sm" aria-label="Удалить элемент"
            @click="removeItem(idx)"
          ><UidIcon :icon="X" :size="14" /></UidButton>
        </span>
      </header>
      <NestedFieldsGroup
        :fields="fields"
        :model-value="item"
        @update:model-value="(v) => updateItem(idx, v)"
      />
    </UidCard>

    <UidButton v-if="canAdd" variant="secondary" size="sm" class="admin-repeater__add" @click="addItem">
      <template #prepend><UidIcon :icon="Plus" :size="14" /></template>
      Добавить элемент
    </UidButton>

    <p v-if="errorMsg" class="uid-form-field__hint uid-form-field__hint--error">{{ errorMsg }}</p>
    <p v-else-if="help" class="uid-form-field__hint">{{ help }}</p>
  </div>
</template>

<style scoped>
.admin-repeater {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm, 8px);
}
.admin-repeater__item-hd {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--uid-space-sm, 8px);
}
.admin-repeater__item-no {
  font-size: 12px;
  color: var(--uid-color-text-subtle, #6b7280);
}
.admin-repeater__add {
  align-self: flex-start;
}
</style>
