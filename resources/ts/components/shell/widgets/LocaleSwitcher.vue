<script setup lang="ts">
/**
 * Переключение локали через UidMenu. В topbar показываем компактно
 * (icon + uppercase-код), под ним выпадает список доступных локалей.
 */
import { UidMenu, UidMenuItem } from '@dskripchenko/ui'
import { useLocaleStore } from '../../../stores/locale'

const locale = useLocaleStore()

async function pick(loc: string): Promise<void> {
  if (loc === locale.current) return
  await locale.setLocale(loc)
}
</script>

<template>
  <UidMenu>
    <template #trigger>
      <button
        type="button"
        class="admin-topbar__icon-btn"
        style="width: auto; padding: 0 8px; gap: 4px; font-size: 12px;"
        aria-label="Сменить локаль"
      >
        <span class="admin-topbar__icon" data-icon="globe" />
        <span>{{ (locale.current ?? '').toUpperCase() }}</span>
      </button>
    </template>

    <UidMenuItem
      v-for="loc in locale.available"
      :key="loc"
      @click="pick(loc)"
    >
      {{ loc.toUpperCase() }}
    </UidMenuItem>
  </UidMenu>
</template>
