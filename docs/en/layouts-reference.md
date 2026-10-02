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
| `AuditTrail` | `AuditTrail::for(User::class)` | Audit timeline of the shown record |
| `Listener` | `Layout::listener([...])->listen([...])` | Part of a form re-rendered by the server when watched fields change |
| `ResourceTable` | `ResourceTable::for(ItemResource::class)` | Table of another resource's records belonging to the edited one |

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
same way, with `->position('left'|'right'|'top'|'bottom')` and `->size()` (`sm`,
`md`, `lg`, `xl` or a CSS length).

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

### Markdown

A block of markdown, rendered by the panel's built-in renderer. The source is
escaped before any markup is produced, so raw HTML shows as text and is never
executed; links and images may only point at http(s), `mailto:`, relative and
anchor targets.

```php
Layout::markdown(file_get_contents(base_path('docs/en/getting-started.md')))
    ->toc()                         // table of contents from the h2/h3 headings
    ->linkBase('/admin/screens/docs/en/') // where relative links lead
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
| `\| a \| b \|` + `\|---\|:---:\|` | Tables, with column alignment |
| `> quote` | Block quote |
| `> **Note** …`, `> **Warning** …` | Callout; also `Tip`, `Important`, `Caution` and GitHub's `> [!NOTE]` form |
| `**bold**`, `*italic*`, `~~struck~~`, backtick code spans | Inline markup |
| `[text](url)`, `![alt](src)` | Links and images |
| `-`/`*`/`1.` lists, `---` | Lists and rules |

Options:

| Method | Effect |
|---|---|
| `toc(bool $enabled = true, int $depth = 3)` | A table of contents from headings of levels 2 to `$depth`, beside the text on wide screens and above it on narrow ones |
| `tocLabel(string $label)` | The caption above it; "On this page" by default |
| `linkBase(string $base, bool $stripExtension = true)` | Relative links resolve against `$base` the way a browser resolves them against `<base href>`: with `/admin/screens/docs/en/`, `concepts/menu.md#items` opens `/admin/screens/docs/en/concepts/menu#items` and `../ru/intro.md` opens `/admin/screens/docs/ru/intro`. The `.md` extension is dropped unless `$stripExtension` is false. Links starting with `/`, `#` or a scheme are left alone |
| `imageBase(string $base)` | The same for relative image paths, without touching extensions |
| `card(bool $card = true)` | Draws the text inside a card |

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
