<script setup lang="ts">
/**
 * Switching the locale, through UidMenu. In the topbar it is compact — an icon
 * and the uppercase code — with the available locales dropping down beneath.
 */
import { Globe } from 'lucide-vue-next'
import { UidIcon, UidMenu, UidMenuItem } from '@dskripchenko/ui'
import { useLocaleStore } from '../../../stores/locale'
import { trSafe as tr } from '../../../stores/i18n'

const locale = useLocaleStore()

async function pick(loc: string): Promise<void> {
  if (loc === locale.current) return
  await locale.setLocale(loc)
  // A full reload, which bootstraps the menu, the manifest and the i18n bag
  // afresh in the new locale: setLocale only persists the choice and changes
  // the header, and the cached menu and manifest would otherwise stay in the
  // old one.
  if (typeof window !== 'undefined') window.location.reload()
}
</script>

<template>
  <UidMenu>
    <template #trigger>
      <button
        type="button"
        class="admin-topbar__icon-btn"
        style="width: auto; padding: 0 8px; gap: 4px; font-size: 12px;"
        :aria-label="tr('Сменить локаль')"
      >
        <UidIcon :icon="Globe" :size="14" data-icon="globe" />
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
