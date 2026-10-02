# API: Search

Глобальный поиск по ресурсам панели (палитра ⌘K) встроен в ядро: это action `search` контроллера `system`, реализация — `Dskripchenko\LaravelAdmin\Support\GlobalSearch`. Отдельного контроллера `search` в ядре нет.

> Конвенции — [conventions.md](conventions.md). Остальные actions `system` — [system.md](system.md).

---

## `system.search`

`GET /api/admin/system/search?q=...` — требует аутентификации (`AdminAuth`); отдельного права на сам action нет, права проверяются по каждому ресурсу.

Регистрация: `'search' => ['method' => ['get']]` в контроллере `system` (`AdminApi::getMethods()`).

| Параметр | Тип | Описание |
|---|---|---|
| `q` | string, необязательный | строка поиска |

Других параметров нет. Если `q` после `trim` короче 2 символов, поиск не выполняется и `groups` пустой.

Ответ `200` (`{GlobalSearchResponse}`):

```json
{
  "success": true,
  "payload": {
    "query": "ivan",
    "groups": [
      {
        "slug": "users",
        "label": "Пользователи",
        "icon": "user",
        "items": [
          { "id": 42, "title": "Иван Иванов", "subtitle": "ivan@example.com", "url": "/r/users/42" }
        ],
        "hasMore": false,
        "moreUrl": "/r/users"
      }
    ]
  }
}
```

| Ключ | Описание |
|---|---|
| `query` | строка запроса как пришла |
| `groups[].slug` | slug ресурса |
| `groups[].label` | `Resource::label()` |
| `groups[].icon` | `Resource::$icon` или `null` |
| `groups[].items[].id` | первичный ключ записи |
| `groups[].items[].title` | `Resource::recordTitle($row)` |
| `groups[].items[].subtitle` | `Resource::recordSubtitle($row)` или `null` |
| `groups[].items[].url` | путь SPA `/r/{slug}/{id}` |
| `groups[].hasMore` | найдено больше записей, чем показано |
| `groups[].moreUrl` | путь списка ресурса `/r/{slug}` |

---

## Как ищет `GlobalSearch`

- Обходит ресурсы текущей панели в порядке регистрации; не больше 8 групп, не больше 5 записей на ресурс (параметры `search()` `$maxGroups` и `$perResource`, через HTTP не настраиваются).
- Поля поиска — `Resource::searchableFields()`: колонки таблицы, помеченные `->search()`. Учитываются только простые имена колонок (`[a-zA-Z0-9_]+`); ресурс без таких полей пропускается.
- Запрос строится от `Resource::indexQuery()`, поэтому действуют его ограничения (soft-delete, тенант, условия хоста). Условие — `OR` по полям: `ILIKE '%q%'` на PostgreSQL, `LIKE '%q%'` на остальных драйверах.
- Права: ресурс пропускается, если у пользователя нет `{Resource::permission()}.view`. Если у модели пользователя нет метода `hasAccess()`, или ресурс возвращает пустой `permission()`, фильтрация по правам не выполняется.
- Заголовок записи (`recordTitle()`): первое непустое из `name`, `title`, `label`, `email`, `slug`, затем первое непустое поисковое поле, иначе `#{id}`. Подзаголовок (`recordSubtitle()`): первое непустое из `email`, `slug`, `status`, `code`, не совпадающее с заголовком. Оба метода можно переопределить в ресурсе.

---

## Пакет `dskripchenko/laravel-admin-search`

Отдельный пакет со своим поиском (драйверы `eloquent`/`scout`, trait `Searchable` для ресурсов, конфиг `config/admin-search.php`) — см. [../sister-packs/search.md](../sister-packs/search.md). Он не добавляет контроллер в `AdminApi`, а регистрирует обычный Laravel-маршрут `GET api/admin/system/search` (имя `admin.search`) — тот же адрес, что и у action ядра. Формат его ответа другой: `{"groups": [...]}`, где группа — `{resource, label, icon, priority, items}`, параметр `q` обязателен.

Шаблоны `SearchResponse`, `SearchGroup`, `SearchItem`, `SearchUnavailableResponse` в `AdminApiSisterPackSchemas` к action ядра не относятся: `system.search` отвечает по шаблону `GlobalSearchResponse`.
