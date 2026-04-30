# dskripchenko/laravel-admin

Конструктор админок для Laravel: Resource-first CRUD, Vue 3 SPA, JSON-API транспорт. Подключается в любой существующий Laravel-проект без вмешательства в его routes/auth.

> **Статус:** in development. Архитектура зафиксирована, идёт реализация. См. `docs/ARCHITECTURE.md`.

## Tldr

```php
class UserResource extends Resource
{
    public static string $model = \App\Models\User::class;
    public static string $icon  = 'user';

    public function fields(): array
    {
        return [
            Field\Input::make('name')->required(),
            Field\Input::make('email')->type('email')->required()->unique(),
            Field\Switch::make('is_active'),
        ];
    }

    public function columns(): array
    {
        return [
            TC::make('id')->sort(),
            TC::make('name')->sort()->search(),
            TC::make('email')->copyable(),
            TC::make('is_active')->as('badge'),
        ];
    }
}
```

```php
// AppServiceProvider
Admin::resources([UserResource::class]);
```

Готово: SPA с list/create/edit/view/audit/permissions.

## Требования

- PHP `^8.5`
- Laravel `^12`
- Redis или Memcached (для tag-aware кэша переводов; иначе soft-fallback)
- Vue 3.4+, TypeScript, Vite (для frontend-сборки)

## Установка

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install
php artisan migrate
php artisan admin:user "Admin" admin@example.com secret
npm install
npm run admin:build
```

После этого админка доступна на `/admin`.

## Документация

- [ARCHITECTURE.md](docs/ARCHITECTURE.md) — архитектура и решения.
- [docs/sister-packs/](docs/sister-packs/) — спецификации опциональных расширений.

## Sister-packs

| Пакет | Назначение |
|---|---|
| `dskripchenko/laravel-admin-starter` | Готовые системные Resource'ы |
| `dskripchenko/laravel-admin-tinymce` / `*-quill` | Альтернативные WYSIWYG |
| `dskripchenko/laravel-admin-search` | Глобальный поиск |
| `dskripchenko/laravel-admin-media` | Медиа-библиотека |
| `dskripchenko/laravel-admin-health` | Health-checks |
| `dskripchenko/laravel-admin-pulse` | Лёгкая телеметрия |
| `dskripchenko/laravel-admin-jobs` | Failed jobs viewer |

## Структура репозитория (dev)

На время разработки до первого релиза проект ведётся как **монорепо**:

```
laravel-admin/
├── src/                      # основной пакет dskripchenko/laravel-admin
├── resources/ts/             # SPA-бандл @dskripchenko/laravel-admin
├── packages/                 # sister-packs, локально
│   ├── starter/
│   ├── tinymce/
│   ├── quill/
│   ├── search/
│   ├── media/
│   ├── health/
│   ├── pulse/
│   └── jobs/
└── docs/
```

- Composer: корневой `composer.json` содержит `repositories: [{ type: path, url: packages/* }]` — sister-packs резолвятся локально через симлинки. Это позволяет менять core и sister-packs одновременно без публикации dev-версий.
- NPM: workspaces для `packages/tinymce` и `packages/quill` (только им нужны npm-зависимости). `npm install` из корня устанавливает зависимости всем сразу.
- **Перед первым стабильным релизом** sister-packs выносятся в отдельные репозитории на github.com/dskripchenko и публикуются на packagist/npm независимо. На этот момент `repositories` в корневом `composer.json` удаляются, sister-packs ставятся обычным `composer require`.

## License

MIT. См. [LICENSE](LICENSE).
