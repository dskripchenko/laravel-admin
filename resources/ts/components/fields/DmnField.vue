<script setup lang="ts">
/**
 * DmnField — the backend's Field\Dmn: BpmnField editing a decision model
 * with DmnEditor. See BpmnField.
 */
import { useAttrs } from 'vue'
import BpmnField from './BpmnField.vue'

defineOptions({ inheritAttrs: false })

// The field's props, `name` among them, pass through as they are.
const attrs = useAttrs() as { name: string } & Record<string, unknown>
</script>

<template>
  <BpmnField v-bind="attrs" notation="dmn" />
</template>
