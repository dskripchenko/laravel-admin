/**
 * Точка входа SPA-бандла @dskripchenko/laravel-admin.
 *
 * На фазе P0 экспортирует только createAdmin() — функцию инициализации
 * Vue-приложения. Регистрация роутера, store'ов, рендереров (LayoutRenderer,
 * FieldRenderer) — фаза P1.
 */

import { createApp, type App } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory, type Router } from 'vue-router'

export interface AdminBootstrap {
  csrf: string
  baseUrl: string
  apiUrl: string
  locale: string
  theme: 'light' | 'dark'
  brand: { name?: string; logo?: string | null; favicon?: string | null }
  manifestVersion: string | null
  user: unknown | null
}

export interface AdminAppOptions {
  bootstrap?: AdminBootstrap
  rootSelector?: string
}

declare global {
  interface Window {
    __ADMIN_BOOTSTRAP__?: AdminBootstrap
  }
}

export function createAdmin(options: AdminAppOptions = {}): {
  app: App
  router: Router
  mount: () => void
} {
  const bootstrap =
    options.bootstrap ??
    window.__ADMIN_BOOTSTRAP__ ??
    null

  if (!bootstrap) {
    throw new Error(
      '[admin] Bootstrap data not found. Set strategy to "inline" or fetch /system/bootstrap before mount.',
    )
  }

  const app = createApp({
    template: '<div>Admin SPA — P0 scaffold</div>',
  })

  const pinia = createPinia()
  app.use(pinia)

  const router = createRouter({
    history: createWebHistory(bootstrap.baseUrl),
    routes: [],
  })
  app.use(router)

  return {
    app,
    router,
    mount: () => app.mount(options.rootSelector ?? '#admin-app'),
  }
}

export type { AdminBootstrap }
