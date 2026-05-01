<script setup lang="ts">
/**
 * Markdown widget — простой preformatted-блок. Для production хост может
 * подкинуть свой widget с marked/MDX через registerWidget('markdown', ...).
 *
 * Тут — fallback с базовым форматированием через line-break preserve.
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
