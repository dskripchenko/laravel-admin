<script setup lang="ts">
/**
 * 404 fallback. Host может переопределить через createAdminApp({ pages: { notFound } }).
 */
import { UidCard, UidButton } from '@dskripchenko/ui'
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="admin-status-page">
    <UidCard padding="lg" class="admin-status-page__card">
      <h1 class="admin-status-page__code">404</h1>
      <p class="admin-status-page__message">Страница не найдена.</p>
      <UidButton variant="ghost" @click="router.replace({ name: 'admin.home' })">
        На главную
      </UidButton>
    </UidCard>
  </div>
</template>
