# dskripchenko/laravel-admin-starter

## 1. Назначение

Готовый набор «системных» Resource'ов, превращающий голый core-конструктор в работающую базовую админку за одну команду. Также служит **референс-имплементацией** того, как пользоваться API admin: каждый Resource написан в идиоматическом стиле и может копироваться как стартовая точка для своих.

**Use case:** новый проект, нужно «администрировать пользователей и роли уже сегодня», без писанины Resource'ов с нуля.

## 2. Состав

### Resource'ы

- **`UserResource`** (модель `AdminUser`) — список, edit, view (Infolist).
  - Bulk: activate / deactivate / send-password-reset / impersonate.
  - Колонки: avatar, name, email, role, last_login, status badge, 2FA-enabled icon.
  - Фильтры: role, status, has_2fa, registered_in (DateRange).
  - Permission: `admin.systems.users.view|create|update|delete`.

- **`RoleResource`** — CRUD ролей.
  - Edit-форма: name, slug, description + матрица всех зарегистрированных `ItemPermission` (table-вид, группа × permission).
  - Системные роли (`is_system = true`, например Super Admin) защищены от удаления и редактирования матрицы.
  - Permission: `admin.systems.roles.*`.

- **`AuditLogResource`** — view-only (нет create/edit/delete, только list + view).
  - Фильтры: пользователь, сущность (morph_type + morph_id), событие (created/updated/deleted/restored/...), диапазон дат, IP.
  - View-страница рендерит inline diff old vs new через `<UiCode>` со side-by-side.
  - Экспорт в CSV (через built-in CsvExporter).
  - Permission: `admin.systems.audit.view`.

- **`SettingsResource`** (через `Resource\SettingsResource` базовый класс из core) — общие настройки приложения.
  - Tabs: «Бренд» (имя/логотип/primary-color), «SMTP» (host/port/encryption/from), «Локализация» (default locale, available locales, timezone), «Безопасность» (session lifetime, password policy, 2FA enforce-list).
  - Storage: `keyValue('admin_settings', group: 'general|smtp|locale|security')`.
  - Permission: `admin.systems.settings.update`.

- **`TranslationResource`** — UI для DB-loader из `dskripchenko/laravel-translatable`.
  - Колонки: `namespace.group.key`, значения по локалям (через `TranslatableInput`-вкладки), updated_at, updated_by.
  - Фильтры: namespace, group, locale, has_missing_translations, search по key/value.
  - Bulk-action: «Скопировать переводы из локали X в локаль Y» (для пропусков).
  - Permission: `admin.systems.translations.*`.

- **`ContentBlockResource`** — для CMS content-блоков (`ContentBlockService`).
  - Колонки: name, page, last_updated_at, has_translations.
  - Edit: WYSIWYG (Tiptap), preview-режим, ревизии.
  - Permission: `admin.systems.content-blocks.*`.

- **`AdminUserSessionResource`** (опционально, флаг в config) — список активных сессий.
  - Колонки: user, ip, user_agent, last_activity, current?
  - Action: «разлогинить» (invalidate session).
  - Permission: `admin.systems.sessions.view|terminate`.

### Меню

Регистрирует группу **«Системные»** в сайдбаре с пунктами в указанном порядке. Иконки и порядок настраиваются через `config/admin-starter.php`.

### Permissions-группа

```php
ItemPermission::group('Системные')
    ->addPermission('admin.systems.users.view',         'Пользователи: просмотр')
    ->addPermission('admin.systems.users.create',       'Пользователи: создание')
    ->addPermission('admin.systems.users.update',       'Пользователи: редактирование')
    ->addPermission('admin.systems.users.delete',       'Пользователи: удаление')
    ->addPermission('admin.impersonate',                'Войти под другим пользователем')
    ->addPermission('admin.systems.roles.*',            'Роли (полный доступ)')
    ->addPermission('admin.systems.audit.view',         'Журнал аудита: просмотр')
    ->addPermission('admin.systems.settings.update',    'Настройки: редактирование')
    ->addPermission('admin.systems.translations.*',     'Переводы (полный доступ)')
    ->addPermission('admin.systems.content-blocks.*',   'Контент-блоки (полный доступ)')
    ->addPermission('admin.systems.sessions.view',      'Сессии: просмотр')
    ->addPermission('admin.systems.sessions.terminate', 'Сессии: завершить чужую');
```

## 3. Зависимости

Composer: `dskripchenko/laravel-admin: ^1.0` (peer по мажорной версии). Никаких сторонних.

NPM: нет (всё через core-компоненты `@dskripchenko/ui`).

## 4. Миграции

Нет. Все Resource'ы работают поверх таблиц core'а (`admin_users`, `admin_roles`, `admin_audit_logs`, `admin_notifications`, `admin_settings`).

## 5. Конфиг

`config/admin-starter.php`:

```php
return [
    'resources' => [
        'users'          => true,
        'roles'          => true,
        'audit_log'      => true,
        'settings'       => true,
        'translations'   => true,
        'content_blocks' => false,   // выключен для не-CMS проектов
        'sessions'       => false,
    ],
    'menu_group' => 'Системные',
    'menu_icon'  => 'gear',
    'menu_order' => 9999,             // в конец сайдбара
];
```

## 6. Подключение

```bash
composer require dskripchenko/laravel-admin-starter
php artisan admin:plugin:install starter
```

Команда:

1. публикует `config/admin-starter.php`;
2. регистрирует группу permissions через `Admin::plugin(AdminStarterPlugin::class)` (можно сделать вручную в `AdminServiceProvider`, без auto-discovery);
3. создаёт первую роль «Super Admin» со всеми пермишенами и привязывает к существующему `AdminUser` (если есть один) — interactive.

## 7. Зачем sister, а не core

Core — конструктор. Готовые Resource'ы делают «admin под ключ», что противоречит позиционированию: одни команды хотят писать с нуля, другие хотят готовое. Разделение на core + starter позволяет обоим сценариям сосуществовать без дублирования.

Кроме того, starter — живая документация: каждый Resource там можно копировать как пример.
