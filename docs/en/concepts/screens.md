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

## Anatomy

| Method | Purpose |
|---|---|
| `slug()` | Stable URL identifier. Default — kebab-case of class basename without `Screen` suffix. |
| `name()` | Display title in the header and sidebar. |
| `description()` | Optional subtitle under the title. |
| `permission()` | Permission gate (string or list). null = any authenticated admin. |
| `query(...$params)` | Returns initial state. Receives `?key=value` from URL as named args. |
| `layout()` | Returns `Renderable[]` (Rows/Columns/Tabs/Block/...). |
| `commandBar()` | Returns `Action[]` rendered in the page header. |
| Public methods | Any other public method (not in the reserved set) is callable as a command via `Button::method('xxx')`. |

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
  back. Recognized keys: `state`, `message`, `alerts`, `redirect_url`,
  `refresh`, `download_url`, `extra`.
- `JsonResponse` — passed through.
- `null` / `void` — `{ok: true}`.

Validation: throw `\Illuminate\Validation\ValidationException` (e.g.
via `validator(...)->validate()`) — frontend's `useScreenStore.errors`
will surface field errors.

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
| URL | `/r/{slug}` (+`/{id}/edit`, `/create`, `/{id}/view`) | `/screens/{slug}` |
| Endpoints | `meta`, `search`, `read`, `create`, `update`, `delete`, ... | `state` (GET), `runMethod` (POST) |
| Auto-generated UI | Yes | No (host-controlled via `layout()`) |
| Multiple records | Yes (table) | No (single state) |

## See also

- [Custom forms cookbook](../recipes/custom-form.md) (TBD)
- [Permissions](permissions.md)
- [Layouts reference](../layouts-reference.md)
