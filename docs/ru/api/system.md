# API: System

Контроллер `system` (`Dskripchenko\LaravelAdmin\Http\Controllers\SystemController`) — служебные данные SPA: bootstrap, манифест, текущий пользователь, меню, глобальный поиск, локали, темы, группы прав, плагины, индикаторы статуса.

> Конвенции — [conventions.md](conventions.md). Регистрация — [registration.md](registration.md).

URL: `/api/admin/system/{action}` (префикс — `laravel-api.prefix`, по умолчанию `api`; у дополнительной панели вместо `admin` стоит её id).

Все ответы — в конверте laravel-api: `{"success": true, "payload": ...}` или `{"success": false, "payload": {"errorKey": "...", "message": "..."}}`.

---

## Регистрация в `AdminApi::getMethods()`

```php
'system' => [
    'controller' => Controllers\SystemController::class,
    'actions' => [
        'bootstrap'   => ['method' => ['get'],  'exclude-middleware' => [Middleware\AdminAuth::class]],
        'manifest'    => ['method' => ['get']],
        'me'          => ['method' => ['get']],
        'menu'        => ['method' => ['get']],
        'search'      => ['method' => ['get']],
        'locales'     => ['method' => ['get'],  'exclude-middleware' => [Middleware\AdminAuth::class]],
        'setLocale'   => ['method' => ['post'], 'exclude-middleware' => [Middleware\AdminAuth::class]],
        'permissions' => ['method' => ['get']],
        'plugins'     => ['method' => ['get']],
        'status'      => ['method' => ['get']],
        'theme'       => ['method' => ['get'],  'exclude-middleware' => [Middleware\AdminAuth::class]],
        'setTheme'    => ['method' => ['post'], 'exclude-middleware' => [Middleware\AdminAuth::class]],
    ],
],
```

Middleware-стек панели (`config('admin.middleware.api')`): `web`, `CaptureApiRequest`, `AdminAuth`, `RunActionMiddleware`, `AdminLocale`, плюс глобальный лимит `ThrottleRequests` из `admin.api.throttle` (по умолчанию `240,1`).

`AdminAuth` требует входа в guard текущей панели. Без него — `401 unauthenticated`; отключённая учётная запись — `403 account_inactive`; пользователь без доступа к панели — `403 forbidden`; сессия, чей пароль сменили, — `401 session_expired`. Actions с `exclude-middleware => [AdminAuth::class]` (`bootstrap`, `locales`, `setLocale`, `theme`, `setTheme`) доступны без входа.

Отдельных прав (`AdminAccess`) на actions `system` нет: всё, что нужно, — аутентификация (где она требуется). Фильтрация по правам происходит внутри данных (меню, поиск, дашборды в манифесте).

> Уведомления и журнал аудита — не actions `system`, а отдельные контроллеры: `notifications` (`list`, `unread`, `markAsRead`, `markAllAsRead`, `destroy`) и `audit` (`list`, `timeline`).

---

## `system.bootstrap`

`GET /api/admin/system/bootstrap` — без аутентификации.

Bootstrap-данные SPA для стратегии `xhr` (`admin.bootstrap.strategy`). При стратегии `inline` (по умолчанию) те же данные вшиваются в `<script>` shell-страницы и этот action не вызывается. Данные собирает `Support\BootstrapBuilder`.

Параметров нет. Ответ `200`:

| Ключ | Тип | Описание |
|---|---|---|
| `csrf` | string | CSRF-токен сессии |
| `panel` | string | id панели |
| `baseUrl` | string | абсолютный URL shell'а панели |
| `apiUrl` | string | абсолютный URL API панели |
| `locale` | string | текущая локаль (см. `LocaleResolver`) |
| `availableLocales` | string[] | `admin.ui.available_locales`, по умолчанию `['ru', 'en']` |
| `theme` | string | текущая тема |
| `availableThemes` | string[] | `admin.ui.available_themes`, по умолчанию `['light', 'dark']` |
| `brand` | object | бренд панели |
| `user` | object\|null | `{id, name, email, avatar, locale, theme, twoFactorEnabled}`; `null` для гостя и для пользователя без доступа к панели |
| `permissions` | string[] | плоский список прав пользователя (пустой для гостя) |
| `manifestVersion` | string\|null | версия манифеста; `null` для гостя — манифест для гостя не строится |
| `plugins` | string[] | классы зарегистрированных `AdminPlugin` |
| `unread_notifications_count` | integer | число непрочитанных уведомлений (0, если таблицы `notifications` нет) |
| `translations` | object | плоский словарь `{ключ: перевод}`: пространства из `admin.translations.namespaces` + JSON-переводы локали |
| `config` | object | `{manifest: {etag: bool}, bootstrap: {strategy: string}}` |

