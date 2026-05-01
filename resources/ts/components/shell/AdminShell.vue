<script setup lang="ts">
/**
 * Корневой layout: top-bar + sidebar + main (router-view).
 *
 * Дизайн намеренно minimal: даёт только семантические узлы и CSS-classes
 * `.admin-shell`, `.admin-shell__topbar`, `.admin-shell__sidebar`,
 * `.admin-shell__main`. Тёмная/светлая тема — через `data-theme` на <html>
 * (theme-store устанавливает атрибут).
 *
 * Slots:
 *   - topbar — подменить весь топбар (по умолчанию AdminTopBar)
 *   - sidebar — подменить сайдбар
 *   - default — main-area (обычно <RouterView/>; рендерится host-проектом)
 */
import AdminTopBar from './AdminTopBar.vue'
import AdminSidebar from './AdminSidebar.vue'
</script>

<template>
  <div class="admin-shell">
    <header class="admin-shell__topbar">
      <slot name="topbar">
        <AdminTopBar />
      </slot>
    </header>
    <aside class="admin-shell__sidebar">
      <slot name="sidebar">
        <AdminSidebar />
      </slot>
    </aside>
    <main class="admin-shell__main">
      <slot />
    </main>
  </div>
</template>

<style>
.admin-shell {
  display: grid;
  grid-template-columns: 240px 1fr;
  grid-template-rows: 56px 1fr;
  grid-template-areas:
    'topbar topbar'
    'sidebar main';
  min-height: 100vh;
}
.admin-shell__topbar { grid-area: topbar; }
.admin-shell__sidebar { grid-area: sidebar; overflow-y: auto; }
.admin-shell__main { grid-area: main; overflow: auto; padding: 16px; }
</style>
