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
import { computed } from 'vue'
import { UidSidebar, UidSidebarGroup } from '@dskripchenko/ui'
import { useMenuStore } from '../../stores/menu'
import AdminSidebarNode from './AdminSidebarNode.vue'
import BrandLogo from './BrandLogo.vue'

interface Props {
  collapsed?: boolean
  /** Заголовок бренда. */
  brandName?: string
  /**
   * Custom mark (если задан — рендерится вместо BrandLogo). Полезно
   * host-проекту с собственным логотипом.
   */
  brandMark?: string | null
  /** Named-route для click'а по brand-row. */
  homeRouteName?: string
  /** Тенант / workspace — опционально показывается под брендом. */
  tenant?: { label: string; name: string } | null
  /** Версия + ссылка на docs в footer'е. */
  version?: string | null
  docsUrl?: string | null
}

withDefaults(defineProps<Props>(), {
  collapsed: false,
  brandName: 'Laravel Admin',
  brandMark: null,
  homeRouteName: 'admin.home',
  tenant: null,
  version: null,
  docsUrl: null,
})

const menu = useMenuStore()

const groups = computed(() => menu.groupedItems)
</script>

<template>
  <UidSidebar :collapsed="collapsed">
    <template #header>
      <router-link
        :to="{ name: homeRouteName }"
        class="admin-sidebar-brand"
        :title="collapsed ? brandName : undefined"
        :aria-label="brandName"
      >
        <div v-if="brandMark" class="admin-sidebar-brand__mark">{{ brandMark }}</div>
        <BrandLogo v-else :size="28" />
        <div v-if="!collapsed" class="admin-sidebar-brand__name">{{ brandName }}</div>
      </router-link>
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
        <AdminSidebarNode
          v-for="item in grp.items"
          :key="item.key"
          :item="item"
          :collapsed="collapsed"
        />
      </UidSidebarGroup>
    </template>

    <template #footer>
      <div class="admin-sidebar-foot">
        <span class="admin-sidebar-foot__text" :title="version ?? 'Laravel Admin'">
          <template v-if="!collapsed">{{ version ?? 'Laravel Admin' }}</template>
          <template v-else>·</template>
        </span>
        <a
          v-if="docsUrl && !collapsed"
          :href="docsUrl"
          class="admin-sidebar-foot__text admin-sidebar-foot__link"
        >
          Docs
        </a>
      </div>
    </template>
  </UidSidebar>
</template>

<style>
/*
 * Sidebar header выровнен по AdminTopBar (height: 56px + 1px border).
 * Padding у uid-pattern-sidebar__header обнулён — brand занимает ровно
 * одну строку шапки. Border-bottom UI-kit'а оставляем — он совпадает
 * по Y с border-bottom топбара, образуя единую горизонталь над nav
 * и контентной частью.
 */
.uid-pattern-sidebar__header:has(.admin-sidebar-brand) {
  padding: 0;
}
.admin-sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 56px;
  padding: 0 16px;
  text-decoration: none;
  color: inherit;
  transition: background var(--uid-duration-fast, 120ms) var(--uid-ease-out, ease);
}
.admin-sidebar-brand:hover {
  background: var(--uid-color-surface-hover, rgba(0, 0, 0, 0.04));
}
.admin-sidebar-brand:focus-visible {
  outline: 2px solid var(--uid-color-focus-ring, var(--uid-accent));
  outline-offset: -2px;
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
  /*
   * Высота строго через --admin-foot-height — совпадает с .admin-main-footer
   * (см. AdminShell.vue), чтобы border-top sidebar foot и border-top main
   * footer проходили по одной Y и давали единую горизонталь под экраном.
   */
  height: var(--admin-foot-height, 32px);
  padding: 0 16px;
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
