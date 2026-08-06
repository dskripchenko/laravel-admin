# Custom Actions

## Row action — простая кнопка с подтверждением

```php
public function actions(): array
{
    return [
        Button::make('Опубликовать')
            ->withName('publish')
            ->method('publish')              // server-side метод на Resource'е
            ->position(['row'])
            ->permission('admin.articles.update')
            ->confirm('Опубликовать статью?'),
    ];
}

// На самом Resource'е:
public function publish(Article $article): void
{
    $article->update(['is_published' => true]);
}
```

## Bulk-action — операция над выделенными rows

```php
BulkAction::make('Опубликовать выделенные')
    ->method('bulkPublish')
    ->requiresAtLeast(1)
    ->requiresAtMost(100)
    ->confirm('Опубликовать N статей?');

// На Resource'е:
public function bulkPublish(array $ids): array
{
    Article::whereIn('id', $ids)->update(['is_published' => true]);
    return ['updated' => count($ids)];
}
```

## Modal-action — action с параметрами

```php
ModalAction::make('Отправить уведомление')
    ->method('sendNotification')
    ->modalTitle('Уведомление подписчикам')
    ->fields([
        Input::make('subject')->required(),
        Textarea::make('body')->rows(5)->required(),
    ])
    ->submitLabel('Отправить');
```

## Async-action — долгая операция через delayed-process

Нужно зарегистрировать handler в whitelist'е (security):

```php
// AppServiceProvider::boot()
public function boot(AllowlistRegistrar $allowlist): void
{
    $allowlist->allow(\App\Jobs\RecomputeStats::class, 'handle');
}

// В Resource'е
AsyncAction::make('Пересчитать статистику')
    ->handler(\App\Jobs\RecomputeStats::class, 'handle')
    ->withParams(['period' => '30d'])
    ->pollInterval(5);
```

SPA получит `process_uuid` и будет polling'ом следить за прогрессом
через `/api/admin/delayed/status?uuid=...`.

## DropDown — группа actions под одну кнопку

```php
use Dskripchenko\LaravelAdmin\Action\BuiltIn\{ReplicateAction, RestoreAction, ForceDeleteAction};

DropDown::make('Ещё')->items([
    ReplicateAction::for($this::permission()),
    RestoreAction::for($this::permission()),
    ForceDeleteAction::for($this::permission()),
]);
```
