<script setup lang="ts">
/**
 * NotificationsDrawer — UidDrawer (right, 400px) с tabs (Все/Непрочитанные/
 * Прочитанные) и списком notification-items.
 *
 * Эталон handoff'а (screens-secondary.jsx → NotificationDrawer):
 *   - Header: «Уведомления (3)» + actions (Прочитать все)
 *   - Tabs: counts, активный tab подсвечен accent
 *   - Item: kind-coloured icon (info/success/warning/error) +
 *     title (bold если unread) + body + relative timestamp;
 *     unread items имеют tinted background + dot слева
 *   - Click на item → переход на data.url + auto markAsRead
 *   - Hover показывает кнопку '×' (destroy)
 */
import { computed, watch } from 'vue'
import { UidButton, UidDrawer, UidTabs, UidTab, UidTabPanel } from '@dskripchenko/ui'
import { useNotificationsStore, type NotificationFilter, type NotificationItem } from '../../stores/notifications'

interface Props {
  /** v-model:open. */
  open: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:open': [value: boolean]
  /** Click on notification item — host обрабатывает navigation на data.url. */
  'select-item': [item: NotificationItem]
}>()

const notifications = useNotificationsStore()

function setOpen(v: boolean): void {
  emit('update:open', v)
}

// Загружаем при открытии.
watch(
  () => props.open,
  async (next) => {
    if (next) {
      await notifications.load(notifications.lastFilter ?? 'all', 1).catch(() => undefined)
    }
  },
  { immediate: true },
)

const activeFilter = computed<NotificationFilter>(() => notifications.lastFilter ?? 'all')

async function setFilter(filter: NotificationFilter | string | number): Promise<void> {
  await notifications
    .load(filter as NotificationFilter, 1)
    .catch(() => undefined)
}

async function markAllRead(): Promise<void> {
  await notifications.markAllAsRead().catch(() => undefined)
}

async function onItemClick(item: NotificationItem): Promise<void> {
  emit('select-item', item)
  if (!item.read_at) {
    await notifications.markAsRead(item.id).catch(() => undefined)
  }
}

async function onDestroy(item: NotificationItem, event: Event): Promise<void> {
  event.stopPropagation()
  await notifications.destroy(item.id).catch(() => undefined)
}

function itemKind(item: NotificationItem): 'info' | 'success' | 'warning' | 'danger' {
  const lvl = item.data.level
  if (lvl === 'success' || lvl === 'warning' || lvl === 'danger') return lvl
  return 'info'
}

function itemTitle(item: NotificationItem): string {
  return (item.data.title as string | undefined) ?? '—'
}

function itemBody(item: NotificationItem): string {
  return (item.data.body as string | undefined) ?? ''
}

function relTime(iso: string | null): string {
  if (!iso) return ''
  const dt = new Date(iso)
  const diff = Math.max(0, Date.now() - dt.getTime())
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'только что'
  if (mins < 60) return `${mins} мин назад`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours} ч назад`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days} д назад`
  return dt.toLocaleDateString('ru-RU')
}
</script>

<template>
  <UidDrawer
    :model-value="open"
    side="right"
    width="400px"
    title="Уведомления"
    @update:model-value="setOpen"
  >
    <template #header>
      <div class="admin-notifs__hd">
        <h2 class="admin-notifs__title">
          Уведомления
          <span v-if="notifications.unreadCount > 0" class="admin-notifs__count">
            ({{ notifications.unreadCount }})
          </span>
        </h2>
        <UidButton
          v-if="notifications.unreadCount > 0"
          variant="ghost"
          size="sm"
          @click="markAllRead"
        >
          Прочитать все
        </UidButton>
      </div>
    </template>

    <UidTabs :model-value="activeFilter" @update:model-value="setFilter">
      <template #list>
        <UidTab value="all">Все</UidTab>
        <UidTab value="unread">
          Непрочитанные<template v-if="notifications.unreadCount > 0">
            ({{ notifications.unreadCount }})
          </template>
        </UidTab>
        <UidTab value="read">Прочитанные</UidTab>
      </template>

      <UidTabPanel v-for="filter in (['all','unread','read'] as const)" :key="filter" :value="filter">
        <ul class="admin-notifs__list">
          <li v-if="notifications.loading" class="admin-notifs__empty">
            Загрузка…
          </li>
          <li v-else-if="notifications.items.length === 0" class="admin-notifs__empty">
            Нет уведомлений
          </li>
          <li
            v-for="item in notifications.items"
            :key="item.id"
            :class="[
              'admin-notifs__item',
              `admin-notifs__item--${itemKind(item)}`,
              { 'admin-notifs__item--unread': !item.read_at },
            ]"
            @click="onItemClick(item)"
          >
            <span class="admin-notifs__icon" :data-kind="itemKind(item)" />
            <div class="admin-notifs__body">
              <div class="admin-notifs__row1">
                <strong v-if="!item.read_at" class="admin-notifs__title-strong">
                  {{ itemTitle(item) }}
                </strong>
                <span v-else class="admin-notifs__title-soft">{{ itemTitle(item) }}</span>
                <span class="admin-notifs__time">{{ relTime(item.created_at) }}</span>
              </div>
              <p v-if="itemBody(item)" class="admin-notifs__text">{{ itemBody(item) }}</p>
            </div>
            <button
              type="button"
              class="admin-notifs__close"
              :aria-label="'Удалить'"
              @click="onDestroy(item, $event)"
            >
              ×
            </button>
          </li>
        </ul>
      </UidTabPanel>
    </UidTabs>
  </UidDrawer>
