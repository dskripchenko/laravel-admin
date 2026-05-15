<script setup lang="ts">
/**
 * AuditTimeline — список изменений конкретной записи.
 *
 * Грузит GET /audit/timeline?subject_type={…}&subject_id={…} (см. backend
 * AuditController::timeline) и рендерит вертикальный timeline по дизайну
 * docs/design_handoff_laravel_admin (Resource View → История изменений).
 *
 * subjectType — Eloquent morph-class модели (например 'App\\Models\\Article'
 * или 'article' если используется map). Если не задан — компонент молча
 * не рендерит timeline (host не включил аудит).
 */
import { computed, onMounted, ref, watch } from 'vue'
import {
  Edit,
  Plus,
  Tag,
  Trash2,
  type LucideIcon,
} from 'lucide-vue-next'
import { UidAvatar, UidIcon, UidSkeleton } from '@dskripchenko/ui'

interface AuditActor {
  id: number | string
  type: string
  name: string | null
}
interface AuditEntry {
  id: number
  event: string
  created_at: string
  actor: AuditActor | null
  summary: string
  diff: Array<{ field: string; before: unknown; after: unknown }> | null
}

interface Props {
  /**
   * Eloquent morph-class либо FQCN модели. Может быть пустой строкой /
   * null — компонент в этом случае не делает запрос и показывает
   * пустой state. Это нужно чтобы блок «История изменений» был всегда
   * виден на view-странице даже когда у Resource не задан model-class.
   */
  subjectType: string | null
  subjectId: string | number
}
const props = defineProps<Props>()

const items = ref<AuditEntry[]>([])
const loading = ref<boolean>(false)
const error = ref<Error | null>(null)

async function load(): Promise<void> {
  // Без subject_type делать запрос бессмысленно — backend ожидает required
  // строку. Просто показываем пустой state.
  if (!props.subjectType || props.subjectType === '') {
    items.value = []
    loading.value = false
    error.value = null
    return
  }
  loading.value = true
  error.value = null
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    const result = await client.get<{ data: AuditEntry[] }>(
      `/audit/timeline?subject_type=${encodeURIComponent(props.subjectType)}&subject_id=${encodeURIComponent(String(props.subjectId))}`,
    )
    items.value = result.data ?? []
  } catch (err) {
    error.value = err instanceof Error ? err : new Error(String(err))
    items.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => [props.subjectType, props.subjectId] as const, load)

const hasItems = computed<boolean>(() => items.value.length > 0)

function eventIcon(event: string): LucideIcon {
  if (event === 'created') return Plus
  if (event === 'deleted' || event === 'destroyed') return Trash2
  if (event.includes('tag') || event.includes('label')) return Tag
  return Edit
}

function eventLabel(event: string): string {
  const map: Record<string, string> = {
    created: 'создал',
    updated: 'отредактировал',
    deleted: 'удалил',
    destroyed: 'удалил',
    restored: 'восстановил',
    published: 'опубликовал',
  }
  return map[event] ?? event
}

function relativeTime(iso: string): string {
  const ts = new Date(iso).getTime()
  if (Number.isNaN(ts)) return ''
  const diff = (Date.now() - ts) / 1000
  if (diff < 60) return `${Math.max(1, Math.floor(diff))} сек назад`
  if (diff < 3600) return `${Math.floor(diff / 60)} мин назад`
  if (diff < 86_400) return `${Math.floor(diff / 3600)} ч назад`
  if (diff < 86_400 * 30) return `${Math.floor(diff / 86_400)} д назад`
  return new Date(iso).toLocaleDateString('ru-RU')
}

function hasDiff(entry: AuditEntry): boolean {
  return !!entry.diff && entry.diff.length > 0
}

function diffSummary(entry: AuditEntry): string {
  // Fall back to the projector's `summary` only when we have no diff details
  // to render — otherwise the generic "Изменено" hides the actual change set.
  if (hasDiff(entry)) return ''
  return entry.summary ?? ''
}

function formatVal(v: unknown): string {
  if (v === null || v === undefined) return '∅'
  if (typeof v === 'boolean') return v ? 'Да' : 'Нет'
  // 0/1 are stored as int but typically reflect booleans in MySQL/Postgres.
  if (v === 0) return 'Нет'
  if (v === 1) return 'Да'
  if (typeof v === 'string') return v.length > 40 ? `${v.slice(0, 40)}…` : v
  if (typeof v === 'object') {
    try {
      return JSON.stringify(v)
    } catch {
      return '[object]'
    }
  }
  return String(v)
}
</script>

