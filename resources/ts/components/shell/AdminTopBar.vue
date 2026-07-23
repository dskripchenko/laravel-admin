<script setup lang="ts">
/**
 * Admin top bar поверх UID-токенов. Структура из docs/design_handoff_laravel_admin/
 * screens-shell.jsx (Topbar): collapse-toggle / breadcrumbs / spacer /
 * search-pill / bell / theme / locale / avatar.
 *
 * Slots:
 *   - actions — host может вставить дополнительные actions перед widget'ами
 *   - search — кастомизация ⌘K command-palette pill (по умолчанию — статичный
 *     placeholder; host сверху поднимет UidCommand или свой)
 *   - breadcrumbs — переопределить хлебные крошки
 */
import { computed } from 'vue'
import { PanelLeft, Search } from 'lucide-vue-next'
import { UidIcon } from '@dskripchenko/ui'
import ThemeToggle from './widgets/ThemeToggle.vue'
import LocaleSwitcher from './widgets/LocaleSwitcher.vue'
import NotificationBell from './widgets/NotificationBell.vue'
import UserMenu from './widgets/UserMenu.vue'

interface Crumb {
  label: string
  to?: string | Record<string, unknown> | null
}

interface Props {
  /** Хлебные крошки. Последний элемент — текущая страница (без to). */
  breadcrumbs?: Crumb[]
  /** Показывать кнопку collapse сайдбара (только в shell-layout'е). */
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
      aria-label="Свернуть меню"
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
        role="button"
        tabindex="0"
        @click="emit('open-search')"
        @keydown.enter.prevent="emit('open-search')"
        @keydown.space.prevent="emit('open-search')"
      >
        <UidIcon :icon="Search" :size="14" data-icon="search" />
        <span>Поиск везде…</span>
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
