# API: Delayed processes

Контроллер `delayed` (`Dskripchenko\LaravelAdmin\DelayedProcess\DelayedProcessController`) — запуск фоновых обработчиков через `dskripchenko/laravel-delayed-process` и опрос их состояния. Через него работают асинхронные действия (`Action\AsyncAction`): SPA (`useActionRunner`) вызывает `delayed/run`, получает `uuid` и опрашивает `delayed/status` каждые `pollInterval` секунд (по умолчанию 2), пока статус не станет конечным.

> Конвенции — [conventions.md](conventions.md). Объявление async-действий — [actions.md](actions.md).

URL: `/api/admin/delayed/{action}`. Оба action требуют аутентификации (`AdminAuth`, см. [system.md](system.md)).

---

## Регистрация в `AdminApi::getMethods()`

```php
'delayed' => [
    'controller' => \Dskripchenko\LaravelAdmin\DelayedProcess\DelayedProcessController::class,
    'actions' => [
        'run'    => ['method' => ['post']],
        'status' => ['method' => ['get']],
    ],
],
```

Других actions (отмены, списка процессов, пакетного статуса) нет.

---

## Allowlist обработчиков — `AllowlistRegistrar`

`delayed/run` запускает только явно разрешённые пары «класс::метод». Без этого SPA могла бы вызвать любой класс приложения. Пара регистрируется в `boot()` сервис-провайдера или плагина:

```php
use Dskripchenko\LaravelAdmin\DelayedProcess\AllowlistRegistrar;

app(AllowlistRegistrar::class)->allow(ReportBuilder::class, 'build');

// С правом: запустить обработчик сможет только пользователь, у которого оно есть.
app(AllowlistRegistrar::class)->allow(ReportBuilder::class, 'build', 'admin.reports.build');

// Несколько прав — нужны все.
app(AllowlistRegistrar::class)->allow(ReportBuilder::class, 'rebuild', ['admin.reports.build', 'admin.reports.delete']);
```

Сигнатура: `allow(string $entity, string $method, array|string|null $permission = null): void`.

- `$entity` должен существовать, иначе — `InvalidArgumentException`.
- `$permission` — ключ или список ключей; все они требуются от того, кто запускает обработчик. Повторный `allow()` той же пары добавляет права к уже зарегистрированным.
- Класс автоматически добавляется в `delayed-process.allowed_entities`, который проверяет сама фабрика процессов.

`AllowlistRegistrar` — singleton; кроме `allow()` у него есть `isAllowed($entity, $method)`, `permissionsFor($entity, $method)`, `all()` и `clear()`.

---

## `delayed.run`

`POST /api/admin/delayed/run`

| Параметр | Правила |
|---|---|
| `entity` | `required`, `string` — FQCN обработчика |
| `method` | `required`, `string` — метод обработчика |
| `params` | `nullable`, `array` — аргументы метода; передаются как `...$params`, то есть строковые ключи становятся именованными аргументами |
| `callback` | `nullable`, `url` — сохраняется в `callback_url` процесса |

SPA собирает `params` из `AsyncAction::withParams()` и для действий над строками/выделением добавляет `ids` — ключи выбранных записей, так что обработчик должен принимать аргумент `ids`.

### Проверки, по порядку

1. Валидация — `422 validation`.
2. Пара `entity::method` не зарегистрирована в `AllowlistRegistrar` — `403`:
   ```json
   { "success": false, "payload": { "errorKey": "forbidden", "message": "This async handler is not allowlisted" } }
   ```
3. У пользователя нет одного из прав, переданных в `allow()` — `403 action_forbidden`, в `message` — первое недостающее право («Доступ запрещён: admin.reports.build»).
4. Права объявленных действий. Сервер собирает действия из `actions()` всех зарегистрированных ресурсов и из `commandBar()` всех кастомных экранов (без `GeneratedScreen` и `DashboardScreen`; экран, чей `commandBar()` бросает исключение вне его страницы, пропускается) и ищет среди них `AsyncAction`, запускающие эту пару (`->handler($entity, $method)`). Если такие есть, пользователь должен иметь возможность запустить хотя бы одно из них: проходит `canSee()`, есть право из `permission()`, и видимы все обёртки — `DropDown`, в котором лежит действие, и layout'ы вокруг. Иначе — `403 action_forbidden`: `message` называет право, которого не хватило («Доступ запрещён: …»), или, если права есть, но действие скрыто условием `canSee()` либо невидимым layout'ом, — «Действие недоступно».

   Если ни одно действие эту пару не запускает, шаг 4 ничего не требует — тогда защищайте обработчик правом в `allow()` (так же стоит поступить для экранов, чей `commandBar()` нельзя построить вне страницы).
5. Создание процесса фабрикой `ProcessFactoryInterface::make()`. Исключение фабрики — `500`:
   ```json
   { "success": false, "payload": { "errorKey": "delayed_run_failed", "message": "<текст исключения>" } }
   ```

Ответ `200`:

```json
{ "success": true, "payload": { "uuid": "9b1d...", "status": "new" } }
```

---

## `delayed.status`

`GET /api/admin/delayed/status?uuid=...`

| Параметр | Правила |
|---|---|
| `uuid` | `required`, `string` |

Ответ `200`:

| Ключ | Описание |
|---|---|
| `uuid` | uuid процесса |
| `status` | `new` \| `wait` \| `done` \| `error` \| `expired` \| `cancelled` (enum `ProcessStatus` пакета delayed-process) |
| `progress` | прогресс, 0–100 |
| `attempts` | число попыток |
| `started_at` | ISO-8601 или `null` |
| `duration_ms` | длительность или `null` |
| `data` | результат обработчика |
| `error` | текст ошибки (`error_message`) или `null` |

Ошибки: `422 validation`; `404 not_found` — процесса с таким `uuid` нет.

Процесс ищется только по `uuid`, без проверки, кто его запустил, и без проверки прав.

SPA считает статусы `done`, `error`, `expired`, `cancelled` конечными; при `done` показывает `data.message` (если обработчик его вернул) и обновляет данные экрана, при остальных — сообщение об ошибке.
