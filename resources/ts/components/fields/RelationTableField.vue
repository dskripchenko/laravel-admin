<script setup lang="ts">
/**
 * RelationTableField — read-only таблица связанных записей на edit-форме
 * (backend Field\RelationTable, fieldType 'relation_table'). Данные берутся
 * из значения поля (record сериализует загруженную relation); колонки — в
 * resource-формате TableColumn::toArray, ячейки — те же presets, что и в
 * списках ресурсов.
 */
import { computed } from 'vue'
import { UidCard, UidTable, type UidTableColumn } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { formatCell, type CellMeta } from '../resource/cellFormat'

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
  emptyText: 'Связанных записей нет',
})

const form = useFormState()

const rows = computed<Record<string, unknown>[]>(() => {
  const v = form.getField(props.name)
  if (!Array.isArray(v)) return []
  return (v as Record<string, unknown>[]).map((row) => {
    const out: Record<string, unknown> = { ...row }
    for (const c of props.columns) {
      out[c.name] = formatCell(row[c.name], c.preset ?? undefined, c.meta ?? {})
    }
    return out
  })
})

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
      <UidTable :columns="uidColumns" :data="rows" :empty-text="emptyText" />
    </UidCard>
    <p v-if="help" class="uid-form-field__hint">{{ help }}</p>
  </div>
</template>
