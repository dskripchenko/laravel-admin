---
title: Resource
audience: developer
status: stable
locale: zh
translated_from: en/concepts/resources.md
translated_at: 2026-10-02
---

# Resource

**Resource**（资源）把一个 Eloquent 模型接入管理面板：表单、表格、
过滤器、Action、权限。

## 最小 Resource

```php
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

final class ArticleResource extends Resource
{
    public static string $model = \App\Models\Article::class;
    public static string $icon  = 'file-text';

    public function fields(): array
    {
        return [Input::make('title')->required()];
    }

    public function columns(): array
    {
        return [TableColumn::make('id'), TableColumn::make('title')];
    }
}
```

注册：`Admin::resources([ArticleResource::class])`。

## slug、标签、分组、图标

```php
public static string $icon = 'package';        // lucide 图标名
public static ?string $group = 'Catalog';      // 侧边栏分区

public static function slug(): string { return 'articles'; }    // URL = /admin/r/articles
public static function label(): string { return '文章'; }   // 侧边栏标签
public static function singularLabel(): ?string { return '文章'; } // 单条记录
```

`singularLabel()` 表示单条记录的名称，按其在句中出现的形式书写。凡是复数
`label()` 读起来不通的地方，面板都会使用它：新建页和编辑页标题、记录的面包屑、
“新建”按钮的提示、删除确认、提示消息以及空状态。它以 `singular_label` 出现在
manifest 中，并像 `label()` 一样在每次请求时通过 JSON 翻译进行翻译。默认情况下，
英文标签通过 `Str::singular()` 转为单数；其他文字的标签返回 `null`，面板改用无需
单数的措辞。翻译可以使用 `:singular`、`:Singular`、`:label` 和 `:plural`，即使
源字符串中没有它们。

## 字段

每个字段返回一个描述符。常见用法：

```php
Input::make('email')->type('email')->required()->placeholder('user@host'),
Number::make('price')->min(0)->step(0.01),
Select::make('status')->options(['draft' => '草稿', 'published' => '已发布'])->required(),
DatePicker::make('published_at')->withTime(),
RelationSelect::make('category_id')->relation(Category::class, 'name'),
Repeater::make('tags')->fields([Input::make('name'), Input::make('color')]),
TranslatableInput::make('title')->locales(['en', 'ru']),
Wysiwyg::make('body')->sanitize(),
```

完整目录见 [字段参考](../fields-reference.md)。

### 按模式控制可见性

```php
Input::make('slug')->required()->onView(false),     // 仅 create + update
Password::make('password')->onUpdate(false),         // 仅 create
```

`onCreate()`、`onUpdate()`、`onView()` 都接受一个 bool，默认值为
`true`；不做任何设置的字段在三种模式下都会显示。若要仅在另一个字段
取某个值时才显示某字段，请使用 `visibleWhen('driver', 's3')`。

### 由其他字段派生的字段

```php
Slug::make('slug')->from('title'),
```

