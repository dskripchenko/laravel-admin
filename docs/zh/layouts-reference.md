---
title: 布局参考
audience: developer
status: stable
locale: zh
translated_from: en/layouts-reference.md
translated_at: 2026-10-02
---

# 布局参考

布局是可渲染的容器，用于承载字段和其他布局。它们可以任意深度地嵌套组合。

## 速查表

| 类 | 工厂方法 | 用途 |
|---|---|---|
| `Rows` | `Layout::rows([...])` | 垂直堆叠 |
| `Columns` | `Layout::columns([...])` | 等宽列 |
| `Block` | `Layout::block($title, [...])` | 带标题的分区 |
| `Tabs` | `Layout::tabs(['Label' => [...], ...])` | 选项卡分区 |
| `Wizard` + `Step` | `Layout::wizard([Layout::step(...), ...])` | 多步骤表单 |
| `Modal` | `Layout::modal($title, [...])` | 在模态对话框中显示 |
| `Drawer` | `Layout::drawer($title, [...])` | 滑入式侧边面板 |
| `Wrapper` | `Layout::wrapper([...])` | 普通 `<div>` 分组 |
| `Accordion` | `Layout::accordion(['Section' => [...]])` | 可折叠分区 |
| `Infolist` | `Layout::infolist([...])` | 只读的键/值展示 |
| `Dashboard` | `Layout::dashboard([...])` | 12 列网格（由 `DashboardScreen` 使用） |
| `View` | `Layout::view('component-name', $props)` | 自定义 Vue 组件 |
| `Markdown` | `Layout::markdown($text)` | 渲染后的 markdown：带锚点的标题、目录、高亮代码、表格、提示框 |
| `Code` | `Layout::code($code, 'php')` | 高亮且可复制的代码块 |
| `Flow` | `Layout::flow('process')` | 带实时监控、开关和子流程下钻的流程或状态机 |
| `AuditTrail` | `AuditTrail::for(User::class)` | 所显示记录的审计时间线 |
| `Listener` | `Layout::listener([...])->listen([...])` | 表单中的一部分，当被监听的字段变化时由服务器重新渲染 |
| `ResourceTable` | `ResourceTable::for(ItemResource::class)` | 属于当前编辑记录的另一个 Resource 的记录表格 |
| `ResourceIndex` | `Layout::resourceIndex(OrderResource::class)` | 在 Screen 上嵌入资源的实时列表页（表格或树） |

## 示例

### Rows

```php
Rows::make([
    Input::make('title'),
    Textarea::make('body'),
    Select::make('status')->options([...]),
]),
```

### Columns

```php
Columns::make([
    Input::make('first_name'),
    Input::make('last_name'),
])->ratios([1, 1]),
```

使用 `->ratios([2, 1])` 获得不等宽的列。

### Block（带标题的分区）

```php
Block::make('个人资料', [
    Input::make('name'),
    Input::make('email'),
])->description('个人数据'),
```

### Tabs

```php
Tabs::make([
    '常规' => [
        Input::make('title'),
        Textarea::make('description'),
    ],
    'SEO' => [
        Input::make('meta_title'),
        Textarea::make('meta_description'),
    ],
    '翻译' => [
        TranslatableInput::make('title'),
    ],
]),
```

当前激活的选项卡状态仅保存在页面本地；切换选项卡不会丢失表单 state（状态）。

### Wizard（多步骤表单）

```php
Layout::wizard([
    Layout::step('账户', [
        Input::make('email')->required(),
        Input::make('password')->type('password')->required(),
    ]),
    Layout::step('个人资料', [
        Input::make('name'),
        DatePicker::make('birthday'),
    ]),
    Layout::step('完成', [
        Layout::view('summary-step', ['fields' => [...]]),
    ]),
]),
```

前端会渲染一个带有“上一步/下一步”按钮的 `UidStepper` 头部。向前推进受校验约束：该步骤的字段会按照其自身规则（`required`、`email`、`numeric`、`integer`、`min`、`max`、`in`；其他规则交由服务器处理）以及该步骤的 `->rules([...])` 进行检查。

```php
Layout::wizard([...])
    ->submit('finish')          // 最后一步的按钮调用该 Screen 方法
    ->freeForm()                // 步骤可以按任意顺序访问
    ->persistKey('onboarding'), // 进度在页面重新加载后保留（localStorage）
```

