---
title: Layouts Reference
audience: developer
status: stable
locale: en
---

# Layouts Reference

Layouts are renderable containers for fields and other layouts. They
compose to arbitrary depth.

## Quick reference

| Class | Factory | Use |
|---|---|---|
| `Rows` | `Layout::rows([...])` | Vertical stack |
| `Columns` | `Layout::columns([...])` | Equal-width columns |
| `Block` | `Layout::block($title, [...])` | Section with title |
| `Tabs` | `Layout::tabs(['Label' => [...], ...])` | Tabbed sections |
| `Wizard` + `Step` | `Layout::wizard([Layout::step(...), ...])` | Multi-step form |
| `Modal` | `Layout::modal($title, [...])` | Show in a modal dialog |
| `Drawer` | `Layout::drawer($title, [...])` | Slide-in side panel |
| `Wrapper` | `Layout::wrapper([...])` | Plain `<div>` group |
| `Accordion` | `Layout::accordion(['Section' => [...]])` | Collapsible sections |
| `Infolist` | `Layout::infolist([...])` | Read-only key/value display |
| `Dashboard` | `Layout::dashboard([...])` | 12-col grid (used by `DashboardScreen`) |
| `View` | `Layout::view('component-name', $props)` | Custom Vue component |
| `Markdown` | `Layout::markdown($text)` | Rendered markdown: anchored headings, table of contents, highlighted code, tables, callouts |
| `Code` | `Layout::code($code, 'php')` | Highlighted, copyable code block |
| `Flow` | `Layout::flow('process')` | Process or state machine with live monitoring, switches and drill-down |
| `Diagram` | `Layout::diagram('schema')` | Diagram from text: Mermaid, PlantUML, DOT, D2, DBML, Structurizr, Wardley map |
| `Bpmn` / `Dmn` | `Layout::bpmn('process_xml')` | BPMN process or DMN decision model, read-only, with pan, zoom and highlighted elements |
| `Drawio` | `Layout::drawio('network')` | draw.io (diagrams.net) file drawn as SVG |
| `AsyncApi` | `Layout::asyncapi('spec')` | AsyncAPI reference: servers, channels, operations, messages |
| `Kanban` | `Layout::kanban('board')` | Kanban board written as markup, editable through a screen method |
| `Calendar` | `Layout::calendar('schedule')` | Event calendar with repeats, written as markup, editable through a screen method |
| `Video` | `Layout::video('/media/intro.mp4')` | Video player: qualities, HLS, subtitles, chapters, thumbnails |
| `Audio` | `Layout::audio([...])` | Audio player: one track or a playlist, waveform, mini variant |
| `AuditTrail` | `AuditTrail::for(User::class)` | Audit timeline of the shown record |
| `Listener` | `Layout::listener([...])->listen([...])` | Part of a form re-rendered by the server when watched fields change |
| `ResourceTable` | `ResourceTable::for(ItemResource::class)` | Table of another resource's records belonging to the edited one |
| `ResourceIndex` | `Layout::resourceIndex(OrderResource::class)` | Live list page of a resource (table or tree) on a screen |

## Examples

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

`->ratios([2, 1])` for non-equal columns.

### Block (titled section)

```php
Block::make('Profile', [
    Input::make('name'),
    Input::make('email'),
])->description('Personal data'),
```

### Tabs

```php
Tabs::make([
    'General' => [
        Input::make('title'),
        Textarea::make('description'),
    ],
    'SEO' => [
        Input::make('meta_title'),
        Textarea::make('meta_description'),
    ],
    'Translations' => [
        TranslatableInput::make('title'),
    ],
]),
```

Active-tab state is local to the page; switching doesn't lose form
state.

### Wizard (multi-step form)

```php
Layout::wizard([
    Layout::step('Account', [
        Input::make('email')->required(),
        Input::make('password')->type('password')->required(),
    ]),
    Layout::step('Profile', [
        Input::make('name'),
        DatePicker::make('birthday'),
    ]),
    Layout::step('Done', [
        Layout::view('summary-step', ['fields' => [...]]),
    ]),
]),
```

The frontend renders a `UidStepper` header with Back/Next buttons. Moving
forward is validation-gated: the step's fields are checked against their
own rules (`required`, `email`, `numeric`, `integer`, `min`, `max`, `in`;
anything else is left to the server) plus the step's `->rules([...])`.

```php
Layout::wizard([...])
    ->submit('finish')          // the last step's button calls this screen method
    ->freeForm()                // steps may be visited in any order
    ->persistKey('onboarding'), // progress survives a reload (localStorage)
```

Without `freeForm()` only the steps already passed are clickable in the
stepper. With `persistKey()` the current step and the entered values are
kept in the browser until the wizard is submitted; passwords and files are
never stored.

### Modal / Drawer

For actions:

```php
ModalAction::make('Set price')
    ->method('setPrice')
    ->fields([
        Number::make('price')->required(),
    ]),
```

Or as a layout in a Screen, opened by an action that names its id:

```php
public function layout(): array
{
    return [
        Layout::modal('Edit', [
            Input::make('title'),
        ])
            ->withId('edit-modal')
            ->size('lg')               // sm | md | lg | xl | full
            ->dismissable(false)       // no cross, no overlay click, no Escape
            ->footer([
                Button::make('Cancel')->withName('cancel'),
                Button::make('Save')->method('save')->primary(),
            ]),
    ];
}

public function commandBar(): array
{
    return [Button::make('Edit')->opens('edit-modal')];
}
```

