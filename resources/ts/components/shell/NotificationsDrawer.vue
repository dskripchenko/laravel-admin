<script setup lang="ts">
/**
 * NotificationsDrawer — slide-in справа панель с уведомлениями.
 *
 * Структура по docs/design_handoff_laravel_admin (Notifications):
 *   Header  — title + unread badge + "Прочитать все" + close
 *   Tabs    — Все (count) | Непрочитанные (count) | Прочитанные
 *   List    — items с иконкой, title, time, description, unread-dot
 *   Backdrop — закрывает по клику
 *
 * Открывается через notificationsStore.toggleDrawer() из NotificationBell
 * в топбаре. Drawer mounted один раз в AdminApp.vue, стейт открытости —
 * в pinia store (см. stores/notifications.ts).
 */
import { computed, onUnmounted, watch } from 'vue'
import {
  AlertTriangle,
  Check,
  CheckCircle,
  MessageSquare,
  Trash2,
  UserPlus,
  X,
  type LucideIcon,
} from 'lucide-vue-next'
import { UidIcon } from '@dskripchenko/ui'
import {
  useNotificationsStore,
  type NotificationFilter,
  type NotificationItem,
} from '../../stores/notifications'

const notifications = useNotificationsStore()

const isOpen = computed<boolean>(() => notifications.isOpen)

const tabs: Array<{ key: NotificationFilter; label: string }> = [
  { key: 'all', label: 'Все' },
  { key: 'unread', label: 'Непрочитанные' },
  { key: 'read', label: 'Прочитанные' },
]

async function selectTab(key: NotificationFilter): Promise<void> {
  if (notifications.lastFilter === key) return
  await notifications.load(key, 1).catch(() => undefined)
}

async function onMarkAll(): Promise<void> {
  await notifications.markAllAsRead().catch(() => undefined)
}

async function onItemClick(item: NotificationItem): Promise<void> {
  if (item.read_at === null) {
    await notifications.markAsRead(item.id).catch(() => undefined)
  }
  // Если у уведомления есть ссылка в data — открываем; иначе остаёмся.
  const url = item.data.url
  if (typeof url === 'string' && url.length > 0) {
    if (url.startsWith('http://') || url.startsWith('https://')) {
      window.open(url, '_blank', 'noopener')
    } else {
      // SPA-навигация — закроем drawer и оставим решение router'у через href.
      notifications.closeDrawer()
      window.location.href = url
    }
  }
}

async function onDelete(item: NotificationItem, e: MouseEvent): Promise<void> {
  e.stopPropagation()
  await notifications.destroy(item.id).catch(() => undefined)
}

function close(): void {
  notifications.closeDrawer()
}

/**
 * Иконка по типу уведомления. Backend laravel-notifications использует FQCN
 * в `type` ('App\\Notifications\\ImportFinished' и т.п.) — пробуем находить
 * по подстроке. Default — bell.
 */
function iconFor(item: NotificationItem): LucideIcon {
  const t = item.type.toLowerCase()
  if (t.includes('import') || t.includes('finished') || t.includes('success')) return CheckCircle
  if (t.includes('comment') || t.includes('message') || t.includes('mention')) return MessageSquare
  if (t.includes('user') || t.includes('member') || t.includes('role')) return UserPlus
  if (t.includes('warning') || t.includes('schedule')) return AlertTriangle
  if (t.includes('delete') || t.includes('failed') || t.includes('error')) return Trash2
  return CheckCircle
}

function variantFor(item: NotificationItem): 'success' | 'info' | 'warning' | 'danger' | 'neutral' {
  const t = item.type.toLowerCase()
  if (t.includes('import') || t.includes('success') || t.includes('finished')) return 'success'
  if (t.includes('warning') || t.includes('schedule')) return 'warning'
  if (t.includes('delete') || t.includes('failed') || t.includes('error')) return 'danger'
  if (t.includes('comment') || t.includes('message')) return 'info'
  return 'neutral'
}

