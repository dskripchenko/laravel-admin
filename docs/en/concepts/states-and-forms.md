---
title: States, forms and related data (1.74)
audience: developer
status: stable
locale: en
---

# States, forms and related data

1.74 adds what recurs on typical screens: one look for "empty / nothing
found / failed / no access", a permission matrix for roles, a list or a
screen in a side panel opened by a link, an event feed, a before/after
comparison in the audit history, a search over the fields of a long form and
an unsaved-changes bar. All of it is opt-in: unconfigured, the panel behaves
as before, except for the empty list's caption (see the
[migration guide](../migration-guide.md)).

## States

A state takes the place of the content — a table, a tab, a widget; the
screen's header and filters stay. One component, `AdminState` (exported for
your own pages), draws all four.

**Empty list.** By default "No records" and "Create" (for a user who may
create). A resource says its own words:

```php
use Dskripchenko\LaravelAdmin\Support\EmptyState;

public function emptyState(): ?EmptyState
{
    return EmptyState::make('No cities yet', 'Cities are used in the office cards.', 'Add a city');
}
```

or one embedded list does (over the resource's):

```php
Layout::resourceIndex(CallbackResource::class)
    ->scope('new')
    ->emptyState('No new calls', 'The menu counter is reset.',
        Link::make('Open "In progress"')->href('/admin/screens/calls?tab=work'));
```

The third argument is the action: `null` — "Create", a string — the same
button under that caption, an `Action` (a link or any panel action; hidden
when the user may not run it), `false` — none.
`EmptyState::make(...)->noResults($title, $description)` — the words of
"nothing found".

**Nothing found.** When the search or the filters match nothing, the state
lists them ("Status «error», Created 01.11–13.11") and offers "Reset the
filters".

**Failed load.** The server's reason, "Retry" and the request code
`req 7f3a-91c2 · 13.11 14:40` with "Copy the code". The code is the
`X-Request-Id` header the panel puts on every request; the server sends it
back and puts it into the log context as `admin_request_id`, so the code a
user reports finds the request in the log. A screen that failed to load and
the embedded table of related records look the same.

**No access.** "The section “Audit log” is not available. It takes the key
`platform.audit-logs`. Access is granted by the panel's administrator." with
"Copy the key". The 403 page gets the section's name and the missing key
from the router (`/forbidden?section=…&permission=…`); an API 403 now carries
`payload.permission`.

## Permission matrix — `PermissionMatrix`

```php
use Dskripchenko\LaravelAdmin\Field\PermissionMatrix;

PermissionMatrix::make('permission_keys')
    ->title('Permissions')
    ->groups(fn () => [
        'Clients › Queues' => [
            'clients.claims' => 'Claims',
            'clients.callbacks' => 'Callbacks',
        ],
        ['label' => 'System', 'items' => [
            ['key' => 'platform.roles', 'label' => 'Roles', 'hint' => 'Who may do what'],
        ]],
    ])
    ->extraColumn('Unmasked personal data', ['clients.claims' => 'clients.claims.reveal']);
```

Groups are folding rows (folded when there are more than five; `->expanded()`
opens them), each with a three-state checkbox and "N of M"; above them a
search over labels, hints and keys, "Only selected", "Select all" and
"Selected N of M". The second column (`extraColumn`) is a key granted along
with the main one (unchecking the main one takes it away too).

The value is a list of keys. How a role stores it (a JSON list, Orchid's
`{key: 1}` map) is the resource's business: fill the field in
`transformRecord()`/`fill` and parse it on save. The field reads both a list
and a map. The groups are an array, a closure (called when the form is
serialized, never while the routes are built) or `->fromRegistry()` — the
groups of `Admin::permissions()`. `->keys()` — every key of the matrix, for
a `Rule::in(...)`.

## A list or a screen in a side panel

```php
Link::make('Claims · 2')
    ->href('/admin/r/claims?filter[phone]=79001234567&scope=open')
    ->inDrawer();                    // or ->target('drawer'); ->inDrawer('lg') — the width

TableColumn::make('claims_count')->asLink('/admin/r/claims?filter[contract_id]={id}', 'drawer');
```

The link opens a panel address in a side panel over the current page: a
resource's list with the link's search (`q`), filters (`filter[...]`), sort
and scope (`scope`, one of `Resource::scopes()`); a screen
(`/admin/screens/{slug}?…`) embedded with that query. The user may change
the filters inside; the address of the page under it stays. The route's
permissions are checked — without them the panel shows "no access". "Open as
a page" goes to the address itself. Any other address opens as a page, an
external one in a new tab. From your own code:
`import { openInDrawer } from '@dskripchenko/laravel-admin'` →
`openInDrawer('/admin/r/claims?q=…', { title: 'Claims' })`.

## Event feed — `Layout::timeline()`

```php
Layout::timeline('events')                     // a state key of the screen
    ->types([
        'mail' => ['label' => 'Letters', 'icon' => 'mail', 'tone' => 'info'],
        'process' => ['label' => 'Processes', 'icon' => 'workflow'],
        'audit' => 'Audit',
    ])
    ->title('Events')
    ->limit(20);
```

An event is an array: `type`, `title`, `time` (ISO — shown in the panel's
locale — or any text), `details` (a string or a list of `['label', 'value']`,
folded under "Details"), `tone` (neutral, info, success, warning, danger),
`icon`, `error: true`, `url`, `linkLabel`, `linkTarget: 'drawer'`. The tone
and the icon default to the type's. Above the feed: "All N", a chip per type
with its count and "Only errors" (`error: true` or the danger tone). The
source is a state key, a list or a closure; the screen gathers the data.
`->withoutFilters()` — no chips; `->emptyText()` — the empty feed's phrase.

## Before / after in the audit history

A record's history (`AuditTrail`, the card's history) shows short values as
before — "before → after" — and compares a long text line by line (a single
long line word by word), a JSON value as `path: value` lines with sorted
keys. The removed parts are struck out on red, the added ones on green, the
unchanged runs folded ("… 12 lines unchanged", a click unfolds them).
`AuditValueDiff` is exported for your own pages.

