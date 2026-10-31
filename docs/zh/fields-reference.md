---
title: 字段参考
audience: developer
status: stable
locale: zh
translated_from: en/fields-reference.md
translated_at: 2026-10-02
---

# 字段参考

所有字段都继承自 `Dskripchenko\LaravelAdmin\Field\Field`。它们共享以下方法：

```php
->required()           // 向校验规则中添加 'required'
->placeholder('text')
->help('显示在字段下方的提示')
->title('自定义标签') // 默认：名称转换为可读形式，`opens_at` → "Opens At"
->default('value')     // 初始表单 state
->onCreate(false)      // 在创建表单中隐藏；另有 onUpdate()、onView()
->visibleWhen('driver', 's3')          // 仅当另一个字段为某个值时显示
->visibleWhen('driver', ['s3', 'minio']) // ……或为多个值中的任意一个时显示
->span(6)              // 在 Rows 布局的 12 列网格中的宽度
->canSee(fn () => Gate::allows('manage-billing')) // 服务端可见性
->readonly()
->disabled()
->rules(['min:3', 'max:255'])  // 显式的 Laravel 规则
```

`rules()` 会替换在它之前设置的显式规则；`required()` 在任何情况下都会保留。除了显式规则之外，每个字段还会根据其类型添加隐式规则：数字的 `numeric`/`integer` 和 `min`/`max`，`->type('email')` 的 `email`，日期的 `date`，多选的 `array`，等等——因此限制只需在字段上声明一次。

字段类未定义的方法会被存储为属性，并作为 props 传递给 SPA 组件。`->placeholder()`、`->type()` 或 `->rows()` 就是这样工作的，这也是拼写错误的方法会静默失败的原因：它变成了一个没有人读取的属性。

## 文本输入

### Input

```php
Input::make('title')->required(),
Input::make('email')->type('email'),
Input::make('phone')->type('tel'),
Input::make('website')->type('url'),
```

密码请使用 `Password`（见下文），颜色请使用 `ColorPicker`。

### Textarea

```php
Textarea::make('description')->rows(6),
```

### Number

```php
Number::make('price')->min(0)->max(99999)->step(0.01),
Number::make('quantity')->integer(),
```

### Slug

由表单中另一个字段派生的 URL slug：

```php
Slug::make('slug')->from('title')->separator('-'),
```

`from()` 指定源字段。SPA 会随着源字段的变化填充 slug，直到它被手动编辑为止；清空 slug 会把它重新交还给源字段。已保存且不再与源字段匹配的 slug 会保持不变。使用 `reactive(false)` 时，只有空的 slug 会被填充：新记录的 slug 跟随源字段直到被编辑，已保存记录的 slug 永远不会被改写。

转换由 `Slug::generate($title, $separator)` 完成，SPA 运行的是它的移植版本，使用同一张音译表，因此建议的 slug 与服务器生成的完全一致：西里尔字母（俄语、乌克兰语、白俄罗斯语、塞尔维亚语、哈萨克语）、希腊字母以及带变音符号的拉丁字母会被音译（`Щука и ёж` → `shchuka-i-yozh`），空白、短横线和 `_` 会变成分隔符，`@` 变成 `at`，其他字符会被丢弃。在不涉及表单的服务端场景中——导入、seeder、保存钩子——请使用 `Slug::generate()`。

### Code

带语法高亮的代码编辑器：

```php
Code::make('snippet')->language('javascript')->lineNumbers()->height(400),
```

`theme()` 会被接受但会被忽略：编辑器跟随面板的主题。

### Markdown

带实时预览的 Markdown 编辑器：

```php
Markdown::make('content')->height('300px'),
Markdown::make('notes')->preview(false)->toolbar(false),
```