A footer action with a method closes the overlay once the method succeeds;
one named `close` or `cancel` just closes it. `Layout::drawer()` works the
same way — `->dismissable()` and `->footer([...])` included — with
`->position('left'|'right'|'top'|'bottom')` and `->size()` (`sm`, `md`, `lg`,
`xl` or a CSS length).

### Wrapper

Plain element to apply CSS:

```php
Layout::wrapper([
    Input::make('title'),
    Input::make('slug'),
])->className('two-col-grid')->tag('section'),
```

`tag()` accepts `div` (default), `section`, `article`, `aside`, `header`,
`footer`, `main`, `nav`, `fieldset` and `span`.

### Accordion

```php
Layout::accordion([
    'Personal' => [Input::make('name')],
    'Billing' => [Input::make('card_last4')->readonly()],
])->multi(),
```

`->multi()` allows several sections to be open at once. Sections start
closed; `->section('Title', [...], defaultOpen: true)` opens one initially.

### Infolist (read-only)

For `view` mode (`ResourceViewPage`, custom Screen):

```php
Layout::infolist([
    TextEntry::make('title'),
    BadgeEntry::make('status')->colors(['published' => 'success', 'draft' => 'default']),
    KeyValueEntry::make('meta'),
])->layout('rows'),  // or 'columns', 'grid'
```

Entry types: `TextEntry`, `BadgeEntry`, `IconEntry`, `KeyValueEntry`,
`ImageEntry`, `RelationEntry`, `RepeatableEntry`, `MapEntry`,
`ColorEntry`.

