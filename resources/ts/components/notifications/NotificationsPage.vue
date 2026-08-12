<script setup lang="ts">
/**
 * Страница уведомлений — полный список против шторки в топбаре.
 *
 * Маршрут `/notifications` регистрировался и раньше, но только если host
 * передавал компонент, а не передавал его никто: адрес отдавал 404. Ссылок
 * туда не было, поэтому дыра и не всплывала — но и посмотреть историю
 * уведомлений было негде: шторка показывает последние и закрывается по клику.
 *
 * Здесь то, чего шторке не положено: фильтры, страницы, разбор пачками.
 */
import { computed, onMounted, ref } from 'vue'
import { UidButton, UidCard, UidSpinner } from '@dskripchenko/ui'
import { useNotificationsStore, type NotificationFilter, type NotificationItem } from '../../stores/notifications'
import { trSafe as tr, tRaw } from '../../stores/i18n'

const notifications = useNotificationsStore()
const filter = ref<NotificationFilter>('all')

const tabs: Array<{ id: NotificationFilter; label: string }> = [
  { id: 'all', label: tr('Все') },
  { id: 'unread', label: tr('Непрочитанные') },
  { id: 'read', label: tr('Прочитанные') },
]

const page = computed<number>(() => notifications.meta?.page ?? 1)
const lastPage = computed<number>(() => notifications.meta?.last_page ?? 1)
const total = computed<number>(() => notifications.meta?.total ?? notifications.items.length)

async function apply(next: NotificationFilter): Promise<void> {
  filter.value = next
  await notifications.load(next, 1).catch(() => undefined)
}

async function goto(next: number): Promise<void> {
  if (next < 1 || next > lastPage.value) return
  await notifications.load(filter.value, next).catch(() => undefined)
}

async function onRead(item: NotificationItem): Promise<void> {
  if (item.read_at) return
  await notifications.markAsRead(item.id).catch(() => undefined)
}

async function onDestroy(item: NotificationItem): Promise<void> {
  await notifications.destroy(item.id).catch(() => undefined)
}

async function onReadAll(): Promise<void> {
  await notifications.markAllAsRead().catch(() => undefined)
  await notifications.load(filter.value, page.value).catch(() => undefined)
}

const itemTitle = (i: NotificationItem): string => (i.data.title as string | undefined) ?? '—'
const itemBody = (i: NotificationItem): string => (i.data.body as string | undefined) ?? ''
const itemUrl = (i: NotificationItem): string | null => (i.data.url as string | undefined) ?? null

function itemKind(i: NotificationItem): 'info' | 'success' | 'warning' | 'danger' {
  const lvl = i.data.level
  if (lvl === 'success' || lvl === 'warning' || lvl === 'danger') return lvl

  return 'info'
}

/**
 * Время — абсолютное, а не «5 мин назад».
 *
 * В шторке относительное уместно: там последние события. Здесь же смотрят
 * историю, и «3 д назад» на второй странице не отвечает на вопрос «когда».
 */
function when(iso: string | null): string {
  if (iso === null) return ''

  return new Date(iso).toLocaleString()
}

onMounted(() => {
  void notifications.load('all', 1).catch(() => undefined)
})
</script>

