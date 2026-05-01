<script setup lang="ts">
/**
 * Резолвит widget-компонент по `node.type` и forwards остальные props.
 */
import { computed } from 'vue'
import { getWidget } from './registry'
import UnknownWidget from './UnknownWidget.vue'

export interface WidgetNode extends Record<string, unknown> {
  type: string
  /** Сколько колонок занимает в 12-grid (1..12). */
  span?: number
}

interface Props {
  node: WidgetNode
}
const props = defineProps<Props>()

const component = computed(() => getWidget(props.node.type))
const widgetProps = computed(() => {
  const { type: _type, span: _span, ...rest } = props.node
  return rest
})
</script>

<template>
  <component :is="component" v-if="component" v-bind="widgetProps" />
  <UnknownWidget v-else :type="node.type" />
</template>