预览同样会绘制 ```` ```mermaid ```` 图表（参见 [Markdown 布局](layouts-reference.md#markdown)）；其中的链接在新标签页打开，以免离开表单。

### Wysiwyg

默认编辑器：`@dskripchenko/wysiwyg`（无依赖）。

```php
Wysiwyg::make('body'),
Wysiwyg::make('summary')->preset('minimal'),     // 'minimal' | 'default' | 'full'
Wysiwyg::make('page')->preset('full')->uploadImages(),
```

默认情况下，HTML 会在保存时被清理；对于你信任的内容，可以用 `->sanitize(false)` 关闭清理。

在编辑器中，` ```mermaid ` 代码块显示为图表，` ```openapi ` 块显示为可就地
编辑的 API 组件：预览、YAML 或 JSON 源码，以及请求构建器（方法、URL、参数、
认证、请求体、响应）。斜杠菜单可插入它们：«Diagram»（`/diagram`）和
«API request»（`/api`）。保存的 HTML 保留代码块，因此同样的内容在 Markdown 布局
或任何运行 @dskripchenko/openapi 的 `renderBlocks` 的地方都会同样渲染。

面板能绘制的所有代码块语言（见 [Markdown](layouts-reference.md#markdown)）在编辑器中
也以同样方式显示。` ```kanban ` 或 ` ```calendar ` 代码块可直接在其中编辑——拖动
卡片、拖动事件、使用事件构建器——块的标记随之更新。斜杠菜单还提供 «Process»
（`/flow`）、«Kanban»（`/kanban`）、«Calendar»（`/calendar`）、«AsyncAPI»
（`/asyncapi`）、«Video»（`/video`）和 «Audio»（`/audio`），每项都附带一个起始示例。
清理器会保留代码块的 `language-…` 类（以及表格单元格的 `text-align`），因此内容保存
后代码块仍会被绘制。

工具栏的全屏按钮可让编辑器铺满整个窗口；按 Escape 退出。

Quill 和 TinyMCE 适配器以子路径导出的形式随 npm 包提供；宿主需要自行安装编辑器（`quill` + `@vueup/vue-quill`，或 `tinymce` + `@tinymce/tinymce-vue`），并将组件注册为 `wysiwyg` 类型：

```ts
import { registerField } from '@dskripchenko/laravel-admin'
import { QuillField } from '@dskripchenko/laravel-admin/quill'
// 或：import { TinymceField } from '@dskripchenko/laravel-admin/tinymce'

registerField('wysiwyg', QuillField)
```

## 选择

### Select

```php
Select::make('status')->options([
    'draft' => '草稿',
    'published' => '已发布',
])->required(),
```

选项也可以来自枚举或模型：

```php
Select::make('status')->fromEnum(ArticleStatus::class),
Select::make('category_id')->fromModel(Category::class, 'id', 'title'),
Select::make('country')->options([...])->searchable()->clearable(),
```

### Combobox

一种也接受列表之外的值的下拉选择——选项只是提示，而不是约束。当取值集合确实封闭时，请使用 `Select`。

```php
Combobox::make('model')
    ->options(['claude-opus-5' => 'Claude Opus 5', 'claude-sonnet-5' => 'Claude Sonnet 5'])
    ->clearable(),
```

`creatable()` 默认开启；`->creatable(false)` 将其限制为仅能选择列表中的值。

### Radio

```php
Radio::make('plan')->options([...])->inline(),
```

### Checkbox / Switch

```php
Checkbox::make('agree')->title('我同意'),
Switcher::make('is_active')->title('启用'),
Switcher::make('published')->title('状态')->labels('已发布', '草稿'),
```

`Switcher` 是一个开关。使用 `labels($on, $off)` 时，开关旁边的说明文字会随状态变化，而字段标题位于上方。

## 日期 / 时间

```php
DatePicker::make('start_date'),
DatePicker::make('birthday')->min('1900-01-01')->max(now()),
DatePicker::make('publish_at')->withTime(),
DateRange::make('period')->presets(['today', 'last_7_days']),
TimePicker::make('start_time')->step(15),
```

`withTime()` 会在日期旁边添加一个时间选择器，并将存储格式切换为 `Y-m-d H:i:s`。另行指定 `format()` 可以决定值的写入方式：`Y-m-d H:i` 去掉秒，`Y-m-d\TH:i:s` 用 `T` 连接。在选择日期之前选好的时间会被保留，直到选择了日期；未选择时间时为午夜。`DateRange` 存储为 `{from: 'YYYY-MM-DD', to: 'YYYY-MM-DD'}`。

## 数值

```php
Slider::make('volume')->min(0)->max(100)->step(5)->marks([0 => '关', 100 => '最大']),
Rating::make('quality')->count(5)->half(),
```

## 文件

```php
FileUpload::make('contract')
    ->maxSize(5 * 1024)        // KB
    ->accept(['application/pdf', '.docx']),

FileUpload::make('avatar')->image(),   // 仅限图片，带预览

ImageCropper::make('hero')
    ->aspectRatio(16 / 9)
    ->minCrop(800, 450)
    ->outputSize(1600, 900)
    ->quality(0.85),
