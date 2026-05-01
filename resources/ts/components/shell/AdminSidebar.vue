<script setup lang="ts">
/**
 * Sidebar — рендерит menu store. Группированные пункты, выделение active
 * через router. Filter по permissions делает store сам.
 *
 * Item.routeName приоритетнее url'а — если задан, используем `<RouterLink :to="{name}">`,
 * иначе — обычный <a href>.
 */
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useMenuStore } from '../../stores/menu'

const menu = useMenuStore()
const route = useRoute()

const groups = computed(() => menu.groupedItems)

function isActive(routeName?: string | null, url?: string | null): boolean {
  if (routeName && route.name === routeName) return true
  if (url && route.path === url) return true
  return false
}
</script>

<template>
  <nav class="admin-sidebar" aria-label="Main navigation">
    <ul v-for="(grp, idx) in groups" :key="`g-${idx}`" class="admin-sidebar__group">
      <li v-if="grp.group" class="admin-sidebar__group-header">{{ grp.group }}</li>
      <li
        v-for="item in grp.items"
        :key="item.key"
        :class="[
          'admin-sidebar__item',
          { 'admin-sidebar__item--active': isActive(item.routeName, item.url) },
        ]"
      >
        <component
          :is="item.routeName ? 'RouterLink' : 'a'"
          :to="item.routeName ? { name: item.routeName } : undefined"
          :href="!item.routeName ? (item.url ?? '#') : undefined"
          class="admin-sidebar__link"
        >
          <span v-if="item.icon" class="admin-sidebar__icon" :data-icon="item.icon" />
          <span class="admin-sidebar__label">{{ item.label }}</span>
          <span v-if="item.badge !== null && item.badge !== undefined" class="admin-sidebar__badge">
            {{ item.badge }}
          </span>
        </component>
      </li>
    </ul>
  </nav>
</template>

<style>
.admin-sidebar {
  padding: 8px 0;
  background: var(--admin-sidebar-bg, #f9fafb);
  border-right: 1px solid var(--admin-border, #e5e7eb);
  height: 100%;
}
.admin-sidebar__group {
  list-style: none;
  margin: 0;
  padding: 8px 0;
}
.admin-sidebar__group + .admin-sidebar__group {
  border-top: 1px solid var(--admin-border, #e5e7eb);
}
.admin-sidebar__group-header {
  padding: 4px 16px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--admin-muted, #6b7280);
}
.admin-sidebar__link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 16px;
  text-decoration: none;
  color: var(--admin-text, #111827);
  border-radius: 6px;
  margin: 0 8px;
}
.admin-sidebar__link:hover {
  background: var(--admin-hover, #e5e7eb);
}
.admin-sidebar__item--active .admin-sidebar__link {
  background: var(--admin-accent-soft, #dbeafe);
  font-weight: 600;
}
.admin-sidebar__label { flex: 1; }
.admin-sidebar__badge {
  background: var(--admin-accent, #3b82f6);
  color: #fff;
  font-size: 11px;
  padding: 0 6px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
}
</style>
