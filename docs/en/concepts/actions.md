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

## Common fluent API

```php
Button::make('Publish')
    ->method('publish')                   // controller method to call
    ->icon('check')                       // lucide icon
    ->primary()                           // visual variant
    ->destructive()                       // red variant
    ->confirm('Publish this article?')    // confirmation prompt
    ->permission('admin.articles.update') // gate
    ->position(['command_bar', 'row'])    // where to show
    ->canSee(fn () => auth()->user()?->is_publisher)
    ->withName('publish-action');         // unique key
```

## Positions

`position(['...'])` — array of:

- `command_bar` — page header (Screen / Resource form / list)
- `row` — table row (per record)
- `bulk` — appears in bulk-toolbar (when 1+ row selected)
- `header` — list-screen toolbar (above the table)

## Resource actions

```php
public function actions(): array
{
    return [
        Button::make('Publish')->method('publish')->position(['row'])
            ->canSee(fn ($r) => $r?->status !== 'published'),

        BulkAction::make('Archive')->method('archiveBulk')
            ->confirm('Archive {n} articles?')
            ->destructive(),
    ];
}

public function publish(int $id): void
{
    $this->repository()->find($id)->update(['status' => 'published']);
}

public function archiveBulk(array $ids): void
{
    Article::whereIn('id', $ids)->update(['status' => 'archived']);
}
```

Backend dispatches via `ResourceController::action` (POST
`/api/admin/{slug}/action` body `{key, ids[], payload?}`).

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
`{method, payload: state}`.

## Modal action (form before submit)

```php
ModalAction::make('Set price')
    ->method('setPrice')
    ->fields([
        Number::make('price')->required()->min(0)->step(0.01),
    ]),

public function setPrice(int $id, array $payload): void
{
    Product::find($id)->update(['price' => $payload['price']]);
}
```

## Async action (long-running)

```php
AsyncAction::make('Re-index search')
    ->handler(\App\Jobs\ReindexSearch::class)
    ->params(['model' => Article::class])
    ->callbackUrl('/admin/r/articles')   // redirect on done
    ->pollInterval(5),                   // seconds
```

The frontend polls `/api/admin/delayed/status?uuid=...` until the
process finishes; UI shows a progress modal.

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
- `alerts` — array of `{type: 'info'|'success'|'warning'|'danger', message}`.
- `redirect_url` — SPA-internal navigation.
- `refresh` — `true` triggers screen reload.
- `download_url` — opens for download.

Unknown keys are passed via `extra`.

## Confirmation dialog

```php
->confirm('Delete this record?')
->confirm(['title' => 'Confirm', 'message' => 'Cannot be undone.'])
```

Frontend shows a modal before the POST.

## Disabling per-row

```php
Button::make('Publish')
    ->method('publish')
    ->position(['row'])
    ->canSee(fn ($record) => $record !== null && $record->status !== 'published'),
```

## See also

- [Resources](resources.md)
- [Screens](screens.md)
- [Permissions](permissions.md)
