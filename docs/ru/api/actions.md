# API: действия (actions)

Действие (`Action\Button`, `ModalAction`, `BulkAction`, `AsyncAction`, `Link`, `DropDown`) — это описание кнопки. На сервере его выполняют три точки входа, в зависимости от того, где оно объявлено и что вызывает:

| Где объявлено | Что вызывает | Эндпоинт |
|---|---|---|
| `Resource::actions()` | метод ресурса (`->method('archive')`) | `POST /api/admin/{slug}/action` |
| `Screen::commandBar()` или `Screen::layout()` | метод экрана (`->method('send')`) | `POST /api/admin/{screen}/runMethod` — см. [screens.md](screens.md) |
| `AsyncAction` в ресурсе или экране | обработчик из allowlist (`->handler(Entity::class, 'run')`) | `POST /api/admin/delayed/run` + `GET /api/admin/delayed/status` |

`Link` ничего не вызывает на сервере, а у `DropDown` выполняются его вложенные действия.

> Конвенции — [conventions.md](conventions.md). CRUD ресурса, `restore`/`forceDelete`/`replicate`/`reorder` — отдельные actions, см. [resources.md](resources.md). Delayed-процессы — [delayed.md](delayed.md).

---

## `{slug}.action` — действие ресурса

```php
/**
 * Применяет одно из действий ресурса к выбранным записям или выполняет standalone-действие.
 *
 * @input array ?$ids Ключи записей; хотя бы один для действия, которое применяется к записям.
 * @input string $key Действие, как его объявляет ресурс.
 * @input object ?$payload Аргументы действия, если оно их принимает.
 * @input [operationSchema]
 *
 * @output object $payload
 *
 * @security AdminSession
 *
 * @response 200 {AffectedResponse}
 * @response 403 {ForbiddenErrorResponse} Нет права действия или его canSee() ложен.
 * @response 404 {NotFoundErrorResponse} Ресурс не объявляет такого действия.
 * @response 422 {ValidationErrorResponse}
 * @response 501 Действие объявлено, а метода на ресурсе нет.
 */
public function action(Request $request): JsonResponse;
```

Маршрут регистрируется для каждого ресурса и требует `<base>.view` (`admin.{slug}.view`).

### Как выполняется

1. **Поиск.** Действие ищется по `key` — имени действия (`Action::name()`) — среди `Resource::actions()`, включая элементы внутри `DropDown`. Имя по умолчанию выводится из подписи (`'Архивировать'` → `action`, `'Archive'` → `archive`), явно задаётся `->withName('archive')`. Не нашлось — `404`, `errorKey: unknown_action`.
2. **Права.** Если пользователь не может выполнить действие — `403`, `errorKey: action_forbidden` (подробнее ниже).
3. **Выборка.** Если действие применяется к записям (`requiresSelection()`), `ids` обязателен и должен содержать хотя бы один элемент, иначе `422`. Применяется к записям:
   - `BulkAction` — всегда;
   - действие с `position(['row'])` или `position(['bulk'])` — если не помечено `->standalone()`.

   Действие в `command_bar` или `header` (позиция по умолчанию — `command_bar`) или явно помеченное `->standalone()` выполняется без `ids`; метод получит пустой список.
4. **Метод.** Вызывается метод ресурса, имя которого задано `->method('...')`. Нет такого метода — `501`, `errorKey: action_not_implemented`.
5. **Payload модалки.** У `ModalAction` `payload` проверяется правилами его `fields()`: строковые `rules` полей и `required()`. Ошибки — обычный `422` с `messages` по именам полей.
6. **Вызов** `$resource->{method}(array $ids, array $payload)`.

### Ответ

```json
{
  "success": true,
  "payload": { "affected": 3, "message": "Action `archive` applied" }
}
```

`affected` — то, что вернул метод, если это целое число, иначе количество переданных `ids`.

### Ошибки

| HTTP | `errorKey` | Когда |
|---|---|---|
| 404 | `unknown_action` | ресурс не объявляет действия с таким `key` |
| 403 | `action_forbidden` | у пользователя нет `permission()` действия (или `DropDown`, в котором оно лежит), либо его `canSee()` ложен |
| 422 | — (ошибка валидации) | нет `key`; нет `ids` у действия, требующего записей; `payload` модалки не прошёл правила полей; метод сам бросил `ValidationException` |
| 422 | `action_failed` | метод бросил `Resource\ActionFailedException` — отказ по существу, `message` — текст исключения |
| 501 | `action_not_implemented` | у действия нет `method()` или на ресурсе нет такого метода |
| 500 | `action_failed` | любое другое исключение из метода |

### Пример

