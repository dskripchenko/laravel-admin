<script setup lang="ts">
/**
 * WidgetFrame — the card of the document-like widgets (diagram, BPMN/DMN,
 * kanban, calendar): the title, and below it a viewport that takes the rest
 * of the widget's cell and scrolls its content inside.
 *
 * The content is laid out of flow, so it never stretches the grid row: the
 * widget keeps the height its rowSpan gives it, and a board or a diagram
 * larger than that scrolls. Outside a dashboard grid — a Layout::dashboard()
 * on a screen, where nothing fixes the height — the viewport keeps a minimum.
 */
import { UidCard } from '@dskripchenko/ui'

withDefaults(defineProps<{ title?: string | null; kind: string }>(), { title: null })
</script>

<template>
  <UidCard padding="md" class="admin-widget admin-widget-frame" :class="`admin-widget-frame--${kind}`">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <div class="admin-widget__body admin-widget-frame__body">
      <div class="admin-widget-frame__viewport" :data-testid="`widget-${kind}`">
        <slot />
      </div>
    </div>
  </UidCard>
</template>

<style>
.admin-widget-frame__body {
  position: relative;
  flex: 1 1 auto;
  min-height: 240px;
}
.admin-widget-frame__viewport {
  position: absolute;
  inset: 0;
  overflow: auto;
}
/* A document layout fills the viewport instead of adding its own margin. */
.admin-widget-frame__viewport > .admin-document-layout {
  height: 100%;
  margin: 0;
}
.admin-widget-frame__viewport > .admin-resource-kanban,
.admin-widget-frame__viewport > .admin-resource-calendar {
  padding-top: 0;
}
.admin-widget-frame__notice {
  margin: 0;
}
</style>
