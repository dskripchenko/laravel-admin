<script setup lang="ts">
/**
 * ResourceViewPage — read-only зеркало form-страницы по дизайну
 * docs/design_handoff_laravel_admin (Resource View):
 *
 *   ← Articles                                Edit … (more menu)
 *   {Title}
 *   ● Status badge · A-{id}
 *
 *   ┌──────────────────────────────┬─────────────────┐
 *   │ Card "Основные данные"        │ Card "Метрики"  │
 *   │ Infolist (manifest.infolist) │ <slot=sidebar>  │
 *   ├──────────────────────────────┤                 │
 *   │ Card "История изменений"      │                 │
 *   │ AuditTimeline                 │                 │
 *   └──────────────────────────────┴─────────────────┘
 */
import { computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeft, MoreHorizontal, Pencil, Trash2 } from 'lucide-vue-next'
import {
  UidAlert,
  UidButton,
  UidCard,
  UidIcon,
  UidMenu,
  UidMenuItem,
  UidSkeleton,
} from '@dskripchenko/ui'
import { useResourceFormStore } from '../../stores/resourceForm'
import { useManifestStore } from '../../stores/manifest'
import InfolistRenderer from '../infolist/InfolistRenderer.vue'
import type { InfolistNode } from '../infolist/InfolistRenderer.vue'
import { provideRecord } from '../infolist/recordContext'
import AuditTimeline from './AuditTimeline.vue'

interface Props {
  slug: string
  id: string | number
  /** Имя router-route для перехода в edit. */
  editRouteName?: string
  /**
   * Override имени router-route для back-link / возврата после delete.
   * По умолчанию выводится из slug: `admin.resource.{slug}.index`. Если
   * у host'а собственное имя — передаётся явно.
   */
  indexRouteName?: string | null
  /**
   * Override Eloquent morph-class. По умолчанию резолвится из
   * `manifest.resources[slug].subject_type` (Resource::meta()).
   */
  auditSubjectType?: string | null
  /** Поле в record, по которому считать status badge. По умолчанию `status`. */
  statusField?: string
  /** Префикс UID-метки рядом со статусом. Например 'A-' даёт 'A-1284'. */
  uidPrefix?: string
}

const props = withDefaults(defineProps<Props>(), {
  editRouteName: undefined,
  indexRouteName: null,
  auditSubjectType: null,
  statusField: 'status',
  uidPrefix: '#',
})

const form = useResourceFormStore()
const manifest = useManifestStore()
const router = useRouter()

provideRecord(form.state)

const resourceMeta = computed(() => manifest.getResource(props.slug))
const isEditable = computed<boolean>(() => {
  const features = (resourceMeta.value?.features ?? {}) as Record<string, unknown>
  return features.editable !== false
})
const layoutNodes = computed<InfolistNode[]>(
  () => (resourceMeta.value?.infolist ?? resourceMeta.value?.fields ?? []) as InfolistNode[],
)

/**
 * subject_type для AuditTimeline. Берём в порядке приоритета:
 *   1. props.auditSubjectType (host явно задал) — даже null допустим как
 *      explicit "не показывать".
 *   2. manifest.resources[slug].subject_type — backend Resource::meta()
 *      кладёт morph-alias / FQCN модели.
 *
 * Если оба пусты — timeline не рендерится (Resource без модели либо
 * morph-class не известен фронту).
 */
const resolvedSubjectType = computed<string | null>(() => {
  if (props.auditSubjectType !== null) return props.auditSubjectType
  return (resourceMeta.value?.subject_type as string | null | undefined) ?? null
})

/**
 * Default metrics: created_at / updated_at / created_by из record.
 * Показываем всегда если у записи есть хоть одно из этих полей —
 * стандартные timestamps Eloquent есть у большинства моделей.
 */
