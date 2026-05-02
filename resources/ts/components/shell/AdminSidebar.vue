<script setup lang="ts">
/**
 * Sidebar admin-каркаса поверх UidSidebar/UidSidebarGroup/UidSidebarItem.
 *
 * Источник данных — useMenuStore (groupedItems, отфильтрованные по permission'ам
 * через auth.hasAnyPermission). UidSidebarItem поддерживает вложенный icon-slot
 * + active + badge — маппим прямо из MenuItem.
 *
 * Brand-row сверху + опциональный tenant-block + footer с версией/docs —
 * по эталону docs/design_handoff_laravel_admin/screens-shell.jsx (Sidebar).
 */
import { computed, h, type Component } from 'vue'
import { useRoute } from 'vue-router'
import { UidSidebar, UidSidebarGroup, UidSidebarItem } from '@dskripchenko/ui'
import { useMenuStore, type MenuItem } from '../../stores/menu'

interface Props {
  collapsed?: boolean
  /** Заголовок бренда. */
  brandName?: string
  /** Logo-mark (буква/инициалы) — отображается вместо логотипа. */
  brandMark?: string
  /** Тенант / workspace — опционально показывается под брендом. */
  tenant?: { label: string; name: string } | null
  /** Версия + ссылка на docs в footer'е. */
  version?: string | null
  docsUrl?: string | null
}

withDefaults(defineProps<Props>(), {
  collapsed: false,
  brandName: 'Laravel Admin',
  brandMark: 'L',
  tenant: null,
  version: null,
  docsUrl: null,
})

const menu = useMenuStore()
const route = useRoute()

const groups = computed(() => menu.groupedItems)

function isActive(item: MenuItem): boolean {
  if (item.routeName && route.name === item.routeName) return true
  if (item.url && route.path === item.url) return true
  return false
}

/**
 * UidSidebarItem ожидает icon-slot. MenuItem.icon — строка (имя lucide-икoнки),
 * host-проект может зарегистрировать свой icon-resolver. Здесь возвращаем
 * простой <span data-icon> placeholder — host'у достаточно css-rule по
 * `[data-icon='users']` чтобы подставить SVG.
 */
function iconSlot(name?: string | null): Component | undefined {
  if (!name) return undefined
  // Functional component возвращающий <span data-icon> placeholder.
  // Host-проект может перебить через css-rule по `[data-icon='users']`.
  return () => h('span', { class: 'admin-sidebar__icon', 'data-icon': name })
}

/** UidSidebarItem.to принимает string | object; routeName маппим через route-object. */
function itemTarget(item: MenuItem): string | Record<string, unknown> | undefined {
  // URL приоритетнее routeName — vue-router бросает "no match for {name}" если
  // route'а ещё нет (dynamic-routes добавляются async после manifest.load).
  // URL-based navigation матчится в любой момент через path.
  if (item.url) return item.url
  if (item.routeName) return { name: item.routeName }
  return undefined
}
</script>

<template>
  <UidSidebar :collapsed="collapsed">
    <template #header>
      <div class="admin-sidebar-brand">
        <div class="admin-sidebar-brand__mark">{{ brandMark }}</div>
        <div v-if="!collapsed" class="admin-sidebar-brand__name">{{ brandName }}</div>
      </div>
      <div v-if="tenant && !collapsed" class="admin-sidebar-tenant">
        <span>{{ tenant.label }}</span>
        <b>{{ tenant.name }}</b>
      </div>
    </template>

    <template #nav>
      <UidSidebarGroup
        v-for="(grp, idx) in groups"
        :key="`grp-${idx}`"
        :title="grp.group ?? undefined"
      >
        <UidSidebarItem
          v-for="item in grp.items"
          :key="item.key"
          :to="itemTarget(item)"
          :active="isActive(item)"
          :badge="item.badge ?? undefined"
        >
          <template v-if="item.icon" #icon>
            <component :is="iconSlot(item.icon)" />
          </template>
          {{ item.label }}
        </UidSidebarItem>
      </UidSidebarGroup>
    </template>

    <template v-if="version || docsUrl" #footer>
      <div class="admin-sidebar-foot">
        <span v-if="version" class="admin-sidebar-foot__text">{{ version }}</span>
        <a v-if="docsUrl" :href="docsUrl" class="admin-sidebar-foot__text admin-sidebar-foot__link">
          Docs
        </a>
      </div>
    </template>
  </UidSidebar>
</template>

<style>
.admin-sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--uid-border-subtle);
}
.admin-sidebar-brand__mark {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  flex: none;
  background: var(--uid-text-primary);
  color: var(--uid-surface-raised);
  display: grid;
  place-items: center;
  font-family: var(--uid-font-family-display);
  font-weight: var(--uid-font-weight-bold);
  font-size: 14px;
  letter-spacing: -0.02em;
}
.admin-sidebar-brand__name {
  font-family: var(--uid-font-family-display);
  font-size: 14px;
  font-weight: var(--uid-font-weight-semibold);
  letter-spacing: -0.01em;
  white-space: nowrap;
  overflow: hidden;
  color: var(--uid-text-primary);
}
.admin-sidebar-tenant {
  margin: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
  height: 32px;
  padding: 0 8px;
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-secondary);
  background: var(--uid-surface-base);
}
.admin-sidebar-tenant b { color: var(--uid-text-primary); font-weight: 500; }
.admin-sidebar-foot {
  padding: 10px 16px;
  border-top: 1px solid var(--uid-border-subtle);
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11px;
  color: var(--uid-text-tertiary);
}
.admin-sidebar-foot__link { cursor: pointer; }
.admin-sidebar-foot__link:hover { color: var(--uid-text-primary); }

.admin-sidebar__icon {
  width: 16px;
  height: 16px;
  display: inline-block;
  flex: none;
  color: currentColor;
}
</style>
