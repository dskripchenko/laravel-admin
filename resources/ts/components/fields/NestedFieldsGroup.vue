<script setup lang="ts">
/**
 * The nested sub-form of the composite fields — Repeater and Builder.
 *
 * It gives the sub-fields a form state of THEIR OWN, since provide/inject
 * scopes to the subtree: the item's values live in a local reactive object and
 * are synced outwards through v-model, without mixing into the parent form's
 * state.
 */
import { reactive, watch } from 'vue'
import { provideFormState } from '../render/formState'
import FieldRenderer, { type FieldNode } from '../render/FieldRenderer.vue'

interface Props {
  fields: FieldNode[]
  modelValue: Record<string, unknown>
}

const props = defineProps<Props>()
const emit = defineEmits<{ 'update:modelValue': [value: Record<string, unknown>] }>()

const state = reactive<Record<string, unknown>>({ ...props.modelValue })
provideFormState(state)

watch(
  state,
  () => emit('update:modelValue', { ...state }),
  { deep: true },
)

// The parent may hand a different value to the same instance — an item moved
// or removed in a repeater, a record reloaded. The local state follows it, in
// place, so the sub-fields that injected it keep their reference.
watch(
  () => props.modelValue,
  (next) => {
    if (JSON.stringify(next ?? {}) === JSON.stringify(state)) return
    for (const k of Object.keys(state)) {
      if (!(k in (next ?? {}))) delete state[k]
    }
    Object.assign(state, next ?? {})
  },
)
</script>

<template>
  <div class="admin-nested-fields">
    <FieldRenderer v-for="f in fields" :key="f.name" :node="f" />
  </div>
</template>

<style scoped>
.admin-nested-fields {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md, 12px);
}
</style>
