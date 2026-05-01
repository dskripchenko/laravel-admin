<script setup lang="ts">
/**
 * DashboardPage — 12-col grid из widget'ов по эталону handoff'а
 * (docs/design_handoff_laravel_admin/screens-secondary.jsx → Dashboard).
 *
 * Источник widget'ов:
 *   - Если задан `slug` — берём dashboard из manifest.dashboards по slug
 *   - Если задан `widgets` prop — рендерим переданные узлы напрямую
 *
 * Каждый widget — `{ type: 'stat', span: 3, title: '...', value: 1284 }`,
 * span — колонки в 12-grid (1..12), default 12 (full width).
 */
import { computed } from 'vue'
import { useManifestStore } from '../../stores/manifest'
import WidgetRenderer, { type WidgetNode } from './WidgetRenderer.vue'

interface DashboardManifest {
  slug: string
  label?: string
  description?: string | null
  widgets: WidgetNode[]
}

interface Props {
  /** Slug dashboard'а в manifest.dashboards. */
  slug?: string
  /** Прямой override widget'ов (если slug не задан или не нашёлся). */
  widgets?: WidgetNode[]
  /** Заголовок страницы. */
  title?: string
  /** Подзаголовок (description). */
  subtitle?: string
}

const props = withDefaults(defineProps<Props>(), {
  slug: undefined,
  widgets: undefined,
  title: undefined,
  subtitle: undefined,
})

const manifest = useManifestStore()

const dashboard = computed<DashboardManifest | null>(() => {
  if (!props.slug || !manifest.manifest) return null
  const dashboards = manifest.manifest.dashboards as DashboardManifest[] | undefined
  return dashboards?.find((d) => d.slug === props.slug) ?? null
})

const resolvedWidgets = computed<WidgetNode[]>(() => {
  if (props.widgets) return props.widgets
  if (dashboard.value) return dashboard.value.widgets ?? []
  return []
})

const resolvedTitle = computed(
  () => props.title ?? dashboard.value?.label ?? 'Dashboard',
)
const resolvedSubtitle = computed(
  () => props.subtitle ?? dashboard.value?.description ?? null,
)

function spanFor(w: WidgetNode): number {
  const s = typeof w.span === 'number' ? w.span : 12
  return Math.max(1, Math.min(12, s))
}
</script>

<template>
  <section class="admin-page admin-dashboard">
    <header class="admin-page__hd">
      <div class="admin-page__title-wrap">
        <h1 class="admin-page__title">{{ resolvedTitle }}</h1>
        <div v-if="resolvedSubtitle" class="admin-page__count">
          {{ resolvedSubtitle }}
        </div>
      </div>
      <div class="admin-page__actions">
        <slot name="actions" />
      </div>
    </header>

    <div class="admin-dashboard__grid">
      <div
        v-for="(w, idx) in resolvedWidgets"
        :key="idx"
        class="admin-dashboard__cell"
        :style="{ gridColumn: `span ${spanFor(w)} / span ${spanFor(w)}` }"
      >
        <WidgetRenderer :node="w" />
      </div>
    </div>
  </section>
</template>

<style>
.admin-dashboard__grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: var(--uid-space-md);
}
.admin-dashboard__cell {
  min-width: 0;
}
</style>
