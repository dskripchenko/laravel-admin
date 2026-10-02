# API: Screens

Произвольные экраны вне CRUD: формы, мастера, отчёты, служебные страницы. Каждый зарегистрированный `Screen` — отдельный ключ контроллера со slug = `Screen::slug()`.

> Конвенции — [conventions.md](conventions.md). Если экран — это CRUD над моделью, лучше Resource (см. [resources.md](resources.md)). Регистрация — [registration.md](registration.md). Действия и их права — [actions.md](actions.md).

URL: `/api/admin/{screen_slug}/{action}`.

---

## Регистрация

`ScreenCompiler` (`src/Screen/ScreenCompiler.php`) проходит по `ScreenRegistry` текущей панели и для каждого экрана добавляет запись с общим контроллером `ScreenController`:

```php
'customers-import' => [
    'controller' => ScreenController::class,
    'actions' => [
        'state'     => ['method' => ['get'],  'middleware' => $middleware],
        'runMethod' => ['method' => ['post'], 'middleware' => $middleware],
        'listener'  => ['method' => ['post'], 'middleware' => $middleware],
    ],
],
```

- **Slug** — имя класса без суффикса `Screen` в kebab-case: `CustomersImportScreen` → `customers-import`.
- **Права.** Если `Screen::permission()` возвращает строку или список, на все три action ставится `AdminAccess` с этими правами (список — нужны все). `null` — достаточно аутентификации. Нет права — `403`, `errorKey: forbidden`.
- **Исключения.** Наследники `GeneratedScreen` обслуживает `ResourceController` (`listScreen`, `editScreen`, … — см. [resources.md](resources.md)), наследники `DashboardScreen` — контроллер `dashboard` (см. [dashboards.md](dashboards.md)). Для них отдельный ключ не регистрируется.
- Экран, которого нет в реестре, — `404`, `errorKey: screen_not_registered`.

---

## `{screen}.state` (GET)

```php
/**
 * Снимок экрана: state, layout, command bar и meta.
 *
 * Принимает произвольные query-параметры и передаёт их в Screen::query()
 * — белого списка нет, экран проверяет их сам.
 *
 * @output object $payload
 *
 * @security AdminSession
 *
 * @response 200 {ScreenStateResponse}
 */
public function state(Request $request): JsonResponse;
```

Query-параметры, кроме начинающихся с `_`, передаются в `Screen::query()` позиционно, в порядке следования.

| Ключ `payload` | Что внутри |
|---|---|
| `state` | результат `Screen::query()` |
| `name` | `Screen::name()` (по умолчанию — имя класса) |
| `description` | `Screen::description()` или `null` |
| `layout` | layout экрана; невидимые layout'ы (`canSee()`) и недоступные действия внутри них не сериализуются |
| `command_bar` | `Screen::commandBar()` без действий, недоступных пользователю (`permission()` или `canSee()`) |
| `permissions` | права экрана из `permission()`, списком |
| `etag` | хеш `state`, `name`, `description` и `permissions` |

`Layout\Listener` внутри layout'а получает первую отрисовку по стартовому состоянию прямо в этом ответе.

---

## `{screen}.runMethod` (POST)

```php
/**
 * Вызывает один из командных методов экрана.
 *
 * @input string $method
 * @input object ?$payload
 * @input array ?$parameters
 *
 * @output object $payload
 *
 * @security AdminSession
 *
 * @response 200 {ScreenMethodResponse}
 * @response 403 {ForbiddenErrorResponse} Действие, вызывающее метод, требует права, которого у пользователя нет.
 * @response 422 {ValidationErrorResponse}
 */
public function runMethod(Request $request): JsonResponse;
```

### Тело

| Параметр | Описание |
|---|---|
| `method` | имя метода экрана (обязателен) |
| `payload` | состояние формы; передаётся методу единственным аргументом |
| `parameters` | массив позиционных аргументов; если передан, используется вместо `payload` |

Если методу нужен ровно один аргумент, а не пришло ни `payload`, ни `parameters`, ему передаётся пустой массив.

### Какой метод можно вызвать