```

SPA 会先通过面板的 `uploads` 端点上传文件，然后把 `{disk, path, url, name, size, mime}` 放入表单 state。磁盘和目录取自 `config('admin.uploads.disk')` 和 `config('admin.uploads.directory')`。每个字段一个文件：`multiple()` 和 `maxFiles()` 会影响校验规则，但 SPA 的上传器只处理单个文件。

音频或视频文件会在其名称下方显示套件的播放器，图片显示其预览；查看页以同样方式显示该字段，并附带文件链接。本地磁盘上的视频以支持 Range 的方式传输，因此可以拖动进度，在 Safari 中也能播放。

## 关联

### RelationSelect

```php
RelationSelect::make('author_id')
    ->relation(User::class, 'name')   // 模型、标签列、值列 = 'id'
    ->preload(['team']),
```

选项在表单序列化时从关联模型加载（最多 100 行；`->eager(500)` 可提高上限），因此它适用于参照表。对于大型表，请使用 `ResourcePicker`。

### RelationTable

在编辑表单上显示关联记录的只读表格——HasMany 或 BelongsToMany。行数据来自字段的值，因此请随记录一起加载该关联：

```php
RelationTable::make('items')
    ->relation('items')
    ->columns([
        TableColumn::make('name')->label('名称'),
        TableColumn::make('price')->asMoney('USD'),
    ]),
```

列为 `TableColumn`，与 Resource 列表使用相同的预设。

### ResourcePicker

在对话框中选取另一个已注册 Resource 的记录。`RelationSelect` 是把模型读入下拉选择框，而选择器与之不同，它通过目标 **Resource** 工作：对话框列出的内容与目标的索引页一致，带有其搜索、过滤器和分页，并且只对拥有目标 `admin.{slug}.view` 权限的用户可用。

```php
ResourcePicker::make('cover_id')
    ->resource(MediaResource::class),       // 或一个 slug：'media-library'

ResourcePicker::make('related_ids')
    ->resource('products')
    ->multiple()                            // 一个有序的键列表
    ->maxItems(5)
    ->filters(['status' => 'active'])       // 固定条件，不在工具栏中显示
    ->perPage(24)
    ->layout('list')                        // 'grid' | 'list'；默认：记录有预览图时为 grid
    ->dialogSize('xl'),                     // 'lg' | 'xl' | 'full'
```

值为记录的键；使用 `multiple()` 时为按用户排列顺序的键列表，请将多选选择器对应的列 cast 为 `array`。保存时，每个键都必须指向目标 `indexQuery()` 中的一条记录，因此对索引的范围限定也就限定了可以关联的内容。

对话框通过 `Resource::pickerItem()` 绘制每条记录：标题来自 `recordTitle()`，副标题来自 `recordSubtitle()`，预览图来自 `pickerPreview()`。可在目标 Resource 上覆盖它们：

```php
public function pickerPreview(Model $row): ?string
{
    return $row->avatar_url;
}
```

`uploadTo()` 会在对话框中添加一个上传按钮。文件以 multipart 数据提交到面板 API 下的某个路径；响应即为新记录（或将其放在 `responseKey` 下），该记录随后会被选中：

```php
ResourcePicker::make('document_id')
    ->resource('documents')
    ->uploadTo('/documents/files/upload', permission: 'admin.documents.create',
        fileField: 'file', responseKey: 'document', data: ['folder' => 'contracts'],
        accept: 'application/pdf'),
```

该按钮只对拥有 `permission` 的用户显示——默认为目标的 `create` 权限。在查看页面上，该字段显示已选取的记录及其预览图，每条记录都链接到目标的查看页面。

### MorphSwitcher

用于多态关联：

```php
MorphSwitcher::make('subject')
    ->morph('article', Article::class, 'title')
    ->morph('product', Product::class),          // 显示列默认为 'name'

// 或一次指定多个，别名 => 模型：
MorphSwitcher::make('subject')->morphMany([
    'article' => Article::class,
    'product' => Product::class,
]),
```

state 为 `{type, id}`。每种类型的记录（最多 100 条）会作为选项加载到表单中。

## 复合字段

### Repeater

可变长度的子表单列表：

```php
Repeater::make('tags')
    ->fields([
        Input::make('name')->required(),
        Input::make('color'),
    ])
    ->minItems(0)->maxItems(10)
    ->defaultItem(['color' => '#888888']),
