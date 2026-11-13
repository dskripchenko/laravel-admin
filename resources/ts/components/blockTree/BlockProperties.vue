<script setup lang="ts">
/**
 * The form of the selected block: the type's fields, drawn by the panel's own
 * FieldRenderer over a form state of the node's `data` — the very object in
 * the screen's state, so a change lands in the tree at once. Remounted per
 * node (the parent keys it by ref).
 */
import { watch } from 'vue'
import { provideFormState } from '../render/formState'
import FieldRenderer, { type FieldNode } from '../render/FieldRenderer.vue'
import { trSafe as tr } from '../../stores/i18n'
import type { BlockNode, BlockTypeDef } from './tree'
import type { NodeErrors } from './useBlockTreeEditor'

const props = defineProps<{
  node: BlockNode
  def: BlockTypeDef | undefined
  errors: NodeErrors | null
  readOnly: boolean
}>()

// A block without data (its row is missing on the server) has no form; a
// scratch object keeps the form state valid.
const data = props.node.data && typeof props.node.data === 'object' && !Array.isArray(props.node.data) ? props.node.data : null
const form = provideFormState(data ?? {}, { ...(props.errors?.fields ?? {}) })

watch(
  () => props.errors,
  (next) => form.setErrors({ ...(next?.fields ?? {}) }),
)
</script>

<template>
  <div class="admin-block-tree-properties">
    <ul v-if="errors && errors.messages.length > 0" class="admin-block-tree-properties__errors" role="alert">
      <li v-for="(m, i) in errors.messages" :key="i">{{ m }}</li>
    </ul>
    <p v-if="!def" class="admin-block-tree-properties__empty">
      {{ tr('Тип блока не описан — свойства недоступны') }}
    </p>
    <p v-else-if="!data" class="admin-block-tree-properties__empty">
      {{ tr('У блока нет данных') }}
    </p>
    <p v-else-if="def.fields.length === 0" class="admin-block-tree-properties__empty">
      {{ tr('У этого блока нет свойств') }}
    </p>
    <fieldset v-else class="admin-block-tree-properties__fields" :disabled="readOnly">
      <FieldRenderer
        v-for="(field, i) in def.fields"
        :key="String(field.name ?? i)"
        :node="(field as FieldNode)"
      />
    </fieldset>
  </div>
</template>
