<script setup lang="ts">
/**
 * The actions widget — the backend's ActionsWidget (1.75), whose data()
 * returns `items: [{key, label, url, icon}]`, already filtered by the
 * user's permissions: a row of buttons, the first one primary. A panel path
 * opens in place, an external address in a new tab.
 */
import { useRouter } from 'vue-router'
import { UidButton, UidCard, UidIcon } from '@dskripchenko/ui'
import WidgetHeader from './WidgetHeader.vue'
import { resolveIcon } from '../shell/iconRegistry'
import { testIdOf } from '../../composables/testId'
import { trSafe as tr } from '../../stores/i18n'
import { isExternalUrl } from './widgetHeader'

interface ActionItem {
  key: string
  label: string
  url: string
  icon?: string | null
}

withDefaults(defineProps<{ title?: string; items?: ActionItem[] }>(), {
  title: '',
  items: () => [],
})

const router = useRouter()

function go(url: string): void {
  if (isExternalUrl(url)) {
    window.open(url, '_blank', 'noopener')
    return
  }
  void router?.push(url).catch(() => undefined)
}
</script>

<template>
  <UidCard padding="md" class="admin-widget admin-actions-widget" data-testid="widget-actions">
    <WidgetHeader :title="title" />
    <div v-if="items.length > 0" class="admin-actions-widget__row">
      <UidButton
        v-for="(item, i) in items"
        :key="item.key"
        :variant="i === 0 ? 'primary' : 'secondary'"
        size="sm"
        :data-testid="testIdOf('widget-action', item.key)"
        @click="go(item.url)"
      >
        <template v-if="resolveIcon(item.icon)" #prepend><UidIcon :icon="resolveIcon(item.icon)!" :size="14" /></template>
        {{ item.label }}
      </UidButton>
    </div>
    <div v-else class="admin-widget__empty" data-testid="widget-actions-empty">{{ tr('Нет доступных действий') }}</div>
  </UidCard>
</template>

<style>
.admin-actions-widget__row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--uid-space-sm, 8px);
}
</style>
