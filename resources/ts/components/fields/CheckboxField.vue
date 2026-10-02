<script setup lang="ts">
/**
 * CheckboxField — the backend's Field\Checkbox.
 *
 * Without options it is one boolean checkbox, its caption inline. With
 * `->options([...])` it is a group of checkboxes, one per option, and the
 * value is the list of the checked options' values; `->inline()` lays the
 * group out in a row. The kit has no checkbox group of its own, so the group
 * is a UidFormField holding a UidCheckbox per option.
 */
import { computed } from 'vue'
import { UidCheckbox, UidFormField } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import type { SelectOption } from './SelectField.vue'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  inlineLabel?: string | null
  disabled?: boolean
  readonly?: boolean
  options?: SelectOption[] | null
  inline?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  inlineLabel: null,
  required: false,
  disabled: false,
  readonly: false,
  options: null,
  inline: false,
})

const form = useFormState()
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])
const isGroup = computed<boolean>(() => Array.isArray(props.options) && props.options.length > 0)
const locked = computed<boolean>(() => props.disabled || props.readonly)

const checked = computed<boolean>(() => Boolean(form.getField(props.name)))

function onUpdate(next: boolean): void {
  if (props.readonly) return
  form.setField(props.name, next)
}

/** The checked values of a group; a JSON string a cast left behind is read too. */
const selected = computed<string[]>(() => {
  let v: unknown = form.getField(props.name)
  if (typeof v === 'string' && v.trim().startsWith('[')) {
    try { v = JSON.parse(v) } catch { v = [] }
  }
  const list = Array.isArray(v) ? v : v === null || v === undefined || v === '' ? [] : [v]
  return list.map((x) => String(x))
})

function isChecked(option: SelectOption): boolean {
  return selected.value.includes(String(option.value))
}

/** Toggles one option, keeping the options' own order and value types. */
function onToggle(option: SelectOption, next: boolean): void {
  if (props.readonly) return
  const keep = new Set(selected.value)
  if (next) keep.add(String(option.value))
  else keep.delete(String(option.value))
  form.setField(
    props.name,
    (props.options ?? []).filter((o) => keep.has(String(o.value))).map((o) => o.value),
  )
}
</script>

<template>
  <UidFormField
    v-if="isGroup"
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="locked"
  >
    <div
      class="admin-checkbox-group"
      :class="{ 'admin-checkbox-group--inline': inline }"
      role="group"
      :aria-label="label ?? name"
    >
      <UidCheckbox
        v-for="o in options ?? []"
        :key="String(o.value)"
        :model-value="isChecked(o)"
        :label="o.label"
        :disabled="locked || o.disabled"
        :name="`${name}[]`"
        @update:model-value="(next: boolean) => onToggle(o, next)"
      />
    </div>
  </UidFormField>
  <!-- A single checkbox: its caption sits inline next to the box, not above
       it as a label, which would hang on a line of its own over an empty box.
       The hint and the error stay under the row. -->
  <UidFormField
    v-else
    :hint="help ?? undefined"
    :error="errorMsg"
    :disabled="locked"
  >
    <UidCheckbox
      :model-value="checked"
      :disabled="locked"
      :required="required"
      :name="name"
      :label="inlineLabel ?? label ?? undefined"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

<style>
.admin-checkbox-group {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-xs);
}
.admin-checkbox-group--inline {
  flex-direction: row;
  flex-wrap: wrap;
  gap: var(--uid-space-xs) var(--uid-space-md);
}
</style>
