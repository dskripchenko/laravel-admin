<script setup lang="ts">
/**
 * GroupField — the backend's Field\Group: nested fields stored as one object
 * under the group's name, `address: {city, street}`.
 *
 * The children read and write `state[name][child]` through a scoped form
 * context, and see the validator's `address.city` errors as their own.
 * `->layout('columns' | 'inline')` arranges them; `->collapsible()` and
 * `->collapsed()` put them behind an accordion header.
 */
import { computed, ref } from 'vue'
import { UidAccordion, UidAccordionItem } from '@dskripchenko/ui'
import { provideScopedFormState, useFormState } from '../render/formState'
import FieldRenderer, { type FieldNode } from '../render/FieldRenderer.vue'

interface Props {
  name: string
  fields?: FieldNode[]
  label?: string | null
  help?: string | null
  description?: string | null
  layout?: 'rows' | 'columns' | 'inline'
  collapsible?: boolean
  collapsed?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  fields: () => [],
  label: null,
  help: null,
  description: null,
  layout: 'rows',
  collapsible: false,
  collapsed: false,
})

const parent = useFormState()
provideScopedFormState(parent, props.name)

const errorMsg = computed<string | undefined>(() => parent.errors[props.name]?.[0])
const note = computed<string | null>(() => props.description ?? props.help)
const open = ref<string>(props.collapsed ? '' : props.name)
const title = computed<string>(() => props.label ?? props.name)
</script>

<template>
  <div class="admin-group-field" :class="`admin-group-field--${layout}`" :data-name="name">
    <UidAccordion v-if="collapsible || collapsed" v-model="open">
      <UidAccordionItem :value="name" :title="title">
        <p v-if="note" class="admin-group-field__note">{{ note }}</p>
        <div class="admin-group-field__body">
          <FieldRenderer v-for="f in fields" :key="f.name" :node="f" />
        </div>
      </UidAccordionItem>
    </UidAccordion>
    <fieldset v-else class="admin-group-field__set">
      <legend v-if="label" class="admin-group-field__title">{{ label }}</legend>
      <p v-if="note" class="admin-group-field__note">{{ note }}</p>
      <div class="admin-group-field__body">
        <FieldRenderer v-for="f in fields" :key="f.name" :node="f" />
      </div>
    </fieldset>
    <p v-if="errorMsg" class="admin-group-field__error" role="alert">{{ errorMsg }}</p>
  </div>
</template>

<style>
.admin-group-field__set {
  margin: 0;
  padding: var(--uid-space-md, 12px);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-lg);
  min-width: 0;
}
.admin-group-field__title {
  padding: 0 var(--uid-space-2xs, 2px);
  font-size: var(--uid-font-size-sm);
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
}
.admin-group-field__note {
  margin: 0 0 var(--uid-space-sm, 8px);
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-tertiary);
}
.admin-group-field__body {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md, 12px);
}
.admin-group-field--columns .admin-group-field__body {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}
.admin-group-field--inline .admin-group-field__body {
  flex-direction: row;
  flex-wrap: wrap;
  align-items: flex-start;
}
.admin-group-field--inline .admin-group-field__body > * {
  flex: 1 1 160px;
  min-width: 0;
}
.admin-group-field__error {
  margin: var(--uid-space-2xs, 2px) 0 0;
  font-size: var(--uid-font-size-xs);
  color: var(--uid-danger);
}
</style>
