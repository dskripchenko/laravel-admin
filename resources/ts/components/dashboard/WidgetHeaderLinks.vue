<script setup lang="ts">
/**
 * The header links of a widget (Widget::headerLink(), 1.75): small links in a
 * row. A panel path opens in place, an external address in a new tab.
 */
import { testIdOf } from '../../composables/testId'
import { isExternalUrl, type WidgetHeaderLink } from './widgetHeader'

defineProps<{ links: WidgetHeaderLink[] }>()

function testId(link: WidgetHeaderLink): string {
  return link.key ? testIdOf('widget-header-link', link.key) : 'widget-header-link'
}
</script>

<template>
  <nav v-if="links.length > 0" class="admin-widget__links">
    <template v-for="(link, i) in links" :key="link.key ?? i">
      <a
        v-if="isExternalUrl(link.url)"
        :href="link.url"
        target="_blank"
        rel="noopener"
        class="admin-widget__link"
        :data-testid="testId(link)"
      >{{ link.label }}</a>
      <router-link
        v-else
        :to="link.url"
        class="admin-widget__link"
        :data-testid="testId(link)"
      >{{ link.label }}</router-link>
    </template>
  </nav>
</template>

<style>
.admin-widget__links {
  display: inline-flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--uid-space-xs, 6px) var(--uid-space-sm, 12px);
  flex: 0 0 auto;
  max-width: 60%;
}
.admin-widget__link {
  font-size: var(--uid-font-size-sm, 13px);
  color: var(--uid-accent);
  text-decoration: none;
  white-space: nowrap;
}
.admin-widget__link:hover {
  text-decoration: underline;
}
</style>