`FlowEntry` draws a stored process model read-only (the view of a [`Flow` field](fields-reference.md#flow)): `FlowEntry::make('workflow')->height(420)`.

`VideoEntry` and `AudioEntry` play a file of the record — a URL, a storage path (with `disk()`), an uploaded file or a list: `AudioEntry::make('call_recording')->disk('local')` (the one-line mini player; `->full()->waveform()` for the full one), `VideoEntry::make('clip_path')->disk('public')->posterFrom('cover_path')`. See [Video and Audio](#video-and-audio-media-players).

On a custom Screen the entries read the screen's state.

### AuditTrail

```php
AuditTrail::for(\App\Models\User::class)
    ->fromState('user_id')          // the state key holding the id; 'id' by default
    ->limit(20)
    ->withPermission('admin.audit'),
```

Shows the record's audit timeline (`GET /audit/timeline`); nothing is shown
while the state has no id.

### Dashboard

```php
Layout::dashboard([
    StatsOverviewWidget::make()->title('Articles')->size(3),
    ChartWidget::make()->title('Daily')->size(8)->rowSpan(2),
    // ...
]),
```

(Used by `DashboardScreen`, but you can drop a Dashboard layout
inside any Screen.)

### Listener (reactive part of a form)

A listener watches some fields of the form. When one of them changes, the
SPA waits for the user to pause (300 ms by default), posts the current form
state to the server, and replaces the listener's children with the ones the
server renders for that state. An optional handler can also return values to
merge into the form.

```php
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Layout\Layout;

public function layout(): array
{
    return [
        Layout::rows([
            Select::make('country_id')->fromModel(Country::class),

            // Children as a closure: rendered against the current state.
            Layout::listener(fn (array $state) => [
                Select::make('city_id')
                    ->title('City')
                    ->fromModel(City::where('country_id', $state['country_id'] ?? null)),
            ])->listen('country_id'),

            Number::make('price'),
            Number::make('quantity'),

            // A handler computing values: a public method of the screen.
            Layout::listener([
                Number::make('total')->readonly(),
            ])->listen(['price', 'quantity'])->handler('recalculateTotal'),
        ]),
    ];
}

/** Returns a patch: the keys of the form state to change. */
public function recalculateTotal(array $state, Request $request): array
{
    return ['total' => round((float) ($state['price'] ?? 0) * (int) ($state['quantity'] ?? 0), 2)];
}
```

| Method | Meaning |
|---|---|
| `Layout::listener(array\|Closure $children)` | Static children, or `fn (array $state): array` |
| `->listen(string\|array $fields)` | The watched field names |
| `->handler(string\|Closure $handler)` | A public method of the screen/resource, or a closure; called as `(array $state, Request $request)`, returns a state patch (`array`, `Repository` or `null`) |
| `->debounce(int $ms)` | The quiet period before a request, 300 by default |
| `->withId(string $id)` | An explicit id; needed only when two listeners watch the same fields with closure handlers |

The flow on each change: the handler runs with the posted state, its patch
is merged into that state, and the children are rendered with the result.
The response carries both the patch and the children; the SPA merges the
patch into the form (skipping fields the user edited while the request was
in flight), swaps the children in place — fields that keep their position
keep their focus — cancels stale requests, and shows errors as a toast (or,
for a `ValidationException`, under the fields).

**In a Resource form** the listener goes into `formLayout()`, and a string
handler is a public method of the Resource:

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

Validation and saving still read `fields()`: a field rendered by a listener
must be declared there as well. The manifest is built without a record, so a
closure receives an empty state at first; it should tolerate missing keys.
When the edit form opens with the watched fields filled, the listener asks
the server once to render its children for the record (without changing any
values).

**Endpoint and security.** Screens get `POST /api/admin/{screen}/listener`
behind the screen's `permission()`; resources get
`POST /api/admin/{resource}/listener` (only when their form has listeners),
which requires `view` plus `create` or `update` according to the form's
context. The body is `{listener, state, context?, id?}` and the answer
`{listener, state, layouts}`. The request names a listener by its id, never a
method: only handlers declared by a listener in that screen's `layout()` or
that resource's `formLayout()` can run, and reserved screen methods
(`query`, `layout`, …) are refused.

A screen's `layout()` is called without `query()` when a listener request is
served, so the listeners should not depend on properties `query()` sets.

### ResourceTable (embedded table of another resource)

```php
use Dskripchenko\LaravelAdmin\Layout\ResourceTable;

public function formLayout(string $context): array
{
    if ($context !== 'update') {
        return [];
    }

    return [
        Layout::tabs([
            'General' => $this->fields(),
            'Items' => [
                ResourceTable::for(DictionaryItemResource::class)
                    ->foreignKey('dictionary_id')   // the child's column pointing at the parent
                    ->parentField('id')             // the parent's column it holds; 'id' by default
                    ->hideColumns(['dictionary_id'])
                    ->features(['create' => true, 'delete' => true, 'bulkDelete' => true]),
            ],
        ]),
    ];
}
```

Shows the child resource's records that belong to the record being edited,
on the parent's edit page. The table takes the child resource's columns from
the manifest (minus `hideColumns()`), loads up to 100 rows through
`POST /{child}/search` with `filters: {foreign_key: parent[parent_field]}`,
and edits cells inline where the child's columns are `editable()`.
`features()` turns on, all off by default:

- `create` — a draft row at the top, saved through `POST /{child}/create`
  with the foreign key filled in;
- `delete` — a delete button per row (`POST /{child}/delete`);
- `bulkDelete` — row selection and a bulk delete.

Each request is checked against the child resource's own permissions
(`.view`, `.create`, `.update`, `.delete`).

The foreign key reaches the query only through a filter the child resource
declares on that column — without one the table lists the records of every
parent. Declare an exact-match filter in the child resource:

```php
public function filters(): array
{
    return [
        QueryFilter::for('dictionary_id')
            ->using(fn ($query, $value) => $query->where('dictionary_id', $value)),
    ];
}
```

Place it in `formLayout('update')`: a record being created has no key yet,
and the table stays empty until it is saved.

### ResourceIndex (live list of a resource on a screen)

```php
use Dskripchenko\LaravelAdmin\Layout\Layout;

public function layout(): array
{
    return [
        Layout::markdown('What the table shows…'),
        Layout::resourceIndex(OrderResource::class),
    ];
}
```

The very list page of the resource — search, filters, sorting, row and bulk
actions, inline edits, reordering, trash, or the tree of a hierarchical
resource — embedded into a screen. A row click opens the record, as on the
list page. Hidden from a user without the resource's `view` permission. One
per screen: the list's state is shared.

### Markdown

A block of markdown, rendered by the panel's built-in renderer. The source is
escaped before any markup is produced, so raw HTML shows as text and is never
executed; links and images may only point at http(s), `mailto:`, relative and
anchor targets.

```php
Layout::markdown(file_get_contents(base_path('docs/en/getting-started.md')))
    ->toc()                         // table of contents from the h2/h3 headings
    ->linkBase('/admin/screens/')   // where relative links lead
    ->imageBase('/docs-assets/')    // where relative images load from
```

`Layout::markdown()` also takes a callable, resolved when the screen is
serialized — handy when the text is read from disk or a database:

```php
Layout::markdown(fn () => Page::whereSlug($slug)->value('body'))
```

The text is sent as is: pick the language version yourself, e.g. by
`app()->getLocale()`.

What the renderer supports:

| Syntax | Result |
|---|---|
| `# Heading` … `###### Heading` | Headings with anchor ids: `## Getting started` → `#getting-started` (letters of any script are kept, duplicates get `-1`, `-2`) |
| Fenced code blocks (three backticks and a language) | Highlighted, copyable code; the language comes from the fence (`php`, `js`, `ts`, `json`, `bash`, `sql`, `html`, `vue`, `css`, …) |
| ` ```mermaid ` blocks | Diagrams in Mermaid syntax: flowchart, sequence, state, ER, class, C4, pie, gantt, timeline, XY chart and mind map, drawn as SVG by [@dskripchenko/diagrams](https://github.com/dskripchenko/diagrams) |
| ` ```openapi ` blocks | API widgets: a request described in YAML or JSON (or a whole OpenAPI document) becomes the operation with try-it, code samples, TypeScript types and PDF, by [@dskripchenko/openapi](https://github.com/dskripchenko/openapi) |
| ` ```flow ` blocks | A read-only process view: a model in JSON, or Mermaid `stateDiagram-v2` / `flowchart` source, drawn by [@dskripchenko/flow](https://github.com/dskripchenko/flow) |
| ` ```plantuml `, ` ```dot `, ` ```d2 `, ` ```dbml `, ` ```structurizr `, ` ```wardley ` blocks | Diagrams in PlantUML, Graphviz DOT, D2, DBML, Structurizr DSL and Wardley-map syntax (also ` ```puml `, ` ```graphviz `), by the same package |
| ` ```bpmn `, ` ```dmn `, ` ```drawio ` blocks | Process and decision models (BPMN 2.0, DMN 1.1–1.5, with each decision's table) and draw.io documents, drawn as pictures |
| ` ```asyncapi ` blocks | The reference of an AsyncAPI 2.x / 3.0 document: channels, operations, messages |
| ` ```kanban `, ` ```calendar ` blocks | A board of cards and a calendar of events with repeats, written as text — read-only here, editable in place in the WYSIWYG editor |
| `\| a \| b \|` + `\|---\|:---:\|` | Tables, with column alignment |
| `> quote` | Block quote |
| `> **Note** …`, `> **Warning** …` | Callout; also `Tip`, `Important`, `Caution` and GitHub's `> [!NOTE]` form |
| `**bold**`, `*italic*`, `~~struck~~`, backtick code spans | Inline markup |
| `[text](url)`, `![alt](src)` | Links and images |
| `-`/`*`/`1.` lists, `---` | Lists and rules |

**Diagrams.** A fenced block with the language `mermaid` becomes a diagram —
`flowchart` (with subgraphs), `sequenceDiagram`, `stateDiagram-v2`,
`erDiagram`, `classDiagram`, C4 (`C4Context`, `C4Container`, `C4Component`,
`C4Dynamic`, `C4Deployment`), `pie`, `gantt`, `timeline`, `xychart-beta` and
`mindmap`, drawn as SVG by [@dskripchenko/diagrams](https://github.com/dskripchenko/diagrams)
in the panel's colours and theme. The package has no dependencies and is
loaded only when a page has a diagram. Labels are escaped like the rest of the
text, so a diagram cannot carry markup or scripts. A block it cannot read is
shown as code with the reason and the line. The supported syntax is listed in
the package's documentation.

**API blocks.** A fenced block with the language `openapi` holds a request
described in YAML or JSON — `method`, `url`, `params`, `auth`, `body`,
`responses`, or `requests: [...]` for several — or a whole OpenAPI document.
It becomes a live widget of [@dskripchenko/openapi](https://github.com/dskripchenko/openapi):
the operation with its parameters and responses, try-it that sends from the
reader's browser, code samples, TypeScript types and a PDF of the operation.
The package is loaded only when a page has such a block. The format is
described in the package's documentation (API blocks).

**Process blocks.** A fenced block with the language `flow` holds a process model in JSON (see [Flow](#flow-process-with-live-monitoring)) or Mermaid `stateDiagram-v2` / `flowchart` source; it becomes a read-only process view — pan, zoom, sub-processes, the details of a step. The package is loaded only when a page has such a block.

**Media blocks.** Fenced blocks with the language `video` or `audio` become the kit's players. The source is a bare URL (one per line for a playlist or for video sources), JSON, or a few lines of YAML with the options of [Video and Audio](#video-and-audio-media-players): `src`, `sources`, `poster`, `tracks`, `thumbnails`, `title`, `aspectRatio`, `maxWidth`, `autoplay`, `loop` for video; `src` or `tracks`, `title`, `artist`, `waveform`, `peaks`, `mini` for audio. The WYSIWYG editor's slash menu inserts them with `/video` and `/audio`.

**Other notations and widgets.** The same fence draws a diagram in another
syntax: `plantuml` (or `puml`), `dot` (or `graphviz`), `d2`, `dbml`,
`structurizr`, `wardley`. `bpmn` and `dmn` take the XML a modeler saves
(Camunda Modeler, bpmn.io and others) — the BPMN file must carry its layout;
a DMN block shows the requirements diagram and then each decision's table.
`drawio` takes a draw.io document (`.drawio`, uncompressed or compressed).
`asyncapi` takes an AsyncAPI document in YAML or JSON. `kanban` and
`calendar` take markup — columns and cards, events with repeats in words
("every month last fri") — in the format of the kit's
[`UidKanban`](https://github.com/dskripchenko/ui/blob/main/docs/en/kanban.md) and
[`UidEventCalendar`](https://github.com/dskripchenko/ui/blob/main/docs/en/event-calendar.md).
In the WYSIWYG field the board and the calendar are edited right in the block
and write the new markup back. Every package is loaded on first use.

````markdown
```video
src: /media/intro.mp4
poster: /media/intro.jpg
tracks:
  - src: /media/intro.en.vtt
    srclang: en
    label: English
```
````

A host adds renderers for other languages the same way, in `onAppCreated`:
`app.provide(markdownBlockRenderersKey, { ...builtinMarkdownRenderers, chart })`
(`markdownBlockRenderersKey` from `@dskripchenko/ui`,
`builtinMarkdownRenderers` from `@dskripchenko/laravel-admin`).

Options:

| Method | Effect |
|---|---|
| `toc(bool $enabled = true, int $depth = 3)` | A table of contents from headings of levels 2 to `$depth`, beside the text on wide screens and above it on narrow ones |
| `tocLabel(string $label)` | The caption above it; "On this page" by default |
| `linkBase(string $base, bool $stripExtension = true)` | Relative links resolve against `$base` the way a browser resolves them against `<base href>`: with `/admin/screens/`, `docs-menu.md#items` opens `/admin/screens/docs-menu#items`, and with `https://example.com/docs/en/`, `concepts/menu.md` opens `https://example.com/docs/en/concepts/menu`. The `.md` extension is dropped unless `$stripExtension` is false. Links starting with `/`, `#` or a scheme are left alone |
| `imageBase(string $base)` | The same for relative image paths, without touching extensions |
| `card(bool $card = true)` | Draws the text inside a card |

Screen slugs are flat — a screen lives at `/admin/screens/{slug}` and nothing
below it — so a page of a multi-page document is a screen of its own, and a
link between pages has to name the target screen's slug. Markdown written for
a file tree (`concepts/menu.md`, `../intro.md`) does not map onto that by
itself: either rewrite its links to slugs before handing the text over
(`concepts/menu.md` → `docs-concepts-menu`) and use `/admin/screens/` as the
base, or point the base at the place where the tree lives as is, such as the
repository or a docs site.

Links behave like a site's: anchors scroll within the page, links that stay
inside the panel navigate without a reload, and external links open in a new
tab.

### Code

A highlighted code block with a copy button:

```php
Layout::code(<<<'PHP'
    Layout::rows([
        Input::make('title')->required(),
    ]);
    PHP, 'php')
    ->title('app/Admin/Resources/PostResource.php')
    ->lineNumbers(),
```

| Method | Effect |
|---|---|
| `title(string $title)` | A caption above the code, a file name for instance |
| `lineNumbers(bool $on = true)` | Line numbers |
| `maxHeight(int\|string $height)` | Scroll after this height: `400` (px) or `'50vh'` |
| `wrap(bool $wrap = true)` | Wrap long lines instead of scrolling sideways |

### Flow (process with live monitoring)

A process or state machine drawn by
[@dskripchenko/flow](https://github.com/dskripchenko/flow): pan, zoom,
minimap, a details drawer, live state of a running instance (or counters for
all of them), switches that enable and disable steps, and drill-down into
sub-processes. Read-only — the model is edited with the
[`Flow` field](fields-reference.md#flow). The package is loaded only when a
page has a process.

The model is the package's JSON (`{version: 1, nodes, edges, groups?, meta?}`,
see its model documentation). Give `Layout::flow()` a key of the screen's
state, or the model itself — an array or a JSON string, checked when the
layout is built (`InvalidArgumentException` on a broken one):

```php
Layout::flow('process')                 // $state['process']
Layout::flow($order->workflow)          // the model as an array
Layout::flow(file_get_contents($path))  // or as JSON
```

A full example — an order state machine: polling a snapshot of the order
picked in a select, switching steps on and off, a sub-process held by
reference, and a button in the details drawer:

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
            'process' => OrderProcess::model(),   // nodes: new, payment (subgraph: 'payment'), paid, shipped…
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
                    Button::make('Retry step')->method('retryStep')->permission('orders.process.manage'),
                ])
                ->direction('LR')
                ->height(560),
        ];
    }

    /** The snapshot for the level on screen: the root, or a sub-process. */
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
            throw new ActionFailedException('Cancellation cannot be switched off.');
        }
        OrderProcess::setEnabled($change['kind'], $change['id'], $change['enabled'], $change['path']);
    }

    public function loadSubprocess(string $ref, array $node): array
    {
        return OrderProcess::subprocess($ref);   // a model, like the root one
    }

    /** ['kind' => 'node', 'id' => 'shipped', 'path' => [], 'instance' => '1042'] */
    public function retryStep(array $selection): array
    {
        RetryStep::dispatch($selection['instance'], $selection['id']);

        return ['message' => 'Queued for a retry'];
    }
}
```

The methods are the screen's own public methods, called through its
`runMethod` action with positional `parameters` — the same session, CSRF,
layers and checks as a button. Each method the layout names is declared to
the server: a user the layout is hidden from (`canSee()`) gets a 403 for all
of them, and `onToggle(..., permission:)` and the drawer's buttons need their
permission too — without it the switches and buttons are not shown at all.

| Method | Effect |
|---|---|
| `poll(string $method, int\|float $seconds = 5)` | Calls `$method(array $context)` every `$seconds` for the [monitoring snapshot](https://github.com/dskripchenko/flow/blob/main/docs/en/monitoring.md) — `nodes`, `edges`, `path`, `current`, `updatedAt`. `$context` holds `path` (the sub-processes entered), `ref` and `instance`. One request at a time, paused in a hidden tab, backing off after errors; the status line shows the last update and staleness |
| `onToggle(string $method, ?string $permission = null)` | Switches on nodes and edges; `$method(array $change)` persists `{kind, id, enabled, path}`. Throw `ActionFailedException` (or answer with an error) to refuse: the switch flips back and the message is shown in a toast |
| `toggleable(bool $on = true)` | Switches without a method: the change stays in the browser |
| `subgraphs(string\|array $subgraphs)` | How string `subgraph` references are resolved: a method `(string $ref, array $node): array` returning the model, or the models keyed by reference. Inline sub-processes need nothing |
| `instance(string\|int\|null $id)` / `instanceFrom(string $key)` | The instance in focus, passed to the poll method and the drawer's actions; with `instanceFrom()` a field of the screen switches it, and the view polls at once |
| `snapshot(array\|string $snapshot)` | A fixed snapshot, or the state key holding one; `poll()` takes over once it answers |
| `actions(array $buttons)` | `Button`s in the details drawer; each method receives `{kind, id, path, instance}`, a `message` in its answer is shown as a toast, then the state is polled again |
| `direction('LR'\|'TB'\|'auto')` | The layout direction; the model's `meta.direction`, else `auto` (follows the box's shape) |
| `edgeStyle('orthogonal'\|'smooth')` | Right angles (the default) or smooth curves |
| `height(int\|string $height)` | The box's height: `560` (px) or `'70vh'`; 480px by default |
| `minimap(bool)`, `details(bool)`, `statusBar(bool)` | The minimap, the details drawer and the status line; all on by default |
| `title(string $title)` | A caption above the graph |

Captions of an inline model (node and edge labels, descriptions, the title)
go through the panel's JSON translations like any other caption; a model read
from the state is shown as stored.

### Documents (diagrams, BPMN, DMN, draw.io, AsyncAPI)

Five layouts show a document drawn from its source text, read-only:

| Factory | Draws | Package |
|---|---|---|
| `Layout::diagram()` | Mermaid, PlantUML, Graphviz DOT, D2, DBML, Structurizr, Wardley maps | [@dskripchenko/diagrams](https://github.com/dskripchenko/diagrams) |
| `Layout::bpmn()` | BPMN 2.0 process models (Camunda Modeler, bpmn.io and other modelers) with pan and zoom | [@dskripchenko/bpmn](https://github.com/dskripchenko/bpmn) |
| `Layout::dmn()` | DMN decision models; a double click on a decision opens its table | [@dskripchenko/bpmn](https://github.com/dskripchenko/bpmn) |
| `Layout::drawio()` | draw.io (diagrams.net) files, as SVG in a scrollable box | [@dskripchenko/drawio](https://github.com/dskripchenko/drawio) |
| `Layout::asyncapi()` | AsyncAPI documents (YAML or JSON): servers, channels, operations, messages and their payloads | [@dskripchenko/openapi](https://github.com/dskripchenko/openapi) |

Each package is loaded only when a page shows such a document; a skeleton
stands in while it loads. A source the package cannot read is shown as an
alert with the reason (for a diagram, the line and a hint) instead of the
picture.

The source is the same for all of them — and for [Kanban and Calendar](#kanban-and-calendar):

```php
Layout::diagram('schema')                      // an identifier: $state['schema']
Layout::diagram("flowchart LR\n  A --> B")     // anything else: the source itself
Layout::bpmn('process')->url('/files/order.bpmn')   // fetched by the browser
```

A string that is an identifier (`/^[A-Za-z_][\w.-]*$/`) is a key of the
screen's state, read from the form (or the infolist record) and followed as
it changes — a field on the same screen can switch the document. Anything
else is the content. `url()` loads the content in the browser instead, from
a path or a URL of the panel's own site, with the user's session; an address
of another site is refused. Each address is fetched once per page.

```php
use Dskripchenko\LaravelAdmin\Layout\Layout;

