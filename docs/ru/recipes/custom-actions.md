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

// На самом Resource'е: ключ строки приходит списком из одного id.
public function publish(array $ids, array $payload = []): int
{
    return Article::whereIn('id', $ids)->update(['is_published' => true]);
}
```

В списке у каждой строки появляется меню «⋮» рядом с иконками
просмотра/правки/удаления — действие выполняется для этой строки. Те же
действия доступны в панели массовых операций, когда строки выделены.

## Standalone-action — без выбранных записей

Действие в `command_bar` или `header` (позиция по умолчанию у `Button`)
выполняется из меню «…» над списком без выделения: `ids` не отправляются,
метод получает пустой список. Действие в `row`/`bulk` требует хотя бы один
id (иначе 422); `standalone()` снимает это требование явно, `BulkAction`
всегда требует выделения.

```php
Button::make('Пересчитать рейтинги')
    ->method('recalculate');

Button::make('Синхронизировать')
    ->method('sync')
    ->position(['header', 'row'])
    ->standalone();

public function recalculate(array $ids, array $payload = []): void
{
    // $ids === []
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

// На Resource'е: значения формы приходят вторым аргументом.
public function sendNotification(array $ids, array $payload): int
{
    // $payload = ['subject' => ..., 'body' => ...]
    return count($ids);
}
```

SPA открывает модалку с полями (`modalSize('sm'|'md'|'lg'|'xl'|'full')`) и шлёт
`POST /{slug}/action` с `{key, ids, payload}`. Сервер проверяет `payload`
правилами полей (`required()`, `rules([...])`); ошибки 422 показываются
у полей, модалка остаётся открытой.

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

SPA запускает процесс через `/api/admin/delayed/run`, получает `uuid` и
каждые `pollInterval` секунд опрашивает `/api/admin/delayed/status?uuid=...`,
показывая прогресс. Если action стоит в позиции `row`/`bulk`, в параметры
добавляются выбранные ключи как `ids` — handler должен их принимать.

## DropDown — группа actions под одну кнопку

```php
use Dskripchenko\LaravelAdmin\Action\BuiltIn\{ReplicateAction, RestoreAction, ForceDeleteAction};

DropDown::make('Ещё')->items([
    ReplicateAction::for($this::permission()),
    RestoreAction::for($this::permission()),
    ForceDeleteAction::for($this::permission()),
]);
```
