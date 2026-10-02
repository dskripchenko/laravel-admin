---
title: 层级菜单
audience: developer
status: stable
locale: zh
translated_from: en/concepts/menu.md
translated_at: 2026-10-02
---

# 层级菜单

侧边栏菜单可以有任意深度。使用 `Admin::menu()` 显式声明它；它会与自动检测到的
Resource/Screen 相整合。

## 自动模式（无需配置）

如果不配置菜单，你会得到一个扁平列表：每个已注册的 Resource 和每个自定义 Screen
都会成为顶级菜单项。适用于小型管理面板。

## 显式层级

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

## MenuNode 工厂方法

| 工厂方法 | 解析为 |
|---|---|
| `MenuNode::make($key, $label)` | 手动节点——自行设置 `icon()`/`url()`/`routeName()`。 |
| `MenuNode::resource($slug)` | 从 `ResourceRegistry` 获取 label/url/permissions。 |
| `MenuNode::screen($slug)` | 从 `ScreenRegistry` 获取 label/url。会自动识别 DashboardScreen，并改为路由到 `/dashboard/{slug}`。 |
| `MenuNode::dashboard($slug)` | 显式的 DashboardScreen 辅助方法——`/dashboard/{slug}`。 |

手动覆盖的值优先于自动解析的值：

```php
MenuNode::resource('articles')->label('All articles')->icon('newspaper'),
```

## Fluent API

```php
MenuNode::make($key, $label)
    ->icon('lucide-name')
    ->url('/custom/path')                  // 或者
    ->routeName('admin.custom.route')      // 优先于 url
    ->badge(42)                            // 数字或字符串
    ->permissions(['admin.articles.view']) // 数组、字符串或 null
    ->order(10)                            // 组内排序
    ->group('Section')                     // 分区标题（仅顶级）
    ->children([ MenuNode::... ])
    ->add(MenuNode::...);                  // 追加子节点
```

## 插入到已有父节点下

```php
Admin::menu()->under('shop', [
    MenuNode::resource('coupons'),
    MenuNode::resource('discounts'),
]);
```

`under($parentKey, [...])` 会递归查找。如果 `$parentKey` 不存在，会创建一个
占位父节点。

## 自动填充控制

默认：树中未提及的任何 Resource/自定义 Screen 都会被自动追加（Screen 放在
`Tools` 分组下，Resource 放在其自身的 `$group` 下，未设置时则不分组）。要禁用：

```php
Admin::menu()->withAuto(false);
```

此后你需要显式提及每个可见的菜单项。

若要保持自动填充开启，但排除某个 Resource 或 Screen——比如只在父级中嵌入显示的
子 Resource：

```php
Admin::menu()->hideAuto('order-items');
```

## 权限

如果当前用户不满足节点的 `permissions`，该节点会被隐藏。如果使用的是
`MenuNode::resource()`，默认的权限检查为 `admin.{slug}.view`。

子树同样会被过滤：只要至少一个子节点通过，父节点就保持可见；否则整个分支都会被
隐藏。

## 视觉层级

前端以递归方式渲染节点（`AdminSidebarNode.vue`）：

- 深度 0..2：逐级缩进（每级 `14px`）。
- 深度 ≥ 3：缩进固定为 `28px`，改用左侧竖条表示（颜色 = primary，透明度随深度
  递减：0.85 → 0.67 → ...）。
- 有子节点的父节点显示一个 chevron 箭头；点击切换展开。
- 当前路由会自动展开其祖先链。

## 后端响应结构

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

## 另请参阅

- [Resource](resources.md)
- [Screen](screens.md)
- [权限](permissions.md)
