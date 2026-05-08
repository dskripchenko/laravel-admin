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
  // Удаляем dashboard-meta поля. Особенно ВАЖНО `size` — это grid-column-span
  // (число 1..12), а в widget-компонентах `size` часто значит pixels (UidGauge,
  // UidStat, etc) — и без удаления получаем `size=4` в pixels → виджет ломается.
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

  // Backend Widget::toArray() кладёт type-specific поля внутрь `data: {...}`.
  // Часть widget-компонентов ждёт их плоско (rows/columns/matrix/value),
  // часть — целиком как `data` prop (ChartWidget читает data.type/labels/datasets).
  // Передаём оба варианта: и flat-spread, и оригинальный `data`.
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
