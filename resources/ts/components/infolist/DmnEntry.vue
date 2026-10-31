<script setup lang="ts">
/**
 * DmnEntry — a stored DMN model, read-only: the backend's Infolist\DmnEntry
 * and the view of a Field\Dmn. BpmnEntry with `notation` set to 'dmn'.
 */
import BpmnEntry from './BpmnEntry.vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <BpmnEntry v-bind="$attrs" notation="dmn" />
</template>
