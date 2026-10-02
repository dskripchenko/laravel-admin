---
title: Actions
audience: developer
status: stable
locale: ru
translated_from: en/concepts/actions.md
translated_at: 2026-10-02
---

# Actions

**Action** — кнопка, ссылка или выпадающее меню на Screen'е, в строке
таблицы или в панели массовых операций. Все actions вызывают метод
контроллера и возвращают ответ одной нормализованной формы.

## Типы actions

| Класс | `type()` | Назначение |
|---|---|---|
| `Button` | `button` | По умолчанию. Один клик → POST `{method, payload}`. |
| `Link` | `link` | Внешняя или внутренняя ссылка, без вызова контроллера. |
| `BulkAction` | `bulk` | Над выбранными строками; получает `ids[]`. |
| `ModalAction` | `modal` | Сначала открывает модалку с формой, затем POST. |
| `DropDown` | `dropdown` | Контейнер для вложенных actions. |
| `AsyncAction` | `async` | Долгая операция через `dskripchenko/laravel-delayed-process`. |

Любой action может вместо вызова метода открыть Modal- или Drawer-layout
экрана: `Button::make('Edit')->opens('edit-modal')`, где `edit-modal` —
значение `withId()` этого layout'а.

## Общий fluent API

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

`make()` выводит ключ из подписи (`'Publish'` → `publish`), `withName()`
задаёт его явно. `canSee()` принимает bool или замыкание **без аргументов**,
которое вычисляется один раз при сериализации схемы, — это не условие
для отдельной строки.

`permission()` и `canSee()` проверяются на сервере. Action, на который у
пользователя нет права (или чей `canSee()` ложен), не попадает ни в
`actions` ресурса в манифесте, ни в командную панель и layout экрана; то же
относится к пунктам выпадающего меню, которые он закрывает. Попытка
выполнить его всё равно отклоняется ответом `403` с
`errorKey: action_forbidden`:

- эндпоинт `action` ресурса — для row-, bulk-, header-, standalone- и
  modal-action'ов и для пунктов `DropDown` (право самого меню закрывает все
  его пункты);
- `runMethod` экрана — для метода, который вызывает `Button` или
  `ModalAction` из командной панели или layout'а; метод, который не назван
  ни в одном action'е, защищён только `permission()` экрана;
- `delayed/run` — для обработчика, который запускает `AsyncAction` с правом
  (см. ниже).

## Позиции

`position(['...'])` — массив из:

- `command_bar` — шапка страницы (Screen / форма ресурса / список)
- `row` — строка таблицы (на каждую запись)
- `bulk` — панель массовых операций (видна, когда выбрана хотя бы одна строка)
- `header` — тулбар списка (над таблицей)

По умолчанию — `['command_bar']`, у `BulkAction` — `['bulk']`. Action в
позиции `row` или `bulk` применяется к записям и требует хотя бы один id.
Action, который работает сам по себе (импорт, синхронизация), помечайте
`->standalone()`: он отправляется без ids, а его метод получает пустой
список.

## Actions ресурса

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

Бэкенд диспетчеризует через `ResourceController::action` (POST
`/api/admin/{slug}/action`, тело `{key, ids[], payload?}`): action ищется
по ключу, а метод ресурса вызывается как
`$resource->{method}(array $ids, array $payload)` — action строки получает
свою строку списком из одного элемента. Целое возвращаемое значение
считается числом затронутых записей (иначе — `count($ids)`).

Метод может вернуть и `string` — сообщение для тоста — или массив с любым из
двух: `['message' => 'Отправлено 12 подписчикам', 'affected' => 12]`. Без
сообщения панель скажет, что действие применено. Чтобы отказать с причиной
(422), бросьте `ActionFailedException('...')`.

## commandBar экрана

```php
public function commandBar(): array
{
    return [
        Button::make('Send')->method('send')->primary(),
        Button::make('Reset')->method('reset')->confirm('Discard changes?'),
    ];
}
```

Фронтенд вызывает `ScreenController::runMethod` с телом
`{method, payload: state}`; метод получает state аргументом.

