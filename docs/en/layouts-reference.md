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
])->help('Personal data'),
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

The frontend renders an `UidStepper` header. Step navigation is
validation-gated: each step's required fields must pass before the
user can advance.

### Modal / Drawer

For actions:

```php
ModalAction::make('Set price')
    ->method('setPrice')
    ->fields([
        Number::make('price')->required(),
    ]),
```

Or as a layout in a Screen:

```php
Layout::modal('Edit', [
    Input::make('title'),
])->size('lg'),
```

### Wrapper

Plain `<div>` to apply CSS:

```php
Layout::wrapper([
    Input::make('title'),
    Input::make('slug'),
])->class('two-col-grid'),
```

### Accordion

```php
Layout::accordion([
    'Personal' => [Input::make('name')],
    'Billing' => [Input::make('card_last4')->readonly()],
])->multiple(),
```

`->multiple()` allows several sections to be open at once.

### Infolist (read-only)

For `view` mode (`ResourceViewPage`, custom Screen):

```php
Layout::infolist([
    TextEntry::make('title'),
    BadgeEntry::make('status')->variant(fn ($v) => $v === 'published' ? 'success' : 'default'),
    KeyValueEntry::make('meta'),
])->layout('rows'),  // or 'columns', 'grid'
```

Entry types: `TextEntry`, `BadgeEntry`, `IconEntry`, `KeyValueEntry`,
`ImageEntry`, `RelationEntry`, `RepeatableEntry`, `MapEntry`,
`ColorEntry`.

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
        Layout::view('audit-trail', ['record_id' => fn ($state) => $state['id']]),
    ],
]),
```

## Toarray contract

Every layout serializes to:

```json
{
  "id": "l-xxxxxxxx",
  "type": "rows",
  "props": {},
  "children": [ ... ]
}
```

`children` are recursive (other layout `toArray`s or field `toArray`s).
The frontend `LayoutRenderer` resolves the type from a registry and
recurses.

## See also

- [Fields reference](fields-reference.md)
- [Frontend extension](frontend-extension.md) — register custom layouts
- [Screens](concepts/screens.md)
