---
title: Иерархическое меню
audience: developer
status: stable
locale: ru
translated_from: en/concepts/menu.md
translated_at: 2026-05-08
---

# Иерархическое меню

Sidebar-меню может быть любой глубины. `Admin::menu()` декларирует его
явно; интегрируется с auto-detected resource'ами/screen'ами.

## Auto-режим (без настройки)

Если `Admin::menu()` не вызывается, получаешь плоский список: каждый
зарегистрированный Resource и каждый custom Screen становятся
top-level items.

## Явная иерархия

```php
use Dskripchenko\LaravelAdmin\Menu\MenuNode;

Admin::menu()->add(
    MenuNode::make('content', 'Контент')->icon('book')->children([
        MenuNode::resource('articles'),
        MenuNode::make('tags', 'Метки')->icon('tag')->children([
            MenuNode::make('tags-tech', 'Tech')->url('/r/articles?tag=tech')->children([
                MenuNode::make('tags-tech-vue', 'Vue')->url('/r/articles?tag=tech.vue'),
                MenuNode::make('tags-tech-php', 'PHP')->url('/r/articles?tag=tech.php'),
            ]),
        ]),
    ]),
);
Admin::menu()->add(
    MenuNode::make('shop', 'Магазин')->icon('shopping-cart')->children([
        MenuNode::resource('products'),
        MenuNode::resource('orders'),
    ]),
);
Admin::menu()->add(
    MenuNode::make('analytics', 'Аналитика')->icon('chart-bar')->children([
        MenuNode::dashboard('content'),
    ]),
);
```

## Фабрики MenuNode

| Фабрика | Резолвит |
|---|---|
| `MenuNode::make($key, $label)` | Ручной узел — `icon()`/`url()`/`routeName()` сам. |
| `MenuNode::resource($slug)` | Тянет label/url/permissions из `ResourceRegistry`. |
| `MenuNode::screen($slug)` | Тянет label/url из `ScreenRegistry`. Auto-detect DashboardScreen → `/dashboard/{slug}`. |
| `MenuNode::dashboard($slug)` | Явный helper — `/dashboard/{slug}`. |

Manual-overrides побеждают auto-resolved значения:

```php
MenuNode::resource('articles')->label('Все статьи')->icon('newspaper'),
```

## Fluent API

```php
MenuNode::make($key, $label)
    ->icon('lucide-name')
    ->url('/custom/path')                  // или
    ->routeName('admin.custom.route')      // имеет приоритет над url
    ->badge(42)                            // число или строка
    ->permissions(['admin.articles.view'])
    ->order(10)                            // sort внутри группы
    ->group('Раздел')                      // header секции (только top-level)
    ->children([ MenuNode::... ])
    ->add(MenuNode::...);                  // добавить child
```

## Вставка в существующий parent

```php
Admin::menu()->under('shop', [
    MenuNode::resource('coupons'),
    MenuNode::resource('discounts'),
]);
```

`under($parentKey, [...])` ищет рекурсивно. Если `$parentKey` нет —
создаётся stub-родитель.

## Auto-fill контроль

По умолчанию: любой Resource/Screen, не упомянутый в дереве, добавляется
автоматически (Screen'ы — в группу `Инструменты`, Resource'ы — без
группы). Чтобы выключить:

```php
Admin::menu()->withAuto(false);
```

Тогда нужно явно перечислить каждый видимый item.

## Permissions

Узел скрывается, если его `permissions` не удовлетворены текущим
пользователем. Для `MenuNode::resource()` дефолт — `admin.{slug}.view`.

Sub-tree фильтруется тоже: parent остаётся видимым если хотя бы один
child проходит; иначе вся ветка скрыта.

## Визуальная иерархия

Frontend рендерит узлы рекурсивно (`AdminSidebarNode.vue`):

- Глубина 0..2: прогрессивный indent (`14px` на уровень).
- Глубина ≥ 3: indent зафиксирован на `28px`, вместо него — левая
  вертикальная stripe-полоса (color = primary, fading alpha по depth:
  0.85 → 0.67 → ...).
- Parents с children показывают chevron; click toggles open.
- Active route auto-expand'ит свою цепочку предков.

## Backend response shape

```json
{
  "items": [
    {
      "key": "content",
      "label": "Контент",
      "icon": "book",
      "url": null,
      "routeName": null,
      "badge": null,
      "group": null,
      "order": 0,
      "permissions": [],
      "children": [
        { "key": "resource.articles", "label": "Статьи", "url": "/r/articles", ... }
      ]
    }
  ]
}
```

## См. также

- [Resources](resources.md)
- [Screens](screens.md)
- [Permissions](../en/concepts/permissions.md) (en)
