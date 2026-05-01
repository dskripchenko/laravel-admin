<script setup lang="ts">
/**
 * FieldRenderer резолвит конкретный field-компонент по `node.type` из реестра
 * и forwards остальные props через v-bind.
 *
 * Узлы манифеста имеют форму:
 *   { type: 'text', name: 'title', label: 'Заголовок', required: true, ... }
 *
 * Field хранит value через provide/inject form-state — узлу не передаётся
 * `modelValue` напрямую. Это позволяет строить произвольно-глубокие layout'ы
 * без явного proppin'га state'а.
 */
import { computed } from 'vue'
import { getField } from './registry'
import UnknownField from '../fields/UnknownField.vue'

export interface FieldNode extends Record<string, unknown> {
  type: string
  name: string
}

interface Props {
  node: FieldNode
}

const props = defineProps<Props>()
const component = computed(() => getField(props.node.type))
const fieldProps = computed(() => {
  const { type: _type, ...rest } = props.node
  return rest
})
</script>

<template>
  <component :is="component" v-if="component" v-bind="fieldProps" />
  <UnknownField v-else :type="node.type" :name="node.name" />
</template>
