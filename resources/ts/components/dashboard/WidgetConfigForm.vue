<script setup lang="ts">
/**
 * WidgetConfigForm — the config form of a widget type drawn from its field
 * descriptors (WidgetTypeDescription.fields, see widgetConfig.ts): text,
 * textarea and code, number, select and switch. A field whose `visible()`
 * returns false for the current values is not shown.
 */
import { computed } from 'vue'
import { UidInput, UidSelect, UidSwitch, UidTextarea } from '@dskripchenko/ui'
import { trSafe as tr } from '../../stores/i18n'
import {
  fieldOptions,
  visibleFields,
  widgetText,
  type WidgetConfigContext,
  type WidgetConfigField,
  type WidgetConfigValues,
  type WidgetTypeDescription,
} from './widgetConfig'

interface Props {
  description: WidgetTypeDescription | null
  context: WidgetConfigContext
}
const props = defineProps<Props>()
const values = defineModel<WidgetConfigValues>({ required: true })

const fields = computed(() => visibleFields(props.description, values.value))

function set(key: string, value: unknown): void {
  values.value = { ...values.value, [key]: value }
}

function text(field: WidgetConfigField): string {
  const v = values.value[field.key]
  return v === null || v === undefined ? '' : String(v)
}

function options(field: WidgetConfigField): Array<{ value: string; label: string }> {
  if (field.kind !== 'select') return []
  return fieldOptions(field, props.context).map((o) => ({ value: o.value, label: widgetText(o.label) }))
}
</script>

<template>
  <div
    v-for="field in fields"
    :key="field.key"
    class="admin-dialog__field"
    :data-testid="`widget-config-${field.key}`"
  >
    <label class="admin-dialog__label">
      {{ widgetText(field.label) }}
      <span v-if="field.required" class="admin-dialog__required">*</span>
    </label>
    <UidTextarea
      v-if="field.kind === 'textarea' || field.kind === 'code'"
      :model-value="text(field)"
      :rows="field.rows ?? 6"
      :placeholder="widgetText(field.placeholder) || undefined"
      :class="{ 'admin-dialog__code': field.kind === 'code' }"
      resize="vertical"
      @update:model-value="(v: string) => set(field.key, v)"
    />
    <UidInput
      v-else-if="field.kind === 'number'"
      :model-value="text(field)"
      type="number"
      @update:model-value="(v: string) => set(field.key, v)"
    />
    <template v-else-if="field.kind === 'select'">
      <UidSelect
        :model-value="text(field) || null"
        :options="options(field)"
        :placeholder="widgetText(field.placeholder) || tr('Выберите…')"
        @update:model-value="(v: unknown) => set(field.key, v === null || v === undefined ? '' : String(v))"
      />
      <p v-if="options(field).length === 0" class="admin-dialog__note">{{ tr('Нет доступных вариантов.') }}</p>
    </template>
    <UidSwitch
      v-else-if="field.kind === 'switch'"
      :model-value="values[field.key] === true"
      @update:model-value="(v: boolean) => set(field.key, v)"
    />
    <UidInput
      v-else
      :model-value="text(field)"
      :placeholder="widgetText(field.placeholder) || undefined"
      @update:model-value="(v: string) => set(field.key, v)"
    />
    <p v-if="field.help" class="admin-dialog__note">{{ widgetText(field.help) }}</p>
  </div>
</template>

<style>
.admin-dialog__code textarea,
textarea.admin-dialog__code {
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
  font-size: 12px;
}
.admin-dialog__note {
  margin: 0;
  font-size: 12px;
  color: var(--uid-text-tertiary);
}
</style>
