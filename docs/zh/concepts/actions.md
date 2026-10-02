---
title: Action（动作）
audience: developer
status: stable
locale: zh
translated_from: en/concepts/actions.md
translated_at: 2026-10-02
---

# Action（动作）

**Action** 是附加在 Screen、表格行或批量选择上的按钮、链接或下拉菜单。所有
Action 都会调用一个控制器方法，并共享同一种规范化的响应结构。

## Action 类型

| 类 | `type()` | 用途 |
|---|---|---|
| `Button` | `button` | 默认。单击 → POST `{method, payload}`。 |
| `Link` | `link` | 外部或内部 href，不调用控制器。 |
| `BulkAction` | `bulk` | 作用于选中的行；接收 `ids[]`。 |
| `ModalAction` | `modal` | 先打开一个表单弹窗，然后再 POST。 |
| `DropDown` | `dropdown` | 子 Action 的容器。 |
| `AsyncAction` | `async` | 长时间运行；使用 `dskripchenko/laravel-delayed-process`。 |

任何 Action 也可以不调用方法，而是打开 Screen 中的某个 Modal 或 Drawer 布局：
`Button::make('Edit')->opens('edit-modal')`，其中 `edit-modal` 是该布局的
`withId()`。

## 通用 fluent API

```php
Button::make('Publish')
    ->method('publish')                   // 要调用的控制器方法
    ->icon('check')                       // lucide 图标
    ->primary()                           // 视觉样式
    ->destructive()                       // 红色样式
    ->confirm('Publish this article?')    // 确认提示
    ->permission('admin.articles.update') // 查看和执行所需的权限
    ->position(['command_bar', 'row'])    // 显示位置
    ->canSee(fn () => auth()->user()?->is_publisher)
    ->withName('publish-action');         // 唯一键
```

`make()` 从标签派生键（`'Publish'` → `publish`）；`withName()` 显式设置键。
`canSee()` 接受一个 bool 或一个**不带参数**的闭包，它只在 schema 序列化时求值一次——
它不是逐行条件。

`permission()` 和 `canSee()` 在服务端强制执行。用户缺少权限的 Action——或
`canSee()` 为 false 的 Action——不会出现在 manifest 中 Resource 的 `actions`
里，也不会出现在 Screen 的命令栏和布局中，它所覆盖的下拉菜单项同样如此。即便强行
执行，也会以 `403` 和 `errorKey: action_forbidden` 被拒绝：

- Resource 的 `action` 端点，适用于行、批量、header、独立（standalone）和弹窗
  Action，以及 `DropDown` 的各项（下拉菜单自身的权限覆盖其各项）；
- Screen 的 `runMethod`，适用于命令栏或布局中的 `Button` 或 `ModalAction`
  所调用的方法——没有任何 Action 指向的方法仅受 Screen 的 `permission()` 保护；
- `delayed/run`，适用于带权限的 `AsyncAction` 所启动的 handler（见下文）。

## 位置

`position(['...'])`——由以下值组成的数组：

- `command_bar`——页面头部（Screen / Resource 表单 / 列表）
- `row`——表格行（每条记录）
- `bulk`——出现在批量工具栏中（选中 1 行及以上时）
- `header`——列表页工具栏（表格上方）

默认值为 `['command_bar']`；`BulkAction` 默认为 `['bulk']`。位于 `row` 或
`bulk` 位置的 Action 作用于记录，至少需要一个 id；对独立运行的 Action（导入、
同步）请用 `->standalone()` 标记——它发送时不带 id，其方法会收到一个空列表。

## Resource Action

```php
public function actions(): array
{
    return [
        Button::make('Publish')->method('publish')->position(['row']),

        BulkAction::make('Archive')->method('archiveBulk')
            ->confirm('Archive the selected articles?')
            ->destructive()
            ->requiresAtMost(500),
    ];
}

public function publish(array $ids, array $payload = []): int
{
    return Article::whereIn('id', $ids)->update(['status' => 'published']);
}

public function archiveBulk(array $ids, array $payload = []): int
{
    return Article::whereIn('id', $ids)->update(['status' => 'archived']);
}
```

后端通过 `ResourceController::action` 分发（POST `/api/admin/{slug}/action`，
请求体 `{key, ids[], payload?}`）：按键查找 Action，并以
`$resource->{method}(array $ids, array $payload)` 的形式调用 Resource 方法——
行 Action 会收到只包含其所在行的单元素列表。整数返回值会被报告为受影响的记录数
（否则为 `count($ids)`）。

方法也可以返回一个 `string`——即 toast 的消息——或者一个包含二者之一的数组：
`['message' => 'Sent to 12 subscribers', 'affected' => 12]`。没有消息时，面板会
提示 Action 已执行。抛出 `ActionFailedException('...')` 可附带原因拒绝执行（422）。

## Screen commandBar

```php
public function commandBar(): array
{
    return [
        Button::make('Send')->method('send')->primary(),
        Button::make('Reset')->method('reset')->confirm('Discard changes?'),
    ];
}
```

前端通过 `ScreenController::runMethod` 分发，请求体为
`{method, payload: state}`；方法以 state 作为参数接收。

