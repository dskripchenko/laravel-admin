<script setup lang="ts">
import { useThemeStore } from '../../../stores/theme'

const theme = useThemeStore()

async function toggle(): Promise<void> {
  const next = theme.current === 'dark' ? 'light' : 'dark'
  await theme.setTheme(next)
}
</script>

<template>
  <button
    type="button"
    class="admin-widget admin-theme-toggle"
    :aria-label="theme.current === 'dark' ? 'Включить светлую тему' : 'Включить тёмную тему'"
    :aria-pressed="theme.current === 'dark'"
    @click="toggle"
  >
    <span class="admin-theme-toggle__icon" aria-hidden="true">
      {{ theme.current === 'dark' ? '☾' : '☀' }}
    </span>
  </button>
</template>

<style>
.admin-widget {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 32px;
  min-width: 32px;
  padding: 0 8px;
  border: 1px solid var(--admin-border, #e5e7eb);
  border-radius: 6px;
  background: transparent;
  cursor: pointer;
  font-size: 14px;
  color: var(--admin-text, #111827);
}
.admin-widget:hover { background: var(--admin-hover, #e5e7eb); }
.admin-theme-toggle__icon { font-size: 16px; }
</style>
