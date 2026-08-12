<script setup lang="ts">
/**
 * NotificationsDrawer — the notifications panel sliding in from the right.
 *
 * Its structure follows docs/design_handoff_laravel_admin (Notifications):
 *   header   — the title, the unread badge, "Mark all as read" and close
 *   tabs     — All (count) | Unread (count) | Read
 *   list     — the items, each with an icon, a title, a time, a description
 *              and an unread dot
 *   backdrop — closes the drawer when clicked
 *
 * It is opened through notificationsStore.toggleDrawer() from the
 * NotificationBell in the topbar. The drawer is mounted once, in AdminApp.vue,
 * and whether it is open lives in the Pinia store — see stores/notifications.ts.
 */
import { computed, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
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
import { trSafe as tr, tRaw } from '../../stores/i18n'

const notifications = useNotificationsStore()
const router = useRouter()

const isOpen = computed<boolean>(() => notifications.isOpen)

const tabs: Array<{ key: NotificationFilter; label: string }> = [
  { key: 'all', label: tr('Все') },
  { key: 'unread', label: tr('Непрочитанные') },
  { key: 'read', label: tr('Прочитанные') },
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
  // When the notification carries a link in its data we follow it; otherwise we stay.
  const url = item.data.url
  if (typeof url === 'string' && url.length > 0) {
    if (url.startsWith('http://') || url.startsWith('https://')) {
      window.open(url, '_blank', 'noopener')
    } else {
      // An SPA navigation: close the drawer and let the router decide, through the href.
      notifications.closeDrawer()
      window.location.href = url
    }
  }
}

async function onDelete(item: NotificationItem, e: MouseEvent): Promise<void> {
  e.stopPropagation()
  await notifications.destroy(item.id).catch(() => undefined)
}

/** The full list: the drawer is closed, or it would stay on top of the page. */
async function openAll(): Promise<void> {
  close()
  await router.push({ name: 'admin.notifications' }).catch(() => undefined)
}

function close(): void {
  notifications.closeDrawer()
}

/**
 * The icon for a notification's type. Laravel's notifications put an FQCN into
 * `type` ('App\\Notifications\\ImportFinished' and the like), so we match on a
 * substring. The default is the bell.
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
  if (diff < 60) return tRaw(':n сек назад', { n: Math.max(1, Math.floor(diff)) })
  if (diff < 3600) return tRaw(':n мин назад', { n: Math.floor(diff / 60) })
  if (diff < 86_400) return tRaw(':n ч назад', { n: Math.floor(diff / 3600) })
  if (diff < 86_400 * 2) return tr('вчера')
  if (diff < 86_400 * 7) return tRaw(':n д назад', { n: Math.floor(diff / 86_400) })
  return new Date(iso).toLocaleDateString('ru-RU')
}

interface ItemView {
  title: string
  description: string
}
function viewOf(item: NotificationItem): ItemView {
  // The backend puts the payload into `data`; we support the usual
  // `title`, `message` and `description` keys, falling back to data.text.
  const d = item.data
  const title =
    typeof d.title === 'string' ? d.title : typeof d.subject === 'string' ? d.subject : tr('Уведомление')
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

// Loaded when it first opens — and on every open after that, to stay fresh.
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
              {{ tr('Уведомления') }}
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
                {{ tr('Прочитать все') }}
              </button>
              <button
                type="button"
                class="admin-notif-drawer__close"
                :aria-label="tr('Закрыть')"
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
              {{ tr('Загрузка…') }}
            </div>
            <div
              v-else-if="notifications.items.length === 0"
              class="admin-notif-drawer__empty"
            >
              {{ tr('Нет уведомлений') }}
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
                  :aria-label="tr('Удалить')"
                  @click.stop="onDelete(item, $event)"
                >
                  <UidIcon :icon="X" :size="12" />
                </button>
              </li>
            </ol>
          </div>

          <!--
            Ссылка на полный список. Без неё страница уведомлений существует, а
            найти её неоткуда: шторка показывает последние и закрывается по
            клику, адрес пришлось бы знать наизусть.
          -->
          <footer class="admin-notif-drawer__ft">
            <a href="#" @click.prevent="openAll">{{ tr('Все уведомления') }}</a>
          </footer>
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
   * The translucent layer dimming the interface underneath. We do not use
   * --uid-color-overlay: in @dskripchenko/ui that is the opaque surface of
   * popovers (white in the light theme, dark grey in the dark one), which
   * would make the backdrop opaque and hide the content entirely. So a hard
   * rgba(0,0,0,0.4) — the usual dim of a modal layer.
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
.admin-notif-drawer__ft {
  padding: 10px 16px;
  border-top: 1px solid var(--uid-color-border, #e5e7eb);
  font-size: 13px;
}
</style>
