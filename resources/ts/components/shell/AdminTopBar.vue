<script setup lang="ts">
/**
 * The admin top bar, built on the UID tokens. Its structure comes from
 * docs/design_handoff_laravel_admin/screens-shell.jsx (Topbar): the collapse
 * toggle, the breadcrumbs, a spacer, the search pill, the bell, the theme, the
 * locale and the avatar.
 *
 * The slots:
 *   - actions — a host may insert extra actions before the widgets
 *   - search — customizes the ⌘K command-palette pill; the default is a static
 *     placeholder, and a host puts UidCommand or its own on top
 *   - breadcrumbs — replaces the breadcrumbs
 */
import { computed } from 'vue'
import { PanelLeft, Search } from 'lucide-vue-next'
import { UidIcon } from '@dskripchenko/ui'
import ThemeToggle from './widgets/ThemeToggle.vue'
import LocaleSwitcher from './widgets/LocaleSwitcher.vue'
import NotificationBell from './widgets/NotificationBell.vue'
import UserMenu from './widgets/UserMenu.vue'
import { trSafe as tr } from '../../stores/i18n'

interface Crumb {
  label: string
  to?: string | Record<string, unknown> | null
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
        <template v-for="(crumb, idx) in breadcrumbs" :key="idx">
          <span v-if="idx > 0" class="sep">›</span>
          <component
            :is="crumb.to ? 'a' : 'span'"
            :href="typeof crumb.to === 'string' ? crumb.to : undefined"
            :class="idx === lastIdx ? 'cur' : ''"
          >
            {{ crumb.label }}
          </component>
        </template>
      </slot>
    </div>

    <div class="admin-topbar__spacer" />

    <slot name="search">
      <div
        class="admin-topbar__search"
        data-testid="topbar-search"
        role="button"
        tabindex="0"
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
    <NotificationBell />
    <ThemeToggle />
    <LocaleSwitcher />
    <UserMenu />
  </header>
</template>