<template>
  <section class="admin-audit-timeline">
    <h2 class="admin-audit-timeline__title">История изменений</h2>

    <div v-if="loading" class="admin-audit-timeline__loading">
      <UidSkeleton v-for="i in 3" :key="i" height="48px" />
    </div>

    <div v-else-if="error" class="admin-audit-timeline__error">
      Не удалось загрузить историю: {{ error.message }}
    </div>

    <div v-else-if="!hasItems" class="admin-audit-timeline__empty">
      Записей в истории пока нет.
    </div>

    <ol v-else class="admin-audit-timeline__list">
      <li
        v-for="entry in items"
        :key="entry.id"
        class="admin-audit-timeline__item"
      >
        <span class="admin-audit-timeline__icon" aria-hidden="true">
          <UidIcon :icon="eventIcon(entry.event)" :size="14" />
        </span>
        <div class="admin-audit-timeline__body">
          <div class="admin-audit-timeline__row">
            <UidAvatar
              :name="entry.actor?.name ?? '?'"
              :alt="entry.actor?.name ?? 'Неизвестный'"
              size="xs"
            />
            <span class="admin-audit-timeline__actor">
              {{ entry.actor?.name ?? 'Система' }}
            </span>
            <span class="admin-audit-timeline__verb">
              {{ eventLabel(entry.event) }}
            </span>
            <span class="admin-audit-timeline__when">
              {{ relativeTime(entry.created_at) }}
            </span>
          </div>
          <ul
            v-if="hasDiff(entry)"
            class="admin-audit-timeline__diff"
          >
            <li
              v-for="d in entry.diff"
              :key="d.field"
              class="admin-audit-timeline__diff-row"
            >
              <span class="admin-audit-timeline__diff-field">{{ d.field }}</span>
              <span class="admin-audit-timeline__diff-before">{{ formatVal(d.before) }}</span>
              <span class="admin-audit-timeline__diff-arrow" aria-hidden="true">→</span>
              <span class="admin-audit-timeline__diff-after">{{ formatVal(d.after) }}</span>
            </li>
          </ul>
          <div v-else-if="diffSummary(entry)" class="admin-audit-timeline__detail">
            {{ diffSummary(entry) }}
          </div>
        </div>
      </li>
    </ol>
  </section>
</template>

<style>
.admin-audit-timeline {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
}
.admin-audit-timeline__title {
  margin: 0;
  font-family: var(--uid-font-family-display);
  font-size: var(--uid-font-size-lg, 16px);
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
}
.admin-audit-timeline__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md);
  position: relative;
}
.admin-audit-timeline__list::before {
  content: '';
  position: absolute;
  left: 11px;
  top: 12px;
  bottom: 12px;
  width: 2px;
  background: var(--uid-border-subtle);
}
.admin-audit-timeline__item {
  position: relative;
  display: flex;
  gap: var(--uid-space-sm);
  padding-left: 2px;
}
.admin-audit-timeline__icon {
  position: relative;
  z-index: 1;
  flex: none;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--uid-surface-base);
  border: 1px solid var(--uid-border-subtle);
  color: var(--uid-text-secondary);
}
.admin-audit-timeline__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-top: 2px;
}
.admin-audit-timeline__row {
  display: flex;
  align-items: center;
  gap: var(--uid-space-2xs);
  flex-wrap: wrap;
}
.admin-audit-timeline__actor {
  font-size: 13px;
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
}
.admin-audit-timeline__verb {
  font-size: 13px;
  color: var(--uid-text-secondary);
}
.admin-audit-timeline__when {
  margin-left: auto;
  font-size: 12px;
  color: var(--uid-text-tertiary);
}
.admin-audit-timeline__detail {
  font-size: 12px;
  color: var(--uid-text-tertiary);
}
.admin-audit-timeline__diff {
  list-style: none;
  margin: var(--uid-space-2xs) 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.admin-audit-timeline__diff-row {
  display: grid;
  grid-template-columns: minmax(100px, max-content) 1fr auto 1fr;
  gap: var(--uid-space-xs);
  align-items: baseline;
  font-size: 12px;
}
.admin-audit-timeline__diff-field {
  font-weight: var(--uid-font-weight-medium);
  color: var(--uid-text-secondary);
}
.admin-audit-timeline__diff-before {
  color: var(--uid-text-tertiary);
  text-decoration: line-through;
  word-break: break-word;
}
.admin-audit-timeline__diff-arrow {
  color: var(--uid-text-tertiary);
}
.admin-audit-timeline__diff-after {
  color: var(--uid-text-primary);
  word-break: break-word;
}
.admin-audit-timeline__empty,
.admin-audit-timeline__error {
  font-size: 13px;
  color: var(--uid-text-tertiary);
}
.admin-audit-timeline__error { color: var(--uid-color-danger, #dc2626); }
.admin-audit-timeline__loading {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-xs);
}
</style>
