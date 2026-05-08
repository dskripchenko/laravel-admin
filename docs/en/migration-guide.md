---
title: Migration Guide
audience: developer
status: stable
locale: en
---

# Migration Guide

Upgrades between major and minor versions. We follow [SemVer](https://semver.org).

## 1.3.x → 1.4.0

**No breaking changes.** New features are opt-in. Notable additions:

### New: hierarchical menu (M1+M2)

```php
Admin::menu()->add(
    MenuNode::make('shop', 'Shop')->children([
        MenuNode::resource('products'),
        MenuNode::resource('orders'),
    ]),
);
```

If you don't call `Admin::menu()`, the auto-fill behaviour from 1.2.x
is preserved.

### New: Custom Screens (P21+P22)

```php
class ContactScreen extends Screen { /* ... */ }

Admin::screen([ContactScreen::class]);
```

URL: `/admin/screens/contact`. See
[concepts/screens.md](concepts/screens.md).

### New: Widget polling and rowSpan

```php
StatsOverviewWidget::make()
    ->title('Live')
    ->refresh(30)        // poll every 30s
    ->rowSpan(2);        // 2 grid rows tall (1.4.0)
```

`refresh()` was already in 1.2.x but the frontend ignored it. Now it
triggers a `setInterval` on `/dashboard/widgets`.

### Frontend: WidgetRenderer prop filter

If you wrote a custom widget Vue component that accessed
`props.size` (in pixels), you'll need to add your own pixel-size prop —
the previous `size` prop was a grid-column-span (1..12), but ambiguity
between span and pixels is now resolved by stripping dashboard-meta
fields. See
[frontend-extension.md → Custom widget](frontend-extension.md#custom-widget).

## 1.2.x → 1.3.0

Released as the initial drop of the Custom Screens API. All 1.4.0
changes started here; we recommend upgrading directly to 1.4.0
(non-breaking).

## 1.1.x → 1.2.0

### New: Dashboard widgets

`Widget::class`, `DashboardScreen::class` were introduced. Sister-packs
weren't bumped — they continue to work at `^1.2.0`.

### New: 2FA TOTP

`AdminUser::twoFactorEnabled` column was added by migration. Run
`php artisan migrate` after upgrade.

### Bell-notifications

`SystemController::me` now returns `unread_notifications_count`. If
you've overridden the `me` payload, merge in the new field.

## 1.0.x → 1.1.0

(Pre-stable, no published 1.0.x release. 1.1.0 was the first stable
publish.)

## Upgrade checklist (general)

For any minor/patch bump:

```bash
composer update dskripchenko/laravel-admin
npm update @dskripchenko/laravel-admin @dskripchenko/ui
php artisan migrate
php artisan optimize:clear
npm run build
```

For major bumps, additionally:

1. Read this guide top-to-bottom for the relevant section.
2. Skim `CHANGELOG.md` for breaking changes.
3. Run `vendor/bin/pest` (or your full test suite) before deploying.

## See also

- [`CHANGELOG.md`](../../CHANGELOG.md)
- [Architecture](architecture.md)
