<script setup lang="ts">
/**
 * Универсальный рекурсивный renderer manifest-узлов.
 *
 * Узел вида `{ kind: 'field', type: 'text', name: 'title', ... }` рендерится
 * через FieldRenderer. Узел вида `{ kind: 'layout', type: 'rows', items: [...] }`
 * — через зарегистрированный layout-компонент с children-списком.
 *
 * Для совместимости со старыми JSON'ами без `kind` — если type есть в
 * field-registry, считаем field; если в layout-registry — считаем layout;
 * иначе — UnknownField fallback.
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
  // Явный hint через `kind` всегда приоритетнее.
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