Метод должен быть публичным, нестатическим и не входить в зарезервированные: `query`, `layout`, `name`, `description`, `permission`, `commandBar`, `compile`, `slug`, `reservedMethods`, `isCallableMethod`.

**Права действия.** Если метод вызывает `Button` или `ModalAction` (действие с `->method($name)`) из `commandBar()` или `layout()` экрана, метод выполняется, только когда хотя бы одно такое действие доступно пользователю — с его `permission()` и `canSee()`, а также с `canSee()` layout'а, в котором оно стоит. Иначе — `403`, `errorKey: action_forbidden`. Метод, который не назван ни в одном действии, защищён только `permission()` экрана и собственными проверками.

### Ответ

Метод может вернуть:

- `JsonResponse` — отдаётся как есть;
- массив — нормализуется (см. ниже);
- `null`/ничего — нормализуется пустой массив.

| Ключ `payload` | Описание |
|---|---|
| `state` | новое состояние (объект, по умолчанию пустой) |
| `layouts` | обновлённые layout'ы (объект, по умолчанию пустой) |
| `alerts` | список уведомлений-тостов; `level`/`variant` в элементе превращаются в `type` (по умолчанию `info`), `message` и `title` переводятся |
| `redirect_url` | куда перейти, или `null` |
| `refresh` | перезагрузить экран |
| `download_url` | файл для скачивания, или `null` |
| `message` | текст баннера (пустая строка — баннера нет) |
| `message_link` | `{url, label}` или `null`. Метод может вернуть `['url' => …, 'label' => …]`, `['href' => …, 'text' => …]`, пару `[$url, $label]` или просто строку-URL; без подписи ставится «Открыть», ссылка без URL отбрасывается; подпись переводится |
| `extra` | все остальные ключи, которые вернул метод (только если они есть) |

### Ошибки

| HTTP | `errorKey` | Когда |
|---|---|---|
| 400 | `screen_method_missing` | не передан `method` |
| 404 | `screen_method_not_callable` | метода нет, он не публичный, статический или зарезервированный |
| 403 | `action_forbidden` | метод вызывает только недоступные пользователю действия |
| 422 | `screen_method_arguments_missing` | метод требует больше аргументов, чем передано |
| 422 | `action_failed` | метод бросил `ActionFailedException`; `message` — её текст |

### Пример

```php
final class CustomersImportScreen extends Screen
{
    public function permission(): string { return 'admin.customers.import'; }

    public function commandBar(): array
    {
        return [Button::make('Запустить')->method('run')->permission('admin.customers.import.run')];
    }

    public function run(array $state): array
    {
        // ...
        return ['message' => 'Импорт запущен', 'refresh' => true];
    }
    // query(), layout() ...
}
```

```http
POST /api/admin/customers-import/runMethod
{ "method": "run", "payload": { "skip_errors": true } }
```

Пользователь с `admin.customers.import`, но без `admin.customers.import.run` не увидит кнопку в `command_bar`, а прямой вызов `run` получит `403 action_forbidden`.

---

## `{screen}.listener` (POST)

Перерисовывает один из `Layout\Listener` экрана по состоянию формы. Слушатель ищется по id только среди объявленных в `layout()` экрана.

```http
POST /api/admin/customers-import/listener
{ "listener": "listener-1a2b3c4d5e6f", "state": { "type": "csv" } }
```

Ответ `200 {ListenerResponse}`: `{listener, state, layouts}` — обработчик слушателя (если есть) возвращает патч состояния, дочерние элементы отрисовываются по пропатченному состоянию.

| HTTP | `errorKey` | Когда |
|---|---|---|
| 422 | `validation` | нет `listener` или `state` не объект |
| 404 | `listener_not_found` | слушателя с таким id в layout'е нет |
| 404 | `listener_handler_not_callable` | обработчик — не публичный метод экрана |

---

## Дашборды

`DashboardScreen` — тоже экран, но через этот контроллер он не обслуживается: данные дашбордов отдаются в манифесте и через контроллер `dashboard`. См. [dashboards.md](dashboards.md).