interface MetricRow {
  label: string
  value: string
}
const defaultMetrics = computed<MetricRow[]>(() => {
  const r = form.state as Record<string, unknown>
  const rows: MetricRow[] = []
  const fmt = (iso: unknown): string | null => {
    if (typeof iso !== 'string' || iso === '') return null
    const ts = new Date(iso)
    if (Number.isNaN(ts.getTime())) return null
    return ts.toLocaleString('ru-RU', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }
  const created = fmt(r.created_at)
  if (created) rows.push({ label: 'Создано', value: created })
  const updated = fmt(r.updated_at)
  if (updated && updated !== created) rows.push({ label: 'Обновлено', value: updated })
  const author =
    typeof r.created_by_name === 'string' && r.created_by_name !== ''
      ? r.created_by_name
      : typeof r.author === 'string'
        ? r.author
        : null
  if (author) rows.push({ label: 'Автор', value: author })
  return rows
})
const recordTitle = computed<string>(() => {
  // Запись может иметь поле `title` / `name` / `label` — пробуем по очереди.
  // Иначе fallback на "{ResourceLabel}: запись #{id}".
  const r = form.state as Record<string, unknown>
  const t = r.title ?? r.name ?? r.label
  if (typeof t === 'string' && t.length > 0) return t
  return `${resourceMeta.value?.label ?? props.slug}: запись #${props.id}`
})
const indexLabel = computed<string>(
  () => resourceMeta.value?.label ?? props.slug,
)
const uidLabel = computed<string>(() => `${props.uidPrefix}${props.id}`)

const statusValue = computed<string | null>(() => {
  const r = form.state as Record<string, unknown>
  const v = r[props.statusField]
  return typeof v === 'string' && v.length > 0 ? v : null
})

/**
 * Маппинг status-значения → визуальный variant. Расширяемо через CSS:
 * `.admin-status-badge[data-status="custom"] { ... }`.
 */
const STATUS_VARIANT: Record<string, string> = {
  published: 'success',
  active: 'success',
  enabled: 'success',
  draft: 'neutral',
  pending: 'warning',
  review: 'warning',
  archived: 'muted',
  disabled: 'muted',
  inactive: 'muted',
  deleted: 'danger',
  failed: 'danger',
  error: 'danger',
}
function statusVariant(s: string): string {
  return STATUS_VARIANT[s.toLowerCase()] ?? 'neutral'
}

/**
 * Header more-actions — показываем те же `actions[]` из manifest'а что
 * и в index page; здесь они применяются к одной записи (id). Реальный
 * endpoint POST `/{slug}/action/{key}` с body {ids:[id]}.
 */
interface HeaderAction {
  key: string
  label: string
  confirm?: string
}
const headerActions = computed<HeaderAction[]>(() => {
  const raw = (resourceMeta.value?.actions ?? []) as Array<Record<string, unknown>>
  return raw
    .map((a) => ({
      key: String(a.key ?? a.name ?? ''),
      label: String(a.label ?? a.name ?? a.key ?? ''),
      confirm: typeof a.confirm === 'string' ? a.confirm : undefined,
    }))
    .filter((a) => a.key !== '' && a.label !== '')
})

async function onCustomAction(action: HeaderAction): Promise<void> {
  if (action.confirm && !window.confirm(action.confirm)) return
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    await client.post(`/${props.slug}/action/${action.key}`, { ids: [props.id] })
    await form.load(props.slug, props.id, 'view').catch(() => undefined)
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] action failed:', err)
  }
}

onMounted(async () => {
  if (manifest.manifest === null) {
    await manifest.load().catch(() => undefined)
  }
  await form.load(props.slug, props.id, 'view').catch(() => undefined)
})

watch(
  () => [props.slug, props.id] as const,
  async ([s, id]) => {
    await form.load(s, id, 'view').catch(() => undefined)
  },
)

/**
 * Имя index-роута для back-link и redirect после delete. Default —
 * `admin.resource.{slug}.index` (зарегистрирован buildResourceRoutes).
 * Manifest может задать `parent_slug` (см. Resource::parentSlug) — тогда
 * back ведёт на index другого ресурса. Prop indexRouteName имеет
 * максимальный приоритет.
 */
