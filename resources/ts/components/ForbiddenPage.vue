<script setup lang="ts">
/**
 * 403 fallback. Host может переопределить через createAdminApp({ pages: { forbidden } }).
 */
import { UidCard, UidButton } from '@dskripchenko/ui'
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="admin-status-page">
    <UidCard padding="lg" class="admin-status-page__card">
      <h1 class="admin-status-page__code">403</h1>
      <p class="admin-status-page__message">Доступ запрещён.</p>
      <UidButton variant="ghost" @click="router.replace({ name: 'admin.home' })">
        На главную
      </UidButton>
    </UidCard>
  </div>
</template>

<style>
.admin-status-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  padding: var(--uid-space-lg);
}
.admin-status-page__card {
  text-align: center;
  max-width: 480px;
}
.admin-status-page__code {
  font-size: 64px;
  font-weight: var(--uid-font-weight-bold);
  margin: 0 0 var(--uid-space-sm);
  color: var(--uid-text-secondary);
}
.admin-status-page__message {
  margin: 0 0 var(--uid-space-md);
  color: var(--uid-text-secondary);
}
</style>
