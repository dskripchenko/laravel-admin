<script setup lang="ts">
/**
 * FlowEntry — a stored process model, read-only: the backend's
 * Infolist\FlowEntry and the view of a Field\Flow on a view page. FlowView of
 * @dskripchenko/flow draws it — pan, zoom, inline sub-processes, the details
 * of a step — without monitoring.
 */
import { computed } from 'vue'
import { UidAlert, UidSkeleton } from '@dskripchenko/ui'
import { useEntryValue } from './entryValue'
import { cssLength, flowLocale, toGraph, useFlowModules } from '../flow/loadFlow'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  name?: string
  value?: unknown
  height?: number | string | null
  direction?: 'LR' | 'TB' | 'auto' | null
  edgeStyle?: 'orthogonal' | 'smooth' | null
  minimap?: boolean
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  height: null,
  direction: null,
  edgeStyle: null,
  minimap: false,
  placeholder: '—',
})

const raw = useEntryValue(props)
const graph = computed(() => toGraph(raw.value))
const { modules, failed } = useFlowModules()
const boxStyle = computed(() => ({ height: cssLength(props.height, '360px') }))
</script>

<template>
  <span v-if="!graph || graph.nodes.length === 0" class="admin-infolist-text">{{ placeholder }}</span>
  <div v-else class="admin-infolist-flow" :style="boxStyle">
    <UidAlert v-if="failed" variant="danger">{{ tr('Не удалось загрузить схему процесса') }}</UidAlert>
    <UidSkeleton v-else-if="!modules" height="100%" />
    <component
      :is="modules.vue.FlowView"
      v-else
      class="admin-infolist-flow__view"
      :graph="graph"
      :minimap="minimap"
      :status-bar="false"
      :direction="direction ?? undefined"
      :edge-style="edgeStyle ?? 'orthogonal'"
      :locale="flowLocale()"
      :toast-provider="false"
    />
  </div>
</template>

<style>
.admin-infolist-flow {
  position: relative;
  width: 100%;
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  overflow: hidden;
}
.admin-infolist-flow__view { width: 100%; height: 100%; }
</style>
