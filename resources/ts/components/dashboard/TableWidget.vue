<script setup lang="ts">
import { computed } from 'vue'
import { UidCard, UidTable, type UidTableColumn } from '@dskripchenko/ui'
import { formatCell, type CellMeta } from '../resource/cellFormat'

/**
 * Backend TableWidget::data() — {rows, columns[TableColumn::toArray]}:
 * колонки в resource-формате ({name, label, preset, meta…}) — ячейки
 * форматируем тем же formatCell, что и Resource list (даты/деньги/boolean).
 */
interface BackendColumn {
  name: string
  label: string
  preset?: string | null
  align?: 'left' | 'center' | 'right'
  width?: string | null
  meta?: CellMeta
}

interface Props {
  title?: string
  columns?: BackendColumn[]
  rows?: Record<string, unknown>[]
  emptyText?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  columns: () => [],
  rows: () => [],
  emptyText: 'Нет данных',
})

const uidColumns = computed<UidTableColumn[]>(() =>
  props.columns.map((c) => ({
    key: c.name,
    label: c.label,
    align: c.align,
    width: c.width ?? undefined,
  })),
)

const formattedRows = computed<Record<string, unknown>[]>(() =>
  props.rows.map((row) => {
    const out: Record<string, unknown> = { ...row }
    for (const c of props.columns) {
      out[c.name] = formatCell(row[c.name], c.preset ?? undefined, c.meta ?? {})
    }
    return out
  }),
)
</script>

<template>
  <UidCard padding="md" class="admin-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <UidTable :columns="uidColumns" :data="formattedRows" :empty-text="emptyText" />
  </UidCard>
</template>