---

## `system.manifest`

`GET /api/admin/system/manifest` — требует аутентификации.

Полный JSON-манифест панели (`Support\Manifest::build()`), собранный в текущей локали запроса (`app()->getLocale()`).

| Заголовок запроса | Описание |
|---|---|
| `If-None-Match` | ETag предыдущего ответа |

Ответ `200`, заголовок `ETag: "<version>"`. Если `If-None-Match` совпал с ETag — `304` без тела (с тем же `ETag`).

| Ключ | Описание |
|---|---|
| `version` | хэш содержимого (32 символа); равен ETag без кавычек |
| `locale` | локаль сборки |
| `panel` | id панели |
| `resources` | описания ресурсов панели (`ResourceManifest::describe()`) |
| `screens` | кастомные экраны: `{slug, name, description, permission}` (без `GeneratedScreen` и `DashboardScreen`) |
| `settings` | `meta()` каждого SettingsResource |
| `dashboards` | дашборды, доступные пользователю (`DashboardScreen::canAccess()`), в виде `toManifest()` |
| `plugins` | классы зарегистрированных плагинов |
| `permissions` | всегда пустой массив; группы прав отдаёт `system.permissions` |

---

## `system.me`

`GET /api/admin/system/me` — требует аутентификации.

Ответ `200`:

| Ключ | Описание |
|---|---|
| `id`, `name`, `email` | данные пользователя |
| `locale` | `user.locale` или локаль по умолчанию (`LocaleResolver::default()`) |
| `theme` | `user.theme` или `admin.ui.default_theme` (`light`) |
| `twoFactorEnabled` | `hasTwoFactorEnabled()` модели, иначе `false` |
| `impersonator` | `{id, name}` исходного пользователя при активной impersonation, иначе `null` |
| `unread_notifications_count` | число непрочитанных уведомлений (0, если таблицы `notifications` нет) |

---

## `system.menu`

`GET /api/admin/system/menu` — требует аутентификации.

Дерево меню сайдбара: сначала узлы, зарегистрированные через `Admin::menu()->add(...)` (`MenuRegistry`), затем — если автозаполнение не выключено — ресурсы и кастомные экраны, которых нет в дереве.

Ответ `200`: `{"items": [MenuItem, ...]}`. Поля `MenuItem`:

| Ключ | Описание |
|---|---|
| `key` | ключ узла |
| `label` | метка (переводится при сериализации) |
| `icon` | иконка или `null` |
| `url` | путь SPA (`/r/{slug}`, `/screens/{slug}`, `/dashboard/{slug}`, ...) или `null` |
| `routeName` | имя маршрута SPA или `null` |
| `badge` | бейдж или `null` |
| `group` | группа или `null` |
| `order` | порядок |
| `permissions` | права, нужные для показа узла (фильтрует SPA) |
| `children` | вложенные узлы |

Автоматические пункты: для ресурса — `permissions: ["{permission}.view"]`, `order: 0`; для экрана — права из `Screen::permission()`, группа «Инструменты», `order: 100`. Сортировка автоматических пунктов — по `order`, затем по метке. Узлы, ведущие на дашборд, который пользователю недоступен (`DashboardScreen::canAccess()`), сервер удаляет сам.

---

## `system.search`

`GET /api/admin/system/search?q=...` — требует аутентификации. Глобальный поиск по ресурсам панели (палитра ⌘K). Подробности — [search.md](search.md).

| Параметр | Тип | Описание |
|---|---|---|
| `q` | string, необязательный | строка поиска; короче 2 символов (после `trim`) — пустой результат |