const resolvedIndexRouteName = computed<string>(() => {
  if (props.indexRouteName) return props.indexRouteName
  const parent = manifest.getResource(props.slug)?.parent_slug
  if (parent) return `admin.resource.${parent}.index`
  return `admin.resource.${props.slug}.index`
})

function onBack(): void {
  router.push({ name: resolvedIndexRouteName.value }).catch(() => {
    // Fallback на path-based push если route не найден.
    void router.push(`/r/${props.slug}`)
  })
}

function onEdit(): void {
  if (!props.editRouteName) {
    router
      .push(`/r/${props.slug}/${props.id}/edit`)
      .catch(() => undefined)
    return
  }
  router
    .push({ name: props.editRouteName, params: { id: props.id } })
    .catch(() => undefined)
}

async function onDelete(): Promise<void> {
  if (!confirm('Удалить запись?')) return
  await form.destroy().catch(() => undefined)
  if (!form.hasError) {
    router.push({ name: resolvedIndexRouteName.value }).catch(() => undefined)
  }
}
</script>

<template>
  <section class="admin-page admin-resource-view">
    <header class="admin-resource-view__hd">
      <div class="admin-resource-view__hd-left">
        <button type="button" class="admin-resource-view__back" @click="onBack">
          <UidIcon :icon="ArrowLeft" :size="14" />
          {{ indexLabel }}
        </button>
        <h1 class="admin-page__title">{{ recordTitle }}</h1>
        <div class="admin-resource-view__meta">
          <span
            v-if="statusValue"
            class="admin-status-badge"
            :data-variant="statusVariant(statusValue)"
            :data-status="statusValue"
          >
            <span class="admin-status-badge__dot" aria-hidden="true" />
            {{ statusValue }}
          </span>
          <span class="admin-resource-view__uid">{{ uidLabel }}</span>
        </div>
      </div>
      <div class="admin-resource-view__hd-right">
        <UidButton v-if="isEditable" variant="secondary" size="md" @click="onEdit">
          <template #prepend><UidIcon :icon="Pencil" :size="14" /></template>
          Редактировать
        </UidButton>
        <UidMenu>
          <template #trigger>
            <UidButton variant="ghost" size="md" aria-label="Действия" class="admin-page__more">
              <UidIcon :icon="MoreHorizontal" :size="16" />
            </UidButton>
          </template>
          <UidMenuItem
            v-for="a in headerActions"
            :key="a.key"
            @click="onCustomAction(a)"
          >
            {{ a.label }}
          </UidMenuItem>
          <UidMenuItem variant="danger" @click="onDelete">
            <template #icon><UidIcon :icon="Trash2" :size="14" /></template>
            Удалить
          </UidMenuItem>
        </UidMenu>
      </div>
    </header>

    <UidAlert
      v-if="form.hasError"
      variant="danger"
      class="admin-resource-view__alert"
      role="alert"
    >
      {{ form.error?.message ?? 'Не удалось загрузить запись' }}
    </UidAlert>

    <div v-if="form.loading" class="admin-resource-view__loading">
      <UidSkeleton v-for="i in 8" :key="i" height="24px" />
    </div>

    <div v-else class="admin-resource-view__grid">
      <div class="admin-resource-view__main">
        <UidCard padding="md" class="admin-resource-view__card">
          <h2 class="admin-resource-view__card-title">Основные данные</h2>
          <InfolistRenderer
            v-for="(node, idx) in layoutNodes"
            :key="idx"
            :node="node"
          />
        </UidCard>

        <UidCard
          padding="md"
          class="admin-resource-view__card"
        >
          <AuditTimeline
            :subject-type="resolvedSubjectType"
            :subject-id="id"
          />
        </UidCard>
      </div>

      <aside class="admin-resource-view__aside">
        <!--
          Default «Метрики» card — created_at / updated_at / автор из record.
          Host может полностью переопределить через slot=sidebar.
        -->
        <UidCard
          v-if="defaultMetrics.length > 0"
          padding="md"
          class="admin-resource-view__card"
        >
          <h2 class="admin-resource-view__card-title">Метрики</h2>
          <dl class="admin-resource-view__metrics">
            <template v-for="row in defaultMetrics" :key="row.label">
              <dt class="admin-resource-view__metric-label">{{ row.label }}</dt>
              <dd class="admin-resource-view__metric-value">{{ row.value }}</dd>
            </template>
          </dl>
        </UidCard>

        <slot name="sidebar" :record="form.state" :resource="resourceMeta" />
      </aside>
    </div>
  </section>
