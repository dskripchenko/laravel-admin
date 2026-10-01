<script setup lang="ts">
/**
 * The body of a ModalAction's dialog: the action's fields drawn through the
 * same LayoutRenderer/FieldRenderer machinery as a resource form, over a form
 * state of their own.
 *
 * The dialog remounts this component on every open (keyed by the runner's
 * `seq`), so each run starts from a fresh state.
 */
import { watch } from 'vue'
import { provideFormState } from '../render/formState'
import LayoutRenderer, { type LayoutNode } from '../render/LayoutRenderer.vue'

interface Props {
  fields: Array<Record<string, unknown>>
  /** The reactive values object; the fields write into it. */
  values: Record<string, unknown>
  errors: Record<string, string[]>
}

const props = defineProps<Props>()

const ctx = provideFormState(props.values, props.errors)

watch(
  () => props.errors,
  (next) => ctx.setErrors({ ...next }),
  { deep: true },
)
</script>

<template>
  <div class="admin-action-form" data-testid="action-form">
    <LayoutRenderer
      v-for="(node, idx) in fields"
      :key="String(node.name ?? idx)"
      :node="(node as unknown as LayoutNode)"
    />
  </div>
</template>

<style>
.admin-action-form {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md);
}
</style>
