<script setup lang="ts">
import { computed } from 'vue'
import { UidCard, UidTable, type UidTableColumn } from '@dskripchenko/ui'

/**
 * Backend RecentListWidget::data() отдаёт columns как `[{column, label}]`,
 * но UidTable ожидает `{key, label}`. Конвертируем here.
 */
interface BackendColumn {
  column?: string
  key?: string
  label: string
  align?: 'left' | 'center' | 'right'
}

interface Props {
  title?: string
  columns?: Array<BackendColumn | UidTableColumn>
  rows?: Record<string, unknown>[]
  emptyText?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  columns: () => [],
  rows: () => [],
  emptyText: 'Нет данных',
})

const normalizedColumns = computed<UidTableColumn[]>(() =>
  props.columns.map((c) => {
    const key = (c as BackendColumn).column ?? (c as UidTableColumn).key
    return {
      key: String(key ?? ''),
      label: c.label,
      align: c.align,
    } as UidTableColumn
  }),
)
</script>

<template>
  <UidCard padding="md" class="admin-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <UidTable :columns="normalizedColumns" :data="rows" :empty-text="emptyText" />
  </UidCard>
</template>