</template>

<style>
.admin-resource-view__alert {
  margin-bottom: var(--uid-space-md);
}
.admin-resource-view__loading {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
}

/* Header */
.admin-resource-view__hd {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--uid-space-md);
  margin-bottom: var(--uid-space-md);
}
.admin-resource-view__hd-left {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-2xs);
  min-width: 0;
}
.admin-resource-view__hd-right {
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm);
  flex: none;
}
.admin-resource-view__back {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-2xs);
  padding: 0;
  margin: 0;
  font-size: 13px;
  color: var(--uid-text-tertiary);
  background: transparent;
  border: 0;
  cursor: pointer;
  align-self: flex-start;
}
.admin-resource-view__back:hover {
  color: var(--uid-text-primary);
}
.admin-resource-view__meta {
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm);
  margin-top: var(--uid-space-2xs);
}
.admin-resource-view__uid {
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
  font-size: 12px;
  color: var(--uid-text-tertiary);
  letter-spacing: 0.02em;
}

/* Status badge */
.admin-status-badge {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-2xs);
  padding: 2px 8px 2px 6px;
  border-radius: var(--uid-radius-sm);
  font-size: 12px;
  font-weight: var(--uid-font-weight-medium);
  text-transform: capitalize;
}
.admin-status-badge__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  flex: none;
}
.admin-status-badge[data-variant='success'] {
  background: color-mix(in srgb, var(--uid-color-success, #10b981) 15%, transparent);
  color: var(--uid-color-success, #10b981);
}
.admin-status-badge[data-variant='warning'] {
  background: color-mix(in srgb, var(--uid-color-warning, #f59e0b) 15%, transparent);
  color: var(--uid-color-warning, #f59e0b);
}
.admin-status-badge[data-variant='danger'] {
  background: color-mix(in srgb, var(--uid-color-danger, #dc2626) 15%, transparent);
  color: var(--uid-color-danger, #dc2626);
}
.admin-status-badge[data-variant='neutral'],
.admin-status-badge[data-variant='muted'] {
  background: var(--uid-border-subtle);
  color: var(--uid-text-secondary);
}

/* Two-column grid */
.admin-resource-view__grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: var(--uid-space-md);
  align-items: start;
}
@media (max-width: 1024px) {
  .admin-resource-view__grid {
    grid-template-columns: 1fr;
  }
}
.admin-resource-view__main {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md);
  min-width: 0;
}
.admin-resource-view__aside {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md);
}
.admin-resource-view__card {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md);
}
.admin-resource-view__card-title {
  margin: 0;
  font-family: var(--uid-font-family-display);
  font-size: var(--uid-font-size-lg, 16px);
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
}

/* Metrics list — label/value pair stacked vertically. */
.admin-resource-view__metrics {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md);
  margin: 0;
  padding: 0;
}
.admin-resource-view__metric-label {
  font-size: 11px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-weight: var(--uid-font-weight-medium);
  color: var(--uid-text-tertiary);
  margin: 0;
}
.admin-resource-view__metric-value {
  font-size: 14px;
  color: var(--uid-text-primary);
  margin: 4px 0 0;
  font-variant-numeric: tabular-nums;
}
</style>
