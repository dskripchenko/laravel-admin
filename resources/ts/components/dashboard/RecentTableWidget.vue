<script setup lang="ts">
import { UidCard, UidTable, type UidTableColumn } from '@dskripchenko/ui'

interface Props {
  title?: string
  columns: UidTableColumn[]
  rows: Record<string, unknown>[]
  emptyText?: string
}

withDefaults(defineProps<Props>(), {
  title: '',
  emptyText: 'Нет данных',
})
</script>

<template>
  <UidCard padding="md" class="admin-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <UidTable :columns="columns" :data="rows" :empty-text="emptyText" />
  </UidCard>
</template>
