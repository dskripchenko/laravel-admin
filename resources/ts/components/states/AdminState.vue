<script setup lang="ts">
/**
 * AdminState — the one look of a place with nothing to show: a list, a tab, a
 * widget or a whole page. It takes the place of the content; the page's
 * header and filters stay where they are.
 *
 *   - `empty` — nothing there yet: a title, a hint, an action (the slot);
 *   - `no-results` — nothing matches the search or the filters;
 *   - `error` — the data did not load: the reason, «Повторить» (emits
 *     `retry`), and the request's code with «Скопировать код» — the code is
 *     the X-Request-Id the server wrote into its log;
 *   - `forbidden` — no access: the section's name, the permission key with
 *     «Скопировать ключ», and the way to the desktop.
 *
 * `error` takes any thrown value: the code, the time and, for a 403, the
 * permission come from it (api/errors: errorTrace(), forbiddenPermission()).
 */
import { computed, getCurrentInstance } from 'vue'
import type { Router } from 'vue-router'
import { UidButton, UidEmptyState, UidErrorState, UidIcon } from '@dskripchenko/ui'
import { Copy, RotateCcw } from 'lucide-vue-next'
import { apiErrorMessage, errorTrace, forbiddenPermission } from '../../api/errors'
import { formatDate, trSafe as tr, tRaw } from '../../stores/i18n'
import { adminToast } from '../../stores/toast'

export type AdminStateKind = 'empty' | 'no-results' | 'error' | 'forbidden'

interface Props {
  kind: AdminStateKind
  title?: string | null
  description?: string | null
  /** The failure behind `error` / `forbidden`. */
  error?: unknown
  /** The section's name for `forbidden`: «Раздел «Журнал аудита» недоступен». */
  section?: string | null
  /** The missing permission key; taken from a 403 when not given. */
  permission?: string | null
  /** «Повторить» under an error; off where nothing can be retried. */
  retryable?: boolean
  /** «На рабочий стол» under `forbidden`. */
  home?: boolean
  /** A smaller frame: a widget, a tab. */
  compact?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: null,
  description: null,
  error: undefined,
  section: null,
  permission: null,
  retryable: true,
  home: false,
  compact: false,
})

const emit = defineEmits<{ retry: [] }>()

// The router when there is one: a state drawn outside the panel's app (a
// test, a host page) still renders.
const router = (getCurrentInstance()?.appContext.config.globalProperties.$router ?? null) as Router | null

const trace = computed(() => errorTrace(props.error))

const permissionKey = computed<string | null>(() => props.permission ?? forbiddenPermission(props.error))

const errorTitle = computed<string>(() => props.title ?? tr('Не удалось загрузить данные'))
const errorDescription = computed<string>(() =>
  props.description ?? apiErrorMessage(props.error, tr('Попробуйте повторить через минуту.')),
)

/** `req 7f3a-91c2 · 13.11 14:40` */
const traceLine = computed<string | null>(() => {
  const { requestId, at } = trace.value
  if (requestId === null) return null
  const when = at ? formatDate(at, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : ''
  return when ? `req ${requestId} · ${when}` : `req ${requestId}`
})

const forbiddenTitle = computed<string>(() => {
  if (props.title) return props.title
  return props.section
    ? tRaw('Раздел «:section» недоступен', { section: props.section })
    : tr('Нет доступа')
})

async function copy(text: string, done: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(text)
    adminToast.success(done)
  } catch {
    adminToast.error(tr('Не удалось скопировать.'))
  }
}

function copyCode(): void {
  if (traceLine.value) void copy(traceLine.value, tr('Код запроса скопирован.'))
}

function copyKey(): void {
  if (permissionKey.value) void copy(permissionKey.value, tr('Ключ скопирован.'))
}

function goHome(): void {
  void router?.push({ name: 'admin.home' }).catch(() => undefined)
}
</script>

<template>
  <div class="admin-state" :class="[`admin-state--${kind}`, { 'admin-state--compact': compact }]" :data-testid="`state-${kind}`">
    <UidErrorState
      v-if="kind === 'error'"
      :title="errorTitle"
      :description="errorDescription"
      role="alert"
    >
      <template #actions>
        <div class="admin-state__actions">
          <UidButton v-if="retryable" variant="primary" size="sm" data-testid="state-retry" @click="emit('retry')">
            <template #prepend><UidIcon :icon="RotateCcw" :size="14" /></template>
            {{ tr('Повторить') }}
          </UidButton>
          <UidButton v-if="traceLine" variant="ghost" size="sm" data-testid="state-copy-code" @click="copyCode">
            <template #prepend><UidIcon :icon="Copy" :size="14" /></template>
            {{ tr('Скопировать код') }}
          </UidButton>
          <slot name="actions" />
        </div>
        <div v-if="traceLine" class="admin-state__trace" data-testid="state-code">{{ traceLine }}</div>
      </template>
    </UidErrorState>

    <UidErrorState
      v-else-if="kind === 'forbidden'"
      code="403"
      :title="forbiddenTitle"
    >
      <template #description>
        <span v-if="description">{{ description }}</span>
        <span v-else-if="permissionKey">
          {{ tr('Нужен ключ') }}
          <code class="admin-state__key" data-testid="state-permission">{{ permissionKey }}</code>.
          {{ tr('Доступ выдаёт администратор панели.') }}
        </span>
        <span v-else>{{ tr('Доступ выдаёт администратор панели.') }}</span>
      </template>
      <template #actions>
        <div class="admin-state__actions">
          <UidButton v-if="permissionKey" variant="secondary" size="sm" data-testid="state-copy-key" @click="copyKey">
            <template #prepend><UidIcon :icon="Copy" :size="14" /></template>
            {{ tr('Скопировать ключ') }}
          </UidButton>
          <UidButton v-if="home" variant="ghost" size="sm" data-testid="state-home" @click="goHome">
            {{ tr('На рабочий стол') }}
          </UidButton>
          <slot name="actions" />
        </div>
      </template>
    </UidErrorState>

    <UidEmptyState
      v-else
      :title="title ?? (kind === 'no-results' ? tr('Ничего не найдено') : tr('Записей нет'))"
      :description="description ?? undefined"
    >
      <template v-if="$slots.actions" #actions>
        <div class="admin-state__actions">
          <slot name="actions" />
        </div>
      </template>
    </UidEmptyState>
  </div>
</template>

<style>
.admin-state {
  min-width: 0;
}
.admin-state__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: var(--uid-space-sm, 8px);
}
.admin-state__trace {
  margin-top: var(--uid-space-sm, 8px);
  font-family: var(--uid-font-mono, monospace);
  font-size: var(--uid-font-size-xs, 12px);
  color: var(--uid-text-tertiary, var(--uid-text-secondary));
  text-align: center;
}
.admin-state__key {
  font-family: var(--uid-font-mono, monospace);
  padding: 1px 4px;
  border-radius: var(--uid-radius-sm, 4px);
  background: var(--uid-surface-sunken, rgba(0, 0, 0, 0.05));
}
.admin-state--compact .uid-empty-state,
.admin-state--compact .uid-error-state {
  padding: var(--uid-space-md, 12px);
}
</style>
