<script setup lang="ts">
import { computed } from 'vue'
import { UidBadge, UidCard, UidTable, type UidTableColumn } from '@dskripchenko/ui'
import { formatTableRows, rowBadgeTone, type CellMeta } from '../resource/cellFormat'
import { trSafe as tr } from '../../stores/i18n'

/**
 * Backend TableWidget::data() — {rows, columns[TableColumn::toArray]}:
 * the columns come in the resource format ({name, label, preset, meta…}), so
 * the cells are formatted by the same formatCell as a resource list uses —
 * dates, money, booleans.
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

const formattedRows = computed<Record<string, unknown>[]>(
  () => formatTableRows(props.rows, props.columns) as Record<string, unknown>[],
)

// A badge column is drawn as a UidBadge, its tone from the raw value.
const badgeColumns = computed(() => props.columns.filter((c) => c.preset === 'badge'))

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
    <UidTable :columns="uidColumns" :data="formattedRows" :empty-text="tr(emptyText)">
      <template v-for="col in badgeColumns" :key="col.name" #[col.name]="slotProps">
        <UidBadge v-if="slotRow(slotProps)[col.name] !== ''" :variant="rowBadgeTone(slotRow(slotProps), col)">{{ slotRow(slotProps)[col.name] }}</UidBadge>
      </template>
    </UidTable>
  </UidCard>
</template>
