---
title: Руководство по обновлению
audience: developer
status: stable
locale: ru
translated_from: en/migration-guide.md
translated_at: 2026-10-02
---

# Руководство по обновлению

Переход между мажорными и минорными версиями. Версионирование —
[SemVer](https://semver.org).

## 1.4.x → 1.36.x

Версии 2.0 пока нет, и ни один из этих релизов не требует переписывать код,
но некоторые меняют поведение, на которое мог опираться ваш проект. От
новых к старым:

- **1.36.0** — нужен `dskripchenko/laravel-api` `^5.11`.
- **1.34.0** — нужен `@dskripchenko/ui` `^1.4.0` (важно только для своей
  сборки). Пункты меню с одинаковым `order` остаются в порядке добавления, а
  не сортируются по алфавиту. Консольные команды (`admin:user`,
  `admin:make-*`) выводят сообщения на английском.
- **1.33.0** — `admin:install` публикует готовую сборку фронтенда в
  `public/vendor/admin`; Node нужен, только если вы регистрируете свои
  Vue-компоненты. Локаль админки по умолчанию берётся из
  `config('app.locale')`, если не задан `ADMIN_LOCALE` /
  `admin.ui.default_locale` (раньше она была жёстко русской), — задайте её
  явно, если рассчитывали на прежнее поведение.
- **1.32.0** — версии API вашего приложения, подключённые рядом с админкой
  (`parent::getApiVersionList()`), больше не получают middleware-стек
  админки. Если ваша версия полагалась на `AdminLocale` или сессию оттуда,
  объявите их в `getMethods()` этой версии.
- **1.27.0** — вкладки профиля «API-токены» и «Сессии» показываются, только
  если приложение заполнило соответствующий слот.
- **1.17.0** — сохранённые представления списков включаются явно: верните
  `true` из `Resource::savedViews()` у ресурсов, которым они нужны; маршруты
  `{slug}_views/*` регистрируются только для них.
- **1.16.0** — action `{slug}/exportCsv` удалена; вызывайте
  `{slug}/export?format=csv`.
- **1.15.0** — actions ресурса регистрируются по его возможностям: `tree` и
  `treeScreen` — только для иерархических ресурсов, `restore` и
  `forceDelete` — только с `SoftDeletes`, `replicate` и `reorder` — только
  при `replicable()` / `reorderable()`. Неподдерживаемая action отвечает 404
  вместо 409/422.
- **1.7.0** — поддерживаются PHP 8.2–8.5 и Laravel 11/12/13;
  `dskripchenko/laravel-api` `^5.0`.
- **1.5.6** — на странице просмотра без собственного `infolist()` поля
  `Switcher` отображаются как `IconEntry` «Да/Нет», а не как сырые
  `true`/`false`.

После обновления выполните `php artisan migrate`: новые таблицы и колонки
приходят миграциями. Остальное — в [`CHANGELOG.md`](../../CHANGELOG.md).

## 1.3.x → 1.4.0

**Без ломающих изменений.** Новые возможности включаются по желанию.
Главное:

### Новое: иерархическое меню (M1+M2)

```php
Admin::menu()->add(
    MenuNode::make('shop', 'Shop')->children([
        MenuNode::resource('products'),
        MenuNode::resource('orders'),
    ]),
);
```

Если `Admin::menu()` не вызывать, меню заполняется автоматически, как в
1.2.x.

### Новое: кастомные экраны (P21+P22)

```php
class ContactScreen extends Screen { /* ... */ }

Admin::screen([ContactScreen::class]);
```

URL: `/admin/screens/contact`. См. [concepts/screens.md](concepts/screens.md).

### Новое: polling виджетов и rowSpan

```php
StatsOverviewWidget::make()
    ->title('Live')
    ->refresh(30)        // poll every 30s
    ->rowSpan(2);        // 2 grid rows tall (1.4.0)
```

`refresh()` был и в 1.2.x, но фронтенд его игнорировал. Теперь он запускает
`setInterval` на `/dashboard/widgets`.

### Фронтенд: фильтр props в WidgetRenderer

Если ваш Vue-компонент виджета читал `props.size` как размер в пикселях,
заведите для этого собственный prop. Прежний `size` означал ширину в
колонках сетки (1..12); чтобы не путать колонки с пикселями, служебные поля
дашборда теперь из props вырезаются. См.
[Frontend-расширение](frontend-extension.md#свой-виджет) — раздел о своём виджете.

## 1.2.x → 1.3.0

Первый выпуск API кастомных экранов. Всё, что вошло в 1.4.0, началось
здесь; рекомендуем обновляться сразу до 1.4.0 (без ломающих изменений).

## 1.1.x → 1.2.0

### Новое: виджеты дашборда

Появились `Widget::class` и `DashboardScreen::class`. Версии sister-пакетов
не поднимались — они продолжают работать с `^1.2.0`.

### Новое: 2FA TOTP

Миграция добавляет в `admin_users` колонки `two_factor_secret`,
`two_factor_recovery_codes` и `two_factor_confirmed_at`. После обновления
выполните `php artisan migrate`.

### Уведомления с колокольчиком

`SystemController::me` теперь возвращает `unread_notifications_count`. Если
вы переопределяли ответ `me`, добавьте в него это поле.

## 1.0.x → 1.1.0

(Предварительные версии; релиза 1.0.x не публиковалось. 1.1.0 — первая
стабильная публикация.)

## Чек-лист обновления (общий)

Для любого минорного или патч-обновления:

```bash
composer update dskripchenko/laravel-admin
php artisan migrate
php artisan admin:publish        # republish the prebuilt frontend (the composer hook does it too)
php artisan optimize:clear
```

При своей сборке (`admin:install --custom-build`) вместо `admin:publish`
обновите npm-пакеты и пересоберите:

```bash
npm update @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
npm run build
```

Конфиг пакета сливается с вашим на один уровень вглубь: новый ключ внутри
опубликованного вами блока (скажем, внутри `auth`) не появится, пока вы его
не перенесёте, — если в релизе упомянуто изменение конфига, сравните свой
файл с `vendor/dskripchenko/laravel-admin/config/admin.php`.

Для мажорных обновлений дополнительно:

1. Прочитайте соответствующий раздел этого руководства целиком.
2. Просмотрите `CHANGELOG.md` на предмет ломающих изменений.
3. Перед выкладкой прогоните полный набор тестов.

## См. также

- [`CHANGELOG.md`](../../CHANGELOG.md)
- [Архитектура](architecture.md)
- [Подключение к существующему приложению → Обновление](integration.md#8-обновление-и-типичные-проблемы)
