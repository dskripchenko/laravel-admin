<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { UidCard, UidTable, type UidTableColumn } from '@dskripchenko/ui'
import AdminTableCell from '../resource/AdminTableCell.vue'
import type { CellMeta } from '../resource/cellFormat'
import { trSafe as tr } from '../../stores/i18n'

/**
 * The backend's RecentListWidget::data() sends each column as
 * TableColumn::toArray() — {name, label, preset, meta, align} — plus the
 * older `column` key; UidTable expects `{key, label}`, so they are converted
 * here. The cells are drawn by the AdminTableCell a resource list uses, so
 * asMoney(), asDate(), asBadge() and asLink() look the same on a dashboard.
 * A column with no preset still gets its ISO dates formatted (formatCell).
 */
interface BackendColumn {
  column?: string
  name?: string
  key?: string
  label: string
  align?: 'left' | 'center' | 'right'
  width?: string | null
  preset?: string | null
  meta?: CellMeta | null
}

interface Props {
  title?: string
  columns?: Array<BackendColumn | UidTableColumn>
  rows?: Record<string, unknown>[]
  emptyText?: string
  /** The resource slug from RecentListWidget::linkTo(); a click on a row opens that record. */
  linkTo?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  columns: () => [],
  rows: () => [],
  emptyText: tr('Нет данных'),
  linkTo: null,
})

const router = useRouter()

function onRowClick(row: Record<string, unknown>): void {
  const id = row.id
  if (!props.linkTo || id === undefined || id === null) return
  void router?.push(`/r/${props.linkTo}/${id}`)
}

interface CellColumn {
  key: string
  preset: string | null
  meta: CellMeta | null
}

const cellColumns = computed<CellColumn[]>(() =>
  props.columns.map((c) => {
    const b = c as BackendColumn
    return {
      key: String(b.column ?? b.name ?? (c as UidTableColumn).key ?? ''),
      preset: b.preset ?? null,
      meta: b.meta ?? null,
    }
  }),
)

const normalizedColumns = computed<UidTableColumn[]>(() =>
  props.columns.map((c, i) => ({
    key: cellColumns.value[i]!.key,
    label: c.label,
    align: c.align,
    width: (c as BackendColumn).width ?? undefined,
  }) as UidTableColumn),
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
    <UidTable
      :columns="normalizedColumns"
      :data="rows"
      :empty-text="tr(emptyText)"
      :class="{ 'admin-widget__table--clickable': !!linkTo }"
      @row-click="onRowClick"
    >
      <template v-for="col in cellColumns" :key="col.key" #[col.key]="slotProps">
        <AdminTableCell
          :value="slotRow(slotProps)[col.key]"
          :preset="col.preset"
          :meta="col.meta"
          :row="slotRow(slotProps)"
        />
      </template>
    </UidTable>
  </UidCard>
</template>

<style scoped>
.admin-widget__table--clickable :deep(tbody tr) {
  cursor: pointer;
}
</style>
