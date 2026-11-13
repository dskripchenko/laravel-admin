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
| `permissionMode()` | How the keys of `permission()` combine: `'all'` (the default, every key) or `'any'` (one is enough). |
| `query(...$params)` | Returns initial state. Receives the values of the page's query string as positional arguments (see below). |
| `layout()` | Returns `Renderable[]` (Rows/Columns/Tabs/Block/...). |
| `commandBar()` | Returns `Action[]` rendered in the page header. |
| Public methods | Any other public method (not in the reserved set) is callable as a command via `Button::make('…')->method('xxx')`. |

Reserved method names: `query`, `layout`, `name`, `description`,
`permission`, `permissionMode`, `commandBar`, `compile`, `slug`, `reservedMethods`,
`isCallableMethod`.

## Command methods

A command method receives a single argument: the state payload from
the frontend (`{form_field: value, ...}`):

```php
public function send(array $state): array { ... }
```

### Button parameters

A button may carry parameters of its own — a dictionary code, a record id.
They travel with the state (the `params` field of the `runMethod` body) and
reach the method as its second argument:

```php
Button::make('Refresh')->method('sync')->parameters(['dictionary' => 'vehicle_marks'])
// ->params([...]) is the same

public function sync(array $state, array $parameters): array
{
    $code = $parameters['dictionary'] ?? null;
    abort_unless(in_array($code, self::DICTIONARIES, true), 422);
    // ...
}
```

A one-argument method (`send(array $state)`) is called as before — the
parameters do not reach it. They come from the browser: validate them as any
input. When several buttons share a method and differ in `permission()`, the
server checks the button whose parameters came: sending the parameters of a
button the user may not press is a `403 action_forbidden`.

Buttons work in `commandBar()` and inside the screen's layouts — in
`Layout::rows()`, a `Layout::columns()` cell, a block: the panel draws a
`Button` from `layout()` as a button (outside a screen — in a resource form —
such nodes are not drawn). A screen has no table layout with row actions; a
"table with a button in every row" is built from rows the server generates:

```php
public function layout(): array
{
    $rows = [];
    foreach (Dictionary::all() as $d) {
        $rows[] = Layout::columns([
            Layout::markdown("**{$d->title}** — synced {$d->synced_at}"),
            Layout::rows([
                Button::make('Refresh')->withName('sync-'.$d->code)
                    ->method('sync')->parameters(['dictionary' => $d->code])
                    ->permission('admin.dictionaries.'.$d->code),
            ]),
        ]);
    }

    return $rows;
}
```

For a real table with filters and row actions use a resource
(`Layout::resourceIndex()`) and its row actions.

Return values:

- `array` — wrapped into a normalized `ScreenMethodPayload` and sent
  back. Recognized keys: `state`, `layouts`, `message`, `message_link`,
  `alerts`, `redirect_url`, `refresh`, `download_url`; any other key goes
  into `extra`.
- `JsonResponse` — passed through.
- `null` / `void` — `{ok: true}`.

`message_link` is the link under the message. Accepted shapes:
`['url' => '/r/jobs/7', 'label' => 'Open the job']`,
`['href' => …, 'text' => …]`, a pair `['/r/jobs/7', 'Open the job']` or a
bare URL string. Without a label it gets the default "Open"; without a URL
it is dropped. `redirect_url` — an in-panel path (`/r/orders`; the panel
prefix may stay) is opened through the router, an external address with a
full page load; `refresh` is skipped when redirecting.

Validation: throw `\Illuminate\Validation\ValidationException` (e.g.
via `validator(...)->validate()`) — frontend's `useScreenStore.errors`
will surface field errors. A refusal on the merits is an
`ActionFailedException`
(`Dskripchenko\LaravelAdmin\Resource\ActionFailedException`): a 422 with
`errorKey: action_failed` and its message, shown by the panel as an error.

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

`AdminAccess:admin.contact` is auto-attached to the `state`, `runMethod`
and `listener` actions. For separate gates per method — a button's
`permission()` or a check inside the command method itself.

A list of keys means "every one" by default. A section screen open to
whoever may work with **at least one** of the section's resources declares
`permissionMode()`:

```php
public function permission(): array|string|null
{
    return ['admin.orders.view', 'admin.quotes.view', 'admin.policies.view'];
}

public function permissionMode(): string
{
    return self::PERMISSION_ANY;   // 'any'; self::PERMISSION_ALL by default
}
```

The rule applies wherever access to the screen is decided: the middleware
of `state`/`runMethod`/`listener` (`AdminAccess:…,any`), the SPA route (the
manifest carries `permission_mode`), the menu items — the auto-filled ones
and `MenuNode::screen()`; for a `DashboardScreen` — the dashboard endpoints,
the manifest and the menu. A user with none of the keys gets a 403 and no
menu item. To keep a screen out of the auto menu use
`Admin::menu()->hideAuto('slug')` (see [Menu](menu.md)).

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