```

`addable()`、`removable()` 和 `reorderable()` 用于切换对应按钮。

### Group

嵌套字段在 state 中存储为一个对象——`contact.email`、`contact.phone`——并作为数组进行校验：

```php
Group::make('contact')
    ->title('联系信息')
    ->fields([
        Input::make('email'),
        Input::make('phone'),
    ])
    ->layout('columns')        // 'rows'（默认）| 'columns' | 'inline'
    ->collapsed(),
```

如果只是纯视觉上的分组，请使用 `Layout::block()` 之类的布局——参见[布局参考](layouts-reference.md)。

### KeyValue

自由格式的键/值对：

```php
KeyValue::make('headers')
    ->keyLabel('请求头')
    ->valueLabel('值')
    ->allowedKeys(['Accept', 'Authorization']),  // 可选
```

### TagsInput

```php
TagsInput::make('tags')
    ->suggestions(['php', 'vue', 'laravel'])
    ->maxItems(8),
```

建议项不会限制输入：可以添加任意字符串。

## 树 / 层级

### TreeSelect

```php
TreeSelect::make('category_id')
    ->tree([
        ['value' => 1, 'label' => '电子产品', 'children' => [
            ['value' => 2, 'label' => '手机'],
        ]],
    ])
    ->multiple(),

// 或来自自引用模型：
TreeSelect::make('category_id')
    ->fromModel(Category::class, 'parent_id', 'id', 'name')
    ->selectableParents(false),   // 仅叶子节点
```

### Cascader

用于嵌套选项的级联下拉框：

```php
Cascader::make('location')
    ->options([
        ['value' => 'us', 'label' => '美国', 'children' => [...]],
    ]),
```

## 特殊字段

### TranslatableInput

每个 locale 一个选项卡；state 为 `{en: '...', ru: '...'}`：

```php
TranslatableInput::make('title')->locales(['en', 'ru', 'de']),
TranslatableInput::make('body')->multiline()->locales(['en', 'ru']),
TranslatableInput::make('name')->requireAllLocales(),
```

不使用 `locales()` 时，列表取自 `config('admin.ui.available_locales')`。参见 [i18n](concepts/i18n.md)。

### Builder

页面构建器风格的区块列表（适用于 CMS 宿主）。每种区块类型都声明了自己的字段；state 为 `{type, data}` 的列表：

```php
Builder::make('blocks')
    ->block('hero', [
        Input::make('title')->required(),
        Markdown::make('subtitle'),
    ], label: '主视觉', icon: 'image')
    ->block('gallery', [
        FileUpload::make('image')->image(),
    ])
    ->maxBlocks(20),
```

### Hidden

```php
Hidden::make('uuid'),
```

### Label

仅用于只读展示——既不可编辑，也不会被提交。显示 state 中与其名称对应的值，或一个固定的 `->value()`：

```php
Label::make('id')->title('记录 ID'),
Label::make('note')->value('更改将在重启后生效。'),
```

### ColorPicker

```php
ColorPicker::make('brand_color')
    ->format('hex')                       // 'hex' | 'rgb' | 'hsl'
    ->palette(['#1e88e5', '#43a047'])
    ->withAlpha(),
```

### Password

```php
Password::make('password')->required()->confirmed()->revealable(),
```

`confirmed()` 会添加 `confirmed` 规则，因此表单还需要一个 `password_confirmation` 字段。

### Generated

在打开创建表单时于浏览器中生成的随机字符串——令牌、密钥——并带有一个“Generate”（生成）按钮：

```php
Generated::make('api_key')->length(40)->charset('abcdef0123456789'),
```

### Flow

流程和状态机的可视化编辑器——[@dskripchenko/flow](https://github.com/dskripchenko/flow)
的 FlowEditor：节点类型面板、拖动连线、撤销/重做、对齐和自动布局、属性面板、问题列表、
就地编辑子流程、JSON 导出以及 JSON/Mermaid 导入。值是 JSON 对象形式的模型——请存入
带 `array` 转换的 JSON 列；它与其他字段一样随表单提交。只有表单中有该字段时才会加载该包。

```php
Flow::make('workflow')
    ->palette(['start', 'state', 'task', 'decision', 'end', 'note'])
    ->locked(['start'])
    ->height(560)
    ->required(),
