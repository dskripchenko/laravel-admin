# Blog Demo

Минимальный пример laravel-admin приложения. Скопируйте файлы в свой
Laravel-проект чтобы быстро увидеть как работает админка.

## Структура

```
examples/blog-demo/
├── README.md                                  # вы здесь
├── app/Models/Article.php
├── app/Models/Category.php
├── app/Admin/Resources/ArticleResource.php
├── app/Admin/Resources/CategoryResource.php
├── app/Admin/Settings/BlogSettings.php
├── app/Admin/Screens/BlogDashboardScreen.php
└── database/migrations/
    ├── 2026_01_01_000001_create_categories_table.php
    └── 2026_01_01_000002_create_articles_table.php
```

## Установка

1. Установите laravel-admin (см. [getting-started.md](../../docs/getting-started.md)).

2. Скопируйте файлы:
   ```bash
   cp -r examples/blog-demo/app/* app/
   cp -r examples/blog-demo/database/migrations/* database/migrations/
   ```

3. Зарегистрируйте Resource'ы и Screen в `AppServiceProvider::boot()`:
   ```php
   public function boot(\Dskripchenko\LaravelAdmin\Admin $admin): void
   {
       $admin->resources([
           \App\Admin\Resources\CategoryResource::class,
           \App\Admin\Resources\ArticleResource::class,
       ]);

       $admin->screens([
           \App\Admin\Screens\BlogDashboardScreen::class,
       ]);

       app(\Dskripchenko\LaravelAdmin\Settings\SettingsRegistry::class)
           ->add(\App\Admin\Settings\BlogSettings::class);
   }
   ```

4. Запустите миграции и создайте суперюзера:
   ```bash
   php artisan migrate
   php artisan admin:user
   ```

5. Откройте `/admin` — увидите главное меню с Categories, Articles,
   Blog Dashboard и settings-секцию Blog Settings.

## Что демонстрирует

- **CategoryResource** — простой CRUD с inline-edit.
- **ArticleResource** — связь BelongsTo с Category, Wysiwyg с image-upload,
  Slug auto-from title, soft-deletes + restore-action, фильтры,
  group-by, custom-action `publish`.
- **BlogSettings** — singleton settings с custom fields.
- **BlogDashboardScreen** — кастомный dashboard с widgets (StatsOverview +
  RecentList).