<template>
  <div class="admin-notifs-page">
    <div class="admin-notifs-page__head">
      <div>
        <h1 class="admin-page__title">{{ tr('Уведомления') }}</h1>
        <p class="admin-notifs-page__sub">
          {{ tRaw('Всего: :total, непрочитанных: :unread', { total, unread: notifications.unreadCount }) }}
        </p>
      </div>
      <UidButton
        v-if="notifications.hasUnread"
        variant="secondary"
        size="sm"
        @click="onReadAll"
      >
        {{ tr('Прочитать все') }}
      </UidButton>
    </div>

    <div class="admin-notifs-page__tabs">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="admin-notifs-page__tab"
        :class="{ 'admin-notifs-page__tab--active': filter === tab.id }"
        @click="apply(tab.id)"
      >
        {{ tab.label }}
      </button>
    </div>

    <UidSpinner v-if="notifications.loading" />

    <p v-else-if="notifications.items.length === 0" class="admin-notifs-page__empty">
      {{ filter === 'unread' ? tr('Непрочитанных нет.') : tr('Уведомлений пока нет.') }}
    </p>

    <UidCard
      v-for="item in notifications.items"
      v-else
      :key="item.id"
      padding="sm"
      class="admin-notifs-page__item"
      :class="{ 'admin-notifs-page__item--unread': !item.read_at }"
    >
      <div class="admin-notifs-page__row">
        <span class="admin-notifs-page__dot" :data-kind="itemKind(item)" />
        <div class="admin-notifs-page__body">
          <component
            :is="itemUrl(item) ? 'a' : 'span'"
            :href="itemUrl(item) ?? undefined"
            class="admin-notifs-page__item-title"
            @click="onRead(item)"
          >
            {{ itemTitle(item) }}
          </component>
          <p v-if="itemBody(item)" class="admin-notifs-page__item-body">{{ itemBody(item) }}</p>
          <span class="admin-notifs-page__time">{{ when(item.created_at) }}</span>
        </div>
        <div class="admin-notifs-page__actions">
          <UidButton
            v-if="!item.read_at"
            variant="ghost"
            size="sm"
            @click="onRead(item)"
          >
            {{ tr('Прочитано') }}
          </UidButton>
          <UidButton variant="ghost" size="sm" @click="onDestroy(item)">
            {{ tr('Удалить') }}
          </UidButton>
        </div>
      </div>
    </UidCard>

    <div v-if="lastPage > 1" class="admin-notifs-page__pager">
      <UidButton variant="ghost" size="sm" :disabled="page <= 1" @click="goto(page - 1)">
        {{ tr('Назад') }}
      </UidButton>
      <span class="admin-notifs-page__pager-info">
        {{ tRaw('Страница :page из :last', { page, last: lastPage }) }}
      </span>
      <UidButton variant="ghost" size="sm" :disabled="page >= lastPage" @click="goto(page + 1)">
        {{ tr('Вперёд') }}
      </UidButton>
    </div>
  </div>
</template>

<style scoped>
.admin-notifs-page {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md);
}
.admin-notifs-page__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--uid-space-md);
  flex-wrap: wrap;
}
.admin-notifs-page__sub {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--uid-color-text-secondary, #62686f);
}
.admin-notifs-page__tabs {
  display: flex;
  gap: 4px;
}
.admin-notifs-page__tab {
  padding: 6px 12px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: var(--uid-color-text-secondary, #62686f);
  font: inherit;
  font-size: 13px;
  cursor: pointer;
}
.admin-notifs-page__tab--active {
  background: var(--uid-color-surface-2, #f3f4f6);
  color: var(--uid-color-text, #1f2937);
  font-weight: 500;
}
.admin-notifs-page__item--unread {
  border-left: 3px solid var(--uid-color-primary, #2dd4bf);
}
.admin-notifs-page__row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.admin-notifs-page__dot {
  width: 8px;
  height: 8px;
  margin-top: 6px;
  border-radius: 50%;
  flex: none;
  background: var(--uid-color-text-secondary, #9ca3af);
}
.admin-notifs-page__dot[data-kind='success'] { background: var(--uid-success, #10b981); }
.admin-notifs-page__dot[data-kind='warning'] { background: var(--uid-warning, #f59e0b); }
.admin-notifs-page__dot[data-kind='danger'] { background: var(--uid-danger, #ef4444); }
.admin-notifs-page__body {
  flex: 1;
  min-width: 0;
}
.admin-notifs-page__item-title {
  font-weight: 500;
  color: var(--uid-color-text, #1f2937);
  text-decoration: none;
}
a.admin-notifs-page__item-title:hover {
  text-decoration: underline;
}
.admin-notifs-page__item-body {
  margin: 2px 0 0;
  font-size: 13px;
  color: var(--uid-color-text-secondary, #62686f);
}
.admin-notifs-page__time {
  display: block;
  margin-top: 4px;
  font-size: 12px;
  color: var(--uid-color-text-secondary, #9ca3af);
}
.admin-notifs-page__actions {
  display: flex;
  gap: 4px;
  flex: none;
}
.admin-notifs-page__empty {
  color: var(--uid-color-text-secondary, #62686f);
}
.admin-notifs-page__pager {
  display: flex;
  align-items: center;
  gap: var(--uid-space-md);
}
.admin-notifs-page__pager-info {
  font-size: 13px;
  color: var(--uid-color-text-secondary, #62686f);
}

/* Телефон: действия под текстом — в строку они не помещаются и наезжают. */
@media (max-width: 640px) {
  .admin-notifs-page__row {
    flex-wrap: wrap;
  }
  .admin-notifs-page__actions {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
