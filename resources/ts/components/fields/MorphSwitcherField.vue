<script setup lang="ts">
/**
 * MorphSwitcherField — the backend's Field\MorphSwitcher: the selector of a
 * morphTo relation, two linked selects.
 *
 *   1. The type, out of the declared `morphTypes` (alias → model).
 *   2. The record, out of that type's `options` — the backend loads them
 *      when it serializes the field, as RelationSelect does.
 *
 * The state is `{type: 'post', id: 42}`; changing the type clears the id.
 */
import { computed } from 'vue'
import { UidFormField, UidSelect, type SelectValue } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { trSafe as tr } from '../../stores/i18n'
import type { SelectOption } from './SelectField.vue'

interface MorphType {
  label?: string
  options?: SelectOption[]
}

interface Props {
  name: string
  morphTypes?: Record<string, MorphType> | null
  label?: string | null
  help?: string | null
  required?: boolean
  disabled?: boolean
  readonly?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  morphTypes: null,
  label: null,
  help: null,
  required: false,
  disabled: false,
  readonly: false,
})

const form = useFormState()
const isLocked = computed<boolean>(() => props.disabled || props.readonly)

const current = computed<{ type: string | null; id: string | number | null }>(() => {
  const v = form.getField(props.name)
  if (!v || typeof v !== 'object') return { type: null, id: null }
  const r = v as Record<string, unknown>
  const type = typeof r.type === 'string' && r.type !== '' ? r.type : null
  const id = typeof r.id === 'string' || typeof r.id === 'number' ? r.id : null
  return { type, id }
})

const typeOptions = computed<SelectOption[]>(() =>
  Object.entries(props.morphTypes ?? {}).map(([alias, def]) => ({
    value: alias,
    label: def?.label ?? alias,
  })),
)

const recordOptions = computed<SelectOption[]>(() => {
  const t = current.value.type
  return t === null ? [] : (props.morphTypes?.[t]?.options ?? [])
})

/** The id re-typed by its option, so '5' from the state matches the option 5. */
const idValue = computed<string | number | null>(() => {
  const id = current.value.id
  if (id === null) return null
  return recordOptions.value.find((o) => String(o.value) === String(id))?.value ?? id
})

const errorMsg = computed<string | undefined>(
  () =>
    form.errors[props.name]?.[0] ??
    form.errors[`${props.name}.type`]?.[0] ??
    form.errors[`${props.name}.id`]?.[0],
)

function onType(value: SelectValue | null): void {
  if (isLocked.value) return
  const type = value === null || value === '' ? null : String(value)
  form.setField(props.name, type === null ? null : { type, id: null })
}

function onId(value: SelectValue | null): void {
  if (isLocked.value || current.value.type === null) return
  form.setField(props.name, { type: current.value.type, id: value })
}
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="disabled"
  >
    <div class="admin-morph-field">
      <UidSelect
        class="admin-morph-field__type"
        :model-value="current.type"
        :options="typeOptions"
        :placeholder="tr('Тип')"
        :disabled="isLocked"
        :clearable="!required"
        @update:model-value="onType"
      />
      <UidSelect
        class="admin-morph-field__id"
        :model-value="idValue"
        :options="recordOptions"
        :placeholder="tr('Запись')"
        :disabled="isLocked || current.type === null"
        searchable
        @update:model-value="onId"
      />
    </div>
  </UidFormField>
</template>

<style>
.admin-morph-field {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
  gap: var(--uid-space-sm, 8px);
}
@media (max-width: 560px) {
  .admin-morph-field { grid-template-columns: minmax(0, 1fr); }
}
</style>
