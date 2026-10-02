---
title: Справочник API
audience: developer
status: stable
locale: ru
translated_from: en/api-reference.md
translated_at: 2026-10-02
---

# Справочник API

SPA админки общается с бэкендом JSON-запросами к `/api/admin/...`. Все ответы
приходят в конверте `{success, payload}` из `dskripchenko/laravel-api`.
Префикс следует за `config('laravel-api.prefix')`: при `api/v1` API админки
переезжает на `/api/v1/admin/...`.

Здесь — сводная карта эндпоинтов. Подробные контракты отдельных групп
(тела запросов, ответы, коды ошибок) — в разделе [API](api/README.md).

## Конверт

```json
{
  "success": true,
  "payload": { ... }
}
```

Ошибка:

```json
{
  "success": false,
  "payload": {
    "errorKey": "validation",
    "message": "...",
    "messages": { "field": ["..."] }
  }
}
```

HTTP-коды: 200 / 304 / 401 / 403 / 404 / 422 / 429 / 500.

Спецификация OpenAPI 3.0 генерируется автоматически и доступна по
`/api/admin/doc` (в интерфейсе Scalar) — см. [Спецификация OpenAPI](#спецификация-openapi).

## Эндпоинты

Базовый префикс: `/api/admin/`.

### system

| Метод | Путь | Что возвращает |
|---|---|---|
| GET | `system/bootstrap` | Стартовые данные SPA (CSRF, локаль, тема, бренд, пользователь, manifestVersion). Публичный. |
| GET | `system/manifest` | Полный манифест. Требует входа. Кешируется по ETag. |
| GET | `system/me` | Краткие данные текущего администратора. |
| GET | `system/menu` | Дерево sidebar (заданное вручную + автоматическое). |
| GET | `system/search` | Глобальный поиск (палитра ⌘K). |
| GET | `system/locales` | Доступные локали. Публичный. |
| POST | `system/setLocale` | Сохранить локаль пользователя. Публичный. |
| GET | `system/permissions` | Все зарегистрированные группы прав. |
| GET | `system/plugins` | Загруженные плагины. |
| GET | `system/status` | Индикаторы статуса для верхней панели (не кешируются). |
| GET | `system/theme` | Текущая тема. Публичный. |
| POST | `system/setTheme` | Сохранить тему пользователя. Публичный. |

### auth

| Метод | Путь | |
|---|---|---|
| POST | `auth/login` | Email + пароль → сессия. Если включена 2FA, вместо входа отвечает `success: false`, `errorKey: two_factor_required` и `challenge_token` (живёт 5 минут). |
| POST | `auth/twoFactorChallenge` | `challenge_token` + TOTP-код. |
| POST | `auth/twoFactorRecovery` | Резервный код. |
| POST | `auth/logout` | |
| POST | `auth/forgotPassword` | |
| POST | `auth/resetPassword` | |
| POST | `auth/verifyEmail` | |
| POST | `auth/resendEmailVerification` | |
| POST | `auth/startImpersonation` | Администратор входит от имени другого пользователя. |
| POST | `auth/stopImpersonation` | |

### profile

| Метод | Путь | |
|---|---|---|
| GET | `profile/show` | |
| POST | `profile/update` | |
| POST | `profile/changePassword` | |
| GET | `profile/twoFactorStatus` | `enabled`, `confirmed_at`, `recovery_codes_remaining`. |
| POST | `profile/twoFactorEnable` | Новый `secret`, URI для QR-кода `qr_uri` и `recovery_codes`; до подтверждения 2FA не активна. |
| POST | `profile/twoFactorConfirm` | Проверка первого кода. |
| POST | `profile/twoFactorDisable` | |
| POST | `profile/twoFactorRegenerateCodes` | |
| GET | `profile/tokensList` | (Sanctum) |
| POST | `profile/tokenCreate` | |
| POST | `profile/tokenRevoke` | |

### dashboard

| Метод | Путь | |
|---|---|---|
| GET | `dashboard/get?key={slug}` | Раскладка, сохранённая пользователем (или null). |
| POST | `dashboard/save` | Сохранить раскладку пользователя. |
| POST | `dashboard/savePeriod` | Сохранить выбранный пользователем период, не трогая раскладку. |
| POST | `dashboard/reset` | Удалить пользовательскую раскладку (вернуться к манифесту). |
| GET | `dashboard/widgets?key={slug}&period={p}` | Перезапросить данные виджетов (для polling и смены периода). |

### Ресурсы (свои для каждого Resource)

Для каждого зарегистрированного Resource префикс — `{slug}/`:

| Метод | Путь | |
|---|---|---|
| GET | `{slug}/meta` | Метаданные ресурса (поля, колонки, фильтры, actions, экраны). |
| POST | `{slug}/search` | Список с фильтрами, сортировкой и пагинацией. Тело: `{filters, q, order: [{column, direction}], page, per_page, ids?, group_by?}`. |
| POST | `{slug}/summary` | Агрегаты для списка (sum/avg/count). |
| GET | `{slug}/read?id={id}` | Одна запись. |
| POST | `{slug}/create` | |
| POST | `{slug}/update` | |
| POST | `{slug}/inlineUpdate` | Изменение одного поля. |
| POST | `{slug}/delete` | |
| POST | `{slug}/restore` | Только для ресурсов с `SoftDeletes`. |
| POST | `{slug}/forceDelete` | Только для ресурсов с `SoftDeletes`. |
| POST | `{slug}/replicate` | Только если `replicable()`. |
| POST | `{slug}/reorder` | Только если `reorderable()`. |
| GET/POST | `{slug}/export?format=csv` | `format`: `csv` (по умолчанию), `xlsx`, `pdf` — какие экспортёры установлены; `columns[]` сужает набор колонок. |
| POST | `{slug}/action` | Общий диспетчер actions. Тело: `{key, ids[], payload}`. |
| GET | `{slug}/listScreen` | Скомпилированный снимок `GeneratedListScreen`. |
| GET | `{slug}/treeScreen` | Только для иерархических ресурсов. |
| POST | `{slug}/tree` | Узлы дерева (иерархические ресурсы). |
| GET | `{slug}/createScreen` | |
| GET | `{slug}/editScreen?id={id}` | |
| GET | `{slug}/viewScreen?id={id}` | |
| POST | `{slug}/listener` | Реактивные части формы (`Layout::listener`); только если они в форме есть. |

Action, которую ресурс не поддерживает, не регистрируется и отвечает 404.

Сохранённые представления: `{slug}_views/{list,create,update,delete}` —
регистрируются только для ресурсов, у которых `savedViews()` возвращает `true`.

### Экраны (свои для каждого Screen)

Для каждого зарегистрированного кастомного экрана префикс — `{slug}/`:

| Метод | Путь | |
|---|---|---|
| GET | `{slug}/state` | Результат `Screen::compile()`. |
| POST | `{slug}/runMethod` | Тело: `{method, payload, parameters?}`. |
| POST | `{slug}/listener` | Реактивные части формы. |

### Настройки (свои для каждого SettingsResource)

Префикс `settings_{slug}/`:

| Метод | Путь | |
|---|---|---|
| GET | `settings_{slug}/meta` | |
| GET | `settings_{slug}/read` | |
| POST | `settings_{slug}/update` | |

### audit

| Метод | Путь | |
|---|---|---|
| GET | `audit/list` | Все записи журнала аудита (фильтры: `subject_type`, `subject_id`, `actor_type`, `actor_id`, `event`, `from`, `to`). |
| GET | `audit/timeline?subject_type=&subject_id=` | Хронология одной записи. |

### notifications

| Метод | Путь | |
|---|---|---|
| GET | `notifications/list?type=all|unread|read` | |
| GET | `notifications/unread` | Для опроса счётчика на колокольчике. |
| POST | `notifications/markAsRead` | |
| POST | `notifications/markAllAsRead` | |
| POST | `notifications/destroy` | |

### import

| Метод | Путь | |
|---|---|---|
| POST | `import/upload` | Загрузить CSV/XLSX во временное хранилище. |
| POST | `import/preview` | Заголовки, пример строк и автоматическое сопоставление колонок. |
| POST | `import/start` | Запустить импорт. |
| GET | `import/status?id={id}` | Ход процесса импорта. |

### uploads

| Метод | Путь | |
|---|---|---|
| POST | `uploads/upload` | Загрузка произвольного файла. |
| POST | `uploads/image` | Загрузка изображения (её использует Wysiwyg). |
| GET | `uploads/serve?disk=&path=` | Отдать сохранённый файл. |

### delayed (долгие задачи)

| Метод | Путь | |
|---|---|---|
| POST | `delayed/run` | Запустить процесс в очереди. Только разрешённые обработчики. |
| GET | `delayed/status?uuid={u}` | Опрос прогресса. |

## Кеширование

- **Манифест** — ETag равен `version` манифеста (sha256 от версии пакета и
  содержимого, которое собирается для конкретной локали и панели, поэтому
  меняется и вместе с правами). С `If-None-Match` придёт 304.
- **Bootstrap** — не кешируется (CSRF-токен на каждый запрос).
- **Остальные эндпоинты** — без HTTP-кеша (требуют входа, запросы быстрые).

## Ограничение частоты запросов

По умолчанию `240/мин` на все эндпоинты админки
(`config('admin.api.throttle')`, `'240,1'`), плюс отдельные лимиты на вход:

- `auth/login`, `auth/twoFactorChallenge`, `auth/twoFactorRecovery` — 5/мин
  (`config('admin.auth.login_throttle')`, `'5,1'`)
- `auth/forgotPassword` — 3 за 5 минут
- `auth/resendEmailVerification` — 3/мин

## Спецификация OpenAPI

```
GET /api/admin/doc            # Scalar UI (interactive)
GET /api/doc/admin            # Raw JSON spec (laravel-api's per-version source)
```

Страница Scalar включена по умолчанию; если `config('admin.openapi.ui')`
(env `ADMIN_OPENAPI_UI`) задан чем-то кроме `scalar`, документацию отдаёт
штатный `/api/doc` из laravel-api.

Операции ресурсов описываются для каждого ресурса отдельно. `create` и
`update` перечисляют собственные поля ресурса — они строятся из `fields()` и
`validationRules()`: типы, форматы, обязательность, перечисления из опций и
границы. `search`, `export`, `action`, `reorder`, `inlineUpdate` и `update`
группы настроек описывают то, что зависит от ресурса (фильтры, сортируемые и
экспортируемые колонки, ключи actions, редактируемые колонки, значения
настроек). Контроллер, обслуживающий много маршрутов, может поступить так же:
объявите `@input [method]` и укажите в этом методе тип
`Dskripchenko\LaravelApi\Services\OpenApi\OperationContext` — в него придут
ключ контроллера и action описываемого маршрута.

`php artisan api:lint --strict` проверяет разметку, в том числе находит
actions, которые валидируют входные данные, но не объявляют их
(`input.undeclared`).

## См. также

- [Архитектура](architecture.md) — устройство манифеста и конверта
- [Frontend-расширение](frontend-extension.md) — добавление своих эндпоинтов
- [API](api/README.md) — подробные контракты по группам эндпоинтов
