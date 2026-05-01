# dskripchenko/laravel-admin

Конструктор админок для Laravel: Resource-first CRUD, Vue 3 SPA, JSON-API транспорт. Подключается в любой существующий Laravel-проект без вмешательства в его routes/auth.

> **Статус:** in development. Архитектура зафиксирована, идёт реализация. См. `docs/ARCHITECTURE.md`.

## Tldr

```php
class UserResource extends Resource
{
    public static string $model = \App\Models\User::class;
    public static string $icon  = 'user';

    public function fields(): array
    {
        return [
            Field\Input::make('name')->required(),
            Field\Input::make('email')->type('email')->required()->unique(),
            Field\Switch::make('is_active'),
        ];
    }

    public function columns(): array
    {
        return [
            TC::make('id')->sort(),
            TC::make('name')->sort()->search(),
            TC::make('email')->copyable(),
            TC::make('is_active')->as('badge'),
        ];
    }
}
```

```php
// AppServiceProvider
Admin::resources([UserResource::class]);
```

Готово: SPA с list/create/edit/view/audit/permissions.

## Требования

- PHP `^8.5`
- Laravel `^12`
- Redis или Memcached (для tag-aware кэша переводов; иначе soft-fallback)
- Vue 3.4+, TypeScript, Vite (для frontend-сборки)

## Установка

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install
php artisan migrate
php artisan admin:user "Admin" admin@example.com secret
npm install
npm run admin:build
```

После этого админка доступна на `/admin`.

## Frontend SPA (`@dskripchenko/laravel-admin` npm-пакет)

Для host-проектов которые хотят встроить SPA в свой Vite-bundle вместо Blade-shell'а либо построить кастомный admin-фронт поверх готовых блоков.

### Установка

```bash
npm install @dskripchenko/laravel-admin @dskripchenko/ui vue@^3.4 vue-router@^4.3 pinia axios
```

### Минимальный entry

```ts
// resources/admin-spa/main.ts
import { createApp } from 'vue'
import { createPinia } from 'pinia'

// (1) UI-кит: токены + темы + reset + global + Uid*-стили
import '@dskripchenko/ui/styles/all.css'
// (2) admin-каркас (impersonation banner, polling-dot, page utilities)
import '@dskripchenko/laravel-admin/style.css'

import {
  createAdminClient,
  setAdminClient,
  loadBootstrap,
  createAdminRouter,
  registerBuiltinComponents,
  registerBuiltinInfolistEntries,
  registerBuiltinWidgets,
  AdminShell,
  ResourceIndexPage,
  ResourceFormPage,
  ResourceViewPage,
  DashboardPage,
} from '@dskripchenko/laravel-admin'

// 1. HTTP-клиент с envelope/CSRF/error handling
const client = createAdminClient({
  baseURL: '/api/admin',
  onUnauthenticated: () => router.push({ name: 'admin.login' }),
})
setAdminClient(client)

// 2. Bootstrap (inline через <script> либо xhr через /system/bootstrap)
const bootstrap = await loadBootstrap({ client })

// 3. Pinia + hydrate stores
const pinia = createPinia()
const app = createApp(AdminShell)
app.use(pinia)

// 4. Регистрация builtin-компонентов в JSON-renderer'ы
registerBuiltinComponents()        // Field/Layout (text/textarea/number/select/...)
registerBuiltinInfolistEntries()   // Read-only display (text/badge/icon/keyvalue)
registerBuiltinWidgets()           // Dashboard widgets (stat/charts/heatmap/gauge)

