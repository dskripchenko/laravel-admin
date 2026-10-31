<script setup lang="ts">
/**
 * The frame of a document layout: the caption, the box with the layout's
 * height, a skeleton while the package or the text loads, and the alert of a
 * failure in their place.
 */
import { UidAlert, UidSkeleton } from '@dskripchenko/ui'

withDefaults(defineProps<{
  kind: string
  title?: string | null
  height?: string
  loading?: boolean
  error?: string | null
  errorTitle?: string | null
  /** Shown in place of a document that has nothing to show. */
  empty?: string | null
  /** Scrolls the content inside the box instead of growing with it. */
  scroll?: boolean
}>(), {
  title: null,
  height: undefined,
  loading: false,
  error: null,
  errorTitle: null,
  empty: null,
  scroll: false,
})
</script>

<template>
  <figure class="admin-document-layout" :class="`admin-document-layout--${kind}`">
    <figcaption v-if="title" class="admin-document-layout__title">{{ title }}</figcaption>
    <div
      class="admin-document-layout__box"
      :class="{ 'admin-document-layout__box--scroll': scroll, 'admin-document-layout__box--fixed': !!height }"
      :style="height ? { height } : undefined"
    >
      <UidAlert v-if="error" variant="danger" :title="errorTitle ?? undefined" class="admin-document-layout__error">
        {{ error }}
      </UidAlert>
      <UidSkeleton v-else-if="loading" :height="height ? '100%' : '240px'" />
      <UidAlert v-else-if="empty" variant="info" class="admin-document-layout__empty">{{ empty }}</UidAlert>
      <slot v-else />
    </div>
  </figure>
</template>
