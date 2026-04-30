# dskripchenko/laravel-admin-search

## 1. Назначение

Глобальный поиск по всем Resource'ам админки с подсказками-результатами в реальном времени. Открывается по `cmd+K` / `ctrl+K` или клику на поисковую строку в шапке.

**Use case:** в проекте больше 5–7 Resource'ов, навигация через сайдбар становится медленной; нужно быстро найти конкретного пользователя/заказ/документ.

## 2. Состав

### Searchable trait

```php
trait Searchable
{
    public function searchableFields(): array;        // ['name', 'email', 'phone']
    public function searchTitle(): string;            // 'name'
    public function searchSubtitle(): ?string;        // 'email'
    public function searchIcon(): ?string;            // 'user'
    public function searchUrl(): string;              // обычно URL на edit
    public function searchPriority(): int;            // для сортировки в выдаче, default 0
}
```

Resource подключает trait и заполняет 1–2 метода. Остальное — defaults.

### Driver'ы

Контракт `SearchDriver`:

```php
interface SearchDriver
{
    public function search(string $query, AdminUser $user): Collection;
}
```

Реализации:

- **`EloquentSearchDriver`** (default) — `LIKE %q%` по `searchableFields()` каждого Resource. Без зависимостей. Для проектов до 100K записей суммарно.
- **`ScoutSearchDriver`** — через `Laravel\Scout\Searchable` модели. `composer suggest`. Использует Algolia/Meilisearch/TNTSearch/Database-engine.

Driver выбирается в `config/admin-search.php → driver`.

### API

`GET /admin/api/v1/system/search?q=...`:

```json
{
  "success": true,
  "payload": {
    "groups": [
      {
        "resource": "users",
        "label": "Пользователи",
        "icon": "user",
        "items": [
          {"id": 42, "title": "Иван Иванов", "subtitle": "ivan@example.com", "url": "/admin/resources/users/42"},
          ...
        ]
      },
      ...
    ]
  }
}
```

Permission-фильтрация: ищет только в Resource'ах, где у текущего пользователя есть `<resource>.view`.

### UI

- `<GlobalSearchBar>` в шапке (input с `<UiCommand>` open-on-shortcut).
- Debounced 200ms, минимум 2 символа.
- Группировка по Resource в выдаче.
- Навигация по выдаче клавишами ↑/↓, выбор по Enter, Esc — закрыть.
- Топ-N (configurable, default 10) на Resource, кнопка «Показать все ...» ведёт на ListScreen с pre-applied search-фильтром.

## 3. Зависимости

**Composer:**

```
"require": {
    "dskripchenko/laravel-admin": "^1.0"
}
"suggest": {
    "laravel/scout": "Для поиска через Algolia/Meilisearch/etc."
}
```

NPM: нет (использует `<UiCommand>` из `@dskripchenko/ui`).

## 4. Миграции

Нет.

## 5. Permissions

Не регистрирует свои permissions. Использует существующие `<resource>.view`.

## 6. Конфиг

`config/admin-search.php`:

```php
return [
    'driver'        => env('ADMIN_SEARCH_DRIVER', 'eloquent'),  // 'eloquent' | 'scout'
    'min_length'    => 2,
    'debounce_ms'   => 200,
    'per_resource'  => 10,
    'cache_ttl'     => 60,                                       // секунд; 0 = выключить кэш
    'shortcut'      => 'mod+k',                                  // mousetrap-like
];
```

## 7. Подключение

```bash
composer require dskripchenko/laravel-admin-search
php artisan admin:plugin:install search

# для Scout-driver:
composer require laravel/scout
# + конфиг scout.php и engine
```

После установки добавляешь `Searchable` trait в Resource'ы:

```php
class UserResource extends Resource
{
    use Searchable;

    public function searchableFields(): array { return ['name', 'email', 'phone']; }
    public function searchTitle(): string     { return 'name'; }
    public function searchSubtitle(): string  { return 'email'; }
    public function searchIcon(): string      { return 'user'; }
}
```

## 8. Зачем sister, а не core

- Не всем нужен — на 5 ресурсах глобальный поиск избыточен.
- Scout-обвязка вытащила бы сложность в core (driver-абстракция, UI-shortcut, кэш).
- Команды с heavy-search потребностями обычно уже имеют Algolia/Meilisearch — им достаточно `ScoutSearchDriver`.
