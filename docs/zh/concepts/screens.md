---
title: Screen
audience: developer
status: stable
locale: zh
translated_from: en/concepts/screens.md
translated_at: 2026-10-02
---

# Screen

**Screen** 是非 CRUD 页面：联系表单、状态报告、自定义导入向导、
集成页面。Screen 复用 `Field`/`Layout`/`Action` 这些基本组件，
但不绑定到 Eloquent 模型。

```php
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Screen\Screen;

final class ContactScreen extends Screen
{
    public function name(): string { return '联系我们'; }

    public function query(mixed ...$params): array
    {
        return ['email' => '', 'message' => ''];
    }

    public function layout(): array
    {
        return [
            Rows::make([
                Input::make('email')->required()->type('email'),
                Textarea::make('message')->required()->rows(6),
            ]),
        ];
    }

    public function commandBar(): array
    {
        return [Button::make('发送')->method('send')->primary()];
    }

    public function send(array $state): array
    {
        validator($state, [
            'email' => 'required|email',
            'message' => 'required|min:10',
        ])->validate();

        \Mail::to('team@example.com')->send(new \App\Mail\Contact($state));

        return [
            'message' => '已发送',
            'state' => ['email' => '', 'message' => ''],
            'alerts' => [['type' => 'success', 'message' => '谢谢！']],
        ];
    }
}
```

注册：`Admin::screen([ContactScreen::class])`。

URL：`/admin/screens/contact`。

slug 是单个路径段：`/admin/screens/{slug}` 是 Screen 唯一的地址，
不存在 `/admin/screens/{slug}/{anything}`。

## 查询字符串

页面的查询字符串会随获取 Screen state（状态）的请求一起发送，因此
Screen 可以打开到地址中指定的标签页、时间段或过滤条件——
`/admin/screens/reports?period=30&tab=billing`。查询字符串的变化
（从 Screen 指向 `?tab=…` 的链接）会加载新的快照，而命令方法之后的
刷新会保留它。

`query()` 按位置接收这些值，顺序与查询字符串一致（以 `_` 开头的键
会被丢弃）；按名称则可以通过请求获取：

```php
public function query(mixed ...$params): array
{
    return [
        'tab' => request()->query('tab', 'overview'),
        'period' => (int) request()->query('period', 7),
    ];
}
```

这些值来自地址栏，因此要像对待任何其他输入一样对其进行校验。

## 结构

| 方法 | 用途 |
|---|---|
| `slug()` | 稳定的 URL 标识符。默认是去掉 `Screen` 后缀的类短名的 kebab-case 形式。 |
| `name()` | 在页头和侧边栏中显示的标题。 |
| `description()` | 标题下方可选的副标题。 |
| `permission()` | 权限闸门（字符串或列表）。null = 任何已认证的管理员。 |
| `query(...$params)` | 返回初始 state。以位置参数的形式接收页面查询字符串的值（见下文）。 |
| `layout()` | 返回 `Renderable[]`（Rows/Columns/Tabs/Block/...）。 |
| `commandBar()` | 返回在页头渲染的 `Action[]`。 |
| 公共方法 | 任何其他公共方法（不在保留集合中）都可以通过 `Button::make('…')->method('xxx')` 作为命令调用。 |

保留的方法名：`query`、`layout`、`name`、`description`、
`permission`、`commandBar`、`compile`、`slug`、`reservedMethods`、
`isCallableMethod`。

## 命令方法

命令方法只接收一个参数：来自前端的 state 载荷
（`{form_field: value, ...}`）：

```php
public function send(array $state): array { ... }
```

返回值：

- `array` —— 包装成规范化的 `ScreenMethodPayload` 后返回。可识别的键：
  `state`、`layouts`、`message`、`message_link`、`alerts`、
  `redirect_url`、`refresh`、`download_url`；任何其他键都会放入
  `extra`。
- `JsonResponse` —— 原样透传。
- `null` / `void` —— `{ok: true}`。

`message_link` 是消息下方的链接。可接受的形式：
`['url' => '/r/jobs/7', 'label' => 'Open the job']`、
`['href' => …, 'text' => …]`、二元组 `['/r/jobs/7', 'Open the job']`，或者
一个裸 URL 字符串。没有标签时使用默认的 "Open"；没有 URL 时该链接会被
丢弃。`redirect_url` —— 面板内路径（`/r/orders`；面板前缀可以保留）
通过路由器打开，外部地址则整页加载；重定向时会跳过 `refresh`。

校验：抛出 `\Illuminate\Validation\ValidationException`（例如
通过 `validator(...)->validate()`）——前端的 `useScreenStore.errors`
会显示字段错误。基于业务原因的拒绝使用
`ActionFailedException`
（`Dskripchenko\LaravelAdmin\Resource\ActionFailedException`）：返回 422，
带有 `errorKey: action_failed` 及其消息，面板会将其显示为错误。

## Listener

`Layout::listener([...])->listen([...])->handler('method')` 让 Screen
表单的一部分变为响应式：当被监听的字段变化时，SPA 把 state 提交到
`POST /api/admin/{slug}/listener`，服务器返回重新渲染的子树和一个
state 补丁。见
[布局参考 → Listener](../layouts-reference.md#listener表单的响应式部分)。

## 示例

### 只读 Screen（无表单）

```php
public function layout(): array
{
    return [
        Rows::make([
            Block::make('健康状况', [
                Number::make('articles_total')->title('文章')->readonly(),
                Input::make('db_status')->title('数据库')->readonly(),
            ]),
        ]),
    ];
}
```

`->readonly()` 对 `Select` 映射为 `disabled`，对 `Input`/`Number`
映射为原生的 `readonly`。

### 确认 Action

```php
Button::make('重置计数器')
    ->method('resetCounter')
    ->confirm('确定吗？此操作无法撤销。')
    ->destructive(),
```

### Action 之后刷新

```php
public function reload(): array
{
    return ['message' => '已刷新', 'refresh' => true];
}
```

`refresh: true` 会在 Action 之后触发 `useScreenStore.load()`。

### 下载

```php
public function exportCsv(): array
{
    $url = Storage::temporaryUrl(...);
    return ['download_url' => $url];
}
```

### 重定向

```php
public function publishAndOpen(array $state): array
{
    $article = Article::create($state);
    return ['redirect_url' => "/admin/r/articles/{$article->id}/edit"];
}
```

## 权限

```php
public function permission(): array|string|null
{
    return 'admin.contact';
}
```

`AdminAccess:admin.contact` 会自动附加到 `state` 和 `runMethod` 两个
Action 上。如需按方法分别设置闸门——在命令方法内部自行检查。

## 与 Resource 的区别

| 方面 | Resource | Screen |
|---|---|---|
| 绑定模型 | 是（Eloquent） | 否 |
| URL | `/r/{slug}`（+`/{id}/edit`、`/create`、`/{id}`） | `/screens/{slug}` |
| 端点 | `meta`、`search`、`read`、`create`、`update`、`delete`、... | `state`（GET）、`runMethod`（POST） |
| 自动生成 UI | 是 | 否（由宿主通过 `layout()` 控制） |
| 多条记录 | 是（表格） | 否（单一 state） |

## 另请参阅

- [权限](permissions.md)
- [布局参考](../layouts-reference.md)
