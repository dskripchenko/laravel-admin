---
title: Permissions
audience: developer
status: stable
locale: en
---

# Permissions

RBAC: users hold roles, roles hold permission strings, every action is
gated by `AdminAccess` middleware.

## Permission keys

Format: `admin.{domain}.{action}`. Examples:

- `admin.users.view`
- `admin.articles.update`
- `admin.system.settings.edit`
- `admin.audit.view`

Wildcards:
- `admin.users.*` — all actions in users domain
- `admin.*` — all admin permissions
- `*` — superadmin

## Roles

Stored in `admin_roles`. A user gets a role via `assignRole()`:

```php
$role = Role::create([
    'name' => 'Editor', 'slug' => 'editor',
    'permissions' => ['admin.articles.*', 'admin.media.view'],
]);
$user->assignRole($role);
```

## Resource auto-permissions

For each Resource the admin auto-generates permissions matching the
actions:

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

`AdminAccess:admin.articles.create` middleware guards the
`create`-route automatically.

Override the base via `Resource::permission()`:

```php
public static function permission(): string
{
    return 'admin.cms.articles';   // → admin.cms.articles.view, .update, ...
}
```

## Custom permission registration

```php
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;

Admin::permissions(
    ItemPermission::group('Reports')
        ->addPermission('admin.reports.view', 'View reports')
        ->addPermission('admin.reports.export', 'Export reports'),
);
```

These appear in the role-edit screen as checkable items.

## Checking in code

```php
$user->hasAccess('admin.articles.update');   // true / false
$user->hasAccess(['admin.articles.update', 'admin.articles.delete']);  // OR
```

In Blade / Vue (manifest exposes user permissions to SPA):

```ts
const auth = useAuthStore()
auth.hasAnyPermission(['admin.articles.update'])
```

## Middleware on actions

`AdminAccess` middleware accepts one or more permissions separated by `;`:

```php
'middleware' => [AdminAccess::class.':admin.users.view'],
'middleware' => [AdminAccess::class.':admin.users.view;admin.users.update'],  // AND
```

For OR semantics across permissions — check inside the action handler
explicitly.

## Screens

A Screen declares its gate via `permission()`:

```php
public function permission(): array|string|null
{
    return ['admin.reports.view', 'admin.exports.run'];   // AND
}
```

Both `state` (GET) and `runMethod` (POST) endpoints are auto-gated.

## Settings

Each `SettingsResource` gets `admin.settings.{slug}.view` /
`admin.settings.{slug}.update`.

## 2FA

Independent of RBAC. If enabled per-user, login flow requires the
TOTP code regardless of role.

## See also

- [Resources](resources.md) — auto-permissions per CRUD action
- [Screens](screens.md) — `permission()` method
- [Tenancy](tenancy.md) — tenant-scoped data + permissions
