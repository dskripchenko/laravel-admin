# API: Dashboards и Widgets

Дашборд — наследник `Widget\DashboardScreen`, который объявляет свои виджеты в `widgets()`. Он регистрируется как обычный экран (в `ScreenRegistry`), но через `ScreenController` не обслуживается: описание дашбордов приходит в манифесте, а пользовательская раскладка и пересчёт виджетов — через контроллер `dashboard`.

> Конвенции — [conventions.md](conventions.md). Регистрация — [registration.md](registration.md). Манифест — [system.md](system.md).

---

## Правила доступа

Одни и те же правила действуют в манифесте и во всех actions `dashboard`:

1. Дашборд открыт только пользователю, у которого есть все права из `DashboardScreen::permission()` (`null` — любому аутентифицированному).
2. Виджеты — объявленные в `widgets()` плюс зарегистрированные плагинами через `$admin->widgets([...])`, без дублей по slug.
3. Виджет, который пользователь видеть не должен (`Widget::permission()` или `canSee()`), отбрасывается до вызова его `data()`.
4. Сохранённая пользователем раскладка не может вернуть отброшенный виджет.
5. Каждый виджет считается в контексте дашборда — с выбранным периодом.

---

## Описание в манифесте

Раздел `dashboards` манифеста (`system.manifest`) — список дашбордов текущей панели, которые пользователь может открыть. Недоступный дашборд в список не попадает.

```json
{
  "slug": "sales",
  "label": "Продажи",
  "description": null,
  "permission": "admin.dashboards.sales",
  "periods": ["7d", "30d", "90d", "all"],
  "period": "30d",
  "widgets": [
    {
      "kind": "widget",
      "slug": "revenue",
      "type": "chart",
      "title": "Выручка",
      "size": 6,
      "rowSpan": 1,
      "refresh": null,
      "permission": null,
      "data": { }
    }
  ]
}
```

| Ключ | Описание |
|---|---|
| `slug` | ключ дашборда в реестре экранов (`Screen::slug()`: имя класса без `Screen` в kebab-case); он же `key` в actions ниже |
| `label`, `description` | `name()` (или slug) и `description()` |
| `permission` | `DashboardScreen::permission()` |
| `periods` | периоды переключателя. Если `periods()` возвращает `null` (по умолчанию) — `7d`, `30d`, `90d`, `all`, но только когда хоть один виджет зависит от периода или экран читает `period()`; иначе пустой список. Пустой массив из `periods()` скрывает переключатель |
| `period` | `defaultPeriod()`, по умолчанию `30d` |
| `widgets` | `Widget::toArray()` видимых виджетов, с уже посчитанным `data` |

`type` виджета — `stats`, `chart`, `table`, `recent_list`, `gauge`, `heatmap`, `markdown`, `iframe` или тип собственного виджета; фронтенд выбирает компонент по нему.

---

## Контроллер `dashboard`

Регистрируется статически в `AdminApi::getMethods()`:

```php
'dashboard' => [
    'controller' => DashboardController::class,
    'actions' => [
        'get'        => ['method' => ['get']],
        'save'       => ['method' => ['post']],
        'savePeriod' => ['method' => ['post']],
        'reset'      => ['method' => ['post']],
        'widgets'    => ['method' => ['get']],
    ],
],
```

URL: `/api/admin/dashboard/{action}`. Дашборд указывается параметром `key`.

Общая проверка `key` во всех actions:

- `key` — зарегистрированный `DashboardScreen` другой панели → `404`, `errorKey: unknown_dashboard`;
- у пользователя нет прав дашборда → `403`, `errorKey: forbidden`;
- `key` не соответствует ни одному `DashboardScreen` — `get`, `save`, `savePeriod` и `reset` работают с раскладкой по этому ключу без фильтрации виджетов (так хост может хранить раскладку собственной страницы-дашборда), а `widgets` отвечает `404 unknown_dashboard`.

Раскладки хранятся в таблице `admin_dashboard_layouts` — одна строка на пару (`dashboard_key`, владелец).

### `dashboard.get` (GET)

```php
/**
 * Сохранённая раскладка текущего пользователя или null — тогда SPA берёт раскладку по умолчанию.
 *
 * @input string $key
 *
 * @output object $payload
 *
 * @security AdminSession
 *
 * @response 200 {DashboardLayoutResponse}
 * @response 403 {ForbiddenErrorResponse}
 * @response 404 {NotFoundErrorResponse}
 */
public function get(Request $request, ScreenRegistry $screens): JsonResponse;
```

Ответ: `{layout, period}`. `layout` — список элементов раскладки (см. `save`) без виджетов, которые пользователю больше не видны, или `null`; `period` — сохранённый период или `null`.

### `dashboard.save` (POST)

```php
/**
 * Сохраняет раскладку текущего пользователя.
 *
 * @input string $key
 * @input array $widgets
 * @input string $widgets[].slug
 * @input integer ?$widgets[].size Колонки, 1..12
 * @input integer ?$widgets[].position
 * @input boolean ?$widgets[].hidden
 * @input string ?$widgets[].type Нужен виджетам, добавленным пользователем
 * @input object ?$widgets[].config Собственные настройки виджета
 *
 * @output object $payload
 *
 * @security AdminSession
 *
 * @response 200 {DashboardLayoutSavedResponse}
 * @response 403 {ForbiddenErrorResponse}
 * @response 404 {NotFoundErrorResponse}
 */
public function save(Request $request, ScreenRegistry $screens): JsonResponse;
```

```http
POST /api/admin/dashboard/save
{
  "key": "sales",
  "widgets": [
    { "slug": "revenue", "size": 12, "position": 0 },
    { "slug": "orders", "hidden": true, "position": 1 },
    { "slug": "note-1", "type": "markdown", "position": 2, "config": { "content": "..." } }
  ]
}
```

- Элементы, ссылающиеся на виджет, который пользователь видеть не может, при сохранении отбрасываются. Элементы с собственным ключом (виджеты, добавленные пользователем в SPA) сохраняются.
- `config` для объявленных виджетов работает как переопределение (например, другой заголовок), для пользовательских — как их данные.
- Ответ: `{id, widgets}`. Без пользователя — `401` (`unauthenticated`).

### `dashboard.savePeriod` (POST)

Сохраняет выбранный период, не трогая раскладку.

| Параметр | Описание |
|---|---|
| `key` | дашборд |
| `period` | `all` или число дней с `d` (`7d`, `30d`, …); если у дашборда задан `periods()`, допустимы только значения из него |

Недопустимый период — `422` с ошибкой у поля `period`. Ответ: `{period}`.

### `dashboard.widgets` (GET)

```php
/**
 * Свежие данные виджетов с применённым периодом. Фронтенд вызывает его при смене периода,
 * чтобы пересчитать виджеты без перезагрузки манифеста.
 *
 * @input string $key
 * @input string ?$period `all` или число дней вроде 7d; иначе — период дашборда по умолчанию
 *
 * @output object $payload
 *
 * @security AdminSession
 *
 * @response 200 {DashboardWidgetsResponse}
 * @response 403 {ForbiddenErrorResponse}
 * @response 404 {NotFoundErrorResponse}
 */
public function widgets(Request $request, ScreenRegistry $screens): JsonResponse;
```

Ответ: `{widgets, period}` — `widgets` в том же формате, что в манифесте. Недопустимый период заменяется периодом по умолчанию.

### `dashboard.reset` (POST)

Удаляет сохранённую раскладку (и период) текущего пользователя для `key` — возвращается раскладка по умолчанию. Ответ: `{key}`. Без пользователя — `401` (`unauthenticated`).
