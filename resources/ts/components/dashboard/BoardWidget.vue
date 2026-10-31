<script setup lang="ts">
/**
 * A collaborative board on a dashboard — Widget\BoardWidget: the board layout
 * inside the widget's frame, filling its height. A board package builds it
 * with the board's endpoint; it is not offered in the «Add widget» dialog.
 */
import BoardLayout from '../layouts/BoardLayout.vue'
import WidgetFrame from './WidgetFrame.vue'

defineOptions({ inheritAttrs: false })

withDefaults(defineProps<{
  title?: string | null
  endpoint: string
  readonly?: boolean
  user?: { id?: string | null; name?: string | null; color?: string | null } | null
  realtime?: { ticketUrl: string; channel: string } | null
  pollInterval?: number
}>(), {
  title: null,
  readonly: false,
  user: null,
  realtime: null,
  pollInterval: 1500,
})
</script>

<template>
  <WidgetFrame kind="board" :title="title">
    <BoardLayout
      :endpoint="endpoint"
      :readonly="readonly"
      :user="user"
      :realtime="realtime"
      :poll-interval="pollInterval"
      height="100%"
    />
  </WidgetFrame>
</template>