不使用 `freeForm()` 时，步进器中只有已经完成的步骤可以点击。使用 `persistKey()` 时，当前步骤和已输入的值会保存在浏览器中，直到向导被提交；密码和文件永远不会被保存。

### Modal / Drawer

用于 Action：

```php
ModalAction::make('设置价格')
    ->method('setPrice')
    ->fields([
        Number::make('price')->required(),
    ]),
```

或者作为 Screen 中的布局，由指明其 id 的 Action 打开：

```php
public function layout(): array
{
    return [
        Layout::modal('编辑', [
            Input::make('title'),
        ])
            ->withId('edit-modal')
            ->size('lg')               // sm | md | lg | xl | full
            ->dismissable(false)       // 无关闭叉号、不响应遮罩点击、不响应 Escape
            ->footer([
                Button::make('取消')->withName('cancel'),
                Button::make('保存')->method('save')->primary(),
            ]),
    ];
}

public function commandBar(): array
{
    return [Button::make('编辑')->opens('edit-modal')];
}
```

带有方法的底部 Action 会在方法执行成功后关闭浮层；名为 `close` 或 `cancel` 的 Action 则直接关闭浮层。`Layout::drawer()` 的工作方式相同——包括 `->dismissable()` 和 `->footer([...])`——另外还有 `->position('left'|'right'|'top'|'bottom')` 和 `->size()`（`sm`、`md`、`lg`、`xl` 或一个 CSS 长度值）。

### Wrapper

用于应用 CSS 的普通元素：

```php
Layout::wrapper([
    Input::make('title'),
    Input::make('slug'),
])->className('two-col-grid')->tag('section'),
```

`tag()` 接受 `div`（默认）、`section`、`article`、`aside`、`header`、`footer`、`main`、`nav`、`fieldset` 和 `span`。

### Accordion

```php
Layout::accordion([
    '个人信息' => [Input::make('name')],
    '账单' => [Input::make('card_last4')->readonly()],
])->multi(),
```

`->multi()` 允许同时展开多个分区。各分区初始为折叠状态；`->section('Title', [...], defaultOpen: true)` 可让某个分区初始展开。

### Infolist（只读）

用于 `view` 模式（`ResourceViewPage`、自定义 Screen）：

```php
Layout::infolist([
    TextEntry::make('title'),
    BadgeEntry::make('status')->colors(['published' => 'success', 'draft' => 'default']),
    KeyValueEntry::make('meta'),
])->layout('rows'),  // 或 'columns'、'grid'
```

条目类型：`TextEntry`、`BadgeEntry`、`IconEntry`、`KeyValueEntry`、`ImageEntry`、`RelationEntry`、`RepeatableEntry`、`MapEntry`、`ColorEntry`。

