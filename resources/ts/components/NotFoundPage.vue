<script setup lang="ts">
/**
 * The 404 fallback; a host may override it through createAdminApp({ pages: { notFound } }).
 */
import { UidCard, UidButton } from '@dskripchenko/ui'
import { useRouter } from 'vue-router'
import { trSafe as tr } from './../stores/i18n'

const router = useRouter()
</script>

<template>
  <div class="admin-status-page">
    <UidCard padding="lg" class="admin-status-page__card">
      <h1 class="admin-status-page__code">404</h1>
      <p class="admin-status-page__message">{{ tr('Страница не найдена.') }}</p>
      <UidButton variant="ghost" @click="router.replace({ name: 'admin.home' })">
        {{ tr('На главную') }}
      </UidButton>
    </UidCard>
  </div>
</template>
