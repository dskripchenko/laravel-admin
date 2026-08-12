/**
 * Уведомления: шторка топбара и страница полного списка.
 *
 * Шторка ЖИВЁТ в `shell/NotificationsDrawer.vue` — именно её рисует AdminApp.
 * Здесь долгое время лежал её двойник с тем же именем: он экспортировался
 * наружу, но не рисовался нигде. Правка в него уходила в пустоту — так и
 * случилось со ссылкой на полный список, добавленной в 1.25.1.
 */
export { default as NotificationsDrawer } from '../shell/NotificationsDrawer.vue'
export { default as NotificationsPage } from './NotificationsPage.vue'
