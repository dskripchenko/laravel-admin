<script setup lang="ts">
/**
 * The markdown widget — a plain preformatted block. In production a host may
 * supply its own with marked or MDX, through registerWidget('markdown', ...).
 *
 * What is here is the fallback: basic formatting that preserves line breaks.
 */
import { UidCard } from '@dskripchenko/ui'

interface Props {
  title?: string
  content: string
}

withDefaults(defineProps<Props>(), { title: '' })
</script>

<template>
  <UidCard padding="md" class="admin-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <div class="admin-markdown-widget">{{ content }}</div>
  </UidCard>
</template>

<style>
.admin-markdown-widget {
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-secondary);
  white-space: pre-wrap;
  line-height: var(--uid-line-height-normal);
}
</style>