`FlowEntry` 以只读方式显示已保存的流程模型（即 [`Flow` 字段](fields-reference.md#flow)的查看形式）：`FlowEntry::make('workflow')->height(420)`。

在自定义 Screen 上，条目读取该 Screen 的 state。

### AuditTrail

```php
AuditTrail::for(\App\Models\User::class)
    ->fromState('user_id')          // 保存 id 的 state 键；默认为 'id'
    ->limit(20)
    ->withPermission('admin.audit'),
```

显示该记录的审计时间线（`GET /audit/timeline`）；当 state 中没有 id 时不显示任何内容。

### Dashboard

```php
Layout::dashboard([
    StatsOverviewWidget::make()->title('文章')->size(3),
    ChartWidget::make()->title('每日')->size(8)->rowSpan(2),
    // ...
]),
```

（由 `DashboardScreen` 使用，但你可以把 Dashboard 布局放入任何 Screen 中。）

### Listener（表单的响应式部分）

Listener 监听表单中的某些字段。当其中一个字段发生变化时，SPA 会等待用户停顿（默认 300 ms），将当前表单 state 提交到服务器，并用服务器针对该 state 渲染出的子元素替换 Listener 的子元素。可选的处理器（handler）还可以返回要合并到表单中的值。

```php
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Layout\Layout;

public function layout(): array
{
    return [
        Layout::rows([
            Select::make('country_id')->fromModel(Country::class),

            // 以闭包形式提供子元素：基于当前 state 渲染。
            Layout::listener(fn (array $state) => [
                Select::make('city_id')
                    ->title('城市')
                    ->fromModel(City::where('country_id', $state['country_id'] ?? null)),
            ])->listen('country_id'),

            Number::make('price'),
            Number::make('quantity'),

            // 计算值的处理器：Screen 的一个 public 方法。
            Layout::listener([
                Number::make('total')->readonly(),
            ])->listen(['price', 'quantity'])->handler('recalculateTotal'),
        ]),
    ];
}

/** 返回一个补丁：需要修改的表单 state 键。 */
public function recalculateTotal(array $state, Request $request): array
{
    return ['total' => round((float) ($state['price'] ?? 0) * (int) ($state['quantity'] ?? 0), 2)];
}
```

| 方法 | 含义 |
|---|---|
| `Layout::listener(array\|Closure $children)` | 静态子元素，或 `fn (array $state): array` |
| `->listen(string\|array $fields)` | 被监听的字段名 |
| `->handler(string\|Closure $handler)` | Screen/Resource 的一个 public 方法，或一个闭包；以 `(array $state, Request $request)` 调用，返回 state 补丁（`array`、`Repository` 或 `null`） |
| `->debounce(int $ms)` | 发送请求前的静默期，默认 300 |
| `->withId(string $id)` | 显式 id；仅当两个使用闭包处理器的 Listener 监听相同字段时才需要 |

每次变化时的流程：处理器以提交的 state 运行，其补丁合并到该 state 中，然后用结果渲染子元素。响应同时包含补丁和子元素；SPA 将补丁合并到表单中（跳过用户在请求进行期间编辑过的字段），原地替换子元素——位置不变的字段保持焦点——取消过期的请求，并以 toast 提示显示错误（若为 `ValidationException`，则显示在字段下方）。

**在 Resource 表单中**，Listener 放在 `formLayout()` 里，字符串形式的处理器是该 Resource 的一个 public 方法：

```php
public function formLayout(string $context): array
{
    return [
        Select::make('country_id')->fromModel(Country::class),
        Layout::listener(fn (array $state) => [
            Select::make('city_id')->fromModel(City::where('country_id', $state['country_id'] ?? null)),
        ])->listen('country_id'),
        Layout::listener([Number::make('total')->readonly()])
            ->listen(['price', 'quantity'])
            ->handler('recalculateTotal'),
    ];
}

public function recalculateTotal(array $state): array
{
    return ['total' => ($state['price'] ?? 0) * ($state['quantity'] ?? 0)];
}
```

校验和保存仍然读取 `fields()`：由 Listener 渲染的字段也必须在那里声明。manifest（清单）的构建不涉及任何记录，因此闭包起初接收到的是空 state；它应当能够容忍缺失的键。当编辑表单打开时被监听的字段已有值，Listener 会向服务器请求一次，为该记录渲染其子元素（不修改任何值）。

**端点与安全。** Screen 获得 `POST /api/admin/{screen}/listener`，受该 Screen 的 `permission()` 保护；Resource 获得 `POST /api/admin/{resource}/listener`（仅当其表单包含 Listener 时），根据表单的上下文要求 `view` 权限加上 `create` 或 `update` 权限。请求体为 `{listener, state, context?, id?}`，响应为 `{listener, state, layouts}`。请求通过 id 指定 Listener，而不是指定方法：只有在该 Screen 的 `layout()` 或该 Resource 的 `formLayout()` 中由 Listener 声明的处理器才能运行，保留的 Screen 方法（`query`、`layout`……）会被拒绝。

处理 Listener 请求时，Screen 的 `layout()` 会在不调用 `query()` 的情况下被调用，因此 Listener 不应依赖由 `query()` 设置的属性。

### ResourceTable（嵌入的另一个 Resource 的表格）

```php
use Dskripchenko\LaravelAdmin\Layout\ResourceTable;

public function formLayout(string $context): array
{
    if ($context !== 'update') {
        return [];
    }

    return [
        Layout::tabs([
            '常规' => $this->fields(),
            '条目' => [
                ResourceTable::for(DictionaryItemResource::class)
                    ->foreignKey('dictionary_id')   // 子记录中指向父记录的列
                    ->parentField('id')             // 该列所保存的父记录列；默认为 'id'
                    ->hideColumns(['dictionary_id'])
                    ->features(['create' => true, 'delete' => true, 'bulkDelete' => true]),
            ],
        ]),
    ];
}
```

在父记录的编辑页面上，显示属于当前编辑记录的子 Resource 记录。该表格从 manifest 中获取子 Resource 的列（去掉 `hideColumns()` 指定的列），通过 `POST /{child}/search` 并带上 `filters: {foreign_key: parent[parent_field]}` 加载最多 100 行，并在子 Resource 的列为 `editable()` 时支持行内编辑单元格。`features()` 用于开启以下功能，默认全部关闭：

- `create` —— 顶部的草稿行，通过 `POST /{child}/create` 保存，并自动填入外键；
- `delete` —— 每行一个删除按钮（`POST /{child}/delete`）；
- `bulkDelete` —— 行选择与批量删除。

每个请求都会依据子 Resource 自身的权限（`.view`、`.create`、`.update`、`.delete`）进行检查。

外键只能通过子 Resource 在该列上声明的过滤器作用于查询——若没有该过滤器，表格会列出所有父记录的记录。请在子 Resource 中声明一个精确匹配的过滤器：

```php
public function filters(): array
{
    return [
        QueryFilter::for('dictionary_id')
            ->using(fn ($query, $value) => $query->where('dictionary_id', $value)),
    ];
}
```

请将其放在 `formLayout('update')` 中：正在创建的记录还没有键，在保存之前表格会保持为空。

### ResourceIndex（在 Screen 上的资源实时列表）

```php
use Dskripchenko\LaravelAdmin\Layout\Layout;

public function layout(): array
{
    return [
        Layout::markdown('表格展示的内容…'),
        Layout::resourceIndex(OrderResource::class),
    ];
}
```

把资源的列表页本身——搜索、筛选、排序、行操作和批量操作、单元格内编辑、拖拽排序、回收站，或层级资源的树——嵌入到 Screen 中。点击行会像列表页一样打开记录。没有该资源 `view` 权限的用户看不到它。每个 Screen 只能放一个：列表状态是共享的。

### Markdown

一段 markdown，由面板的内置渲染器渲染。源文本在生成任何标记之前都会被转义，因此原始 HTML 会以文本形式显示，绝不会被执行；链接和图片只能指向 http(s)、`mailto:`、相对路径和锚点目标。

```php
Layout::markdown(file_get_contents(base_path('docs/en/getting-started.md')))
    ->toc()                         // 由 h2/h3 标题生成目录
    ->linkBase('/admin/screens/')   // 相对链接的指向位置
    ->imageBase('/docs-assets/')    // 相对图片的加载位置
```

`Layout::markdown()` 也接受一个 callable，在 Screen 序列化时解析——当文本从磁盘或数据库读取时很方便：

```php
Layout::markdown(fn () => Page::whereSlug($slug)->value('body'))
```

文本按原样发送：请自行选择语言版本，例如依据 `app()->getLocale()`。

渲染器支持的内容：

| 语法 | 结果 |
|---|---|
| `# Heading` … `###### Heading` | 带锚点 id 的标题：`## Getting started` → `#getting-started`（保留任何文字系统的字母，重复的标题会追加 `-1`、`-2`） |
| 围栏代码块（三个反引号加语言名） | 高亮且可复制的代码；语言取自围栏标记（`php`、`js`、`ts`、`json`、`bash`、`sql`、`html`、`vue`、`css`……） |
| ` ```mermaid ` 代码块 | 图表：Mermaid 语法的 flowchart、sequence、state、ER、类图、C4、pie、gantt、timeline、XY 图表和思维导图，由 [@dskripchenko/diagrams](https://github.com/dskripchenko/diagrams) 绘制为 SVG |
| ` ```openapi ` 代码块 | API 组件：用 YAML 或 JSON 描述的请求（或完整的 OpenAPI 文档）会变成带 try-it、代码示例、TypeScript 类型和 PDF 的操作，由 [@dskripchenko/openapi](https://github.com/dskripchenko/openapi) 渲染 |
| ` ```flow ` 代码块 | 只读的流程视图：JSON 模型，或 Mermaid `stateDiagram-v2` / `flowchart` 源码，由 [@dskripchenko/flow](https://github.com/dskripchenko/flow) 绘制 |
| `\| a \| b \|` + `\|---\|:---:\|` | 表格，支持列对齐 |
| `> quote` | 块引用 |
| `> **Note** …`、`> **Warning** …` | 提示框；也支持 `Tip`、`Important`、`Caution` 以及 GitHub 的 `> [!NOTE]` 形式 |
| `**bold**`、`*italic*`、`~~struck~~`、反引号代码片段 | 行内标记 |
| `[text](url)`、`![alt](src)` | 链接和图片 |
| `-`/`*`/`1.` 列表、`---` | 列表和分隔线 |

**图表。** 语言为 `mermaid` 的围栏代码块会变成图表——`flowchart`（含子图）、
`sequenceDiagram`、`stateDiagram-v2`、`erDiagram`、`classDiagram`、C4（`C4Context`、
`C4Container`、`C4Component`、`C4Dynamic`、`C4Deployment`）、`pie`、`gantt`、
`timeline`、`xychart-beta` 和 `mindmap`，由
[@dskripchenko/diagrams](https://github.com/dskripchenko/diagrams) 按面板的配色和主题绘制为 SVG。
该包没有任何依赖，只在页面含有图表时才加载。标签与其余文本一样会被转义，
因此图表不能携带标记或脚本。无法解析的代码块会显示为代码，并附上原因和行号。
支持的语法见该包的文档。

**API 块。** 语言为 `openapi` 的围栏代码块包含用 YAML 或 JSON 描述的请求——
`method`、`url`、`params`、`auth`、`body`、`responses`，多个请求用
`requests: [...]`——或完整的 OpenAPI 文档。它会变成
[@dskripchenko/openapi](https://github.com/dskripchenko/openapi) 的实时组件：
带参数和响应的操作、从读者浏览器发送请求的 try-it、代码示例、TypeScript 类型
以及操作的 PDF。只有页面中有这种块时才会加载该包。格式见该包文档（API blocks）。

**流程块。** 语言为 `flow` 的围栏代码块包含 JSON 形式的流程模型（见 [Flow](#flow带实时监控的流程)）或 Mermaid `stateDiagram-v2` / `flowchart` 源码；它会变成只读的流程视图——平移、缩放、子流程、步骤详情。只有页面上有这样的代码块时才会加载该包。

宿主以同样方式在 `onAppCreated` 中为其他语言添加渲染器：
`app.provide(markdownBlockRenderersKey, { ...builtinMarkdownRenderers, chart })`
（`markdownBlockRenderersKey` 来自 `@dskripchenko/ui`，
`builtinMarkdownRenderers` 来自 `@dskripchenko/laravel-admin`）。

选项：

| 方法 | 作用 |
|---|---|
| `toc(bool $enabled = true, int $depth = 3)` | 由 2 级到 `$depth` 级标题生成的目录，宽屏时位于正文旁边，窄屏时位于正文上方 |
| `tocLabel(string $label)` | 目录上方的标题；默认为 "On this page" |
| `linkBase(string $base, bool $stripExtension = true)` | 相对链接按照浏览器依据 `<base href>` 解析的方式相对于 `$base` 解析：使用 `/admin/screens/` 时，`docs-menu.md#items` 打开 `/admin/screens/docs-menu#items`；使用 `https://example.com/docs/en/` 时，`concepts/menu.md` 打开 `https://example.com/docs/en/concepts/menu`。除非 `$stripExtension` 为 false，否则会去掉 `.md` 扩展名。以 `/`、`#` 或协议开头的链接保持不变 |
| `imageBase(string $base)` | 对相对图片路径做同样的处理，但不改动扩展名 |
| `card(bool $card = true)` | 将文本绘制在卡片内 |

Screen 的 slug 是扁平的——Screen 位于 `/admin/screens/{slug}`，其下没有更深的层级——因此多页文档中的每一页都是一个独立的 Screen，页面之间的链接必须写明目标 Screen 的 slug。为文件树编写的 Markdown（`concepts/menu.md`、`../intro.md`）本身无法直接对应到这种结构：要么在传入文本之前把其中的链接改写为 slug（`concepts/menu.md` → `docs-concepts-menu`），并使用 `/admin/screens/` 作为 base；要么把 base 指向文件树原样所在的位置，例如代码仓库或文档站点。

链接的行为与网站一致：锚点在页面内滚动，留在面板内的链接无需重新加载即可导航，外部链接在新标签页中打开。

### Code

带复制按钮的高亮代码块：

```php
Layout::code(<<<'PHP'
    Layout::rows([
        Input::make('title')->required(),
    ]);
    PHP, 'php')
    ->title('app/Admin/Resources/PostResource.php')
    ->lineNumbers(),
```

| 方法 | 作用 |
|---|---|
| `title(string $title)` | 代码上方的标题，例如文件名 |
| `lineNumbers(bool $on = true)` | 行号 |
| `maxHeight(int\|string $height)` | 超过该高度后滚动：`400`（px）或 `'50vh'` |
| `wrap(bool $wrap = true)` | 长行自动换行，而不是横向滚动 |

### Flow（带实时监控的流程）

由 [@dskripchenko/flow](https://github.com/dskripchenko/flow) 绘制的流程或状态机：
平移、缩放、小地图、详情面板、运行中实例的实时状态（或所有实例的计数）、
启用和停用步骤的开关，以及进入子流程的下钻。只读——模型用
[`Flow` 字段](fields-reference.md#flow)编辑。只有页面上有流程时才会加载该包。

模型是该包的 JSON（`{version: 1, nodes, edges, groups?, meta?}`，见其模型文档）。
`Layout::flow()` 接受 Screen state 的键，或模型本身——数组或 JSON 字符串，
在构建布局时检查（模型有误时抛出 `InvalidArgumentException`）：

```php
Layout::flow('process')                 // $state['process']
Layout::flow($order->workflow)          // 数组形式的模型
Layout::flow(file_get_contents($path))  // 或 JSON
```

完整示例——订单状态机：轮询在下拉框中选择的订单的快照、开关步骤、按引用持有的
子流程，以及详情面板中的按钮：

```php
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Resource\ActionFailedException;

final class OrderProcessScreen extends Screen
{
    public function query(mixed ...$params): array
    {
        return [
            'order' => request('order'),
            'process' => OrderProcess::model(),   // 节点：new、payment（subgraph: 'payment'）、paid、shipped…
        ];
    }

    public function layout(): array
    {
        return [
            Layout::rows([Select::make('order')->options(Order::recentOptions())]),
            Layout::flow('process')
                ->poll('orderState', seconds: 5)
                ->onToggle('toggleStep', permission: 'orders.process.manage')
                ->subgraphs('loadSubprocess')
                ->instanceFrom('order')
                ->actions([
                    Button::make('重试步骤')->method('retryStep')->permission('orders.process.manage'),
                ])
                ->direction('LR')
                ->height(560),
        ];
    }

    /** 当前显示层级的快照：根流程或子流程。 */
    public function orderState(array $context): array
    {
        // $context = ['path' => ['payment'], 'ref' => 'payment', 'instance' => '1042']
        $order = Order::find($context['instance']);

        return [
            'nodes' => [
                'new' => ['status' => 'done'],
                'payment' => ['status' => 'done'],
                'shipped' => ['status' => 'active', 'updatedAt' => $order->updated_at->toIso8601String()],
            ],
            'path' => ['start', 'new', 'payment', 'paid', 'shipped'],
            'current' => ['shipped'],
            'updatedAt' => now()->toIso8601String(),
        ];
    }

    /** ['kind' => 'node'|'edge', 'id' => 'cancelled', 'enabled' => false, 'path' => []] */
    public function toggleStep(array $change): void
    {
        if ($change['id'] === 'cancelled') {
            throw new ActionFailedException('取消步骤不能关闭。');
        }
        OrderProcess::setEnabled($change['kind'], $change['id'], $change['enabled'], $change['path']);
    }

    public function loadSubprocess(string $ref, array $node): array
    {
        return OrderProcess::subprocess($ref);   // 与根流程相同的模型
    }

    /** ['kind' => 'node', 'id' => 'shipped', 'path' => [], 'instance' => '1042'] */
    public function retryStep(array $selection): array
    {
        RetryStep::dispatch($selection['instance'], $selection['id']);

        return ['message' => '已加入重试队列'];
    }
}
```

这些方法是 Screen 自己的公共方法，通过其 `runMethod` 动作以位置参数
`parameters` 调用——与按钮相同的会话、CSRF、层和检查。布局指定的每个方法都会
向服务器声明：布局对其隐藏（`canSee()`）的用户调用其中任何一个都会得到 403；
`onToggle(..., permission:)` 和详情面板的按钮还需要各自的权限——没有权限时
开关和按钮根本不会显示。

| 方法 | 作用 |
|---|---|
| `poll(string $method, int\|float $seconds = 5)` | 每 `$seconds` 秒调用 `$method(array $context)` 获取[监控快照](https://github.com/dskripchenko/flow/blob/main/docs/zh/monitoring.md)——`nodes`、`edges`、`path`、`current`、`updatedAt`。`$context` 包含 `path`（已进入的子流程）、`ref` 和 `instance`。同一时间只有一个请求，标签页隐藏时暂停，出错后退避；状态栏显示最后更新时间和过期提示 |
| `onToggle(string $method, ?string $permission = null)` | 节点和连线上的开关；`$method(array $change)` 保存 `{kind, id, enabled, path}`。要拒绝时抛出 `ActionFailedException`（或返回错误）：开关会复原，消息显示在提示中 |
| `toggleable(bool $on = true)` | 没有方法的开关：更改只保留在浏览器中 |
| `subgraphs(string\|array $subgraphs)` | 如何解析字符串形式的 `subgraph` 引用：返回模型的方法 `(string $ref, array $node): array`，或按引用给出的模型。内联子流程无需任何设置 |
| `instance(string\|int\|null $id)` / `instanceFrom(string $key)` | 当前关注的实例，传给轮询方法和详情面板的按钮；使用 `instanceFrom()` 时由 Screen 上的字段切换，并立即轮询 |
| `snapshot(array\|string $snapshot)` | 固定快照，或保存快照的 state 键；`poll()` 首次响应后取而代之 |
| `actions(array $buttons)` | 详情面板中的 `Button`；方法接收 `{kind, id, path, instance}`，响应中的 `message` 以提示显示，随后重新轮询 |
| `direction('LR'\|'TB'\|'auto')` | 布局方向；默认取模型的 `meta.direction`，否则为 `auto`（随区域形状） |
| `edgeStyle('orthogonal'\|'smooth')` | 直角（默认）或平滑曲线 |
| `height(int\|string $height)` | 区域高度：`560`（px）或 `'70vh'`；默认 480px |
| `minimap(bool)`、`details(bool)`、`statusBar(bool)` | 小地图、详情面板和状态栏；默认全部开启 |
| `title(string $title)` | 图上方的标题 |

内联模型的文字（节点和连线标签、描述、标题）与其他文字一样经过面板的 JSON 翻译；
从 state 读取的模型按存储原样显示。

### View（自定义 Vue 组件）

```php
Layout::view('my-custom-card', [
    'count' => 42,
    'label' => '条目',
]),
```

前端：

```ts
import { registerLayout } from '@dskripchenko/laravel-admin'
import MyCustomCard from './MyCustomCard.vue'
registerLayout('my-custom-card', MyCustomCard)
```

## 可见性

```php
Layout::block('仅限管理员', [...])
    ->canSee(fn () => auth()->user()?->hasAccess('admin.*')),
```

## 组合

布局可以嵌套：

```php
Tabs::make([
    '表单' => [
        Block::make('基本信息', [
            Columns::make([
                Input::make('first_name'),
                Input::make('last_name'),
            ]),
        ]),
        Block::make('设置', [
            Switcher::make('is_active'),
            Select::make('plan')->options([...]),
        ]),
    ],
    '审计' => [
        AuditTrail::for(\App\Models\User::class),
    ],
]),
```

## toArray 约定

每个布局都会序列化为：

```json
{
  "id": "l-xxxxxxxx",
  "kind": "layout",
  "type": "rows",
  "items": [ ... ],
  "props": {},
  "children": [ ... ]
}
```

props 也会展开到顶层，`items` 重复 `children` 的内容。`children` 是递归的（其他布局的 `toArray` 或字段的 `toArray`）。前端的 `LayoutRenderer` 从注册表中解析类型并递归渲染。

## 另请参阅

- [字段参考](fields-reference.md)
- [前端扩展](frontend-extension.md) —— 注册自定义布局
- [Screens](concepts/screens.md)
