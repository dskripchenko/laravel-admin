<script setup lang="ts">
/**
 * The light/dark toggle, built on @dskripchenko/ui. It uses the topbar's
 * icon-button style (.admin-topbar__icon-btn) so as to look of a piece with
 * the other widgets in the bar.
 */
import { computed } from 'vue'
import { Moon, Sun } from 'lucide-vue-next'
import { UidIcon } from '@dskripchenko/ui'
import { useThemeStore } from '../../../stores/theme'
import { trSafe as tr } from '../../../stores/i18n'

const theme = useThemeStore()

const isDark = computed(() => theme.current === 'dark')
const icon = computed(() => (isDark.value ? Sun : Moon))
const iconName = computed(() => (isDark.value ? 'sun' : 'moon'))
const ariaLabel = computed(() =>
  isDark.value ? tr('Переключить на светлую тему') : tr('Переключить на тёмную тему'),
)

async function toggle(): Promise<void> {
  const next = isDark.value ? 'light' : 'dark'
  await theme.setTheme(next)
}
</script>

<template>
  <button
    type="button"
    class="admin-topbar__icon-btn"
    :aria-label="ariaLabel"
    :aria-pressed="isDark"
    @click="toggle"
  >
    <UidIcon :icon="icon" :size="18" :data-icon="iconName" />
  </button>
</template>
