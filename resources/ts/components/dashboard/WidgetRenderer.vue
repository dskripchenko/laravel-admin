<script setup lang="ts">
/**
 * Resolves the widget component by `node.type` and forwards the rest of the
 * props.
 */
import { computed } from 'vue'
import { ArrowUpRight } from 'lucide-vue-next'
import { UidIcon } from '@dskripchenko/ui'
import { getWidget } from './registry'
import { trSafe as tr } from '../../stores/i18n'
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
/** Where the widget leads (Widget::link()): a panel path, or an external address. */
const link = computed<string | null>(() => (typeof props.node.link === 'string' && props.node.link !== '' ? props.node.link : null))
const external = computed<boolean>(() => link.value !== null && /^[a-z]+:\/\//i.test(link.value))
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
    link: _link,
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
  <div v-if="component && link" class="admin-widget-linked">
    <component :is="component" v-bind="widgetProps" />
    <router-link
      v-if="!external"
      :to="link"
      class="admin-widget-linked__go"
      data-testid="widget-link"
      :aria-label="tr('Открыть')"
      :title="tr('Открыть')"
    >
      <UidIcon :icon="ArrowUpRight" :size="14" />
    </router-link>
    <a
      v-else
      :href="link"
      target="_blank"
      rel="noopener"
      class="admin-widget-linked__go"
      data-testid="widget-link"
      :aria-label="tr('Открыть')"
      :title="tr('Открыть')"
    >
      <UidIcon :icon="ArrowUpRight" :size="14" />
    </a>
  </div>
  <component :is="component" v-else-if="component" v-bind="widgetProps" />
  <UnknownWidget v-else :type="node.type" />
</template>

<style>
/* A widget with a link (Widget::link()): an arrow in its top corner. */
.admin-widget-linked {
  position: relative;
  height: 100%;
  min-width: 0;
}
.admin-widget-linked__go {
  position: absolute;
  top: 10px;
  right: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: var(--uid-radius-sm, 4px);
  color: var(--uid-text-tertiary);
  z-index: 1;
}
.admin-widget-linked__go:hover {
  color: var(--uid-text-primary);
  background: var(--uid-color-surface-hover, rgba(0, 0, 0, 0.04));
}
</style>