</template>

<style>
.admin-notifs__hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--uid-space-sm);
}
.admin-notifs__title {
  margin: 0;
  font-size: var(--uid-font-size-md);
  font-weight: var(--uid-font-weight-semibold);
}
.admin-notifs__count {
  color: var(--uid-text-tertiary);
  font-weight: var(--uid-font-weight-regular);
  font-size: var(--uid-font-size-sm);
}

.admin-notifs__list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.admin-notifs__empty {
  padding: var(--uid-space-xl) var(--uid-space-md);
  text-align: center;
  color: var(--uid-text-tertiary);
  font-size: var(--uid-font-size-sm);
}
.admin-notifs__item {
  position: relative;
  display: flex;
  gap: var(--uid-space-sm);
  padding: var(--uid-space-sm) var(--uid-space-md);
  cursor: pointer;
  border-bottom: 1px solid var(--uid-border-subtle);
}
.admin-notifs__item:hover { background: var(--uid-surface-hover); }
.admin-notifs__item--unread {
  background: color-mix(in srgb, var(--uid-accent) 6%, transparent);
}
.admin-notifs__item--unread:hover {
  background: color-mix(in srgb, var(--uid-accent) 10%, transparent);
}

.admin-notifs__icon {
  width: 28px;
  height: 28px;
  border-radius: var(--uid-radius-md);
  flex: none;
  margin-top: 2px;
}
.admin-notifs__icon[data-kind='info'] {
  background: color-mix(in srgb, var(--uid-info) 14%, transparent);
}
.admin-notifs__icon[data-kind='success'] {
  background: color-mix(in srgb, var(--uid-success) 14%, transparent);
}
.admin-notifs__icon[data-kind='warning'] {
  background: color-mix(in srgb, var(--uid-warning) 14%, transparent);
}
.admin-notifs__icon[data-kind='danger'] {
  background: color-mix(in srgb, var(--uid-danger) 14%, transparent);
}

.admin-notifs__body { flex: 1; min-width: 0; }
.admin-notifs__row1 {
  display: flex;
  align-items: baseline;
  gap: var(--uid-space-sm);
  margin-bottom: 2px;
}
.admin-notifs__title-strong {
  font-size: var(--uid-font-size-sm);
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
  flex: 1;
  min-width: 0;
}
.admin-notifs__title-soft {
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-primary);
  flex: 1;
  min-width: 0;
}
.admin-notifs__time {
  font-size: 11px;
  color: var(--uid-text-tertiary);
  flex: none;
  font-variant-numeric: tabular-nums;
}
.admin-notifs__text {
  margin: 0;
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-secondary);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.admin-notifs__close {
  position: absolute;
  top: 6px;
  right: 8px;
  appearance: none;
  border: 0;
  background: transparent;
  color: var(--uid-text-tertiary);
  cursor: pointer;
  font-size: 18px;
  width: 22px;
  height: 22px;
  border-radius: var(--uid-radius-sm);
  opacity: 0;
  transition: opacity var(--uid-duration-fast, 100ms);
}
.admin-notifs__item:hover .admin-notifs__close { opacity: 1; }
.admin-notifs__close:hover {
  background: var(--uid-surface-hover);
  color: var(--uid-text-primary);
}
</style>
