<script setup lang="ts">
/**
 * FieldRenderer resolves the field component by `node.type` through the
 * registry and forwards the rest of the props with v-bind.
 *
 * The manifest's nodes look like:
 *   { type: 'text', name: 'title', label: 'Title', required: true, ... }
 *
 * A field keeps its value in the provide/inject form state, so no `modelValue`
 * is passed to the node directly. That is what lets the layouts nest as deeply
 * as they like without threading the state through props.
 *
 * Conditional visibility: with `node.reactive = {fieldName: expected}` the
 * field stays hidden until another field of the form matches `expected` — or
 * one of the values in a list. The comparison is `===`.
 */
import { computed } from 'vue'
import { testIdOf } from '../../composables/testId'
import { fieldMatches, useFieldSearch } from './fieldSearch'
import { useChangedFields } from '../../composables/useUnsavedChanges'
import { getField } from './registry'
import { tryUseFormState } from './formState'
import UnknownField from '../fields/UnknownField.vue'

export interface FieldNode extends Record<string, unknown> {
  type: string
  name: string
  reactive?: Record<string, unknown>
  visibility?: { create?: boolean; update?: boolean; view?: boolean }
}

interface Props {
  node: FieldNode
}

const props = defineProps<Props>()
const component = computed(() => getField(props.node.type))

// There may be no form state at all, when FieldRenderer is used outside a
// form — inside a Repeater with a state of its own, say. Then the visibility
// is always true, since `reactive` would mean nothing.
const form = tryUseFormState()

// Visibility by context: the backend's Field::onCreate(false) and
// Field::onUpdate(false) are serialized into node.visibility, and we hide a
// field that is not meant for the form's current mode. With no mode at all,
// everything is rendered.
const isContextVisible = computed<boolean>(() => {
  const mode = form?.mode
  if (!mode) return true
  return props.node.visibility?.[mode] !== false
})

const isReactiveVisible = computed<boolean>(() => {
  const reactive = props.node.reactive
  if (!reactive || typeof reactive !== 'object' || !form) return true

  for (const [fieldName, expected] of Object.entries(reactive)) {
    const actual = form.getField(fieldName)
    if (Array.isArray(expected)) {
      if (!expected.includes(actual as never)) return false
    } else if (actual !== expected) {
      return false
    }
  }
  return true
})

// The search over the form's fields (Tabs::searchable()): a field it does
// not find hides, one it does is marked.
const search = useFieldSearch()
const searchHit = computed<boolean | null>(() => {
  const q = search?.query.value ?? ''
  return q === '' ? null : fieldMatches(props.node as unknown as Record<string, unknown>, q)
})

// A field changed since the form was loaded or saved (Screen::saveMethod()).
const changedNames = useChangedFields()
const isChanged = computed<boolean>(() => changedNames?.value.has(props.node.name) ?? false)

const fieldProps = computed(() => {
  // The backend's Field::toArray() puts the type-specific options into
  // `attributes`: suggestions, options, multiple, currency and the rest. We
  // spread them to the top level, since the field components expect the props
  // unwrapped.
  const { type: _type, attributes, ...rest } = props.node
  const attrs = (attributes as Record<string, unknown> | undefined) ?? {}
  return { ...rest, ...attrs }
})
</script>

<template>
  <!-- The wrapper names the field for the tests (`field-{name}`); it draws no
       box of its own (display: contents), so the layouts lay out the field. -->
  <div
    v-if="isContextVisible && isReactiveVisible && searchHit !== false"
    class="admin-field-slot"
    :class="{ 'admin-field-slot--match': searchHit === true, 'admin-field-slot--changed': isChanged }"
    :data-changed="isChanged ? 'true' : undefined"
    :data-testid="testIdOf('field', node.name)"
    :data-field-name="node.name"
  >
    <component :is="component" v-if="component" v-bind="fieldProps" />
    <UnknownField v-else :type="node.type" :name="node.name" />
  </div>
</template>
