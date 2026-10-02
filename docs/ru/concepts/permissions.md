---
title: Права доступа
audience: developer
status: stable
locale: ru
translated_from: en/concepts/permissions.md
translated_at: 2026-10-02
---

# Права доступа

RBAC: у пользователей есть роли, у ролей — строки прав, каждый эндпоинт
защищён middleware `AdminAccess`.

## Ключи прав

Формат: `admin.{domain}.{action}`. Примеры:

- `admin.users.view`
- `admin.articles.update`
- `admin.system.settings.edit`
- `admin.audit.view`

Wildcard'ы:
- `admin.users.*` — все действия в разделе users
- `admin.*.view` — просмотр всего
- `admin.*` — все права админки
- `*` — суперадмин

Выданный ключ со `*` сравнивается через `fnmatch()`, где `*` совпадает и
с точками: `admin.*` покрывает в том числе `admin.cms.articles.view`.

## Роли

Хранятся в `admin_roles`; `permissions` — JSON-список строк. Модель
пользователя с trait'ом `HasAdminAccess` получает роль через
`assignRole()` (принимает `Role`, её id или slug) и теряет через
`revokeRole()`:

```php
$role = Role::create([
    'name' => 'Editor', 'slug' => 'editor',
    'permissions' => ['admin.articles.*', 'admin.media.view'],
]);
$user->assignRole($role);
```

## Автоматические права ресурса

Для каждого Resource админка выводит ключи прав из
`Resource::permission()` (по умолчанию `admin.{slug}`) и закрывает ими
маршруты ресурса:

```
admin.articles.view
admin.articles.create
admin.articles.update
admin.articles.delete
admin.articles.restore         (if soft-delete)
admin.articles.force-delete    (if soft-delete)
admin.articles.replicate       (if replicable)
admin.articles.reorder         (if reorderable)
```

Маршрут `create` автоматически закрыт middleware
`AdminAccess:admin.articles.create`. Свои actions (`POST /{slug}/action`)
требуют `.view`. Эти ключи — только проверки на маршрутах: чтобы их можно
было отметить в редакторе ролей, зарегистрируйте их через
`Admin::permissions()` (см. ниже).

Базу можно переопределить через `Resource::permission()`:

```php
public static function permission(): string
{
    return 'admin.cms.articles';   // → admin.cms.articles.view, .update, ...
}
```

## Регистрация своих прав

```php
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;

Admin::permissions(
    ItemPermission::group('Reports')
        ->addPermission('admin.reports.view', 'View reports')
        ->addPermission('admin.reports.export', 'Export reports'),
);
```

Они появляются на экране редактирования роли как пункты с галочками.

Название группы и подписи передавайте исходными строками (или ключами
перевода), а не результатом `__()`: группы регистрируются один раз при
загрузке, а подписи переводятся через JSON-переводы в момент отдачи, на
языке конкретного запроса. Уже переведённая подпись возвращается как есть.

## Проверка в коде

```php
$user->hasAccess('admin.articles.update');   // true / false
$user->hasAnyAccess(['admin.articles.update', 'admin.articles.delete']);  // OR
$user->hasAllAccess(['admin.articles.update', 'admin.articles.delete']);  // AND
```

В SPA (bootstrap и ответ на логин содержат плоский список прав
пользователя; store понимает те же маски `*`):

```ts
const auth = useAuthStore()
auth.hasAnyPermission(['admin.articles.update'])
```

## Middleware на эндпоинтах

Middleware `AdminAccess` принимает одно или несколько прав через `;`:

```php
'middleware' => [AdminAccess::class.':admin.users.view'],
'middleware' => [AdminAccess::class.':admin.users.view;admin.users.update'],  // AND
```

Для семантики OR проверяйте права явно внутри обработчика.

## Screens

Screen объявляет свою проверку через `permission()`:

```php
public function permission(): array|string|null
{
    return ['admin.reports.view', 'admin.exports.run'];   // AND
}
```

Эндпоинты `state` (GET), `runMethod` и `listener` (POST) закрываются
автоматически. `null` (по умолчанию) означает, что достаточно
аутентификации.

## Settings

Каждый `SettingsResource` получает `admin.settings.{slug}.view` /
`admin.settings.{slug}.update`.

## 2FA

Не зависит от RBAC. Если 2FA включена у пользователя, вход требует
TOTP-код независимо от роли.

## См. также

- [Resources](resources.md) — автоматические права на CRUD-действия
- [Screens](screens.md) — метод `permission()`
- [Tenancy](tenancy.md) — данные и права в разрезе tenant'а
