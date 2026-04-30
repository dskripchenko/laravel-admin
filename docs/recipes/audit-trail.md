# Audit Trail

## Включить логирование модели

```php
class Article extends Model
{
    use \Dskripchenko\LaravelAdmin\Audit\Concerns\Loggable;

    // Optional: исключить sensitive поля из снимков (default config:
    // password, remember_token, two_factor_secret, two_factor_recovery_codes).
    public function getAuditExcluded(): array
    {
        return ['internal_notes'];
    }
}
```

После этого создание/обновление/удаление/восстановление пишутся в
таблицу `admin_audit_logs` с before/after снимками.

## Auth-events (login, logout, password reset)

Логируются автоматически при `config('admin.audit.log_auth_events') = true`
(default). Никаких дополнительных шагов.

## AuditTrail layout на view-screen

Чтобы показать timeline на странице записи, добавьте AuditTrail в Resource'е:

```php
use Dskripchenko\LaravelAdmin\Layout\AuditTrail;

public function infolist(): array
{
    return [
        TextEntry::make('title'),
        TextEntry::make('created_at')->asDateTime(),
        // и т.д.
    ];
}

// Custom view-screen с timeline:
class GeneratedArticleViewScreen extends GeneratedViewScreen
{
    public function layout(): array
    {
        return [
            Infolist::make($this->resource->infolist()),
            AuditTrail::for(\App\Models\Article::class)
                ->fromState('id')
                ->limit(50)
                ->withPermission('admin.articles.view'),
        ];
    }
}
```

## Custom event для domain-action

```php
use Dskripchenko\LaravelAdmin\Audit\AuditLog;

AuditLog::create([
    'actor_type' => $user->getMorphClass(),
    'actor_id' => $user->id,
    'subject_type' => $article->getMorphClass(),
    'subject_id' => $article->id,
    'event' => 'article.published',
    'changes' => ['payload' => ['ip' => request()->ip()]],
]);
```

## Конфигурация

```php
// config/admin.php
'audit' => [
    'enabled' => true,
    'log_auth_events' => true,
    'excluded_attributes' => ['password', 'remember_token', /* ... */],
],
```
