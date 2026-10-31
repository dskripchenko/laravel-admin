<script setup lang="ts">
/**
 * KanbanEntry — a stored kanban board, read-only. See MarkupEntry.
 */
import MarkupEntry from './MarkupEntry.vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <MarkupEntry v-bind="$attrs" kind="kanban" />
</template>
