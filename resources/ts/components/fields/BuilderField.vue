<script setup lang="ts">
/**
 * BuilderField — a page builder: a list of typed blocks (the backend's
 * Field\Builder, fieldType 'builder'). The state is a list<{type, data}>, and
 * the block types with their fields arrive in attributes.blocks. Each block is
 * edited in a nested sub-form.
 */
import { computed, ref, watch } from 'vue'
import { ChevronDown, ChevronUp, Plus, X } from 'lucide-vue-next'
import { UidButton, UidCard, UidIcon, UidMenu, UidMenuItem } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import type { FieldNode } from '../render/FieldRenderer.vue'
import NestedFieldsGroup from './NestedFieldsGroup.vue'
import { trSafe as tr, tRaw } from '../../stores/i18n'

interface BlockDef {
  type: string
  label: string
  icon?: string | null
  fields: FieldNode[]
}

interface BlockItem {
  type: string
  data: Record<string, unknown>
}

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  /** name => the block's definition; see Builder::block. */
  blocks?: Record<string, BlockDef>
  maxBlocks?: number | null
  reorderable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  blocks: () => ({}),
  maxBlocks: null,
  reorderable: true,
})

const form = useFormState()

function fromState(): BlockItem[] {
  const v = form.getField(props.name)
  if (!Array.isArray(v)) return []
  return (v as BlockItem[]).map((b) => ({ type: b.type, data: { ...(b.data ?? {}) } }))
}

const blocksState = ref<BlockItem[]>(fromState())

watch(
  () => form.getField(props.name),
  (next) => {
    if (JSON.stringify(next ?? []) !== JSON.stringify(blocksState.value)) {
      blocksState.value = fromState()
    }
  },
)

function sync(): void {
  form.setField(props.name, blocksState.value.map((b) => ({ type: b.type, data: { ...b.data } })))
}

const blockTypes = computed<BlockDef[]>(() => Object.values(props.blocks))

const canAdd = computed(
  () => props.maxBlocks === null || blocksState.value.length < props.maxBlocks,
)

function addBlock(type: string): void {
  blocksState.value.push({ type, data: {} })
  sync()
}

function removeBlock(idx: number): void {
  blocksState.value.splice(idx, 1)
  sync()
}

function move(idx: number, dir: -1 | 1): void {
  const target = idx + dir
  if (target < 0 || target >= blocksState.value.length) return
  const copy = [...blocksState.value]
  ;[copy[idx], copy[target]] = [copy[target], copy[idx]]
  blocksState.value = copy
  sync()
}

function updateBlock(idx: number, value: Record<string, unknown>): void {
  blocksState.value[idx] = { ...blocksState.value[idx], data: value }
  sync()
}

function defFor(type: string): BlockDef | null {
  return props.blocks[type] ?? null
}

const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])
</script>

<template>
  <div class="uid-form-field admin-builder" :class="{ 'uid-form-field--error': !!errorMsg }">
    <label v-if="label" class="uid-form-field__label">
      {{ label }}<span v-if="required" class="uid-form-field__required" aria-hidden="true">*</span>
    </label>

    <UidCard
      v-for="(block, idx) in blocksState"
      :key="idx"
      padding="md"
      class="admin-builder__block"
    >
      <header class="admin-builder__block-hd">
        <span class="admin-builder__block-type">{{ defFor(block.type)?.label ?? block.type }}</span>
        <span class="admin-builder__block-actions">
          <UidButton
            v-if="reorderable"
            variant="ghost" size="sm" :disabled="idx === 0"
            :aria-label="tr('Выше')" @click="move(idx, -1)"
          ><UidIcon :icon="ChevronUp" :size="14" /></UidButton>
          <UidButton
            v-if="reorderable"
            variant="ghost" size="sm" :disabled="idx === blocksState.length - 1"
            :aria-label="tr('Ниже')" @click="move(idx, 1)"
          ><UidIcon :icon="ChevronDown" :size="14" /></UidButton>
          <UidButton
            variant="ghost" size="sm" :aria-label="tr('Удалить блок')"
            @click="removeBlock(idx)"
          ><UidIcon :icon="X" :size="14" /></UidButton>
        </span>
      </header>
      <NestedFieldsGroup
        v-if="defFor(block.type)"
        :fields="defFor(block.type)!.fields"
        :model-value="block.data"
        @update:model-value="(v) => updateBlock(idx, v)"
      />
      <p v-else class="uid-form-field__hint">{{ tRaw('Неизвестный тип блока: :type', { type: block.type }) }}</p>
    </UidCard>

    <UidMenu v-if="canAdd && blockTypes.length > 0">
      <template #trigger>
        <UidButton variant="secondary" size="sm" class="admin-builder__add">
          <template #prepend><UidIcon :icon="Plus" :size="14" /></template>
          {{ tr('Добавить блок') }}
        </UidButton>
      </template>
      <UidMenuItem v-for="bt in blockTypes" :key="bt.type" @click="addBlock(bt.type)">
        {{ bt.label }}
      </UidMenuItem>
    </UidMenu>

    <p v-if="errorMsg" class="uid-form-field__hint uid-form-field__hint--error">{{ errorMsg }}</p>
    <p v-else-if="help" class="uid-form-field__hint">{{ help }}</p>
  </div>
</template>

<style scoped>
.admin-builder {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm, 8px);
}
.admin-builder__block-hd {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--uid-space-sm, 8px);
}
.admin-builder__block-type {
  font-weight: 600;
  font-size: 13px;
}
.admin-builder__add {
  align-self: flex-start;
}
</style>
