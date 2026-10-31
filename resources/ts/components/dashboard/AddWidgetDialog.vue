<script setup lang="ts">
/**
 * AddWidgetDialog — the modal for picking a widget type and setting it up:
 * WidgetConfigDialog in its add mode, kept under this name for the hosts that
 * import it.
 *
 * The types and their config forms come from the dashboard registry — the
 * registered components and their descriptions (describeWidget(), see
 * widgetConfig.ts) — so a host's own type appears here with its own form.
 * Saving emits `add` with a finished WidgetLayoutItem, which the parent puts
 * into the dashboard store.
 */
import WidgetConfigDialog from './WidgetConfigDialog.vue'
import type { WidgetLayoutItem } from '../../stores/dashboard'

interface Props {
  open: boolean
  /** The dashboard's hidden widgets, offered for restoring. */
  restorable?: Array<{ slug: string; title: string }>
}
withDefaults(defineProps<Props>(), { restorable: () => [] })

const emit = defineEmits<{
  close: []
  add: [item: WidgetLayoutItem]
  restore: [slug: string]
}>()
</script>

<template>
  <WidgetConfigDialog
    :open="open"
    mode="add"
    :restorable="restorable"
    @close="emit('close')"
    @add="(item) => emit('add', item)"
    @restore="(slug) => emit('restore', slug)"
  />
</template>
