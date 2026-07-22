<script setup lang="ts">
/**
 * Вложенная под-форма для составных полей (Repeater / Builder).
 *
 * Даёт под-полям СОБСТВЕННЫЙ form-state (provide/inject скоупится на
 * поддерево): значения item'а живут в локальном reactive-объекте и
 * синхронизируются наружу через v-model, не смешиваясь с состоянием
 * родительской формы.
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