## 弹窗 Action（提交前的表单）

```php
ModalAction::make('Set price')
    ->method('setPrice')
    ->position(['row', 'bulk'])
    ->fields([
        Number::make('price')->required()->min(0)->step(0.01),
    ]);

public function setPrice(array $ids, array $payload): int
{
    return Product::whereIn('id', $ids)->update(['price' => $payload['price']]);
}
```

在方法运行之前，payload 会按照弹窗字段的规则（`required()`、`rules([...])`）
进行校验；422 会把错误显示在对应字段旁边，并保持弹窗打开。

## 异步 Action（长时间运行）

```php
// AppServiceProvider::boot(AllowlistRegistrar $allowlist)
$allowlist->allow(\App\Jobs\ReindexSearch::class, 'handle');
// 或者，要求具备某个权限才能启动：
$allowlist->allow(\App\Jobs\ReindexSearch::class, 'handle', 'admin.search.reindex');

AsyncAction::make('Re-index search')
    ->handler(\App\Jobs\ReindexSearch::class, 'handle')
    ->withParams(['model' => Article::class])
    ->pollInterval(5);                   // 秒
```

handler 必须以 `entity::method` 对的形式在
`Dskripchenko\LaravelAdmin\DelayedProcess\AllowlistRegistrar` 中被允许，否则
SPA 无法启动它。`delayed/run` 要求具备传给 `allow()` 的权限；当 handler 由
Resource 的 `actions()` 或 Screen 命令栏中声明的 `AsyncAction` 启动时，用户必须
至少被允许其中之一。SPA 通过 `/api/admin/delayed/run` 启动进程，并轮询
`/api/admin/delayed/status?uuid=...` 直至完成；UI 显示一个进度弹窗。在
`row`/`bulk` 位置中，选中的键会以 `ids` 的形式加入参数。`->callback($url)`
设置一个接收进度和结果的 webhook。

handler 自行上报进度：注入
`Dskripchenko\DelayedProcess\Contracts\ProcessProgressInterface`（或在方法内部用
`app(ProcessProgressInterface::class)` 解析），然后调用 `setProgress(0..100)`。
`delayed/status` 返回该值，弹窗将其绘制为进度条；成功时运行器会设为 100。在
delayed-process 运行之外，该调用不做任何事，因此 handler 仍可被同步调用。

```php
use Dskripchenko\DelayedProcess\Contracts\ProcessProgressInterface;

final class ReindexSearch
{
    public function __construct(private readonly ProcessProgressInterface $progress) {}

    public function handle(string $model): array
    {
        $chunks = $this->chunks($model);
        foreach ($chunks as $i => $chunk) {
            $this->reindex($chunk);
            $this->progress->setProgress(intdiv(($i + 1) * 100, count($chunks)));
        }

        return ['ok' => true];
    }
}
```

## 响应载荷

命令方法返回一个数组，它会被规范化为：

```json
{
  "success": true,
  "payload": {
    "state": {...},
    "layouts": {...},
    "alerts": [{"type": "success", "message": "..."}],
    "redirect_url": null,
    "refresh": true,
    "download_url": null,
    "message": "OK"
  }
}
```

可识别的键：

- `state`——替换 Screen 上的表单 state。
- `message`——toast 或成功提示条。
- `alerts`——`{type: 'info'|'success'|'warning'|'danger', message, title?, duration_ms?}` 的数组，以 toast 形式显示（与 `message` 重复的 alert 会被跳过）。
- `redirect_url`——SPA 内部导航。
- `refresh`——`true` 触发 Screen 重新加载。
- `download_url`——打开以下载。
- `message_link`——消息指向的位置，例如已启动任务的页面：
  `['url' => …, 'label' => …]`、`['href' => …, 'text' => …]`、`[$url, $label]`
  或一个裸 URL（标签为“Open”）；参见 [Screen](screens.md#命令方法)。

未知的键通过 `extra` 传递。

## 确认对话框

```php
->confirm('Delete this record?')
->confirm(['title' => 'Confirm', 'message' => 'Cannot be undone.',
           'confirmLabel' => 'Delete', 'cancelLabel' => 'Keep'])
```

前端会在 POST 之前显示一个弹窗。

## 针对特定记录拒绝执行

不存在逐行的可见性条件：行 Action 会显示在每一行上。请在方法中检查记录，并用
`Dskripchenko\LaravelAdmin\Resource\ActionFailedException` 拒绝执行——面板会收到
带有你的消息的 422，而不是 500：

```php
use Dskripchenko\LaravelAdmin\Resource\ActionFailedException;

public function publish(array $ids, array $payload = []): int
{
    $articles = Article::whereIn('id', $ids)->get();
    if ($articles->contains('status', 'published')) {
        throw new ActionFailedException('Some articles are already published.');
    }

    return Article::whereIn('id', $ids)->update(['status' => 'published']);
}
```

## 另请参阅

- [Resource](resources.md)
- [Screen](screens.md)
- [权限](permissions.md)
- [自定义 Action 示例](../../ru/recipes/custom-actions.md)（俄文）