```php
public function actions(): array
{
    return [
        BulkAction::make('Archive')->method('archive')->permission('admin.users.archive'),
        ModalAction::make('Notify')
            ->method('notify')
            ->position(['row'])
            ->fields([Input::make('subject')->required()]),
        Button::make('Recalculate')->method('recalculate')->standalone(),
    ];
}

public function archive(array $ids, array $payload): int
{
    return User::query()->whereKey($ids)->update(['archived' => true]);
}
```

```http
POST /api/admin/users/action
{ "key": "archive", "ids": [1, 2, 3] }
```

```http
POST /api/admin/users/action
{ "key": "notify", "ids": [7], "payload": { "subject": "Привет" } }
```

```http
POST /api/admin/users/action
{ "key": "recalculate" }
```

---

## Права на действия

У действия два условия видимости:

- `->permission('admin.users.archive')` — ключ права, который нужен пользователю;
- `->canSee(bool|callable)` — произвольное условие.

Действие доступно, если выполняются оба условия и доступно всё, внутри чего оно лежит: `DropDown`, в котором оно находится, и layout (у layout есть свой `canSee()`).

**Что пользователь не видит.** Недоступное действие не попадает:

- в `actions` метаданных ресурса (`{slug}.meta`) и в `resources[].actions` манифеста;
- в `command_bar` экрана (`state` экрана и сгенерированные страницы ресурса `listScreen`, `editScreen` и т.д.);
- в `layout` экрана — недоступные дочерние элементы layout не сериализуются;
- в `items` `DropDown` — вложенные элементы фильтруются по отдельности.

**Что сервер отказывается выполнять.** Скрытие кнопки — не единственная защита. Запрос, который всё равно пытается выполнить недоступное действие, получает `403` с `errorKey: action_forbidden`:

```json
{
  "success": false,
  "payload": { "errorKey": "action_forbidden", "message": "Доступ запрещён: admin.users.archive" }
}
```

В `message` указано право, которого не хватает; если действие закрыто только `canSee()`, сообщение — «Действие недоступно».

- **`{slug}/action`** — проверяется найденное по `key` действие, в том числе элемент `DropDown`, если у пользователя нет права самого `DropDown` (тогда в `message` — право `DropDown`). Если под одним `key` объявлено несколько действий, берётся то, которое пользователю доступно.
- **`{screen}/runMethod`** — если метод экрана вызывает `Button` или `ModalAction` (любое действие с `->method($name)`) из `commandBar()` или `layout()` экрана, метод выполняется, только когда хотя бы одно из таких действий доступно пользователю. Метод, который не назван ни в одном действии, по-прежнему защищён только `permission()` экрана (и собственными проверками метода).
- **`delayed/run`** — если обработчик `entity::method` запускается `AsyncAction`'ом из `actions()` какого-либо ресурса или из `commandBar()` какого-либо экрана, запуск разрешён, только когда хотя бы одно такое действие доступно пользователю.

---

## Асинхронные действия: `AsyncAction`

```php
AsyncAction::make('Пересчитать статистику')
    ->handler(StatsBuilder::class, 'rebuild')
    ->withParams(['scope' => 'all'])
    ->pollInterval(3)
    ->permission('admin.stats.rebuild');
```

Обработчик должен быть разрешён в `AllowlistRegistrar` (обычно в `boot()` сервис-провайдера или плагина):

```php
app(AllowlistRegistrar::class)->allow(StatsBuilder::class, 'rebuild', 'admin.stats.rebuild');
```

Третий аргумент — необязательное право (строка или список; нужны все), которое требуется от любого, кто запускает обработчик. Без регистрации в allowlist запустить обработчик нельзя.

SPA запускает процесс и следит за ним:

```http
POST /api/admin/delayed/run
{ "entity": "App\\Admin\\StatsBuilder", "method": "rebuild", "params": { "scope": "all" } }
```

```json
{ "success": true, "payload": { "uuid": "01J...", "status": "new" } }
```

```http
GET /api/admin/delayed/status?uuid=01J...
```

Если `AsyncAction` стоит в строке или в bulk-панели, SPA добавляет в `params` ключи выбранных записей как `ids` — обработчик должен принимать аргумент `ids`.

Проверки `delayed/run` по порядку:

| HTTP | `errorKey` | Когда |
|---|---|---|
| 422 | — | нет `entity`/`method`, `params` не массив, `callback` не URL |
| 403 | `forbidden` | пары `entity::method` нет в allowlist |
| 403 | `action_forbidden` | нет права, с которым пара разрешена в allowlist |
| 403 | `action_forbidden` | пару запускают только недоступные пользователю `AsyncAction` (см. выше) |
| 500 | `delayed_run_failed` | процесс не удалось создать |

Полное описание `delayed/run` и `delayed/status` — в [delayed.md](delayed.md).
