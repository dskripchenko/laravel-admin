<script setup lang="ts">
/**
 * «+ Create» in the top bar: the create pages the user may open
 * (MenuRegistry::quickCreate()), filtered by the server. Hidden when there are
 * none.
 */
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Plus } from 'lucide-vue-next'
import { UidButton, UidIcon, UidMenu, UidMenuItem } from '@dskripchenko/ui'
import { useMenuStore } from '../../../stores/menu'
import { testIdOf } from '../../../composables/testId'
import { resolveIcon } from '../iconRegistry'
import { trSafe as tr } from '../../../stores/i18n'

const menu = useMenuStore()
const router = useRouter()
const items = computed(() => menu.quickCreate)

function go(url: string): void {
  if (/^https?:\/\//.test(url)) {
    window.open(url, '_blank', 'noopener')
    return
  }
  void router.push(url).catch(() => undefined)
}
</script>

<template>
  <UidMenu v-if="items.length > 0">
    <template #trigger>
      <UidButton variant="secondary" size="sm" data-testid="quick-create">
        <template #prepend><UidIcon :icon="Plus" :size="14" /></template>
        {{ tr('Создать') }}
      </UidButton>
    </template>
    <UidMenuItem
      v-for="item in items"
      :key="item.key"
      :data-testid="testIdOf('quick-create', item.key)"
      @click="go(item.url)"
    >
      <UidIcon v-if="resolveIcon(item.icon)" :icon="resolveIcon(item.icon)!" :size="14" />
      {{ item.label }}
    </UidMenuItem>
  </UidMenu>
</template>
