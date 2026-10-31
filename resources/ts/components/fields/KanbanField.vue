<script setup lang="ts">
/**
 * KanbanField — the backend's Field\Kanban: MarkupField with the kit's
 * UidKanban. See MarkupField.
 */
import { useAttrs } from 'vue'
import MarkupField from './MarkupField.vue'

defineOptions({ inheritAttrs: false })

// The field's props, `name` among them, pass through as they are.
const attrs = useAttrs() as { name: string } & Record<string, unknown>
</script>

<template>
  <MarkupField v-bind="attrs" kind="kanban" />
</template>
