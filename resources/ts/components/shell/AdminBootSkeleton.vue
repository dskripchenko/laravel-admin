<script setup lang="ts">
/**
 * The page's stand-in while the shell loads; see useAppReady.
 *
 * Its shape repeats a typical page — a heading, a row of actions, blocks of
 * content — so that the switch to the real content does not move the layout.
 * Showing the real page here is not an option: before the manifest it does not
 * know what to render, and manages to draw the wrong screen.
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
