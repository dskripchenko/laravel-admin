# API: Screens

Кастомные Screen-страницы вне Resource'ов (dashboards, мастера, отчёты, settings, любые page'и с собственным lifecycle). Каждый зарегистрированный Screen — отдельный controller со slug = `Str::kebab(class_basename(ScreenClass))`.

> Конвенции — [conventions.md](conventions.md). Если Screen реализует CRUD — лучше Resource (см. [resources.md](resources.md)). Регистрация — [registration.md](registration.md).

URL: `api/admin/{screen_slug}/{action}`. Контроллер у всех Screen'ов один (compiled), actions фиксированы.

---

## CompiledScreenController

### Регистрация (динамически)

`ScreenCompiler` для каждого зарегистрированного Screen регистрирует controller в `getMethods()`:

```php
'dashboard' => [                               // slug = Str::kebab('DashboardScreen') без 'Screen' suffix
    'controller' => CompiledScreenController::class,
    'middleware' => [AdminAuth::class],
    'actions' => [
        'state'     => ['method' => ['get']],
        'runMethod' => ['method' => ['post']],
        'async'     => ['method' => ['get']],
    ],
],
```

DI Screen'а в controller-конструктор — через runtime-binding по slug'у.

---

## Действия

### `dashboard.state`

```php
/**
 * Получить state Screen'а — результат query() + layout + commandBar.
 *
 * @input object ?$param Route-параметры Screen'а (если Screen зарегистрирован
 *               с параметрами, например {user_id}, передаются здесь).
 *
 * @output object $payload
 * @output object $payload.state Результат Screen::query().
 * @output string $payload.name Screen::name().
 * @output string ?$payload.description
 * @output array  $payload.layout Dehydrated layout с сериализацией values.
 * @output array  $payload.command_bar Список ActionSchema.
 * @output array  $payload.permissions Применимые permissions.
 * @output string $payload.etag
 *
 * @header string ?$If-None-Match
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ScreenStateResponse}
 * @response 304 {NotModifiedResponse}
 * @response 404 {NotFoundErrorResponse} Screen не зарегистрирован.
 * @response 403 {ForbiddenErrorResponse} Screen::permission().
 */
public function state(Request $request): JsonResponse;
```

### `dashboard.runMethod`

```php
/**
 * Вызвать command-метод Screen'а (по аналогии с Button::method('save')).
 *
 * @input string $method Имя метода Screen.
 * @input object ?$state Текущее состояние формы.
 * @input object ?$parameters Дополнительные args метода.
 * @input object ?$param Route-параметры (для контекста).
 *
 * @header string ?$X-Idempotency-Key
 *
 * @output object $payload Синхронный ответ.
 * @output object $payload.state Новое состояние.
 * @output object ?$payload.layouts Map id → schema; обновившиеся слои.
 * @output array  $payload.alerts Toast-flashes.
 * @output string $payload.alerts[].type info|success|warning|danger.
 * @output string $payload.alerts[].message
 * @output integer ?$payload.alerts[].duration_ms
 * @output string ?$payload.redirect_url
 * @output boolean $payload.refresh Полная перезагрузка query()+layout().
 * @output string ?$payload.download_url
 * @output string $payload.message
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ScreenMethodResponse}
 * @response 202 {DelayedResponse}
 * @response 404 {NotFoundErrorResponse} Метод не существует или зарезервирован.
 * @response 405 {MethodNotAllowedResponse} Метод не публичный.
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse} Screen::permission() или Action::permission().
 */
public function runMethod(Request $request): JsonResponse;
```

Events: `Admin\Events\ScreenMethodCalled` (audit с Screen FQCN + method).

**Пример (Screen «Импорт клиентов» с методом `run`):**

```http
POST /api/admin/customers-import/runMethod
{
  "method": "run",
  "state": {
    "file_id": "uuid-of-uploaded-file",
    "mapping": { "0": "name", "1": "email" },
    "skip_errors": true
  }
}
```

```json
{
  "success": true,
  "payload": {
    "delayed": { "uuid": "01...", "status": "new", "progress": 0, "message": "Импорт запущен" }
  }
}
```

### `dashboard.async`

```php
/**
 * Reactive layout reload — частичная перезагрузка слоя в Screen
 * (когда Screen реализует поле/слой с ->reactive() или Listener-стиль).
 *
 * @input string $method Имя async-метода Screen'а.
 * @input object ?$context Значения reactive-зависимостей.
 *
 * @output object $payload
 * @output object $payload.layouts Map id → новая LayoutSchema.
 * @output object ?$payload.state_patch Частичный merge state.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ScreenAsyncResponse}
 * @response 404 {NotFoundErrorResponse} Метод не существует или не async.
 * @response 403 {ForbiddenErrorResponse}
 */
public function async(Request $request): JsonResponse;
```

---

## Wizard Screen

Screen с `Layout::wizard` использует те же actions:

- Навигация между шагами (next/prev) — клиентская либо вызовы `runMethod` с `method: 'next'/'prev'`.
- Финальный submit — `runMethod` с `method: 'finish'` и aggregated state всех шагов.
- Per-step validation — на сервере применяется `Step::validate(state)` внутри `runMethod`.

---

## Dashboards как Screens

Простой dashboard может быть зарегистрирован двумя способами:

1. **Как Dashboard-page** через `Dashboard::class extends Dashboard` — отдельные actions, см. [dashboards.md](dashboards.md).
2. **Как Screen** для одноразовых дашбордов с произвольным layout (`Layout::metrics`/`Layout::chart`/`Layout::view`) — использует actions этого файла.

Решение принимает разработчик: «полноценный custom-layout per-user» → Dashboard, «фиксированная страница» → Screen.
