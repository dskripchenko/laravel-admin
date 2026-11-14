<script setup lang="ts">
/**
 * The list widget — the backend's ListWidget (1.75), whose data() returns
 * `items: [{label, secondary?, badge?, tone?, url?, key?}]` and `emptyText`:
 * rows without a table header, each a link when it has a url.
 */
import { UidCard } from '@dskripchenko/ui'
import WidgetHeader from './WidgetHeader.vue'
import ListRows, { type ListRowItem } from './ListRows.vue'

withDefaults(defineProps<{ title?: string; items?: ListRowItem[]; emptyText?: string | null }>(), {
  title: '',
  items: () => [],
  emptyText: null,
})
</script>

<template>
  <UidCard padding="md" class="admin-widget admin-list-widget" data-testid="widget-list">
    <WidgetHeader :title="title" />
    <div class="admin-widget__body">
      <ListRows :items="items" :empty-text="emptyText" />
    </div>
  </UidCard>
</template>

<style>
.admin-list-widget .admin-widget__body {
  overflow: auto;
}
</style>
