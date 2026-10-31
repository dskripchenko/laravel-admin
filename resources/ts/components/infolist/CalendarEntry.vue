<script setup lang="ts">
/**
 * CalendarEntry — a stored event calendar, read-only. See MarkupEntry.
 */
import MarkupEntry from './MarkupEntry.vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <MarkupEntry v-bind="$attrs" kind="calendar" />
</template>
