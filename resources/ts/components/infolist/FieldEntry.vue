<script setup lang="ts">
/**
 * FieldEntry — the view of a form field on the view page (the backend's
 * Infolist\FieldEntry, which the default Resource::infolist() emits for the
 * fields that have a view of their own: markdown, code, rating, radio,
 * tree_select, cascader, date_range, morph_switcher, group).
 *
 * The node carries the whole serialized field; the entry registered under the
 * field's type draws it with the field's attributes — the options to look a
 * label up in, the tree, the language — and TextEntry stands in for a type
 * with no view of its own.
 */
import { computed, type Component } from 'vue'
import { getInfolistEntry } from './registry'
import TextEntry from './TextEntry.vue'

interface SerializedField {
  type: string
  name?: string
  label?: string | null
  options?: unknown
  attributes?: Record<string, unknown>
}

interface Props {
  name?: string
  label?: string
  value?: unknown
  field: SerializedField
}

// The renderer's own extras (preset, meta) must not reach the view through
// the root: the view gets exactly what viewProps builds.
defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
})

const view = computed<Component>(() =>
  props.field.type === 'field' ? TextEntry : (getInfolistEntry(props.field.type) ?? TextEntry),
)

const viewProps = computed<Record<string, unknown>>(() => {
  const { format, ...attrs } = props.field.attributes ?? {}
  const top = Array.isArray(props.field.options) && props.field.options.length > 0
    ? { options: props.field.options }
    : {}
  return {
    ...top,
    ...attrs,
    name: props.field.name ?? props.name,
    label: props.label || (props.field.label ?? ''),
    ...(props.value !== undefined ? { value: props.value } : {}),
    meta: format !== undefined ? { format } : {},
  }
})
</script>

<template>
  <component :is="view" v-bind="viewProps" />
</template>