每当 `title` 变化时，前端都会重新生成 `slug`，直到 slug 被手动
编辑为止。更复杂的情况——依赖于另一个字段的选项、计算出的总额——
请把这些字段包裹在
[Listener](../layouts-reference.md#listener表单的响应式部分) 中。

## 列（表格）

```php
TableColumn::make('id')->sort(),
TableColumn::make('title')->sort()->search(),
TableColumn::make('status')->asBadge(['published' => 'success'])->align('center'),
TableColumn::make('created_at')->asDateTime()->sort(),
TableColumn::make('price')->asMoney('USD')->align('right'),
TableColumn::make('cover')->asImage(),
```

格式化器：`asDate()`、`asDateTime()`、`asMoney()`、`asBoolean()`、
`asBytes()`、`asBadge()`、`asLink()`、`asImage()`，其他情况使用
`format(callable)`。行 Action 由表格自身渲染。

`asAudio(?string $disk = null, bool $waveform = false)` 在单元格中放入套件的迷你音频播放器——一行开始播放时其他行会暂停，点击播放器不会打开该行。`asVideo(?string $disk = null, ?string $posterColumn = null, int $width = 64, ?int $height = null)` 显示海报（来自该行的另一列）或带播放按钮的普通方块，点击后在模态框中打开播放器；点击前不会下载任何内容。值可以是 URL、存储路径（配合 `$disk`）或已上传的文件。

```php
TableColumn::make('recording')->asAudio('local'),
TableColumn::make('clip_path')->asVideo('public', posterColumn: 'cover_url'),
```

`asBadge()` 把值映射到一种色调（`info`、`success`、`warning`、`danger`、
`default`，或颜色名 `green`、`red`、`yellow`、`blue`、`gray`），并且
在存储的值不适合直接给人阅读时，再映射到一个标签：

```php
TableColumn::make('status')->asBadge([
    'draft' => ['label' => '草稿', 'tone' => 'warning'],
    'published' => ['label' => '已发布', 'tone' => 'success'],
]),
// 或者把色调和标签分开传入：
TableColumn::make('status')->asBadge(['draft' => 'warning'], ['draft' => '草稿']),
```

标签会像其他任何文字说明一样被翻译；没有标签的值按原样显示。

`format(callable)` 在服务器端序列化行数据时运行——Resource 的列表和
树、`TableWidget`——形式为 `fn ($value, array $row)`，单元格显示
它的返回值：

```php
TableColumn::make('author_id')->format(fn ($id, array $row) => $row['author']['name'] ?? '—'),
```

## 过滤器

```php
public function filters(): array
{
    return [
        InputFilter::for('title')->label('标题'),               // LIKE %…%
        OptionsFilter::for('status')->options(['draft' => '草稿', 'published' => '已发布']),
        DateRangeFilter::for('created_at'),                       // {from, to}
        SelectFromModelFilter::for('category_id')->fromModel(Category::class, 'name'),
        SwitcherFilter::for('is_featured'),
    ];
}
```

过滤器位于 `Dskripchenko\LaravelAdmin\Filter`。对于使用
`SoftDeletes` 的模型，会自动添加 `TrashedFilter`。`QueryFilter` 接受
一个闭包，用于其他过滤器覆盖不到的任何情况。

## Action

行 Action / 批量 Action / 命令栏（command bar）——见 [Action](actions.md)。

```php
public function actions(): array
{
    return [
        Button::make('发布')->method('publish')->position(['row']),
        BulkAction::make('归档')->method('archive')->confirm('归档 N 篇文章？'),
    ];
}

public function publish(array $ids, array $payload = []): void
{
    \App\Models\Article::whereKey($ids)->update(['status' => 'published']);
}
```

Action 按名称分发，Resource 方法接收选中的 id 和该 Action 的
载荷。名称仅由标签中的拉丁字母和数字派生——其他文字的标签会折叠为
`action`，多个这样的 Action 会合并成一个——因此使用非拉丁标签时，
请用 `withName('publish')` 显式设置名称。

## 软删除 / 恢复 / 彻底删除

如果你的模型使用 `SoftDeletes`，管理面板会自动启用：
- `TrashedFilter`（active/trashed/with）
- `Restore` 行 Action
- `ForceDelete` 行 Action（受 `admin.{slug}.force-delete` 控制）

## 复制

```php
public function replicable(): bool { return true; }

public function replicate(Model $original): Model
{
    $copy = parent::replicate($original);
    $copy->slug = $original->slug.'-copy';

    return $copy;
}
```

默认情况下，`replicate()` 就是 Eloquent 的 `Model::replicate()`，再给
`name`/`title`/`slug` 加上副本后缀；如需重新生成唯一字段，请重写它。

## 排序

适用于带有排序列的模型：

```php
public function reorderable(): bool { return true; }
public function reorderColumn(): string { return 'position'; }   // 默认值
```

列表 Screen 会多出一个拖拽手柄列。

## 看板视图与日历视图

除了列表（或树）之外，索引页还可以把资源的记录显示为看板或日历。声明需要的视图后，页面上方会出现切换器（列表 | 看板 | 日历），所选视图会写入 URL（`?view=kanban`）和已保存视图，搜索和过滤器对每个视图都生效。

### 看板

```php
use Dskripchenko\LaravelAdmin\Resource\Views\KanbanView;

public function kanban(): ?KanbanView
{
    return KanbanView::make('status')                  // 每个取值一列
        ->columns(['todo' => '待办', 'doing' => '进行中', 'done' => '已完成'])
        ->title('name')
        ->description('summary')
        ->tags('labels')                               // 数组或 "a, b, c"
        ->assignee('owner.name')                       // 经由关联的点路径
        ->due('due_at')                                // 逾期卡片会被标出
        ->lanes('priority')                            // 可选的泳道
        ->wip(['doing' => 5])                          // 在制品（WIP）上限
        ->limit(50);                                   // 每列每次加载的卡片数
}
```

- `columns()` 接受“值 ⇒ 标签”映射、`{value, label}` 列表或枚举类（backed 值；标签为 case 名，与 `Select::fromEnum()` 一致）。不指定时，列取自同名表单字段的选项，否则取记录中出现的不同值。`lanes()` 以第二个参数用同样的方式接受标签。
- 点路径（`owner.name`）会被预加载（eager loading）。除非 `title()` 另行指定，标题为 `recordTitle()`。
- 每列加载 `limit()` 张卡片；列头显示总数、WIP 上限以及加载下一页的“更多”按钮。
- 把卡片拖到另一列会把该列的值写入状态列（泳道的值写入泳道列）。对于可排序的资源，卡片按 `reorderColumn()`（或 `orderBy()`）排序，在列内放下的位置按列表拖动排序的方式保存：该列的记录互换各自持有的位置。没有 `reorder` 权限的用户仍可在列之间移动卡片，此时顺序保持不变。
- `readonly()` 关闭拖动；没有 `update` 权限的用户同样不能拖动。`editableForRow($record, 'status')` 返回 false 的卡片只能留在原列。

### 日历

```php
use Dskripchenko\LaravelAdmin\Resource\Views\CalendarView;

public function calendar(): ?CalendarView
{
    return CalendarView::make('starts_at', 'ends_at')  // date 或 datetime 列
        ->allDay('all_day')                            // 可选的布尔列
        ->title('name')
        ->color('type', ['meeting' => 'primary', 'deadline' => 'danger'])
        ->views(['month', 'week', 'day', 'agenda'])    // 以及 'year'
        ->initialView('month');
}
```

- 未调用 `allDay()` 时，若开始列存储的是日期（`date` 类型转换或存储为 `Y-m-d`），事件即为全天事件。全天事件的结束是其最后一天（含当天）。没有结束列时，事件是一个时间点。
- `color()` 把列的取值映射为色调（`primary`、`success`、`warning`、`danger`、`info`、`neutral`）或 CSS 颜色。
- 日历加载区间与可见日期相交的记录，每次请求最多 1000 条。
- 拖动可移动事件；在日视图和周视图的网格中拉伸其下边缘可修改结束时间。单击打开记录。对于有创建权限的用户，单击月视图中的空白日期（或在日历非只读时单击日/周网格中的时段）会打开创建表单，并通过查询参数预填开始列。
- `readonly()` 关闭拖动；没有 `update` 权限的用户同样不能拖动。

### 移动时的检查

移动与单元格内联编辑走同一条路径：路由要求 `update` 权限；`editableForRow()` 必须允许写入的每一列；看板的值必须是它的某一列（泳道必须是它的某一泳道），若该列定义了内联编辑规则也会一并校验；日期必须有效且结束不早于开始。记录通过模型保存，因此模型事件会触发，审计日志（`Loggable` trait）会记录变更。拒绝时以 API 的错误格式返回 403 或 422，面板会把卡片或事件放回原处。

端点：`POST {slug}/board`、`{slug}/boardMove`、`{slug}/calendar`、`{slug}/calendarMove`——参见 [API 参考](../api-reference.md)。

## 权限

默认基础权限：`admin.{slug}`。自动派生的子权限：
`view`、`create`、`update`、`delete`、`restore`、`force-delete`、
`replicate`、`reorder`。重写方式：

```php
public static function permission(): string
{
    return 'admin.articles';     // admin.articles.view, admin.articles.update, ...
}
```

## 可搜索 / 可排序

列表的全文搜索（`?q=`）在标记了 `search()` 的列上进行；重写
`searchableFields()` 可自行指定这些列。没有显式排序时，列表按
`defaultOrder()` 排序——按主键倒序，最新的在前：

```php
public function searchableFields(): array { return ['title', 'slug']; }

public function defaultOrder(): array
{
    return [['column' => 'created_at', 'direction' => 'desc']];
}
```

## 字段/列中的关联关系

```php
RelationSelect::make('author_id')
    ->relation(User::class, 'name')
    ->searchable(['name', 'email']),

// 另一个 Resource 的记录，在对话框中借助其搜索和过滤器选择
ResourcePicker::make('cover_id')->resource(MediaResource::class),
```

列表单元格读取的是序列化行中的扁平键，因此要显示关联的值，
请在 `indexQuery()` 中加载该关联，并把值暴露为一个属性（列在模型
`$appends` 中的访问器）：

```php
public function indexQuery(): Builder
{
    return parent::indexQuery()->with('author');
}

public function columns(): array
{
    return [TableColumn::make('author_name')->label('作者')];   // Article::getAuthorNameAttribute()
}
```

Resource 在选择器中通过 `pickerItem()` 展示：`recordTitle()`、
`recordSubtitle()` 和 `pickerPreview()`（图片 URL 或 `null`）。见
[ResourcePicker](../fields-reference.md#resourcepicker)。

## 另请参阅

- [Screen](screens.md) —— 非 CRUD 页面
- [权限](permissions.md) —— RBAC 细节
- [字段参考](../fields-reference.md)
- [布局参考](../layouts-reference.md)
