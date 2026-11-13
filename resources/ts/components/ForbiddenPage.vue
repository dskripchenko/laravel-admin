<script setup lang="ts">
/**
 * The 403 fallback; a host may override it through createAdminApp({ pages: { forbidden } }).
 *
 * The router guard sends the section's name and the missing key along
 * (`?section=…&permission=…`): the page says which section is closed and
 * which key opens it, with «Скопировать ключ» for the request to the
 * administrator.
 */
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { UidCard } from '@dskripchenko/ui'
import AdminState from './states/AdminState.vue'

const route = useRoute()
const section = computed<string | null>(() => (typeof route.query.section === 'string' && route.query.section !== '' ? route.query.section : null))
const permission = computed<string | null>(() => (typeof route.query.permission === 'string' && route.query.permission !== '' ? route.query.permission : null))
</script>

<template>
  <div class="admin-status-page" data-testid="forbidden-page">
    <UidCard padding="lg" class="admin-status-page__card">
      <AdminState kind="forbidden" :section="section" :permission="permission" home />
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