// 5. Router с динамическими роутами из manifest'а
const router = createAdminRouter({
  base: '/admin',
  components: {
    login: () => import('./pages/Login.vue'),
    home: () => import('./pages/Home.vue'),
    forbidden: () => import('./pages/403.vue'),
    notFound: () => import('./pages/404.vue'),
    resourceIndex: ResourceIndexPage,
    resourceCreate: ResourceFormPage,
    resourceEdit: ResourceFormPage,
    screen: () => import('./pages/Screen.vue'),
    settings: () => import('./pages/Settings.vue'),
    dashboard: DashboardPage,
  },
})
app.use(router)
app.mount('#admin')
```

### Готовые компоненты-страницы

- `LoginPage` + `LoginForm` + `TwoFactorForm` — auth + TOTP/recovery
- `ResourceIndexPage` — список с filter-bar / bulk toolbar / pagination
- `ResourceFormPage` — create/edit unified с sticky save-bar
- `ResourceViewPage` — read-only display через Infolist
- `DashboardPage` — 12-col widget grid
- `ProfilePage` — sidebar nav + cards
- `ImportWizardPage` — 4-step wizard
- `NotificationsDrawer` — UidDrawer right с tabs
- `FieldGalleryPage` — каталог field-типов (docs/playground)

### Расширение через registry

```ts
import { registerField, registerWidget, registerInfolistEntry } from '@dskripchenko/laravel-admin'
import MyCustomField from './fields/MyCustomField.vue'

registerField('my-custom', MyCustomField)
// теперь manifest узел { type: 'my-custom', name: 'x', ... } рендерится через MyCustomField
```

### Шрифты (опционально)

UID design system предполагает Inter Variable + Inter Display. Library не bundle'ит шрифты в style.css (Vite lib-mode инлайнит base64 → 1.4 MB). Варианты:

- `npm i @fontsource-variable/inter @fontsource/inter-display` + `import` (рекомендуется)
- Google Fonts CDN
- Self-hosted — копировать `node_modules/@dskripchenko/laravel-admin/resources/fonts/*.woff2` в `public/fonts/` + подключить `fonts.css`

### Скрипты разработки

```bash
npm run lint        # eslint flat config + vue-eslint-parser
npm run typecheck   # vue-tsc --noEmit
npm test            # vitest (jsdom)
npm run build       # vite build + vue-tsc --emitDeclarationOnly
npm run build:analyze  # с rollup-plugin-visualizer → dist/stats.html
npm run size        # size-limit budgets (ESM/CJS gzipped + CSS)
npm run preflight   # lint + typecheck + test + build (CI gate)
```

## Документация

- [ARCHITECTURE.md](docs/ARCHITECTURE.md) — архитектура и решения.
- [docs/sister-packs/](docs/sister-packs/) — спецификации опциональных расширений.
- [docs/design_handoff_laravel_admin/](docs/design_handoff_laravel_admin/) — UID design handoff (эталон вёрстки SPA).

## Sister-packs

| Пакет | Назначение |
|---|---|
| `dskripchenko/laravel-admin-starter` | Готовые системные Resource'ы |
| `dskripchenko/laravel-admin-tinymce` / `*-quill` | Альтернативные WYSIWYG |
| `dskripchenko/laravel-admin-search` | Глобальный поиск |
| `dskripchenko/laravel-admin-media` | Медиа-библиотека |
| `dskripchenko/laravel-admin-health` | Health-checks |
| `dskripchenko/laravel-admin-pulse` | Лёгкая телеметрия |
| `dskripchenko/laravel-admin-jobs` | Failed jobs viewer |

## Структура репозитория (dev)

На время разработки до первого релиза проект ведётся как **монорепо**:

```
laravel-admin/
├── src/                      # основной пакет dskripchenko/laravel-admin
├── resources/ts/             # SPA-бандл @dskripchenko/laravel-admin
├── packages/                 # sister-packs, локально
│   ├── starter/
│   ├── tinymce/
│   ├── quill/
│   ├── search/
│   ├── media/
│   ├── health/
│   ├── pulse/
│   └── jobs/
└── docs/
```

- Composer: корневой `composer.json` содержит `repositories: [{ type: path, url: packages/* }]` — sister-packs резолвятся локально через симлинки. Это позволяет менять core и sister-packs одновременно без публикации dev-версий.
- NPM: workspaces для `packages/tinymce` и `packages/quill` (только им нужны npm-зависимости). `npm install` из корня устанавливает зависимости всем сразу.
- **Перед первым стабильным релизом** sister-packs выносятся в отдельные репозитории на github.com/dskripchenko и публикуются на packagist/npm независимо. На этот момент `repositories` в корневом `composer.json` удаляются, sister-packs ставятся обычным `composer require`.

## License

MIT. См. [LICENSE](LICENSE).
