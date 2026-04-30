# API: Search

Глобальный поиск (cmd+K) поверх всех Resource'ов. Реализуется в sister-pack `dskripchenko/laravel-admin-search`. Контракт фиксируется здесь, чтобы admin core знал endpoint и форматы.

> Полная спецификация sister-pack'а — [../sister-packs/search.md](../sister-packs/search.md). Регистрация — [registration.md](registration.md).

URL: `api/admin/search/{action}`.

---

## SearchController (sister-pack)

### Регистрация (в sister-pack'е)

```php
'search' => [
    'controller' => SearchController::class,
    'middleware' => [AdminAuth::class, ThrottleRequests::class . ':30,1'],
    'actions' => [
        'global' => ['method' => ['get']],
    ],
],
```

---

## Действия

### `search.global`

```php
/**
 * Глобальный поиск по всем доступным Resource'ам.
 * Состав выдачи фильтруется по permission'ам <resource>.view.
 *
 * @input string $q Поисковый запрос (минимум 2 символа, configurable).
 * @input integer ?$per_resource Макс результатов на Resource (default 10).
 * @input array ?$resources Ограничить поиск конкретными Resource-slug'ами.
 * @input string ?$resources[]
 *
 * @output object $payload
 * @output string $payload.query
 * @output array  $payload.groups Список SearchGroup.
 * @output string $payload.groups[].resource Slug.
 * @output string $payload.groups[].label Локализованная метка Resource.
 * @output string ?$payload.groups[].icon
 * @output integer $payload.groups[].count Всего найдено в этом Resource.
 * @output boolean $payload.groups[].has_more Больше чем per_resource.
 * @output string ?$payload.groups[].more_url ListScreen с pre-applied ?q=.
 * @output array  $payload.groups[].items Список SearchItem.
 * @output mixed  $payload.groups[].items[].id
 * @output string $payload.groups[].items[].title
 * @output string ?$payload.groups[].items[].subtitle
 * @output string ?$payload.groups[].items[].icon
 * @output string $payload.groups[].items[].url
 * @output object $payload.groups[].items[].meta
 * @output number ?$payload.groups[].items[].score Только для Scout-driver.
 * @output integer $payload.total
 * @output integer $payload.elapsed_ms
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SearchResponse}
 * @response 422 {ValidationErrorResponse} Слишком короткий запрос.
 * @response 429 {ThrottledResponse}
 * @response 503 {SearchUnavailableResponse} Scout engine не отвечает.
 */
public function global(Request $request): JsonResponse;
```

---

## Driver

Резолвится из `config/admin-search.php`:

- `eloquent` — `LIKE %q%` по `Resource::searchableFields()`. Без зависимостей, default.
- `scout` — `Resource\\Model::search($q)`. Требует `laravel/scout` + engine (Algolia/Meilisearch/Database/TNTSearch).
- `custom` — реализуй `SearchDriver` контракт и зарегистрируй через `Admin::searchDriver(MyDriver::class)`.

Клиент об этом не знает — единый формат ответа.