Ответ `200`: `{"query": "...", "groups": [...]}`.

---

## `system.locales`

`GET /api/admin/system/locales` — без аутентификации.

Ответ `200`:

| Ключ | Описание |
|---|---|
| `available` | доступные локали (`admin.ui.available_locales`, по умолчанию `['ru', 'en']`) |
| `current` | локаль запроса по цепочке `LocaleResolver`: `?locale=` → заголовок `X-Admin-Locale` → `user.locale` → cookie `admin_locale` → `Accept-Language` → по умолчанию |
| `default` | `admin.ui.default_locale` (или локаль приложения), если она доступна, иначе первая доступная |
| `fallback` | `admin.ui.fallback_locale` (по умолчанию `en`) |

---

## `system.setLocale`

`POST /api/admin/system/setLocale` — без аутентификации.

| Параметр | Правила |
|---|---|
| `locale` | `required`, `string`; должна входить в доступные локали |

Сохраняет локаль в `user.locale` (если пользователь вошёл и у таблицы есть колонка `locale`) и в cookie `admin_locale` на год.

Ответ `200`: `{"locale": "en"}`.

Ошибки: `422 validation` — нет параметра; `422 unsupported_locale` — локали нет в списке доступных.

---

## `system.theme`

`GET /api/admin/system/theme` — без аутентификации.

Ответ `200`: `{"current": "...", "default": "...", "available": [...]}`. `current` — `user.theme` → cookie `admin_theme` → `admin.ui.default_theme` (по умолчанию `light`); `available` — `admin.ui.available_themes` (по умолчанию `['light', 'dark']`).

---

## `system.setTheme`

`POST /api/admin/system/setTheme` — без аутентификации.

| Параметр | Правила |
|---|---|
| `theme` | `required`, `string`; должна входить в доступные темы |

Сохраняет тему в `user.theme` (если пользователь вошёл и у таблицы есть колонка `theme`) и в cookie `admin_theme` на год.

Ответ `200`: `{"theme": "dark"}`.

Ошибки: `422 validation`; `422 unsupported_theme` — темы нет в списке доступных.

---

## `system.permissions`

`GET /api/admin/system/permissions` — требует аутентификации (отдельного права не требует).

Группы прав текущей панели — для матрицы ролей в UI.

Ответ `200`:

```json
{
  "groups": [
    { "name": "<группа>", "items": [ { "key": "<ключ права>", "label": "<локализованная метка>" } ] }
  ]
}
```

Группы — это `ItemPermission`, зарегистрированные для панели (`$admin->permissions(...)`); `name` и `label` локализуются.

---

## `system.plugins`

`GET /api/admin/system/plugins` — требует аутентификации.

Ответ `200`: `{"plugins": [{"id": "<FQCN плагина>", "version": "0.0.0-dev", "requires": []}]}`. Поля `version` и `requires` сейчас заполняются константами.

---

## `system.status`

`GET /api/admin/system/status` — требует аутентификации.

Состояние индикаторов верхней панели. Отдельный action, а не поле манифеста: манифест кэшируется по ETag и меняется на деплое, а статус меняется сам по себе.

Индикаторы регистрирует хост или плагин через `$admin->statusIndicators([...])`; классы реализуют `Dskripchenko\LaravelAdmin\Status\StatusIndicator` (`key()`, `state()`). Отдаются индикаторы текущей панели. Индикатор, бросивший исключение, пропускается (исключение уходит в `report()`), запрос не падает.

Ответ `200`:

| Ключ | Описание |
|---|---|
| `indicators[].key` | идентификатор, например `admin.health` |
| `indicators[].status` | `ok` \| `warning` \| `error` \| `unknown` (любое другое значение превращается в `unknown`) |
| `indicators[].label` | короткая подпись рядом с точкой |
| `indicators[].detail` | текст подсказки или `null` |
| `indicators[].url` | куда ведёт клик, или `null` |

Фронт (`StatusIndicators.vue`) опрашивает action раз в минуту и не рисует индикаторы со статусом `ok`; недоступный action молча игнорируется.