```

| 方法 | 作用 |
|---|---|
| `palette(array $types)` | 元素面板提供的节点类型及顺序；默认全部。此时服务器只接受这些类型（以及锁定的类型） |
| `locked(array $types)` | 编辑器不允许修改或删除的节点类型 |
| `readonly()` | 只能浏览 |
| `height(int\|string)` | 编辑器高度；默认 520px |
| `direction('LR'\|'TB'\|'auto')`、`edgeStyle('orthogonal'\|'smooth')` | 与 [`Layout::flow()`](layouts-reference.md#flow带实时监控的流程) 相同 |
| `minimap(bool)`、`snapGrid(int)` | 小地图（默认关闭）和节点吸附的网格（`0` 关闭吸附） |
| `mermaidImport(bool)` | JSON/Mermaid 菜单中的“导入 Mermaid”；默认开启 |
| `exportName(string)` | JSON 导出的文件名；默认 `{name}.json` |

`ValidationRulesExporter` 会添加 `array` 和 `FlowModel` 规则：模型的结构（节点、连线和
分组的 id 唯一，连线连接已存在的节点，内联子流程同样检查），设置了面板时还检查节点类型。
自定义验证器中：`'workflow' => ['required', Flow::rule()]`。编辑器的警告——不可达的步骤、
死胡同——不会导致验证失败。锁定类型只由编辑器保证；如有需要，请在服务器上检查。

在查看页上，该字段由 `FlowEntry` 以只读方式显示。

### Bpmn 与 Dmn

BPMN 2.0 流程与 DMN 决策模型的编辑器——
[@dskripchenko/bpmn](https://github.com/dskripchenko/bpmn) 的 `BpmnEditor` 和
`DmnEditor`：调色板、选择、移动与缩放大小、连线、上下文面板、就地输入名称、属性面板（BPMN）、一键打开的决策表与字面表达式（DMN）、撤销、平移与缩放。值是文件的 XML——与 Camunda Modeler、bpmn.io 等建模工具读写的是同一个文件，它们的扩展会被保留。请将其存放在文本列中（大型模型使用 `MEDIUMTEXT` 或更大）；它像其他字段一样随表单提交。只有表单中包含该字段时才会加载此包，编辑器使用面板的语言（英语或俄语）。

```php
use Dskripchenko\LaravelAdmin\Field\Bpmn;
use Dskripchenko\LaravelAdmin\Field\Dmn;

Bpmn::make('process_xml')
    ->title('流程')
    ->height(640)
    ->properties()
    ->required(),

Dmn::make('rules_xml')
    ->title('折扣规则')
    ->decision('Decision_discount')
    ->maxSize(512 * 1024),
```

| 方法 | 字段 | 作用 |
|---|---|---|
| `height(int\|string)` | `Bpmn`、`Dmn` | 编辑器高度；默认 560px |
| `maxSize(int $bytes)` | `Bpmn`、`Dmn` | 服务器接受的最大文件；默认 2 MB。请不要超过列的容量 |
| `readonly()`、`disabled()` | `Bpmn`、`Dmn` | 编辑器变为查看器 |
| `properties(bool)` | `Bpmn` | 一开始就打开属性面板 |
| `diagram(string\|int)` | `Bpmn` | 文件含多张图时显示哪一张：其 id 或序号；默认第一张 |
| `decision(string $id)` | `Dmn` | 打开该决策的逻辑而不是图 |

`ValidationRulesExporter` 会添加 `string` 以及 `BpmnModel` / `DmnModel` 规则。空值可以通过（是否必填由 `required()` 决定）；其他值必须满足：

- 不超过上限——在解析 XML 之前检查；
- 不含 DOCTYPE 的 XML，解析时关闭网络且不替换实体——因此既不能利用外部实体，也不能利用实体膨胀；解析器错误会成为验证消息；
- 根元素是 BPMN 模型命名空间（`http://www.omg.org/spec/BPMN/20100524/MODEL`）或某个 DMN 模型命名空间（DMN 1.1 至 1.5——即 `@dskripchenko/bpmn` 能读取的命名空间）中的 `definitions`。

该规则检查的是编辑器能否打开该文件，而不是流程本身：没有出口流的网关、没有规则的决策等都可以通过。自定义验证器可以这样写：`'process_xml' => ['required', Bpmn::rule()]`、`Dmn::rule(maxBytes: 1024 * 1024)`。

