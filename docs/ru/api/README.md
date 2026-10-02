# HTTP API панели

Описание JSON-API, через который SPA панели (и любые другие клиенты) работают с сервером. API построен на `dskripchenko/laravel-api`: все эндпоинты объявлены в `AdminApi::getMethods()` (`src/Http/AdminApi.php`), часть из них — статически, часть генерируется из зарегистрированных ресурсов, настроек и экранов.

## URL-паттерн

```
/{laravel-api.prefix}/{version}/{controller}/{action}

Панель по умолчанию:     /api/admin/{controller}/{action}
Дополнительная панель:   /api/{panelId}/{controller}/{action}
```

- `laravel-api.prefix` по умолчанию `api`; `{version}` — идентификатор панели (`admin` для основной, ключ из `admin.panels` для дополнительных). Это не версия API в смысле semver, а внутренний контракт между ядром и SPA.
- **API живёт отдельно от SPA-shell.** SPA — под `admin.path` (по умолчанию `/admin/*`), API — под `/api/admin/*`. `admin.api_path` задают только тогда, когда прокси переписывает путь, который видит браузер; чтобы перенести сам API, меняют `laravel-api.prefix`.
- **Никаких path-параметров** кроме `{controller}` и `{action}`: идентификаторы, фильтры и прочие аргументы передаются в query-string (GET) или в теле запроса (POST).
- HTTP-метод каждого action'а задан в `getMethods()` (`'method' => ['get']` / `['post']`); все изменяющие действия — `POST`.

## Структура раздела

| Файл | Содержимое |
|---|---|
| [conventions.md](conventions.md) | Общие конвенции: конверт ответа, заголовки, коды и `errorKey`, права доступа, пагинация и фильтры, ограничение частоты, локаль, docblock'и |
| [registration.md](registration.md) | Как собирается `AdminApi::getMethods()`: статические контроллеры, генерация контроллеров ресурсов/настроек/экранов, панели (`PanelApi`), каскад middleware, security schemes, OpenAPI-шаблоны |
| [schemas.md](schemas.md) | Реестр named-шаблонов ответов (`{XxxResponse}`) из `src/Http/Schemas/*` — 275 шаблонов в пяти трейтах |
| [system.md](system.md) | Контроллер `system`: bootstrap, manifest, me, menu, search (глобальный поиск ⌘K), locales/setLocale, permissions, plugins, status, theme/setTheme |
| [auth.md](auth.md) | Контроллер `auth`: login, logout, восстановление пароля, подтверждение email, 2FA-challenge и recovery-коды, impersonation |
| [profile.md](profile.md) | Контроллер `profile`: профиль, смена пароля, настройка 2FA и recovery-кодов, персональные API-токены (laravel/sanctum) |
| [resources.md](resources.md) | Контроллер каждого ресурса (`{slug}`): meta, search, summary, read, create, update, inlineUpdate, delete, restore/forceDelete, replicate, reorder, tree, export, экраны list/tree/create/edit/view, listener; сохранённые представления `{slug}_views` |
| [actions.md](actions.md) | Действия ресурса через `{slug}/action` (`key`, `ids`, `payload`), модальные действия, асинхронные действия, проверка прав на действие (`action_forbidden`) |
| [screens.md](screens.md) | Контроллер каждого экрана (`{slug}`): state, runMethod, listener |
| [settings.md](settings.md) | Контроллер каждого раздела настроек (`settings_{slug}`): meta, read, update |
| [dashboards.md](dashboards.md) | Контроллер `dashboard`: раскладка виджетов пользователя (get, save, reset), период (savePeriod), данные виджетов (widgets) |
| [exports-imports.md](exports-imports.md) | Экспорт — action `{slug}/export` ресурса; импорт — контроллер `import`: upload, preview, start, status |
| [uploads.md](uploads.md) | Контроллер `uploads`: upload (любой файл), image (картинки для WYSIWYG и ImageCropper), serve (отдача файла с диска) |
| [delayed.md](delayed.md) | Контроллер `delayed`: run (запуск асинхронного обработчика из allowlist) и status (статус процесса) |
| [search.md](search.md) | Глобальный поиск (⌘K): action ядра `system/search` и пакет-компаньон `dskripchenko/laravel-admin-search`, который регистрирует обычный Laravel-маршрут на тот же адрес. Контроллера `search` в `AdminApi` нет |
| [health.md](health.md) | Health-checks из пакета-компаньона `dskripchenko/laravel-admin-health`. Своего контроллера у пакета нет: он добавляет ресурс `system-health-results`, индикатор для `system/status` и виджет дашборда |

Контроллеры `audit` (list, timeline) и `notifications` (list, unread, markAsRead, markAllAsRead, destroy) отдельной страницы не имеют; их actions перечислены в [registration.md](registration.md#2-статические-контроллеры).

## OpenAPI и Scalar UI

OpenAPI-документ генерируется laravel-api из docblock'ов action'ов (`@input`, `@output`, `@header`, `@security`, `@response`) и шаблонов `getOpenApiTemplates()`.

| URL | Что отдаёт | Кто регистрирует |
|---|---|---|
| `GET /api/admin/doc` | Scalar UI со спецификациями **всех** версий модуля (основная панель, дополнительные панели, версии хост-модуля) | ядро, `ScalarDocController`, маршрут `admin.api-doc` |
| `GET /api/doc` | страница документации laravel-api; версии из `laravel-api.hidden_versions` в её список не попадают | laravel-api |
| `GET /api/doc/{version}` | JSON-спецификация одной версии, например `/api/doc/admin` | laravel-api |

- `/api/admin/doc` регистрируется, только если `admin.openapi.ui` = `'scalar'` (по умолчанию). Скрипт Scalar берётся из `admin.openapi.scalar_script` (по умолчанию CDN jsdelivr; можно указать локальный путь), тема — `admin.openapi.scalar_theme`. Спецификации пишутся в storage, в каталог `laravel-api.openapi_path` (по умолчанию `public/openapi`), файлами `{version}.json` и пересобираются при отсутствии файла или в debug-режиме.
- **Доступ к документации не проверяется правами панели.** `/api/admin/doc` работает на стеке `admin.middleware.shell` (`web`, `AdminLocale`, `AdminCspNonce`) без `AdminAuth`; маршруты laravel-api `/api/doc*` — на `getDocMiddleware()` модуля, который у `AdminApiModule` пуст. Если карта API не должна быть публичной, закройте эти пути на уровне приложения или веб-сервера.

Artisan-команды для работы со спецификацией поставляет laravel-api:

| Команда | Назначение |
|---|---|
| `php artisan api:generate-types --api-version=admin` | TypeScript-интерфейсы из спецификации (`--output=` — путь файла) |
| `php artisan api:export --api-version=admin --format=postman` | экспорт спецификации: `postman`, `http`, `markdown`, `curl`, `bruno` |
| `php artisan api:doc-clear` | удалить закэшированные файлы спецификаций |
| `php artisan api:lint --api-version=admin` | проверка карты маршрутов и docblock-разметки (`--strict`, `--unrouted`, `--json`) |

## Стиль документации

- Структуры payload в описаниях даются в TypeScript-подобной нотации — для краткости.
- Сигнатуры action'ов — PHP с реальным docblock'ом.
- Даты — ISO-8601 (`2026-04-30T10:00:00Z`).
- Идентификаторы записей: `string | number` (зависит от модели).
- `null` означает «значение отсутствует / неприменимо», в отличие от отсутствия ключа.
