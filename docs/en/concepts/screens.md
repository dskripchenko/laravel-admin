---
title: Screens
audience: developer
status: stable
locale: en
---

# Screens

A **Screen** is a non-CRUD page: contact form, status report, custom
import wizard, integration page. Screens reuse the `Field`/`Layout`/
`Action` primitives but don't bind to an Eloquent model.

```php
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Screen\Screen;

final class ContactScreen extends Screen
{
    public function name(): string { return 'Contact'; }

    public function query(mixed ...$params): array
    {
        return ['email' => '', 'message' => ''];
    }

    public function layout(): array
    {
        return [
            Rows::make([
                Input::make('email')->required()->type('email'),
                Textarea::make('message')->required()->rows(6),
            ]),
        ];
    }

    public function commandBar(): array
    {
        return [Button::make('Send')->method('send')->primary()];
    }

    public function send(array $state): array
    {
        validator($state, [
            'email' => 'required|email',
            'message' => 'required|min:10',
        ])->validate();

        \Mail::to('team@example.com')->send(new \App\Mail\Contact($state));

        return [
            'message' => 'Sent',
            'state' => ['email' => '', 'message' => ''],
            'alerts' => [['type' => 'success', 'message' => 'Thanks!']],
        ];
    }
}
```

Register: `Admin::screen([ContactScreen::class])`.

URL: `/admin/screens/contact`.

The slug is a single path segment: `/admin/screens/{slug}` is the only
address a screen has, and there is no `/admin/screens/{slug}/{anything}`.

## Query string

The page's query string travels with the request for the screen's state, so
a screen can open on a tab, a period or a filter named in the address —
`/admin/screens/reports?period=30&tab=billing`. A change of the query string
(a link from the screen to `?tab=…`) loads a fresh snapshot, and a refresh
after a command method keeps it.

`query()` receives the values positionally, in the order of the query string
(keys starting with `_` are dropped); by name they are at hand through the
request:

```php
public function query(mixed ...$params): array
{
    return [
        'tab' => request()->query('tab', 'overview'),
        'period' => (int) request()->query('period', 7),
    ];
}
```

The values come from the address bar, so validate them like any other input.

## Anatomy

| Method | Purpose |
|---|---|
| `slug()` | Stable URL identifier. Default — kebab-case of class basename without `Screen` suffix. |
| `name()` | Display title in the header and sidebar. |
| `description()` | Optional subtitle under the title. |
| `permission()` | Permission gate (string or list). null = any authenticated admin. |
| `query(...$params)` | Returns initial state. Receives the values of the page's query string as positional arguments (see below). |
| `layout()` | Returns `Renderable[]` (Rows/Columns/Tabs/Block/...). |
| `commandBar()` | Returns `Action[]` rendered in the page header. |
| Public methods | Any other public method (not in the reserved set) is callable as a command via `Button::make('…')->method('xxx')`. |

Reserved method names: `query`, `layout`, `name`, `description`,
`permission`, `commandBar`, `compile`, `slug`, `reservedMethods`,
`isCallableMethod`.

## Command methods

A command method receives a single argument: the state payload from
the frontend (`{form_field: value, ...}`):

```php
public function send(array $state): array { ... }
```

Return values:

- `array` — wrapped into a normalized `ScreenMethodPayload` and sent
  back. Recognized keys: `state`, `layouts`, `message`, `message_link`,
  `alerts`, `redirect_url`, `refresh`, `download_url`; any other key goes
  into `extra`.
- `JsonResponse` — passed through.
- `null` / `void` — `{ok: true}`.

Validation: throw `\Illuminate\Validation\ValidationException` (e.g.
via `validator(...)->validate()`) — frontend's `useScreenStore.errors`
will surface field errors.

## Listeners

`Layout::listener([...])->listen([...])->handler('method')` makes part of a
screen's form reactive: the SPA posts the state to
`POST /api/admin/{slug}/listener` as the watched fields change, and the
server answers with the re-rendered subtree and a state patch. See
[Layouts reference → Listener](../layouts-reference.md#listener-reactive-part-of-a-form).

## Examples

### Read-only Screen (no form)

```php
public function layout(): array
{
    return [
        Rows::make([
            Block::make('Health', [
                Number::make('articles_total')->title('Articles')->readonly(),
                Input::make('db_status')->title('DB')->readonly(),
            ]),
        ]),
    ];
}
```

`->readonly()` is mapped to `disabled` for `Select`, native `readonly`
for `Input`/`Number`.

### Confirm action

```php
Button::make('Reset counter')
    ->method('resetCounter')
    ->confirm('Are you sure? This cannot be undone.')
    ->destructive(),
```

### Refresh after action

```php
public function reload(): array
{
    return ['message' => 'Refreshed', 'refresh' => true];
}
```

`refresh: true` triggers `useScreenStore.load()` after the action.

### Download

```php
public function exportCsv(): array
{
    $url = Storage::temporaryUrl(...);
    return ['download_url' => $url];
}
```

### Redirect

```php
public function publishAndOpen(array $state): array
{
    $article = Article::create($state);
    return ['redirect_url' => "/admin/r/articles/{$article->id}/edit"];
}
```

## Permissions

```php
public function permission(): array|string|null
{
    return 'admin.contact';
}
```

`AdminAccess:admin.contact` is auto-attached to both `state` and
`runMethod` actions. For separate gates per method — guard inside the
command method itself.

## How it differs from Resource

| Aspect | Resource | Screen |
|---|---|---|
| Bound to model | Yes (Eloquent) | No |
| URL | `/r/{slug}` (+`/{id}/edit`, `/create`, `/{id}`) | `/screens/{slug}` |
| Endpoints | `meta`, `search`, `read`, `create`, `update`, `delete`, ... | `state` (GET), `runMethod` (POST) |
| Auto-generated UI | Yes | No (host-controlled via `layout()`) |
| Multiple records | Yes (table) | No (single state) |

## See also

- [Permissions](permissions.md)
- [Layouts reference](../layouts-reference.md)