## Modal action (форма перед отправкой)

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

До вызова метода payload проверяется правилами полей модалки
(`required()`, `rules([...])`); при 422 ошибки показываются рядом с полями,
а модалка остаётся открытой.

## Async action (долгая операция)

```php
// AppServiceProvider::boot(AllowlistRegistrar $allowlist)
$allowlist->allow(\App\Jobs\ReindexSearch::class, 'handle');
// или с правом, без которого его не запустить:
$allowlist->allow(\App\Jobs\ReindexSearch::class, 'handle', 'admin.search.reindex');

AsyncAction::make('Re-index search')
    ->handler(\App\Jobs\ReindexSearch::class, 'handle')
    ->withParams(['model' => Article::class])
    ->pollInterval(5);                   // seconds
```

Обработчик должен быть разрешён в
`Dskripchenko\LaravelAdmin\DelayedProcess\AllowlistRegistrar` парой
`entity::method`, иначе SPA не сможет его запустить. `delayed/run` требует
право, переданное в `allow()`, а если обработчик запускают `AsyncAction`'ы
из `actions()` ресурсов или командных панелей экранов, пользователю должен
быть доступен хотя бы один из них. SPA стартует процесс
через `/api/admin/delayed/run` и опрашивает
`/api/admin/delayed/status?uuid=...` до завершения, показывая модалку с
прогрессом. В позиции `row`/`bulk` выбранные ключи добавляются в параметры
как `ids`. `->callback($url)` задаёт webhook, который получит прогресс и
результат.

Параметры доходят до обработчика по имени: каждый ключ `withParams()` (и `ids`)
связывается с одноимённым параметром обработчика в любом порядке, пропущенные
параметры получают значения по умолчанию, а обязательный параметр с типом-классом
берётся из контейнера. Поэтому каждый ключ должен быть параметром обработчика;
если хотя бы один им не является, обработчик получит весь массив одним аргументом.
Имена `entity` и `method` зарезервированы: на них `delayed/run` отвечает 422.

Прогресс обработчик сообщает сам: внедрите
`Dskripchenko\DelayedProcess\Contracts\ProcessProgressInterface` (или
возьмите его через `app(ProcessProgressInterface::class)`) и вызывайте
`setProgress(0..100)`; по успешному завершению раннер ставит 100. Пример —
в [рецепте](../recipes/custom-actions.md#async-action--долгая-операция-через-delayed-process).

## Ответ

Командный метод возвращает массив, который нормализуется в:

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

Распознаваемые ключи:

- `state` — заменить состояние формы на экране.
- `message` — toast или строка об успехе.
- `alerts` — массив `{type: 'info'|'success'|'warning'|'danger', message, title?, duration_ms?}`, показывается toast'ами (alert, повторяющий `message`, пропускается).
- `redirect_url` — навигация внутри SPA.
- `refresh` — `true` перезагружает экран.
- `download_url` — открывается на скачивание.
- `message_link` — куда ведёт сообщение, например на страницу запущенной задачи:
  `['url' => …, 'label' => …]`, `['href' => …, 'text' => …]`, `[$url, $label]`
  или просто URL (с подписью «Открыть»); см. [Экраны](screens.md).

Неизвестные ключи передаются в `extra`.

## Диалог подтверждения

```php
->confirm('Delete this record?')
->confirm(['title' => 'Confirm', 'message' => 'Cannot be undone.',
           'confirmLabel' => 'Delete', 'cancelLabel' => 'Keep'])
```

Перед POST фронтенд показывает модальное окно.

## Отказ для конкретной записи

Условия видимости для отдельной строки нет: action строки показывается в
каждой строке. Проверяйте запись в методе и отказывайте через
`Dskripchenko\LaravelAdmin\Resource\ActionFailedException` — панель получит
422 с вашим сообщением, а не 500:

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

## См. также

- [Resources](resources.md)
- [Screens](screens.md)
- [Permissions](permissions.md)
- [Рецепт: свои actions](../recipes/custom-actions.md)
