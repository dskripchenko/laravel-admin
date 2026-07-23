/**
 * Public exports SFC-каркаса admin-панели.
 *
 * Использование (host-проект):
 *
 *     import { AdminShell } from '@dskripchenko/laravel-admin'
 *
 *     <AdminShell>
 *       <RouterView />
 *     </AdminShell>
 */

export { default as AdminShell } from './AdminShell.vue'
export { default as AdminTopBar } from './AdminTopBar.vue'
export { default as AdminSidebar } from './AdminSidebar.vue'
export { default as GlobalSearch } from './GlobalSearch.vue'
export { default as ThemeToggle } from './widgets/ThemeToggle.vue'
export { default as LocaleSwitcher } from './widgets/LocaleSwitcher.vue'
export { default as NotificationBell } from './widgets/NotificationBell.vue'
export { default as UserMenu } from './widgets/UserMenu.vue'
