<script setup lang="ts">
/**
 * Toggle между light/dark поверх @dskripchenko/ui. Использует topbar
 * icon-button-стиль (.admin-topbar__icon-btn) для визуальной согласованности
 * с остальными widget'ами в баре.
 */
import { computed } from 'vue'
import { Moon, Sun } from 'lucide-vue-next'
import { UidIcon } from '@dskripchenko/ui'
import { useThemeStore } from '../../../stores/theme'

const theme = useThemeStore()

const isDark = computed(() => theme.current === 'dark')
const icon = computed(() => (isDark.value ? Sun : Moon))
const iconName = computed(() => (isDark.value ? 'sun' : 'moon'))
const ariaLabel = computed(() =>
  isDark.value ? 'Переключить на светлую тему' : 'Переключить на тёмную тему',
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
