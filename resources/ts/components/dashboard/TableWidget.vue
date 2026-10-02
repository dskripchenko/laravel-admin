<script setup lang="ts">
import { computed } from 'vue'
import { UidCard, UidTable, type UidTableColumn } from '@dskripchenko/ui'
import AdminTableCell from '../resource/AdminTableCell.vue'
import type { CellMeta } from '../resource/cellFormat'
import { trSafe as tr } from '../../stores/i18n'

/**
 * Backend TableWidget::data() — {rows, columns[TableColumn::toArray]}:
 * the columns come in the resource format ({name, label, preset, meta…}), so
 * the cells are drawn by the same AdminTableCell as a resource list uses —
 * dates, money, booleans, badges, links and images.
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
  emptyText: tr('Нет данных'),
})

const uidColumns = computed<UidTableColumn[]>(() =>
  props.columns.map((c) => ({
    key: c.name,
    label: c.label,
    align: c.align,
    width: c.width ?? undefined,
  })),
)


// The UidTable scoped slot passes {row}.
function slotRow(slotProps: unknown): Record<string, unknown> {
  return (slotProps as { row?: Record<string, unknown> } | undefined)?.row ?? {}
}
</script>

<template>
  <UidCard padding="md" class="admin-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <UidTable :columns="uidColumns" :data="rows" :empty-text="tr(emptyText)">
      <template v-for="col in columns" :key="col.name" #[col.name]="slotProps">
        <AdminTableCell
          :value="slotRow(slotProps)[col.name]"
          :preset="col.preset"
          :meta="col.meta"
          :row="slotRow(slotProps)"
        />
      </template>
    </UidTable>
  </UidCard>
</template>
