<script setup lang="ts">
/**
 * Default страница для admin.settings.{slug} роутов.
 *
 * Загружает settings-meta из manifest'а и рендерит fields через LayoutRenderer
 * с form-state поверх SettingsApiClient. Host'ы переопределяют через
 * createAdminApp({ pages: { settings: MySettings } }) если нужна более
 * специфичная UX (например, side-tab навигация по Settings-классам).
 */
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { UidCard } from '@dskripchenko/ui'
import { useManifestStore } from '../stores/manifest'

const route = useRoute()
const manifest = useManifestStore()

const slug = computed(() => String(route.params.slug ?? ''))
const meta = computed(() => manifest.manifest?.settings?.find((s) => s.slug === slug.value) ?? null)
</script>

<template>
  <div class="admin-settings-page">
    <UidCard padding="lg">
      <h1 class="admin-settings-page__title">{{ meta?.label ?? slug }}</h1>
      <p v-if="!meta" class="admin-settings-page__hint">
        Settings «{{ slug }}» не найдено в manifest'е.
      </p>
      <pre v-else class="admin-settings-page__debug">{{ JSON.stringify(meta.fields, null, 2) }}</pre>
    </UidCard>
  </div>
</template>

<style>
.admin-settings-page {
  padding: var(--uid-space-md);
}
.admin-settings-page__title {
  font-size: var(--uid-font-size-xl);
  font-weight: var(--uid-font-weight-semibold);
  margin: 0 0 var(--uid-space-sm);
}
.admin-settings-page__debug {
  background: var(--uid-surface-raised);
  border: 1px solid var(--uid-border-subtle);
  padding: var(--uid-space-sm);
  border-radius: var(--uid-radius-sm);
  font-family: var(--uid-font-mono);
  font-size: var(--uid-font-size-xs);
  overflow: auto;
  max-height: 60vh;
}
.admin-settings-page__hint {
  color: var(--uid-text-secondary);
}
</style>
