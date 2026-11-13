<script setup lang="ts">
/**
 * The form of the selected block: the type's fields, drawn by the panel's own
 * FieldRenderer over a form state of the node's `data` — the very object in
 * the screen's state, so a change lands in the tree at once. Remounted per
 * node (the parent keys it by the node).
 *
 * A field the server describes in `meta.fields` is marked: inherited from the
 * base block, or overridden here — with "Return the base value", which puts
 * `meta.fields[name].base` back (the parent does it, through the editor).
 * A locked block (`meta.locked`) shows its form read-only.
 */
import { watch } from 'vue'
import { UidIcon } from '@dskripchenko/ui'
import { Lock } from 'lucide-vue-next'
import { provideFormState } from '../render/formState'
import FieldRenderer, { type FieldNode } from '../render/FieldRenderer.vue'
import { trSafe as tr } from '../../stores/i18n'
import { fieldInheritance, type BlockNode, type BlockTypeDef } from './tree'
import type { NodeErrors } from './useBlockTreeEditor'

const props = defineProps<{
  node: BlockNode
  def: BlockTypeDef | undefined
  errors: NodeErrors | null
  readOnly: boolean
  locked?: boolean
}>()

const emit = defineEmits<{ revert: [name: string] }>()

// A block without data (its row is missing on the server) has no form; a
// scratch object keeps the form state valid.
const data = props.node.data && typeof props.node.data === 'object' && !Array.isArray(props.node.data) ? props.node.data : null
const form = provideFormState(data ?? {}, { ...(props.errors?.fields ?? {}) })

watch(
  () => props.errors,
  (next) => form.setErrors({ ...(next?.fields ?? {}) }),
)

function fieldName(field: Record<string, unknown>): string | null {
  return typeof field.name === 'string' && field.name !== '' ? field.name : null
}

/** Inherited or overridden — read from the node each render, so an edit flips it at once. */
function inheritanceOf(field: Record<string, unknown>): 'inherited' | 'overridden' | null {
  const name = fieldName(field)
  return name === null ? null : fieldInheritance(props.node, name)
}

function revert(field: Record<string, unknown>): void {
  const name = fieldName(field)
  if (name === null) return
  emit('revert', name)
  form.setError(name, null)
}
</script>

<template>
  <div class="admin-block-tree-properties">
    <p v-if="locked" class="admin-block-tree-properties__locked">
      <UidIcon :icon="Lock" :size="14" />
      {{ tr('Блок закреплён: только просмотр') }}
    </p>
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
      <div
        v-for="(field, i) in def.fields"
        :key="String(field.name ?? i)"
        class="admin-block-tree-properties__field"
        :data-field="fieldName(field) ?? undefined"
        :data-inheritance="inheritanceOf(field) ?? undefined"
      >
        <FieldRenderer :node="(field as FieldNode)" />
        <div v-if="inheritanceOf(field)" class="admin-block-tree-properties__inheritance">
          <span v-if="inheritanceOf(field) === 'inherited'" class="admin-block-tree-mark is-inherited" :title="tr('Значение берётся из основы')">
            {{ tr('Унаследовано') }}
          </span>
          <template v-else>
            <span class="admin-block-tree-mark is-overridden" :title="tr('Значение задано в этом блоке')">{{ tr('Переопределено') }}</span>
            <button
              v-if="!readOnly"
              type="button"
              class="admin-block-tree-properties__revert"
              data-action="revert-field"
              @click="revert(field)"
            >
              {{ tr('Вернуть значение основы') }}
            </button>
          </template>
        </div>
      </div>
    </fieldset>
  </div>
</template>
