---
title: Hierarchical Menu
audience: developer
status: stable
locale: en
---

# Hierarchical Menu

The sidebar menu can be any depth. Use `Admin::menu()` to declare it
explicitly; it integrates with auto-detected resources/screens.

## Auto mode (no setup)

If you don't configure menu, you get a flat list: every registered
Resource and every custom Screen becomes a top-level item. Useful for
small admins.

## Explicit hierarchy

```php
use Dskripchenko\LaravelAdmin\Menu\MenuNode;

Admin::menu()->add(
    MenuNode::make('content', 'Content')->icon('book')->children([
        MenuNode::resource('articles'),
        MenuNode::make('tags', 'Tags')->icon('tag')->children([
            MenuNode::make('tags-tech', 'Tech')->url('/r/articles?tag=tech')->children([
                MenuNode::make('tags-tech-vue', 'Vue')->url('/r/articles?tag=tech.vue'),
                MenuNode::make('tags-tech-php', 'PHP')->url('/r/articles?tag=tech.php'),
            ]),
        ]),
    ]),
);
Admin::menu()->add(
    MenuNode::make('shop', 'Shop')->icon('shopping-cart')->children([
        MenuNode::resource('products'),
        MenuNode::resource('orders'),
    ]),
);
Admin::menu()->add(
    MenuNode::make('analytics', 'Analytics')->icon('chart-bar')->children([
        MenuNode::dashboard('content'),
    ]),
);
```

## MenuNode factories

| Factory | Resolves |
|---|---|
| `MenuNode::make($key, $label)` | Manual node — set `icon()`/`url()`/`routeName()` yourself. |
| `MenuNode::resource($slug)` | Pulls label/url/permissions from `ResourceRegistry`. |
| `MenuNode::screen($slug)` | Pulls label/url from `ScreenRegistry`. Auto-detects DashboardScreen and routes to `/dashboard/{slug}` instead. |
| `MenuNode::dashboard($slug)` | Explicit DashboardScreen helper — `/dashboard/{slug}`. |

Manual overrides win over auto-resolved values:

```php
MenuNode::resource('articles')->label('All articles')->icon('newspaper'),
```

## Fluent API

```php
MenuNode::make($key, $label)
    ->icon('lucide-name')
    ->url('/custom/path')                  // or
    ->routeName('admin.custom.route')      // takes precedence over url
    ->badge(42)                            // number or string
    ->permissions(['admin.articles.view']) // array, string, or null
    ->order(10)                            // sort within group
    ->group('Section')                     // section header (top-level only)
    ->children([ MenuNode::... ])
    ->add(MenuNode::...);                  // append child
```

## Insert into existing parent

```php
Admin::menu()->under('shop', [
    MenuNode::resource('coupons'),
    MenuNode::resource('discounts'),
]);
```

`under($parentKey, [...])` searches recursively. If `$parentKey` doesn't
exist, a stub parent node is created.

## Auto-fill control

Default: any Resource/custom Screen not mentioned in your tree is
appended automatically (under the `Tools` group for screens; with no
group for resources). To disable:

```php
Admin::menu()->withAuto(false);
```

You then need to mention every visible item explicitly.

## Permissions

A node is hidden if its `permissions` aren't satisfied by the current
user. If `MenuNode::resource()` was used, the gate defaults to
`admin.{slug}.view`.

Sub-trees are filtered too: a parent stays visible if at least one
child passes; otherwise the whole branch is hidden.

## Visual hierarchy

The frontend renders nodes recursively (`AdminSidebarNode.vue`):

- Depth 0..2: progressively indented (`14px` per level).
- Depth ≥ 3: indent fixed at `28px`, replaced by a left vertical
  stripe (color = primary, fading alpha by depth: 0.85 → 0.67 → ...).
- Parents with children show a chevron; click toggles open.
- Active route auto-expands its ancestor chain.

## Backend response shape

```json
{
  "items": [
    {
      "key": "content",
      "label": "Content",
      "icon": "book",
      "url": null,
      "routeName": null,
      "badge": null,
      "group": null,
      "order": 0,
      "permissions": [],
      "children": [
        { "key": "resource.articles", "label": "Articles", "url": "/r/articles", ..., "children": [] }
      ]
    }
  ]
}
```

## See also

- [Resources](resources.md)
- [Screens](screens.md)
- [Permissions](permissions.md)
