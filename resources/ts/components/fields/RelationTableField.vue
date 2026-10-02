<script setup lang="ts">
/**
 * RelationTableField — a read-only table of related records on an edit form
 * (the backend's Field\RelationTable, fieldType 'relation_table'). The data
 * comes from the field's value, where the record serializes the loaded
 * relation; the columns come in the resource format of TableColumn::toArray,
 * and the cells use the same presets as a resource list.
 */
import { computed } from 'vue'
import { UidBadge, UidCard, UidTable, type UidTableColumn } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { formatTableRows, rowBadgeTone, type CellMeta } from '../resource/cellFormat'
import { trSafe as tr } from '../../stores/i18n'

interface BackendColumn {
  name: string
  label: string
  preset?: string | null
  align?: 'left' | 'center' | 'right'
  width?: string | null
  meta?: CellMeta
}

interface Props {
  name: string
  label?: string | null
  help?: string | null
  columns?: BackendColumn[]
  emptyText?: string
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  columns: () => [],
  emptyText: tr('Связанных записей нет'),
})

const form = useFormState()

const rows = computed<Record<string, unknown>[]>(() => {
  const v = form.getField(props.name)
  if (!Array.isArray(v)) return []
  return formatTableRows(v as Record<string, unknown>[], props.columns) as Record<string, unknown>[]
})

// A badge column is drawn as a UidBadge, its tone from the raw value.
const badgeColumns = computed(() => props.columns.filter((c) => c.preset === 'badge'))

// The UidTable scoped slot passes {row}.
function slotRow(slotProps: unknown): Record<string, unknown> {
  return (slotProps as { row?: Record<string, unknown> } | undefined)?.row ?? {}
}

const uidColumns = computed<UidTableColumn[]>(() =>
  props.columns.map((c) => ({
    key: c.name,
    label: c.label,
    align: c.align,
    width: c.width ?? undefined,
  })),
)
</script>

<template>
  <div class="uid-form-field admin-relation-table">
    <label v-if="label" class="uid-form-field__label">{{ label }}</label>
    <UidCard padding="sm">
      <UidTable :columns="uidColumns" :data="rows" :empty-text="tr(emptyText)">
        <template v-for="col in badgeColumns" :key="col.name" #[col.name]="slotProps">
          <UidBadge v-if="slotRow(slotProps)[col.name] !== ''" :variant="rowBadgeTone(slotRow(slotProps), col)">{{ slotRow(slotProps)[col.name] }}</UidBadge>
        </template>
      </UidTable>
    </UidCard>
    <p v-if="help" class="uid-form-field__hint">{{ help }}</p>
  </div>
</template>
