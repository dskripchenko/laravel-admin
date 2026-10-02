---
title: Демо-режим
audience: developer
status: stable
locale: ru
translated_from: en/demo-mode.md
translated_at: 2026-10-02
---

# Демо-режим

Демо-режим превращает установку в публичный демонстрационный стенд:
посетитель входит в один клик под одним из демо-аккаунтов, а операции,
которыми один посетитель мог бы испортить стенд следующему, отклоняются. По
умолчанию режим выключен.

```dotenv
ADMIN_DEMO=true
```

## Демо-аккаунты

Перечислите аккаунты в `config/admin.php` — на странице входа для каждого
появится кнопка «Войти как …»:

```php
'demo' => [
    'enabled' => (bool) env('ADMIN_DEMO', false),
    'accounts' => [
        [
            'label' => 'Администратор',
            'email' => 'admin@demo.test',
            'password' => 'demo',
            'description' => 'Полный доступ',
        ],
        [
            'label' => 'Редактор',
            'email' => 'editor@demo.test',
            'password' => 'demo',
            'description' => 'Только контент, без настроек',
        ],
    ],
],
```

Клик заполняет форму и входит через обычный эндпоинт входа, поэтому всё, что
действует при входе, — throttle, отключённая учётная запись, 2FA — действует
и здесь. `label` и `description` проходят через переводчик и могут быть
ключами перевода.

> **Внимание** Пароли уходят каждому, кто открыл страницу входа. Указывайте
> только демо-аккаунты, никогда — настоящие, и выдавайте им те права, которые
> не жалко показать.

Сами аккаунты — обычные пользователи: создайте их в сидере.

## Защита от записи

При включённом `readonly` (по умолчанию, как только включён демо-режим) API
отклоняет набор операций ответом `403` с `errorKey: demo_readonly`. Панель
показывает отказ тостом «Демо-режим: это действие отключено.» вместо общей
ошибки.

```php
'demo' => [
    'readonly' => (bool) env('ADMIN_DEMO_READONLY', true),

    // Действия API как шаблоны `controller.action`; `*` — что угодно.
    'blocked' => [
        'profile.update',
        'profile.changePassword',
        'profile.twoFactorEnable',
        'profile.twoFactorConfirm',
        'profile.twoFactorDisable',
        'profile.twoFactorRegenerateCodes',
        'profile.tokenCreate',
        'profile.tokenRevoke',
        'auth.startImpersonation',
        'import.*',
        'settings_*.update',
    ],

    // Запись в ресурсы этих моделей отклоняется. null — модель
    // пользователей панели и Role: никто не запрёт стенд следующему.
    'protected_models' => null,
    'protected_actions' => [
        'create', 'update', 'inlineUpdate', 'replicate', 'reorder',
        'delete', 'restore', 'forceDelete', 'action',
    ],

    // Загруженные файлы больше этого размера отклоняются; 0 — без лимита.
    'max_upload_kb' => 2048,
],
```

Что закрыто по умолчанию:

| Операция | Почему |
|---|---|
| Изменение профиля и пароля | Следующий посетитель не сможет войти |
| Включение и выключение 2FA, новые коды восстановления | То же |
| Создание и отзыв API-токенов | Токен переживает демо-сессию |
| Имперсонация | Открывает все аккаунты стенда |
| Запись в пользователей и роли | Удаление демо-аккаунтов или их прав |
| Настройки | Настройки общие для всех посетителей |
| Импорт | Массовая запись |
| Большие файлы | Место на диске |

Всё остальное — создание, правка и удаление демо-записей — открыто: ради
этого посетитель и пришёл.

### Настройка списков

Списки — обычный конфиг, хост добавляет и убирает пункты:

```php
// Профиль разрешить, массовые действия «articles» — закрыть.
'blocked' => [
    'profile.changePassword',
    'articles.action',
    'settings_*.update',
],

// Защитить ещё одну модель.
'protected_models' => [
    App\Models\User::class,
    Dskripchenko\LaravelAdmin\Permission\Models\Role::class,
    App\Models\Tenant::class,
],
```

Контроллер ресурса — его slug (`articles.update`), страницы настроек —
`settings_{slug}`, экрана — его slug (`reports.runMethod`). Встроенные
контроллеры: `auth`, `profile`, `system`, `dashboard`, `audit`, `import`,
`uploads`, `notifications`, `delayed`.

`readonly` проверяется на каждом запросе, поэтому его можно переключать и на
лету — в middleware, для конкретного пользователя:

```php
config(['admin.demo.readonly' => ! $request->user()?->is_staff]);
```

## Баннер

Расскажите посетителю, на каком стенде он оказался, баннером установки
`admin.notice`. Его рисует оболочка — над панелью и на странице входа:

```dotenv
ADMIN_NOTICE="Демо-стенд: данные сбрасываются каждый час"
ADMIN_NOTICE_HREF=https://example.com/docs
ADMIN_NOTICE_COUNTDOWN_LABEL="до сброса"
```

`countdown_to` (ISO-8601) добавляет обратный отсчёт; задавайте его из
middleware — закешированный конфиг заморозит вычисленное значение:

```php
config(['admin.notice.countdown_to' => now()->startOfHour()->addHour()->toIso8601String()]);
```

## Сброс данных

Пакет сам стенд не сбрасывает: запланируйте свою команду, которая
восстанавливает базу (`migrate:fresh --seed`, дамп, снапшот) и заново создаёт
демо-аккаунты.

```php
// routes/console.php
Schedule::command('migrate:fresh --seed --force')->hourly();
```
