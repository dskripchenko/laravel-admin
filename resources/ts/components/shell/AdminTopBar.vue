<script setup lang="ts">
/**
 * The admin top bar, built on the UID tokens. Its structure comes from
 * docs/design_handoff_laravel_admin/screens-shell.jsx (Topbar): the collapse
 * toggle, the breadcrumbs, a spacer, the search pill, the status indicators,
 * the bell, the theme, the locale and the avatar.
 *
 * The slots:
 *   - actions — a host may insert extra actions before the widgets
 *   - search — customizes the ⌘K command-palette pill; the default is a static
 *     placeholder, and a host puts UidCommand or its own on top
 *   - breadcrumbs — replaces the breadcrumbs
 */
import { computed } from 'vue'
import { useRouter, type RouteLocationRaw } from 'vue-router'
import { PanelLeft, Search } from 'lucide-vue-next'
import { UidBreadcrumb, UidBreadcrumbItem, UidIcon } from '@dskripchenko/ui'
import ThemeToggle from './widgets/ThemeToggle.vue'
import LocaleSwitcher from './widgets/LocaleSwitcher.vue'
import NotificationBell from './widgets/NotificationBell.vue'
import StatusIndicators from './widgets/StatusIndicators.vue'
import UserMenu from './widgets/UserMenu.vue'
import { trSafe as tr } from '../../stores/i18n'

interface Crumb {
  label: string
  /** A router location, or a path within the panel. */
  to?: RouteLocationRaw | null
}

interface Props {
  /** The breadcrumbs. The last one is the current page and carries no `to`. */
  breadcrumbs?: Crumb[]
  /** Whether to show the sidebar's collapse button; only inside the shell layout. */
  showCollapseToggle?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
  showCollapseToggle: true,
})

const emit = defineEmits<{
  'toggle-sidebar': []
  'open-search': []
}>()

const lastIdx = computed(() => props.breadcrumbs.length - 1)

const router = useRouter()

/**
 * The crumb's target for UidBreadcrumbItem, which renders it through the
 * app's RouterLink: a plain click navigates in place, a modified one opens a
 * new tab. A target the router cannot resolve yet — the resource routes are
 * added once the manifest is in — leaves the crumb a plain caption, since
 * RouterLink would throw on it.
 */
function targetOf(crumb: Crumb): string | Record<string, unknown> | undefined {
  if (!crumb.to) return undefined
  try {
    router.resolve(crumb.to)
  } catch {
    return undefined
  }
  return crumb.to as string | Record<string, unknown>
}
</script>

<template>
  <header class="admin-topbar">
    <button
      v-if="showCollapseToggle"
      type="button"
      class="admin-topbar__icon-btn"
      :aria-label="tr('Свернуть меню')"
      @click="emit('toggle-sidebar')"
    >
      <UidIcon :icon="PanelLeft" :size="18" data-icon="panel-left" />
    </button>

    <div class="admin-topbar__breadcrumbs">
      <slot name="breadcrumbs">
        <UidBreadcrumb
          v-if="breadcrumbs.length > 0"
          :label="tr('Навигация')"
          separator="›"
          collapse
          collapse-first
          container=".admin-topbar"
        >
          <UidBreadcrumbItem
            v-for="(crumb, idx) in breadcrumbs"
            :key="idx"
            :to="targetOf(crumb)"
            :current="idx === lastIdx"
            :title="crumb.label"
          >{{ crumb.label }}</UidBreadcrumbItem>
        </UidBreadcrumb>
      </slot>
    </div>

    <div class="admin-topbar__spacer" />

    <slot name="search">
      <div
        class="admin-topbar__search"
        data-testid="topbar-search"
        role="button"
        tabindex="0"
        :aria-label="tr('Поиск везде…')"
        @click="emit('open-search')"
        @keydown.enter.prevent="emit('open-search')"
        @keydown.space.prevent="emit('open-search')"
      >
        <UidIcon :icon="Search" :size="14" data-icon="search" />
        <span>{{ tr('Поиск везде…') }}</span>
        <kbd>⌘K</kbd>
      </div>
    </slot>

    <slot name="actions" />
    <StatusIndicators />
    <NotificationBell />
    <ThemeToggle />
    <LocaleSwitcher />
    <UserMenu />
  </header>
</template>