final class ArchitectureScreen extends Screen
{
    public function query(mixed ...$params): array
    {
        return [
            'erd' => file_get_contents(database_path('schema.dbml')),
            'process' => Storage::get('bpmn/order.bpmn'),
            'rules' => Storage::get('dmn/discounts.dmn'),
            'step' => Order::find(request('order'))?->current_step,
        ];
    }

    public function layout(): array
    {
        return [
            Layout::diagram('erd')->syntax('dbml')->title('Data model')->height(480),
            Layout::bpmn('process')->highlight(['Task_ship']),
            Layout::dmn('rules')->decision('Decision_discount'),
            Layout::drawio('network')->url('/docs/network.drawio')->page('Rack B'),
            Layout::asyncapi('spec')->url('/docs/asyncapi.yaml'),
        ];
    }
}
```

| Method | Layouts | Effect |
|---|---|---|
| `url(string $url)` | all | Loads the content in the browser from a path (`/files/a.bpmn`) or an `http(s)` URL of the same site, with the user's session; replaces the state key or the inline content |
| `title(string $title)` | all | A caption above the document |
| `height(int\|string $height)` | all | The box's height: `560` (px) or `'70vh'`. Diagrams, draw.io and AsyncAPI grow with their content without it and scroll inside the box with it; BPMN and DMN are 480px by default. A non-positive number or a string that is not a CSS length throws `InvalidArgumentException` |
| `syntax(string $syntax)` | `diagram` | `mermaid`, `plantuml`, `dot`, `d2`, `dbml`, `structurizr` or `wardley`; without it the notation is told from the source's start (`@startuml` — PlantUML, `digraph` — DOT, `Table` — DBML…). Anything else throws `InvalidArgumentException` |
| `highlight(array $ids)` | `bpmn`, `dmn` | The ids of the elements to outline — the current step of a process, say |
| `showDiagram(string\|int $diagram)` | `bpmn` | Which diagram of a file that has several: its id or index; the first by default |
| `decision(string $id)` | `dmn` | Opens this decision's table instead of the diagram |
| `page(int\|string $page)` | `drawio` | Which page: its index, id or name; the first by default |

`showDiagram()` is not called `diagram()` because that name is taken by the
`Layout::diagram()` factory.

### Kanban and Calendar

A kanban board and an event calendar written as markup, drawn by the UI
kit's `UidKanban` and `UidEventCalendar`: cards with tags, assignees and due
dates in columns; events, all-day and timed, with repeats, in day, week,
month, year and agenda views. The source works as for the
[documents](#documents-diagrams-bpmn-dmn-drawio-asyncapi): a state key, the
markup itself, or `url()`.

Read-only by default. With `editable()` the user moves, adds and changes
cards and events (and edits the markup on the source tab); after each change
the layout calls a screen method with the whole new markup:

```php
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Resource\ActionFailedException;

