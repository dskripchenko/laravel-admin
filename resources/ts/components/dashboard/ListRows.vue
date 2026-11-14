<script setup lang="ts">
/**
 * Rows of a list without a table header (ListWidget, and the list under the
 * numbers of StatsOverviewWidget, 1.75): a caption, a secondary text and a
 * badge with its tone, the whole row a link when it has a url.
 */
import { computed } from 'vue'
import { UidBadge } from '@dskripchenko/ui'
import { badgeVariant } from '../tones'
import { testIdOf } from '../../composables/testId'
import { formatNumber, trSafe as tr } from '../../stores/i18n'
import { isExternalUrl } from './widgetHeader'

export interface ListRowItem {
  label?: string
  secondary?: string | null
  badge?: string | number | null
  tone?: string | null
  url?: string | null
  key?: string | null
}

const props = withDefaults(defineProps<{ items?: ListRowItem[]; emptyText?: string | null }>(), {
  items: () => [],
  emptyText: null,
})

interface Row {
  label: string
  secondary: string | null
  badge: string | null
  variant: ReturnType<typeof badgeVariant>
  url: string | null
  testId: string
}

const rows = computed<Row[]>(() =>
  props.items.map((item) => ({
    label: item.label ?? '',
    secondary: typeof item.secondary === 'string' && item.secondary !== '' ? item.secondary : null,
    badge: typeof item.badge === 'number'
      ? formatNumber(item.badge)
      : (typeof item.badge === 'string' && item.badge !== '' ? item.badge : null),
    variant: badgeVariant(item.tone),
    url: typeof item.url === 'string' && item.url !== '' ? item.url : null,
    testId: item.key ? testIdOf('list-item', item.key) : 'list-item',
  })),
)

function linkAttrs(url: string): Record<string, string> {
  return isExternalUrl(url) ? { href: url, target: '_blank', rel: 'noopener' } : { to: url }
}
</script>

<template>
  <ul v-if="rows.length > 0" class="admin-list-rows">
    <li v-for="(row, i) in rows" :key="i" class="admin-list-rows__item">
      <component
        :is="row.url ? (isExternalUrl(row.url) ? 'a' : 'router-link') : 'div'"
        class="admin-list-rows__row"
        :class="{ 'admin-list-rows__row--link': !!row.url }"
        :data-testid="row.testId"
        v-bind="row.url ? linkAttrs(row.url) : {}"
      >
        <span class="admin-list-rows__label" :title="row.label">{{ row.label }}</span>
        <span class="admin-list-rows__end">
          <span v-if="row.secondary !== null" class="admin-list-rows__secondary">{{ row.secondary }}</span>
          <UidBadge v-if="row.badge !== null" :variant="row.variant" size="sm" class="admin-list-rows__badge">{{ row.badge }}</UidBadge>
        </span>
      </component>
    </li>
  </ul>
  <div v-else class="admin-list-rows__empty" data-testid="widget-list-empty">
    {{ emptyText ?? tr('Нет данных') }}
  </div>
</template>

<style>
.admin-list-rows {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.admin-list-rows__item + .admin-list-rows__item {
  border-top: 1px solid var(--uid-border-subtle, var(--uid-color-zinc-100, #f4f4f5));
}
.admin-list-rows__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--uid-space-sm, 12px);
  padding: 8px 0;
  min-width: 0;
  color: var(--uid-text-primary);
  text-decoration: none;
  font-size: var(--uid-font-size-sm, 13px);
}
.admin-list-rows__row--link:hover .admin-list-rows__label {
  color: var(--uid-accent);
}
.admin-list-rows__label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.admin-list-rows__end {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-xs, 8px);
  flex: 0 0 auto;
  max-width: 60%;
}
.admin-list-rows__secondary {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: var(--uid-font-size-xs, 12px);
  color: var(--uid-text-secondary);
}
.admin-list-rows__badge {
  font-variant-numeric: tabular-nums;
}
.admin-list-rows__empty {
  padding: var(--uid-space-sm, 12px) 0;
  font-size: var(--uid-font-size-sm, 13px);
  color: var(--uid-text-tertiary);
}
</style>
