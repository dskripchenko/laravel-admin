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