final class SprintScreen extends Screen
{
    public function query(mixed ...$params): array
    {
        $sprint = Sprint::current();

        return [
            'board' => $sprint->board,          // "## To do\n- Write docs #docs @anna\n## Done\n"
            'board_locked' => $sprint->closed,
            'schedule' => $sprint->calendar,
        ];
    }

    public function layout(): array
    {
        return [
            Layout::kanban('board')
                ->title('Sprint board')
                ->editable('saveBoard', permission: 'sprints.edit')
                ->lockable('lockBoard', permission: 'sprints.close')
                ->locked('board_locked'),
            Layout::calendar('schedule')
                ->initialView('week')
                ->views(['week', 'month', 'agenda'])
                ->editable('saveSchedule', permission: 'sprints.edit'),
        ];
    }

    public function saveBoard(string $markup): void
    {
        $sprint = Sprint::current();
        if ($sprint->closed) {
            throw new ActionFailedException('The sprint is closed.');
        }
        $sprint->update(['board' => $markup]);
    }

    public function lockBoard(bool $locked): void
    {
        Sprint::current()->update(['closed' => $locked]);
    }

    public function saveSchedule(string $markup): void
    {
        Sprint::current()->update(['calendar' => $markup]);
    }
}
```

The change is shown at once. To refuse it, throw `ActionFailedException` (or
answer with an error): the board goes back to the markup before the change
and the message is shown in a toast. A `message` in the method's answer is
shown as a toast too. The markup arrives as the request's other strings do —
through the application's middleware, so `TrimStrings` drops its leading and
trailing whitespace.

The methods are the screen's own public methods, called through its
`runMethod` action with positional `parameters` — the same session, CSRF,
layers and checks as a button. Each method the layout names is declared to
the server: a user the layout is hidden from (`canSee()`) gets a 403 for all
of them, and a method with a permission needs it too. A user without the
permission gets a read-only board (and no lock button): the layout serializes
`editable` / `lockable` as false.

| Method | Layouts | Effect |
|---|---|---|
| `editable(string $method, ?string $permission = null)` | `kanban`, `calendar` | Editing on; `$method(string $markup)` receives the whole markup after each change. Refuse with `ActionFailedException` to roll the change back |
| `lockable(string $method, ?string $permission = null)` | `kanban`, `calendar` | The lock button; `$method(bool $locked)` receives the new state. A refusal flips it back with a toast |
| `locked(bool\|string $locked = true)` | `kanban`, `calendar` | Read-only whatever `editable()` says: a flag, or the state key that holds it |
| `initialView(string $view)` | `calendar` | The view shown first: `day`, `week`, `month`, `year` or `agenda`; the markup's `view:`, else the month |
| `date(string $date)` | `calendar` | The date shown first, `YYYY-MM-DD`; the markup's `date:`, else today |
| `views(array $views)` | `calendar` | The views offered, in this order |
| `url()`, `title()`, `height()` | `kanban`, `calendar` | As for the [documents](#documents-diagrams-bpmn-dmn-drawio-asyncapi) |

An unknown view, a date that is not `YYYY-MM-DD` or an empty list of views
throws `InvalidArgumentException`. `initialView()` is not called `view()`
because that name is taken by the `Layout::view()` factory. The markup itself
is described in the UI kit's documentation of `UidKanban` and
`UidEventCalendar`; the same markup in a ` ```kanban ` or ` ```calendar `
block of [Markdown](#markdown) is shown read-only.

### Video and Audio (media players)

The UI kit's players, `UidVideo` and `UidAudio`: a scrub bar with buffered ranges, chapters and thumbnail previews, volume, speed, quality and subtitle menus, full screen, picture-in-picture, keyboard shortcuts and the Media Session API. Starting a player pauses the others on the page. The controls speak the panel's language. HLS (`.m3u8`, fMP4 or MPEG-TS, AES-128, alternate audio renditions) plays through the kit's adapter, loaded only when a stream appears.

```php
Layout::video([
    ['src' => '/media/intro-1080.mp4', 'label' => '1080p'],   // labelled sources form the quality menu
    ['src' => '/media/intro-720.mp4', 'label' => '720p'],
])
    ->title('Introduction')
    ->poster('/media/intro.jpg')
    ->subtitles('/media/intro.en.vtt', 'en', 'English', default: true)
    ->subtitles('/media/intro.ru.vtt', 'ru', 'Русский')
    ->chapters('/media/intro.chapters.vtt')
    ->thumbnails('/media/intro.thumbs.vtt')   // cues point to sprite tiles: sprite.jpg#xywh=0,0,160,90
    ->resume()                                // remembers the position in the browser
    ->maxWidth(760),

