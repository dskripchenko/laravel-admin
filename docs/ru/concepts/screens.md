---
title: Screens
audience: developer
status: stable
locale: ru
translated_from: en/concepts/screens.md
translated_at: 2026-05-08
---

# Screens

**Screen** — non-CRUD страница: контактная форма, отчёт, кастомный
импорт-визард, integration page. Screen'ы переиспользуют примитивы
`Field`/`Layout`/`Action`, но не привязаны к Eloquent-модели.

```php
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Screen\Screen;

final class ContactScreen extends Screen
{
    public function name(): string { return 'Связаться'; }

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
        return [Button::make('Отправить')->method('send')->primary()];
    }

    public function send(array $state): array
    {
        validator($state, [
            'email' => 'required|email',
            'message' => 'required|min:10',
        ])->validate();

        \Mail::to('team@example.com')->send(new \App\Mail\Contact($state));

        return [
            'message' => 'Отправлено',
            'state' => ['email' => '', 'message' => ''],
            'alerts' => [['type' => 'success', 'message' => 'Спасибо!']],
        ];
    }
}
```

Регистрация: `Admin::screen([ContactScreen::class])`.
URL: `/admin/screens/contact`.

## Анатомия

| Метод | Назначение |
|---|---|
| `slug()` | Стабильный URL-идентификатор. По умолчанию — kebab-case basename без суффикса `Screen`. |
| `name()` | Заголовок в шапке и пункте меню. |
| `description()` | Опциональный подзаголовок. |
| `permission()` | Permission-gate (string или list). null = только аутентификация. |
| `query(...$params)` | Возвращает initial state. Принимает `?key=value` из URL как named-arg'и. |
| `layout()` | Возвращает `Renderable[]` (Rows/Columns/Tabs/Block/...). |
| `commandBar()` | Возвращает `Action[]` для шапки страницы. |
| Public-методы | Любой другой public-метод (не из reserved) вызывается как command через `Button::method('xxx')`. |

Reserved method names: `query`, `layout`, `name`, `description`,
`permission`, `commandBar`, `compile`, `slug`, `reservedMethods`,
`isCallableMethod`.

## Command-методы

Получают один аргумент — state-payload:

```php
public function send(array $state): array { ... }
```

Возвращаемые значения:

- `array` — нормализуется в `ScreenMethodPayload` и отправляется
  обратно. Ключи: `state`, `message`, `alerts`, `redirect_url`,
  `refresh`, `download_url`, `extra`.
- `JsonResponse` — пробрасывается как есть.
- `null`/`void` — `{ok: true}`.

Валидация: бросай `ValidationException` (например
`validator(...)->validate()`) — фронтовый `useScreenStore.errors`
получит field-ошибки.

## Примеры

### Read-only Screen (без формы)

```php
public function layout(): array
{
    return [
        Rows::make([
            Block::make('Здоровье', [
                Number::make('articles_total')->title('Статей')->readonly(),
                Input::make('db_status')->title('БД')->readonly(),
            ]),
        ]),
    ];
}
```

`->readonly()` маппится в `disabled` для `Select`, native `readonly`
для `Input`/`Number`.

### Confirm action

```php
Button::make('Сбросить счётчик')
    ->method('resetCounter')
    ->confirm('Точно? Действие необратимо.')
    ->destructive(),
```

### Refresh после действия

```php
public function reload(): array
{
    return ['message' => 'Обновлено', 'refresh' => true];
}
```

`refresh: true` триггерит `useScreenStore.load()` после действия.

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

`AdminAccess:admin.contact` авто-привязывается и к `state`, и к
`runMethod`. Для разных gate-ов на разные методы — проверяй внутри
самого метода.

## Чем отличается от Resource

| Аспект | Resource | Screen |
|---|---|---|
| Привязан к модели | Да (Eloquent) | Нет |
| URL | `/r/{slug}` (+`/{id}/edit`, `/create`, `/{id}/view`) | `/screens/{slug}` |
| Endpoints | `meta`, `search`, `read`, `create`, `update`, `delete`, ... | `state` (GET), `runMethod` (POST) |
| Auto-генерация UI | Да | Нет (host контролирует через `layout()`) |
| Несколько записей | Да (таблица) | Нет (один state) |

## См. также

- [Permissions](../en/concepts/permissions.md) (en)
- [Каталог layouts](../en/layouts-reference.md) (en)
