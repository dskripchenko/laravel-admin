<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { UidCard, UidTable, type UidTableColumn } from '@dskripchenko/ui'
import { formatCell } from '../resource/cellFormat'
import { trSafe as tr } from '../../stores/i18n'

/**
 * The backend's RecentListWidget::data() returns the columns as
 * `[{column, label}]` while UidTable expects `{key, label}`, so they are
 * converted here.
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

/**
 * RecentListWidget sends no column presets, but formatCell recognizes an ISO
 * date by itself and renders it as `d.m.Y H:i:s`. Without this pass the widget
 * showed a raw `2026-08-05T03:03:44.000000Z`, while the resource's own list
 * draws the same date as `05.08.2026 03:03:44`.
 */
const formattedRows = computed<Record<string, unknown>[]>(() =>
  props.rows.map((row) => {
    const out: Record<string, unknown> = { ...row }
    for (const c of normalizedColumns.value) {
      out[c.key] = formatCell(row[c.key], undefined, {})
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
    <UidTable
      :columns="normalizedColumns"
      :data="formattedRows"
      :empty-text="tr(emptyText)"
      :class="{ 'admin-widget__table--clickable': !!linkTo }"
      @row-click="onRowClick"
    />
  </UidCard>
</template>

<style scoped>
.admin-widget__table--clickable :deep(tbody tr) {
  cursor: pointer;
}
</style>
