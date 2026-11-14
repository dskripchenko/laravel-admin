<script setup lang="ts">
/**
 * The header of a dashboard widget: the title, an optional description under
 * it, and the widget's header links (Widget::headerLink(), 1.75) right of the
 * title. Drawn when there is any of the three.
 */
import { computed } from 'vue'
import WidgetHeaderLinks from './WidgetHeaderLinks.vue'
import { useWidgetHeader } from './widgetHeader'

const props = withDefaults(defineProps<{
  title?: string | null
  description?: string | null
  /** Extra classes of the title, for a widget that styles its own. */
  titleClass?: string | null
}>(), {
  title: null,
  description: null,
  titleClass: null,
})

const header = useWidgetHeader()
const links = computed(() => header?.links.value ?? [])
// The links are shown here, so WidgetRenderer does not repeat them.
if (header) header.claimed.value = true

const shown = computed(() => !!props.title || !!props.description || links.value.length > 0)
</script>

<template>
  <header v-if="shown" class="admin-widget__hd">
    <div class="admin-widget__hd-row">
      <h3 v-if="title" class="admin-widget__title" :class="titleClass">{{ title }}</h3>
      <span v-else class="admin-widget__title-gap" />
      <WidgetHeaderLinks :links="links" />
    </div>
    <p v-if="description" class="admin-widget__desc">{{ description }}</p>
  </header>
</template>

<style>
.admin-widget__hd-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--uid-space-sm, 12px);
  min-width: 0;
}
.admin-widget__hd-row > .admin-widget__title {
  min-width: 0;
}
.admin-widget__title-gap {
  flex: 1 1 auto;
}
/* A linked widget's arrow sits in the top corner: keep the links clear of it. */
.admin-widget-linked--arrow .admin-widget__hd-row {
  padding-right: 24px;
}
</style>
