---
title: Actions
audience: developer
status: stable
locale: en
---

# Actions

An **Action** is a button/link/dropdown attached to a Screen, table
row, or bulk selection. All actions invoke a controller method and
share one normalized response shape.

## Action types

| Class | `type()` | Use |
|---|---|---|
| `Button` | `button` | Default. Single click → POST `{method, payload}`. |
| `Link` | `link` | External or internal href, no controller call. |
| `BulkAction` | `bulk` | On selected rows; receives `ids[]`. |
| `ModalAction` | `modal` | Opens a form-modal first, then POSTs. |
| `DropDown` | `dropdown` | Container for sub-actions. |
| `AsyncAction` | `async` | Long-running; uses `dskripchenko/laravel-delayed-process`. |

Any action can also open one of the screen's Modal or Drawer layouts
instead of calling a method: `Button::make('Edit')->opens('edit-modal')`,
where `edit-modal` is the layout's `withId()`.

## Common fluent API

```php
Button::make('Publish')
    ->method('publish')                   // controller method to call
    ->icon('check')                       // lucide icon
    ->primary()                           // visual variant
    ->destructive()                       // red variant
    ->confirm('Publish this article?')    // confirmation prompt
    ->permission('admin.articles.update') // required to see and to run it
    ->position(['command_bar', 'row'])    // where to show
    ->canSee(fn () => auth()->user()?->is_publisher)
    ->withName('publish-action');         // unique key
```

`make()` derives the key from the label (`'Publish'` → `publish`);
`withName()` sets it explicitly. `canSee()` takes a bool or a closure
**without arguments**, evaluated once when the schema is serialized — it
is not a per-row condition.

`permission()` and `canSee()` are enforced on the server. An action the
user lacks the permission for — or whose `canSee()` is false — is left out
of the resource's `actions` in the manifest and of the screen's command bar
and layout, and so are the dropdown items it covers. Running it anyway is
refused with `403` and `errorKey: action_forbidden`:

- the resource `action` endpoint, for row, bulk, header, standalone and
  modal actions and for the items of a `DropDown` (the dropdown's own
  permission covers its items);
- the screen's `runMethod`, for a method that a `Button` or a
  `ModalAction` in the command bar or the layout calls — a method no
  action names is guarded only by the screen's `permission()`;
- `delayed/run`, for a handler that an `AsyncAction` with a permission
  starts (see below).

## Positions

`position(['...'])` — array of:

- `command_bar` — page header (Screen / Resource form / list)
- `row` — table row (per record)
- `bulk` — appears in bulk-toolbar (when 1+ row selected)
- `header` — list-screen toolbar (above the table)

The default is `['command_bar']`; a `BulkAction` defaults to `['bulk']`.
An action in a `row` or `bulk` position applies to records and needs at
least one id; mark an action that runs on its own (an import, a sync)
with `->standalone()` — it is sent without ids and its method receives
an empty list.

## Resource actions

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

Backend dispatches via `ResourceController::action` (POST
`/api/admin/{slug}/action` body `{key, ids[], payload?}`): the action is
found by its key, and the resource method is called as
`$resource->{method}(array $ids, array $payload)` — a row action receives
its own row as a one-element list. An integer return value is reported as
the number of affected records (otherwise `count($ids)`).

The method may also answer with a `string` — the message for the toast — or
an array with either: `['message' => 'Sent to 12 subscribers', 'affected' => 12]`.
With no message the panel says that the action was applied. Throw
`ActionFailedException('...')` to refuse with a reason (422).

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

Frontend dispatches via `ScreenController::runMethod` body
`{method, payload: state}`; the method receives the state as its
argument.

## Modal action (form before submit)

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

The payload is validated against the modal fields' rules (`required()`,
`rules([...])`) before the method runs; a 422 shows the errors next to the
fields and keeps the modal open.

## Async action (long-running)

```php
// AppServiceProvider::boot(AllowlistRegistrar $allowlist)
$allowlist->allow(\App\Jobs\ReindexSearch::class, 'handle');
// or, with a permission required to start it:
$allowlist->allow(\App\Jobs\ReindexSearch::class, 'handle', 'admin.search.reindex');

AsyncAction::make('Re-index search')
    ->handler(\App\Jobs\ReindexSearch::class, 'handle')
    ->withParams(['model' => Article::class])
    ->pollInterval(5);                   // seconds
```

The handler must be allowed in
`Dskripchenko\LaravelAdmin\DelayedProcess\AllowlistRegistrar` as an
`entity::method` pair, or the SPA cannot start it. `delayed/run` requires
the permission given to `allow()`, and when the handler is started by
`AsyncAction`s declared in resources' `actions()` or screens' command bars,
the user has to be allowed at least one of them. The SPA starts the
process via `/api/admin/delayed/run` and polls
`/api/admin/delayed/status?uuid=...` until it finishes; the UI shows a
progress modal. In a `row`/`bulk` position the selected keys are added to
the params as `ids`. `->callback($url)` sets a webhook that receives the
progress and the result.

The handler reports its own progress: inject
`Dskripchenko\DelayedProcess\Contracts\ProcessProgressInterface` (or resolve
it with `app(ProcessProgressInterface::class)` inside the method) and call
`setProgress(0..100)`. `delayed/status` returns the value and the modal draws
it as a bar; the runner sets 100 on success. Outside a delayed-process run
the call does nothing, so the handler can still be called synchronously.

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

## Response payload

A command method returns an array which is normalized into:

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

Recognized keys:

- `state` — replace form-state on the screen.
- `message` — toast or success bar.
- `alerts` — array of `{type: 'info'|'success'|'warning'|'danger', message, title?, duration_ms?}`, shown as toasts (an alert repeating `message` is skipped).
- `redirect_url` — SPA-internal navigation.
- `refresh` — `true` triggers screen reload.
- `download_url` — opens for download.
- `message_link` — where the message leads, e.g. the page of a started job:
  `['url' => …, 'label' => …]`, `['href' => …, 'text' => …]`, `[$url, $label]`
  or a bare URL (labelled "Open"); see [Screens](screens.md#command-methods).

Unknown keys are passed via `extra`.

## Confirmation dialog

```php
->confirm('Delete this record?')
->confirm(['title' => 'Confirm', 'message' => 'Cannot be undone.',
           'confirmLabel' => 'Delete', 'cancelLabel' => 'Keep'])
```

Frontend shows a modal before the POST.

## Refusing for a particular record

There is no per-row visibility condition: a row action is shown on every
row. Check the record in the method and refuse with
`Dskripchenko\LaravelAdmin\Resource\ActionFailedException` — the panel
gets a 422 with your message instead of a 500:

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

## See also

- [Resources](resources.md)
- [Screens](screens.md)
- [Permissions](permissions.md)
- [Custom actions recipe](../../ru/recipes/custom-actions.md) (ru)