The package does not roll a value back: it has no restore from the audit log.
That is a project action (`Button::make('Roll back')->method(...)`) writing
the log entry's `before` into the model.

## Search over the fields of a form

```php
Layout::tabs([...])->vertical()->persistInQuery('tab')->searchable();
```

A search appears above the tabs: by a field's label, hint, placeholder and
key. The number of finds — in all and per tab (nested tab sets count too);
tabs without finds are dimmed; when the open tab finds nothing, the first
one that does opens. The open tab hides the fields it does not find and the
blocks left without fields, and marks the fields it finds.

## Unsaved changes

**A screen.** `Screen::saveMethod()` names the command method that saves the
screen's form:

```php
public function saveMethod(): ?string
{
    return 'save';
}
```

With it a sticky bar at the bottom reads "1 unsaved change · Captcha › Server
key · Discard · Save" (the trail is the tabs and blocks above the field), a
changed field is marked, leaving the page (the menu, a link, Back) asks
first, closing the browser tab gets the browser's question. "Discard" puts
the values back, "Save" calls the screen's method.

**A resource form.** Its "There are unsaved changes · Cancel · Save" bar was
there before; now leaving a changed form asks too (off with
`features.warnOnUnsavedChanges = false` in the resource's manifest).

## Small things

- `Field::counter(?int $max)` — a character counter under an input or a
  textarea: "27 of 120 characters"; with `$max` the field takes no more (add
  the `max:` rule for the server yourself).
- `StatsOverviewWidget::stat(..., hint: 'oldest 26 h')` — a line under the
  number.

## data-testid

`state-empty`, `state-no-results`, `state-error`, `state-forbidden`,
`state-retry`, `state-copy-code`, `state-code`, `state-permission`,
`state-copy-key`, `state-home`; in a list `resource-error`,
`resource-forbidden`, `resource-empty-action`; `forbidden-page`,
`screen-load-error`; the matrix — `permission-matrix-{name}`,
`permission-matrix-search`, `permission-matrix-only-selected`,
`permission-matrix-all`, `permission-matrix-total`, `permission-group-{label}`,
`permission-group-toggle`, `permission-group-check`, `permission-group-count`,
`permission-{key}`, `permission-check`, `permission-extra`; the side panel —
`link-drawer` (`data-kind`), `link-drawer-page`, `cell-link`; the feed —
`timeline`, `timeline-chip-all`, `timeline-chip-{type}`, `timeline-only-errors`,
`timeline-event-{id}` (`data-type`), `timeline-link`,
`timeline-details-toggle`, `timeline-details`, `timeline-show-all`,
`timeline-empty`; the audit — `audit-diff-{field}`, `value-diff`,
`value-diff-fold`; the search — `tabs-search`, `tabs-search-count`,
`tab-matches-{key}`; unsaved changes — `screen-savebar`,
`screen-savebar-count`, `screen-savebar-fields`, `screen-savebar-discard`,
`screen-savebar-save`, `data-changed="true"` on a field; `stat-hint`.

## See also

- [Navigation](navigation.md) — list scopes, tab badges, Ctrl+K
- [Screens](screens.md)
- [Permissions](permissions.md)
