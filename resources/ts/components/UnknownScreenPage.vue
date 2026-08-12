<script setup lang="ts">
/**
 * The stand-in for the manifest's arbitrary screen routes.
 * Hosts override it through createAdminApp({ pages: { screen: MyScreen } })
 * and render `route.params.slug` from a component of their own.
 */
import { computed } from 'vue'
import { tRaw } from '../stores/i18n'
import { useRoute } from 'vue-router'
import { UidCard } from '@dskripchenko/ui'

const route = useRoute()
const slug = computed(() => String(route.params.slug ?? ''))
const title = computed(() => String(route.meta?.title ?? slug.value))
</script>

<template>
  <div class="admin-status-page">
    <UidCard padding="lg" class="admin-status-page__card">
      <h1 class="admin-status-page__title">{{ title }}</h1>
      <p class="admin-status-page__hint">
        {{ tRaw('Это заглушка экрана «:slug». Передайте свой компонент через', { slug }) }}
        <code>createAdminApp({{ '{' }} pages: {{ '{' }} screen: MyScreen {{ '}' }} {{ '}' }})</code>.
      </p>
    </UidCard>
  </div>
</template>

<style>
.admin-status-page__title {
  font-size: var(--uid-font-size-xl);
  font-weight: var(--uid-font-weight-semibold);
  margin: 0 0 var(--uid-space-sm);
}
.admin-status-page__hint {
  margin: 0;
  color: var(--uid-text-secondary);
  font-size: var(--uid-font-size-sm);
}
</style>
