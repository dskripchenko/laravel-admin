<script setup lang="ts">
/**
 * Топ-бар admin. Содержит:
 *   - бренд (название/логотип) — берётся из bootstrap.brand либо props
 *   - ThemeToggle (light/dark switcher)
 *   - LocaleSwitcher
 *   - NotificationBell с unread badge
 *   - UserMenu
 *
 * Все widget'ы — sub-компоненты, которые сами читают своё состояние из stores.
 * Top-bar только компонует.
 */
import { computed } from 'vue'
import ThemeToggle from './widgets/ThemeToggle.vue'
import LocaleSwitcher from './widgets/LocaleSwitcher.vue'
import NotificationBell from './widgets/NotificationBell.vue'
import UserMenu from './widgets/UserMenu.vue'

interface Props {
  brandName?: string
  brandLogo?: string | null
}
const props = withDefaults(defineProps<Props>(), { brandName: 'Admin', brandLogo: null })

const initial = computed(() => props.brandName.charAt(0).toUpperCase())
</script>

<template>
  <div class="admin-topbar">
    <div class="admin-topbar__brand">
      <img v-if="brandLogo" :src="brandLogo" :alt="brandName" class="admin-topbar__logo" />
      <span v-else class="admin-topbar__logo-placeholder" aria-hidden="true">{{ initial }}</span>
      <span class="admin-topbar__name">{{ brandName }}</span>
    </div>
    <nav class="admin-topbar__actions" aria-label="Admin top actions">
      <slot name="actions" />
      <ThemeToggle />
      <LocaleSwitcher />
      <NotificationBell />
      <UserMenu />
    </nav>
  </div>
</template>

<style>
.admin-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  height: 56px;
  border-bottom: 1px solid var(--admin-border, #e5e7eb);
  background: var(--admin-topbar-bg, #fff);
}
.admin-topbar__brand {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
}
.admin-topbar__logo { height: 28px; width: auto; }
.admin-topbar__logo-placeholder {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background: var(--admin-accent, #3b82f6);
  color: #fff;
  font-size: 14px;
}
.admin-topbar__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
</style>
