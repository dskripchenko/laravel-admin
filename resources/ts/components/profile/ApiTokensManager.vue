<script setup lang="ts">
/**
 * ApiTokensManager — управление personal-access tokens.
 *
 * Backend endpoints:
 *   GET  /profile/tokensList            — { tokens: [{id, name, last_used_at, created_at}] }
 *   POST /profile/tokenCreate {name}   — { token: 'plain', id, name }
 *   POST /profile/tokenRevoke {id}     — success
 *
 * Plain-token показывается ОДИН раз сразу после создания. Дальше — только
 * id и метаданные.
 */
import { onMounted, ref } from 'vue'
import { Copy, Plus, Trash2 } from 'lucide-vue-next'
import { UidButton, UidIcon, UidInput } from '@dskripchenko/ui'
import { adminToast } from '../../stores/toast'

interface Token {
  id: number
  name: string
  last_used_at: string | null
  created_at: string | null
}

const tokens = ref<Token[]>([])
const loading = ref<boolean>(false)
const newName = ref<string>('')
const justCreated = ref<{ id: number; name: string; token: string } | null>(null)

async function load(): Promise<void> {
  loading.value = true
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    const result = await client.get<{ tokens: Token[] }>('/profile/tokensList')
    tokens.value = result.tokens ?? []
  } catch {
    tokens.value = []
  } finally {
    loading.value = false
  }
}

async function create(): Promise<void> {
  const name = newName.value.trim()
  if (name === '') {
    adminToast.error('Введите название токена.')
    return
  }
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    const result = await client.post<{ id: number; name: string; token: string }>(
      '/profile/tokenCreate',
      { name },
    )
    justCreated.value = result
    newName.value = ''
    await load()
    adminToast.success('Токен создан. Скопируйте plain-значение — оно не будет показано повторно.')
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] token create failed:', err)
    adminToast.error('Не удалось создать токен.')
  }
}

async function revoke(id: number): Promise<void> {
  if (!window.confirm('Отозвать токен? Запросы с ним перестанут работать.')) return
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    await client.post('/profile/tokenRevoke', { id })
    await load()
    adminToast.success('Токен отозван.')
  } catch {
    adminToast.error('Не удалось отозвать токен.')
  }
}

async function copyJustCreated(): Promise<void> {
  if (!justCreated.value) return
  try {
    await navigator.clipboard.writeText(justCreated.value.token)
    adminToast.success('Скопировано.')
  } catch {
    adminToast.warning('Скопируйте вручную.')
  }
}

function dismissJustCreated(): void {
  justCreated.value = null
}

function fmtDate(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(load)
</script>

<template>
  <div class="admin-tokens">
    <p class="admin-tokens__lead">
      Personal access tokens используются для авторизации API-запросов в обход cookie-сессии.
      Создайте токен с понятным названием и используйте в `Authorization: Bearer {token}`.
    </p>

    <!-- Just-created token (one-time-display) -->
    <div v-if="justCreated" class="admin-tokens__new">
      <div class="admin-tokens__new-row">
        <strong>{{ justCreated.name }}</strong>
        <span>создан только что</span>
      </div>
      <code class="admin-tokens__plain">{{ justCreated.token }}</code>
      <div class="admin-tokens__new-actions">
        <UidButton size="sm" variant="primary" @click="copyJustCreated">
          <template #prepend><UidIcon :icon="Copy" :size="12" /></template>
          Скопировать
        </UidButton>
        <UidButton size="sm" variant="ghost" @click="dismissJustCreated">Скрыть</UidButton>
      </div>
      <p class="admin-tokens__warn">
        ⚠ Plain-значение показывается один раз. Если потеряете — нужно пересоздать.
      </p>
    </div>

    <!-- Create form -->
    <div class="admin-tokens__create">
      <UidInput v-model="newName" placeholder="Название (например, ci-deploy)" />
      <UidButton variant="primary" @click="create">
        <template #prepend><UidIcon :icon="Plus" :size="14" /></template>
        Создать токен
      </UidButton>
    </div>

    <!-- Existing tokens -->
    <div v-if="loading" class="admin-tokens__empty">Загрузка…</div>
    <div v-else-if="tokens.length === 0" class="admin-tokens__empty">Токенов нет.</div>
    <ul v-else class="admin-tokens__list">
      <li v-for="t in tokens" :key="t.id" class="admin-tokens__item">
        <div class="admin-tokens__item-meta">
          <strong>{{ t.name }}</strong>
          <span class="admin-tokens__item-dates">
            Создан: {{ fmtDate(t.created_at) }} ·
            Использован: {{ fmtDate(t.last_used_at) }}
          </span>
        </div>
        <button
          type="button"
          class="admin-tokens__revoke"
          aria-label="Отозвать"
          @click="revoke(t.id)"
        >
          <UidIcon :icon="Trash2" :size="14" />
        </button>
      </li>
    </ul>
  </div>
</template>

<style>
.admin-tokens { display: flex; flex-direction: column; gap: var(--uid-space-md); }
.admin-tokens__lead { margin: 0; color: var(--uid-text-secondary); font-size: 14px; }
.admin-tokens__new {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-xs);
  padding: var(--uid-space-md);
  border: 1px solid color-mix(in srgb, var(--uid-color-success, #10b981) 35%, transparent);
  border-radius: var(--uid-radius-md);
  background: color-mix(in srgb, var(--uid-color-success, #10b981) 8%, transparent);
}
.admin-tokens__new-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: var(--uid-text-primary);
}
.admin-tokens__plain {
  display: block;
  padding: 8px 10px;
  background: var(--uid-surface-raised);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-sm);
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
  font-size: 12px;
  word-break: break-all;
}
.admin-tokens__new-actions { display: flex; gap: var(--uid-space-xs); }
.admin-tokens__warn { margin: 0; font-size: 12px; color: var(--uid-text-tertiary); }
.admin-tokens__create {
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm);
}
.admin-tokens__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.admin-tokens__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--uid-space-sm);
  padding: var(--uid-space-sm);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  background: var(--uid-surface-base);
}
.admin-tokens__item-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}
.admin-tokens__item-dates { font-size: 11px; color: var(--uid-text-tertiary); }
.admin-tokens__revoke {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: 0;
  background: transparent;
  border-radius: var(--uid-radius-sm);
  color: var(--uid-text-secondary);
  cursor: pointer;
}
.admin-tokens__revoke:hover {
  background: color-mix(in srgb, var(--uid-color-danger, #dc2626) 14%, transparent);
  color: var(--uid-color-danger, #dc2626);
}
.admin-tokens__empty { color: var(--uid-text-tertiary); font-size: 13px; }
</style>
