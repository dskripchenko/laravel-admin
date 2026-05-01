<script setup lang="ts">
/**
 * Toggle между light/dark поверх @dskripchenko/ui. Использует topbar
 * icon-button-стиль (.admin-topbar__icon-btn) для визуальной согласованности
 * с остальными widget'ами в баре.
 */
import { computed } from 'vue'
import { useThemeStore } from '../../../stores/theme'

const theme = useThemeStore()

const isDark = computed(() => theme.current === 'dark')
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
    <span class="admin-topbar__icon" :data-icon="iconName" />
  </button>
</template>
