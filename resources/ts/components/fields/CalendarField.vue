<script setup lang="ts">
/**
 * CalendarField — the backend's Field\Calendar: MarkupField with the kit's
 * UidEventCalendar. See MarkupField.
 */
import { useAttrs } from 'vue'
import MarkupField from './MarkupField.vue'

defineOptions({ inheritAttrs: false })

// The field's props, `name` among them, pass through as they are.
const attrs = useAttrs() as { name: string } & Record<string, unknown>
</script>

<template>
  <MarkupField v-bind="attrs" kind="calendar" />
</template>
