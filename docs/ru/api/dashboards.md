# API: Dashboards и Widgets

Dashboard'ы как первоклассный page-тип + Widget'ы как самостоятельные блоки.

> Концепции — ARCHITECTURE.md п.5.17. Конвенции — [conventions.md](conventions.md). Регистрация — [registration.md](registration.md).

URL: `api/admin/dashboards/{action}` — общий controller для всех Dashboard'ов. Slug конкретного dashboard'а передаётся параметром.

---

## DashboardsController

### Регистрация

```php
'dashboards' => [
    'controller' => DashboardsController::class,
    'middleware' => [AdminAuth::class],
    'actions' => [
        'list'         => ['method' => ['get']],
        'show'         => ['method' => ['get']],
        'widgetData'   => ['method' => ['get']],
        'saveLayout'   => ['method' => ['post']],
        'resetLayout'  => ['method' => ['post']],
        'duplicate'    => ['method' => ['post']],
    ],
],
```

---

## Действия

### `dashboards.list`

```php
/**
 * Список всех dashboard'ов, доступных текущему пользователю.
 *
 * @output object $payload
 * @output array  $payload.data Список DashboardSummary.
 * @output string $payload.data[].slug
 * @output string $payload.data[].title
 * @output string ?$payload.data[].description
 * @output string ?$payload.data[].icon
 * @output string $payload.data[].url
 * @output string ?$payload.data[].permission
 * @output boolean $payload.data[].is_customizable
 * @output string ?$payload.default Slug дефолтного dashboard юзера.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {DashboardsListResponse}
 */
public function list(Request $request): JsonResponse;
```

### `dashboards.show`

```php
/**
 * Получить конкретный dashboard со списком widget'ов и layout.
 *
 * @input string $slug Slug dashboard'а.
 * @input string ?$layout default|user (default 'user' если custom есть, иначе 'default').
 *
 * @output object $payload
 * @output object $payload.dashboard
 * @output string $payload.dashboard.slug
 * @output string $payload.dashboard.title
 * @output string ?$payload.dashboard.description
 * @output boolean $payload.dashboard.is_customizable
 * @output array  $payload.widgets Список WidgetInstance.
 * @output string $payload.widgets[].id
 * @output string $payload.widgets[].type stats_overview|chart|recent_list|table|...
 * @output string $payload.widgets[].label
 * @output string ?$payload.widgets[].description
 * @output string $payload.widgets[].url URL для widgetData action'а.
 * @output string ?$payload.widgets[].poll Например '30s'.
 * @output string ?$payload.widgets[].permission
 * @output mixed  $payload.widgets[].initial_data Первый snapshot.
 * @output object $payload.widgets[].options Type-specific.
 * @output array  $payload.layout Список WidgetLayoutItem.
 * @output string $payload.layout[].widget_id
 * @output integer $payload.layout[].x
 * @output integer $payload.layout[].y
 * @output integer $payload.layout[].w
 * @output integer $payload.layout[].h
 * @output string(date-time) ?$payload.user_layout_saved_at
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {DashboardShowResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse}
 */
public function show(Request $request): JsonResponse;
```

### `dashboards.widgetData`

```php
/**
 * Получить данные widget'а — для polling и manual refresh.
 *
 * @input string $slug Dashboard slug.
 * @input string $widget Widget id.
 * @input string ?$range Type/диапазон (зависит от widget; например '7d' / '24h').
 *
 * @output object $payload
 * @output mixed  $payload.data Type-specific.
 * @output string(date-time) $payload.fetched_at
 * @output string(date-time) ?$payload.next_refresh_at Если poll включён.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {WidgetDataResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse}
 */
public function widgetData(Request $request): JsonResponse;
```

**Примеры payload по типу widget'а:**

`stats_overview`:

```json
{
  "data": {
    "items": [
      { "label": "Всего пользователей", "value": 1234, "delta": "+12%", "trend": "up" },
      { "label": "Активных за 7д",      "value": 567,  "delta": "−2%",  "trend": "down" }
    ]
  }
}
```

`chart`:

```json
{
  "data": {
    "type": "line",
    "labels": ["2026-04-01", "2026-04-02"],
    "datasets": [
      { "label": "Заказы", "data": [12, 18] }
    ]
  }
}
```

### `dashboards.saveLayout`

```php
/**
 * Сохранить custom-layout пользователя.
 *
 * @input string $slug Dashboard slug.
 * @input array  $layout Список WidgetLayoutItem.
 * @input string $layout[].widget_id
 * @input integer $layout[].x
 * @input integer $layout[].y
 * @input integer $layout[].w
 * @input integer $layout[].h
 * @input array  ?$hidden_widgets Список widget id, скрытых пользователем.
 *
 * @output object $payload
 * @output string(date-time) $payload.saved_at
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {LayoutSavedResponse}
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse} Dashboard не is_customizable либо нет permission.
 */
public function saveLayout(Request $request): JsonResponse;
```

### `dashboards.resetLayout`

```php
/**
 * Сбросить пользовательский layout, вернуть к дефолтному из кода.
 *
 * @input string $slug
 *
 * @output null $payload
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SuccessResponse}
 */
public function resetLayout(Request $request): JsonResponse;
```

### `dashboards.duplicate`

```php
/**
 * Создать личную копию dashboard'а с независимым редактированием.
 *
 * @input string $slug Source slug.
 * @input string $name Новое имя.
 *
 * @output object $payload
 * @output object $payload.dashboard
 * @output string $payload.dashboard.slug Новый, формат "{original}--{user-suffix}".
 * @output string $payload.dashboard.title
 * @output string $payload.redirect_url
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 201 {DashboardCreatedResponse}
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse}
 */
public function duplicate(Request $request): JsonResponse;
```
