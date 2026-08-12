<script setup lang="ts">
/**
 * Resolves the widget component by `node.type` and forwards the rest of the
 * props.
 */
import { computed } from 'vue'
import { getWidget } from './registry'
import UnknownWidget from './UnknownWidget.vue'

export interface WidgetNode extends Record<string, unknown> {
  type: string
  /** How many columns it takes in the twelve-column grid (1..12). */
  span?: number
}

interface Props {
  node: WidgetNode
}
const props = defineProps<Props>()

const component = computed(() => getWidget(props.node.type))
const widgetProps = computed(() => {
  // The dashboard's meta fields are dropped. `size` especially: here it is a
  // grid-column span of 1..12, while in the widget components `size` often
  // means pixels (UidGauge, UidStat and the rest) — leave it in and `size=4`
  // arrives as pixels, breaking the widget.
  const {
    type: _type,
    span: _span,
    size: _size,
    rowSpan: _rs,
    row_span: _rs2,
    kind: _kind,
    refresh: _r,
    permission: _p,
    slug: _slug,
    data,
    ...rest
  } = props.node as Record<string, unknown> & { data?: Record<string, unknown> }

  // The backend's Widget::toArray() puts the type-specific fields inside
  // `data: {...}`. Some widget components expect them flat (rows, columns,
  // matrix, value), others expect the whole `data` prop — ChartWidget reads
  // data.type, data.labels and data.datasets. So we pass both: the flat spread
  // and the original `data`.
  if (data && typeof data === 'object' && !Array.isArray(data)) {
    return { ...rest, ...(data as Record<string, unknown>), data }
  }
  return data !== undefined ? { ...rest, data } : rest
})
</script>

<template>
  <component :is="component" v-if="component" v-bind="widgetProps" />
  <UnknownWidget v-else :type="node.type" />
</template>
