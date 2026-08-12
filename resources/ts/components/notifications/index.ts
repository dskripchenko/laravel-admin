/**
 * The notifications: the topbar's drawer and the full-list page.
 *
 * The drawer LIVES in `shell/NotificationsDrawer.vue` — that is the one
 * AdminApp draws. For a long time a twin of it sat here under the same name:
 * exported outwards, rendered nowhere. An edit to it went into the void, which
 * is exactly what happened to the link to the full list added in 1.25.1.
 */
export { default as NotificationsDrawer } from '../shell/NotificationsDrawer.vue'
export { default as NotificationsPage } from './NotificationsPage.vue'
