<script setup lang="ts">
/**
 * EmbeddedScreenLayout — another screen shown in place, the backend layout
 * `'admin.screen'` (Layout::screen(), see src/Layout/EmbeddedScreen.php):
 * «Настройки сайта · Капча» as a tab of the site settings.
 *
 * It is ScreenPage itself in its embedded form: its own store, state, command
 * bar, methods and messages, loaded through the screen's own endpoints with
 * `params` as its query. The server leaves the layout out for a user the
 * screen's permissions do not let in.
 */
import ScreenPage from '../ScreenPage.vue'

interface Props {
  /** The embedded screen's slug. */
  screen: string
  /** Its query, as its address would carry it. */
  params?: Record<string, string> | null
  /** Whether to show its name above it. */
  showTitle?: boolean
}

withDefaults(defineProps<Props>(), { params: null, showTitle: true })
</script>

<template>
  <div class="admin-embedded-screen" data-testid="embedded-screen">
    <ScreenPage :slug="screen" :params="params ?? {}" :show-title="showTitle" embedded />
  </div>
</template>

<style>
.admin-embedded-screen {
  min-width: 0;
}
</style>
