<script setup lang="ts">
/**
 * The general recursive renderer of the manifest's nodes.
 *
 * A node like `{ kind: 'field', type: 'text', name: 'title', ... }` is drawn by
 * FieldRenderer; one like `{ kind: 'layout', type: 'rows', items: [...] }` by
 * the registered layout component, with its list of children.
 *
 * For compatibility with older JSON that carries no `kind`: a type found in
 * the field registry counts as a field, one found in the layout registry as a
 * layout, and anything else falls back to UnknownField.
 */
import { computed } from 'vue'
import { getField, getLayout } from './registry'
import FieldRenderer, { type FieldNode } from './FieldRenderer.vue'
import UnknownField from '../fields/UnknownField.vue'

export interface LayoutNode extends Record<string, unknown> {
  type: string
  kind?: 'field' | 'layout'
  items?: LayoutNode[]
}

interface Props {
  node: LayoutNode
}
const props = defineProps<Props>()

type Resolved =
  | { kind: 'field' }
  | { kind: 'layout'; component: ReturnType<typeof getLayout> }
  | { kind: 'unknown' }

const resolved = computed<Resolved>(() => {
  // An explicit `kind` always wins.
  if (props.node.kind === 'field') {
    return { kind: 'field' }
  }
  if (props.node.kind === 'layout') {
    const component = getLayout(props.node.type)
    return component ? { kind: 'layout', component } : { kind: 'unknown' }
  }
  // Auto-detect.
  const layoutComponent = getLayout(props.node.type)
  if (layoutComponent) return { kind: 'layout', component: layoutComponent }
  if (getField(props.node.type)) return { kind: 'field' }
  return { kind: 'unknown' }
})

const layoutProps = computed(() => {
  const { type: _type, kind: _kind, ...rest } = props.node
  return rest
})

const fieldNode = computed<FieldNode>(() => props.node as unknown as FieldNode)
</script>

<template>
  <component
    :is="resolved.component"
    v-if="resolved.kind === 'layout' && resolved.component"
    v-bind="layoutProps"
  />
  <FieldRenderer v-else-if="resolved.kind === 'field'" :node="fieldNode" />
  <UnknownField v-else :type="node.type" :name="(node.name as string | undefined)" />
</template>
