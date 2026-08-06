# Soft Deletes

Laravel-admin **автоматически** detect'ит SoftDeletes trait на модели через
`Resource::supportsSoftDeletes()`. Никаких дополнительных шагов на стороне
Resource не требуется.

## Включение

```php
class Article extends Model
{
    use SoftDeletes;

    // ...
}
```

После этого:

- `delete` action делает soft-delete (Eloquent default).
- Появляются доступными actions `restore` и `forceDelete`.
- `meta.features.softDeletes = true` в манифесте.
- Permission'ы `admin.{slug}.restore` и `admin.{slug}.force-delete` работают.

## Готовые row-actions

```php
use Dskripchenko\LaravelAdmin\Action\BuiltIn\{RestoreAction, ForceDeleteAction};

public function actions(): array
{
    return [
        RestoreAction::for($this::permission()),
        ForceDeleteAction::for($this::permission()),
    ];
}
```

## Фильтр по trashed

```php
use Dskripchenko\LaravelAdmin\Filter\TrashedFilter;

public function filters(): array
{
    return [
        TrashedFilter::for()->label('Корзина'), // tri-state: '', 'with', 'only'
    ];
}
```

URL: `?filters[trashed]=only` — показывает только удалённые.
`?filters[trashed]=with` — все вместе.

## Назначить роли

```php
$role = Role::create([
    'name' => 'Editor',
    'slug' => 'editor',
    'permissions' => [
        'admin.articles.view',
        'admin.articles.update',
        'admin.articles.delete',     // soft-delete
        'admin.articles.restore',    // восстановление
        // 'admin.articles.force-delete' — не давать никому кроме Super
    ],
]);
```