Layout::video('/streams/intro/master.m3u8'),          // HLS, by the extension
Layout::video('videos/intro.mp4')->disk('public'),    // a storage path
Layout::video('recording'),                           // the screen's state: $state['recording']

Layout::audio('/media/episode-12.mp3')->title('Episode 12')->artist('The Podcast')->waveform(),
Layout::audio([
    ['src' => '/media/a.mp3', 'title' => 'Intro', 'artist' => 'Band', 'duration' => 64],
    ['src' => '/media/b.ogg', 'title' => 'Outro', 'peaks' => $peaks],   // precomputed peaks, 0..1
])->waveform(),
Layout::audio('calls/2026-10-26.ogg')->disk('local')->mini(),
```

**The source.** A string is a URL, a storage path or a state key: it is media when it has a scheme or a slash or ends in a media extension (`intro.mp4`); otherwise it names a key of the screen's state, or of the record on a view page (`fromState('media.url')` says so explicitly). The value found there may be a URL, a path, an uploaded file `{url, mime, name, …}` (what a `FileUpload` stores), a list of them (a playlist, or video sources), or for video a whole `{sources, poster, tracks, thumbnails}` object. A change of the value — a select on the same form, say — switches the player.

**Storage paths.** With `disk()` paths are turned into URLs — the posters and WebVTT files too: `Storage::url()` for a disk with a public URL (`url` in its config, or a cloud driver), the panel's streaming endpoint `uploads/serve` for a local disk without one. That endpoint answers Range requests, which seeking needs (and Safari, to play a video at all); the disk has to be listed in `admin.uploads.servable_disks`. Values from the state are resolved in the browser the same way. A disk with signed, temporary URLs needs the URLs themselves in the state.

Both players:

| Method | Effect |
|---|---|
| `disk(string $disk)` | Resolve storage paths through this disk |
| `fromState(string $key)` | Read the media from a key of the state |
| `resume(?string $key = null)` | Remember the playback position (localStorage); the media's URL is the key by default |
| `loop()`, `preload('none'\|'metadata'\|'auto')` | |
| `exclusive(false)` | Play alongside the other players instead of pausing them |
| `hotkeys(false)`, `rates([1, 1.5, 2])` | Keyboard shortcuts, the speed menu |
| `crossorigin('anonymous')` | For media, tracks and waveforms from another origin |
| `maxWidth(int\|string $width)` | The widest the player gets: `760` (px) or `'40rem'` |
| `hlsOptions(array $options)` | The HLS adapter: `preferNative` (`false` — the adapter even where the browser plays HLS itself, with its quality and audio-track menus), `startLevel`, `audioLanguage`, `maxBufferAhead`, `maxBufferBehind`, `retries`, `initialBandwidth`, `capToPlayerSize` |

Video:

| Method | Effect |
|---|---|
| `source($src, $label = null, $type = null)`, `quality($label, $src)` | Add a source; sources go in order of preference, labelled ones form the quality menu |
| `hls($src, $label = null)`, `mime($type)` | A stream whose address does not end in `.m3u8`; the type of the sources given without one |
| `title($title)` | A caption above the player and its accessible name |
| `poster($url)`, `thumbnails($vtt)` | The image before playback; the WebVTT with sprite tiles over the scrub bar |
| `subtitles($src, $lang, $label, $default)`, `captions(...)`, `chapters($src)`, `tracks([...])` | WebVTT tracks |
| `autoplay()` | Start at once, muted (browsers block unmuted autoplay); `muted()`, `controls(false)` |
| `aspectRatio('16 / 9')` | The box ratio before the video's size is known; `'4:3'` works too |
| `autoHide(int $ms)` | Idle time before the controls hide while playing |

Audio:

| Method | Effect |
|---|---|
| `track($src, ['title' => …, 'artist' => …, 'duration' => …, 'peaks' => […]])` | Add a track; two or more make a playlist with previous/next and a track list |
| `title()`, `artist()`, `mime()` | For a single track |
| `waveform()` | The waveform as the scrub bar, decoded in the browser (same origin or CORS; not for streams) |
| `peaks([...])` | Precomputed peaks 0..1: the waveform without decoding — for long recordings |
| `mini()` | One line: play, a short bar, time |
| `compact()` | The full row without volume and speed |
| `playlist(false)`, `autoAdvance(false)` | Hide the track list; stop after each track |

`VideoEntry` and `AudioEntry` show a record's file on a view page (see [Infolist](#infolist-read-only)), `TableColumn::asAudio()` and `asVideo()` in a table ([Columns](concepts/resources.md#columns-table)), and a `FileUpload` field shows a player under an uploaded audio or video file.

### View (custom Vue component)

```php
Layout::view('my-custom-card', [
    'count' => 42,
    'label' => 'Items',
]),
```

Frontend:

```ts
import { registerLayout } from '@dskripchenko/laravel-admin'
import MyCustomCard from './MyCustomCard.vue'
registerLayout('my-custom-card', MyCustomCard)
```

## Visibility

```php
Layout::block('Admin only', [...])
    ->canSee(fn () => auth()->user()?->hasAccess('admin.*')),
```

## Composition

Layouts nest:

```php
Tabs::make([
    'Form' => [
        Block::make('Basic', [
            Columns::make([
                Input::make('first_name'),
                Input::make('last_name'),
            ]),
        ]),
        Block::make('Settings', [
            Switcher::make('is_active'),
            Select::make('plan')->options([...]),
        ]),
    ],
    'Audit' => [
        AuditTrail::for(\App\Models\User::class),
    ],
]),
```

## Toarray contract

Every layout serializes to:

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

The props are also spread at the top level, and `items` repeats
`children`. `children` are recursive (other layout `toArray`s or field
`toArray`s).
The frontend `LayoutRenderer` resolves the type from a registry and
recurses.

## See also

- [Fields reference](fields-reference.md)
- [Frontend extension](frontend-extension.md) — register custom layouts
- [Screens](concepts/screens.md)