function relativeTime(iso: string | null): string {
  if (!iso) return ''
  const ts = new Date(iso).getTime()
  if (Number.isNaN(ts)) return ''
  const diff = (Date.now() - ts) / 1000
  if (diff < 60) return `${Math.max(1, Math.floor(diff))} сек назад`
  if (diff < 3600) return `${Math.floor(diff / 60)} мин назад`
  if (diff < 86_400) return `${Math.floor(diff / 3600)} ч назад`
  if (diff < 86_400 * 2) return 'вчера'
  if (diff < 86_400 * 7) return `${Math.floor(diff / 86_400)} д назад`
  return new Date(iso).toLocaleDateString('ru-RU')
}

interface ItemView {
  title: string
  description: string
}
function viewOf(item: NotificationItem): ItemView {
  // Backend кладёт payload в `data`; поддерживаем стандартные ключи
  // `title`/`message`/`description` + fallback на data.text.
  const d = item.data
  const title =
    typeof d.title === 'string' ? d.title : typeof d.subject === 'string' ? d.subject : 'Уведомление'
  const description =
    typeof d.description === 'string'
      ? d.description
      : typeof d.message === 'string'
        ? d.message
        : typeof d.text === 'string'
          ? d.text
          : ''
  return { title, description }
}

const tabCounts = computed<Record<NotificationFilter, number>>(() => {
  const meta = notifications.meta
  const total = meta?.total ?? notifications.items.length
  const unread = notifications.unreadCount
  const read = Math.max(0, total - unread)
  return { all: total, unread, read }
})

// Загружаем при первом открытии. И каждый раз — чтобы свежие данные.
watch(isOpen, async (open) => {
  if (open) {
    await notifications.load(notifications.lastFilter, 1).catch(() => undefined)
    document.addEventListener('keydown', onKey)
  } else {
    document.removeEventListener('keydown', onKey)
  }
})

function onKey(e: KeyboardEvent): void {
  if (e.key === 'Escape') close()
}

onUnmounted(() => {
  document.removeEventListener('keydown', onKey)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="admin-notif-drawer">
      <div
        v-if="isOpen"
        class="admin-notif-drawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="admin-notif-drawer-title"
      >
        <div class="admin-notif-drawer__backdrop" @click="close" />
        <aside class="admin-notif-drawer__panel">
          <header class="admin-notif-drawer__hd">
            <h2 id="admin-notif-drawer-title" class="admin-notif-drawer__title">
              Уведомления
              <span
                v-if="notifications.unreadCount > 0"
                class="admin-notif-drawer__unread-badge"
              >{{ notifications.unreadCount }}</span>
            </h2>
            <div class="admin-notif-drawer__hd-actions">
              <button
                v-if="notifications.unreadCount > 0"
                type="button"
                class="admin-notif-drawer__mark-all"
                @click="onMarkAll"
              >
                <UidIcon :icon="Check" :size="14" />
                Прочитать все
              </button>
              <button
                type="button"
                class="admin-notif-drawer__close"
                aria-label="Закрыть"
                @click="close"
              >
                <UidIcon :icon="X" :size="16" />
              </button>
            </div>
          </header>

          <nav class="admin-notif-drawer__tabs" role="tablist">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              type="button"
              role="tab"
              :class="[
                'admin-notif-drawer__tab',
                {
                  'admin-notif-drawer__tab--active': notifications.lastFilter === tab.key,
                },
              ]"
              :aria-selected="notifications.lastFilter === tab.key"
              @click="selectTab(tab.key)"
            >
              {{ tab.label }}
              <span class="admin-notif-drawer__tab-count">{{ tabCounts[tab.key] }}</span>
            </button>
          </nav>

          <div class="admin-notif-drawer__body">
            <div
              v-if="notifications.loading && notifications.items.length === 0"
              class="admin-notif-drawer__empty"
            >
              Загрузка…
            </div>
            <div
              v-else-if="notifications.items.length === 0"
              class="admin-notif-drawer__empty"
            >
              Нет уведомлений
            </div>
            <ol v-else class="admin-notif-drawer__list">
              <li
                v-for="item in notifications.items"
                :key="item.id"
                :class="[
                  'admin-notif-drawer__item',
                  { 'admin-notif-drawer__item--unread': item.read_at === null },
                ]"
                @click="onItemClick(item)"
              >
                <span
                  class="admin-notif-drawer__icon"
                  :data-variant="variantFor(item)"
                  aria-hidden="true"
                >
                  <UidIcon :icon="iconFor(item)" :size="14" />
                </span>
                <div class="admin-notif-drawer__content">
                  <div class="admin-notif-drawer__row">
                    <span class="admin-notif-drawer__item-title">{{ viewOf(item).title }}</span>
                    <span class="admin-notif-drawer__item-time">
                      {{ relativeTime(item.created_at) }}
                    </span>
                  </div>
                  <div
                    v-if="viewOf(item).description"
                    class="admin-notif-drawer__item-description"
                  >
                    {{ viewOf(item).description }}
                  </div>
                </div>
                <span
                  v-if="item.read_at === null"
                  class="admin-notif-drawer__unread-dot"
                  aria-hidden="true"
                />
                <button
                  type="button"
                  class="admin-notif-drawer__item-delete"
                  aria-label="Удалить"
                  @click.stop="onDelete(item, $event)"
                >
                  <UidIcon :icon="X" :size="12" />
                </button>
              </li>
            </ol>
          </div>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style>
