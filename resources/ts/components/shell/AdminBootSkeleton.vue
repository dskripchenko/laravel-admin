<script setup lang="ts">
/**
 * Заглушка страницы на время загрузки каркаса (см. useAppReady).
 *
 * Форма повторяет типовую страницу — заголовок, полоса действий, блоки
 * содержимого, — чтобы переход к реальному контенту не двигал layout.
 * Показывать здесь настоящую страницу нельзя: до манифеста она не знает,
 * что рендерить, и успевает нарисовать чужой экран.
 */
import { UidSkeleton } from '@dskripchenko/ui'
</script>

<template>
  <div class="admin-page admin-boot-skeleton" aria-busy="true" aria-live="polite">
    <div class="admin-boot-skeleton__hd">
      <UidSkeleton width="220px" height="26px" />
      <UidSkeleton width="140px" height="32px" />
    </div>
    <div class="admin-boot-skeleton__row">
      <UidSkeleton height="88px" />
      <UidSkeleton height="88px" />
    </div>
    <UidSkeleton height="240px" />
  </div>
</template>

<style>
.admin-boot-skeleton {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.admin-boot-skeleton__hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.admin-boot-skeleton__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
@media (max-width: 720px) {
  .admin-boot-skeleton__row {
    grid-template-columns: 1fr;
  }
}
</style>
