---
title: Screens
audience: developer
status: stable
locale: ru
translated_from: en/concepts/screens.md
translated_at: 2026-10-02
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

Slug — один сегмент пути: у экрана есть только адрес `/admin/screens/{slug}`,
вложенных `/admin/screens/{slug}/{что-то}` нет.

## Query-строка

Query-строка страницы уходит вместе с запросом состояния экрана, так что экран
может открыться на вкладке, периоде или фильтре из адреса —
`/admin/screens/reports?period=30&tab=billing`. Смена query-строки (ссылка с
экрана на `?tab=…`) загружает свежий снимок, а перезагрузка после
command-метода её сохраняет.

`query()` получает значения позиционно, в порядке query-строки (ключи,
начинающиеся с `_`, отбрасываются); по имени они доступны через запрос:

```php
public function query(mixed ...$params): array
{
    return [
        'tab' => request()->query('tab', 'overview'),
        'period' => (int) request()->query('period', 7),
    ];
}
```

Значения приходят из адресной строки — валидируйте их как любой ввод.

## Анатомия

| Метод | Назначение |
|---|---|
| `slug()` | Стабильный URL-идентификатор. По умолчанию — kebab-case basename без суффикса `Screen`. |
| `name()` | Заголовок в шапке и пункте меню. |
| `description()` | Опциональный подзаголовок. |
| `permission()` | Permission-gate (string или list). null = только аутентификация. |
| `query(...$params)` | Возвращает initial state. Получает значения query-строки страницы позиционными аргументами (см. ниже). |
| `layout()` | Возвращает `Renderable[]` (Rows/Columns/Tabs/Block/...). |
| `commandBar()` | Возвращает `Action[]` для шапки страницы. |
| Public-методы | Любой другой public-метод (не из reserved) вызывается как command через `Button::make('…')->method('xxx')`. |

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
  обратно. Ключи: `state`, `layouts`, `message`, `message_link`,
  `alerts`, `redirect_url`, `refresh`, `download_url`; всё остальное
  попадает в `extra`.
- `JsonResponse` — пробрасывается как есть.
- `null`/`void` — `{ok: true}`.

`message_link` — ссылка под сообщением. Принимается
`['url' => '/r/jobs/7', 'label' => 'Открыть задание']`,
`['href' => …, 'text' => …]`, пара `['/r/jobs/7', 'Открыть задание']` или
просто строка-URL; без подписи ставится «Открыть», без URL ссылка
отбрасывается. `redirect_url` — путь внутри панели (`/r/orders`, префикс
панели можно не убирать) открывается роутером, внешний адрес — обычным
переходом; `refresh` при редиректе не выполняется.

Валидация: бросай `ValidationException` (например
`validator(...)->validate()`) — фронтовый `useScreenStore.errors`
получит field-ошибки. Отказ по существу — `ActionFailedException`
(`Dskripchenko\LaravelAdmin\Resource\ActionFailedException`): ответ 422 с
`errorKey: action_failed` и её текстом, панель показывает его как ошибку.

## Listener — реактивная часть формы

`Layout::listener()` следит за полями формы: когда одно из них меняется,
SPA выжидает паузу (300 мс по умолчанию), отправляет текущий state на
сервер и заменяет детей listener'а теми, что сервер отрисовал для этого
state. Необязательный handler возвращает патч значений, который
вливается в форму.

```php
public function layout(): array
{
    return [
        Layout::rows([
            Select::make('country_id')->fromModel(Country::class),

            // Дети-замыкание: рендерятся по текущему state.
            Layout::listener(fn (array $state) => [
                Select::make('city_id')
                    ->fromModel(City::where('country_id', $state['country_id'] ?? null)),
            ])->listen('country_id'),

            Number::make('price'),
            Number::make('quantity'),

            // Handler — public-метод экрана, возвращает патч state.
            Layout::listener([Number::make('total')->readonly()])
                ->listen(['price', 'quantity'])
                ->handler('recalculateTotal'),
        ]),
    ];
}

public function recalculateTotal(array $state, Request $request): array
{
    return ['total' => (float) ($state['price'] ?? 0) * (int) ($state['quantity'] ?? 0)];
}
```

- Endpoint: `POST /api/admin/{slug}/listener` с телом `{listener, state}`,
  под тем же `permission()`, что и экран; ответ — `{listener, state, layouts}`.
- Запрос называет listener по id, а не метод: выполняются только handler'ы,
  объявленные listener'ами в `layout()` этого экрана; reserved-методы
  отклоняются.
- `layout()` при таком запросе вызывается без `query()`.
- В Resource listener кладётся в `formLayout()`, handler — public-метод
  Resource; endpoint `POST /api/admin/{resource}/listener` требует `view` и
  `create`/`update` по контексту формы. Поле, которое рисует listener, должно
  быть и в `fields()` — валидация и сохранение читают их.

Подробности и все методы — в [каталоге layout'ов](../layouts-reference.md).

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
| URL | `/r/{slug}` (+`/{id}/edit`, `/create`, `/{id}`) | `/screens/{slug}` |
| Endpoints | `meta`, `search`, `read`, `create`, `update`, `delete`, ... | `state` (GET), `runMethod` (POST) |
| Auto-генерация UI | Да | Нет (host контролирует через `layout()`) |
| Несколько записей | Да (таблица) | Нет (один state) |

## См. также

- [Recipes](../recipes/README.md) — практические рецепты
- [Permissions](permissions.md)
- [Каталог layout'ов](../layouts-reference.md)