.admin-notif-drawer {
  position: fixed;
  inset: 0;
  z-index: var(--uid-z-drawer, 400);
  pointer-events: none;
}
.admin-notif-drawer__backdrop {
  position: absolute;
  inset: 0;
  /*
   * Полупрозрачный затеняющий слой над основным интерфейсом. Не используем
   * --uid-color-overlay — в @dskripchenko/ui это opaque surface для popover'ов
   * (white в light / dark-grey в dark), и backdrop становится непрозрачным,
   * закрывая контент полностью. Берём жёсткий rgba(0,0,0,0.4) — стандартный
   * dim для модальных слоёв.
   */
  background: rgba(0, 0, 0, 0.4);
  pointer-events: auto;
}
.admin-notif-drawer__panel {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(480px, 100%);
  display: flex;
  flex-direction: column;
  background: var(--uid-surface-raised);
  border-left: 1px solid var(--uid-border-subtle);
  box-shadow: var(--uid-shadow-lg);
  pointer-events: auto;
}

/* Header */
.admin-notif-drawer__hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--uid-space-sm);
  padding: var(--uid-space-md);
  border-bottom: 1px solid var(--uid-border-subtle);
}
.admin-notif-drawer__title {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-xs);
  margin: 0;
  font-family: var(--uid-font-family-display);
  font-size: 16px;
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
}
.admin-notif-drawer__unread-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  padding: 2px 6px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--uid-color-danger, #dc2626) 14%, transparent);
  color: var(--uid-color-danger, #dc2626);
  font-size: 11px;
  font-weight: var(--uid-font-weight-semibold);
  line-height: 1;
}
.admin-notif-drawer__hd-actions {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-xs);
}
.admin-notif-drawer__mark-all {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-2xs);
  padding: 6px 10px;
  border: 0;
  background: transparent;
  border-radius: var(--uid-radius-sm);
  color: var(--uid-text-secondary);
  font-size: 13px;
  cursor: pointer;
}
.admin-notif-drawer__mark-all:hover {
  background: var(--uid-color-surface-hover, var(--uid-border-subtle));
  color: var(--uid-text-primary);
}
.admin-notif-drawer__close {
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
.admin-notif-drawer__close:hover {
  background: var(--uid-color-surface-hover, var(--uid-border-subtle));
  color: var(--uid-text-primary);
}

/* Tabs */
.admin-notif-drawer__tabs {
  display: flex;
  align-items: center;
  gap: var(--uid-space-md);
  padding: 0 var(--uid-space-md);
  border-bottom: 1px solid var(--uid-border-subtle);
}
.admin-notif-drawer__tab {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-2xs);
  padding: 12px 0;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: var(--uid-text-secondary);
  font-size: 13px;
  font-weight: var(--uid-font-weight-medium);
  cursor: pointer;
}
.admin-notif-drawer__tab:hover { color: var(--uid-text-primary); }
.admin-notif-drawer__tab--active {
  color: var(--uid-text-primary);
  border-bottom-color: var(--uid-accent);
}
.admin-notif-drawer__tab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  padding: 1px 6px;
  border-radius: 9px;
  background: var(--uid-border-subtle);
  color: var(--uid-text-tertiary);
  font-size: 11px;
  font-weight: var(--uid-font-weight-semibold);
}
.admin-notif-drawer__tab--active .admin-notif-drawer__tab-count {
  background: color-mix(in srgb, var(--uid-color-danger, #dc2626) 14%, transparent);
  color: var(--uid-color-danger, #dc2626);
}

/* Body / list */
.admin-notif-drawer__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}
.admin-notif-drawer__empty {
  padding: var(--uid-space-xl);
  text-align: center;
  color: var(--uid-text-tertiary);
  font-size: 13px;
}
.admin-notif-drawer__list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.admin-notif-drawer__item {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--uid-space-sm);
  padding: var(--uid-space-md);
  border-bottom: 1px solid var(--uid-border-subtle);
  cursor: pointer;
  transition: background var(--uid-duration-fast, 120ms) var(--uid-ease-out, ease);
}
.admin-notif-drawer__item:hover {
  background: var(--uid-color-surface-hover, var(--uid-border-subtle));
}
.admin-notif-drawer__item--unread {
  background: color-mix(in srgb, var(--uid-accent) 5%, transparent);
}
.admin-notif-drawer__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: none;
  width: 32px;
  height: 32px;
  border-radius: 50%;
}
.admin-notif-drawer__icon[data-variant='success'] {
  background: color-mix(in srgb, var(--uid-color-success, #10b981) 14%, transparent);
  color: var(--uid-color-success, #10b981);
}
.admin-notif-drawer__icon[data-variant='warning'] {
  background: color-mix(in srgb, var(--uid-color-warning, #f59e0b) 14%, transparent);
  color: var(--uid-color-warning, #f59e0b);
}
.admin-notif-drawer__icon[data-variant='danger'] {
  background: color-mix(in srgb, var(--uid-color-danger, #dc2626) 14%, transparent);
  color: var(--uid-color-danger, #dc2626);
}
.admin-notif-drawer__icon[data-variant='info'] {
  background: color-mix(in srgb, var(--uid-accent) 14%, transparent);
  color: var(--uid-accent);
}
.admin-notif-drawer__icon[data-variant='neutral'] {
  background: var(--uid-border-subtle);
  color: var(--uid-text-secondary);
}

.admin-notif-drawer__content {
  flex: 1;
  min-width: 0;
}
.admin-notif-drawer__row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: var(--uid-space-sm);
}
.admin-notif-drawer__item-title {
  font-size: 13px;
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
}
.admin-notif-drawer__item-time {
  font-size: 11px;
  color: var(--uid-text-tertiary);
  flex: none;
}
.admin-notif-drawer__item-description {
  margin-top: 2px;
  font-size: 13px;
  color: var(--uid-text-secondary);
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.admin-notif-drawer__unread-dot {
  position: absolute;
  top: 16px;
  right: 36px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--uid-color-success, #10b981);
}
.admin-notif-drawer__item-delete {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border: 0;
  background: transparent;
  border-radius: 50%;
  color: var(--uid-text-tertiary);
  cursor: pointer;
  opacity: 0;
  flex: none;
  align-self: flex-start;
  margin-top: -2px;
}
.admin-notif-drawer__item:hover .admin-notif-drawer__item-delete { opacity: 1; }
.admin-notif-drawer__item-delete:hover {
  background: color-mix(in srgb, var(--uid-color-danger, #dc2626) 14%, transparent);
  color: var(--uid-color-danger, #dc2626);
}

/* Slide transition */
.admin-notif-drawer-enter-active,
.admin-notif-drawer-leave-active {
  transition: opacity 200ms ease-out;
}
.admin-notif-drawer-enter-active .admin-notif-drawer__panel,
.admin-notif-drawer-leave-active .admin-notif-drawer__panel {
  transition: transform 240ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.admin-notif-drawer-enter-from,
.admin-notif-drawer-leave-to {
  opacity: 0;
}
.admin-notif-drawer-enter-from .admin-notif-drawer__panel,
.admin-notif-drawer-leave-to .admin-notif-drawer__panel {
  transform: translateX(100%);
}
</style>
