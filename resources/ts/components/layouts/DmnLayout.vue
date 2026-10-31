<script setup lang="ts">
/**
 * The DMN layout — `Layout::dmn(...)`: BpmnLayout showing a decision model
 * with DmnView. See BpmnLayout.
 */
import BpmnLayout from './BpmnLayout.vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <BpmnLayout v-bind="$attrs" notation="dmn" />
</template>