在查看页上，这些字段由 `BpmnEntry` 和 `DmnEntry` 以只读方式显示（即 [BPMN 与 DMN 布局](layouts-reference.md#文档图表bpmndmndrawioasyncapi)的查看器）。

### Kanban 与 Calendar

以标记书写的看板和事件日历——与 [`Layout::kanban()` / `Layout::calendar()`](layouts-reference.md#kanban-与-calendar) 以及 ` ```kanban ` / ` ```calendar ` 代码块中的标记相同——在表单中用 UI 套件的 `UidKanban` 和 `UidEventCalendar` 编辑。卡片可以在列之间移动、添加和修改；事件可以拖动、拉伸、添加和修改；标记可以在源码标签页中编辑。每次修改都会写入表单的值，并随表单一起保存。值是标记，即文本列中的字符串。

```php
use Dskripchenko\LaravelAdmin\Field\Calendar;
use Dskripchenko\LaravelAdmin\Field\Kanban;

Kanban::make('board')
    ->title('看板')
    ->height(480),

Calendar::make('schedule')
    ->initialView('week')
    ->date('2026-11-02')
    ->views(['week', 'month', 'agenda']),
```

| 方法 | 字段 | 作用 |
|---|---|---|
| `height(int\|string)` | `Kanban`、`Calendar` | 外框高度，内容在其中滚动；不设置时外框随内容增长 |
| `maxLength(int $characters)` | `Kanban`、`Calendar` | 服务器接受的最长标记；默认 65 535（`TEXT` 列的容量） |
| `readonly()`、`disabled()` | `Kanban`、`Calendar` | 只读 |
| `initialView(string $view)` | `Calendar` | 首先显示的视图：`day`、`week`、`month`、`year` 或 `agenda`；否则取标记中的 `view:`，再否则为月视图 |
| `date(string $date)` | `Calendar` | 首先显示的日期，`YYYY-MM-DD`；否则取标记中的 `date:`，再否则为今天 |
| `views(array $views)` | `Calendar` | 提供的视图，按此顺序 |

字段中没有锁定按钮：字段自身的 `readonly()` 或 `disabled()` 会让看板或日历变为只读。日历选项的检查方式与布局相同：未知视图、不是 `YYYY-MM-DD` 格式的日期或空的视图列表都会抛出 `InvalidArgumentException`。

`ValidationRulesExporter` 会添加 `string` 和带长度上限的 `max:`。在查看页上，这些字段由 `KanbanEntry` 和 `CalendarEntry` 以只读方式显示。

### MarkdownEditor

像文字处理器一样编辑的 Markdown 编辑器——
[@dskripchenko/markdown-editor](https://github.com/dskripchenko/markdown-editor)：可视模式（面板的 WYSIWYG 引擎，带工具栏、斜杠菜单和快捷键）、Markdown 源码，以及两者并排。值是 Markdown 字符串；未作修改时保持原样。与 [`Markdown`](#markdown)（带预览的文本框）不同，用户无需了解语法。

面板的代码块语言会直接在编辑器中绘制——` ```mermaid ` 及其他图表记法、` ```bpmn `、` ```openapi `、` ```kanban `、` ```calendar `，以及应用注册的其他语言（参见 [Markdown](layouts-reference.md#markdown)）。编辑器使用面板的语言（英语或俄语）；只有表单中包含该字段时才会加载此包。

```php
use Dskripchenko\LaravelAdmin\Field\MarkdownEditor;

MarkdownEditor::make('body')
    ->modes(['visual', 'source'])
    ->placeholder('开始书写…')
    ->minHeight(320)
    ->maxHeight('70vh'),
```

| 方法 | 作用 |
|---|---|
| `modes(array $modes)` | 提供的模式，按此顺序——`visual`、`source`、`split`；第一个最先显示。默认三个都有 |
| `placeholder(string)` | 空编辑器中显示的文字 |
| `minHeight(int\|string)`、`maxHeight(int\|string)` | 编辑区域的高度：默认至少 240px；超过最大值后滚动 |
| `readonly()`、`disabled()` | 仅浏览 |

未知模式或空列表会抛出 `InvalidArgumentException`。`ValidationRulesExporter` 会添加 `string`。在查看页上，值以渲染后的 Markdown 显示（`MarkdownEntry`），代码块语言也一并绘制。

## 另请参阅

- [Resources](concepts/resources.md)
- [布局参考](layouts-reference.md)
- [前端扩展](frontend-extension.md) —— 注册自定义字段
