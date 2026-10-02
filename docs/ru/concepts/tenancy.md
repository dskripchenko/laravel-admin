---
title: Tenancy
audience: developer
status: stable
locale: ru
translated_from: en/concepts/tenancy.md
translated_at: 2026-10-02
---

# Tenancy

Админка не выбирает стратегию мультитенантности — это решение host'а
(по домену, по заголовку, по пути, общая БД или БД на tenant'а). Мы даём
контракты и trait для ограничения выборок.

## Контракты

| Класс | Роль |
|---|---|
| `Tenancy\Tenant` | Интерфейс вашей сущности-tenant'а: `getTenantKey()` и `getTenantLabel()`. |
| `Tenancy\TenantResolver` | Знает текущего tenant'а: `current()`, `setCurrent()`, `available($user)`. |
| `Tenancy\TenantContext` | Фасад над resolver'ом для scope'ов и trait'ов: `current()`, `currentKey()`, `withTenant()`. |
| `Tenancy\SingleTenantResolver` | Вариант по умолчанию для однотенантных установок: `current()` — null, пока не задан. |
| `Tenancy\Concerns\TenantScoped` (trait) | Автоматически ограничивает Eloquent-модель текущим tenant'ом. |
| `Tenancy\TenantedModel` | Абстрактная модель с уже подключённым `TenantScoped`. |

`TenantResolver` и `TenantContext` зарегистрированы как `scoped()` (на
запрос), поэтому в долгоживущих рантаймах (Octane, воркеры очередей)
состояние не перетекает между запросами.

## Реализация Tenant

```php
use Dskripchenko\LaravelAdmin\Tenancy\Tenant;

class Organization extends Model implements Tenant
{
    public function getTenantKey(): int|string
    {
        return $this->id;
    }

    public function getTenantLabel(): string
    {
        return $this->name;
    }
}
```

## Реализация resolver'а

Resolver сам находит tenant'а по тому, что несёт запрос, — нашего
middleware, который бы его заполнял, нет:

```php
namespace App\Tenancy;

use App\Models\Organization;
use Dskripchenko\LaravelAdmin\Tenancy\Tenant;
use Dskripchenko\LaravelAdmin\Tenancy\TenantResolver;
use Illuminate\Database\Eloquent\Model;

final class HeaderTenantResolver implements TenantResolver
{
    private ?Tenant $current = null;

    private bool $resolved = false;

    public function current(): ?Tenant
    {
        if (! $this->resolved) {
            $id = request()->header('X-Tenant-Id');
            $this->current = $id ? Organization::find($id) : null;
            $this->resolved = true;
        }

        return $this->current;
    }

    public function setCurrent(?Tenant $tenant): void
    {
        $this->current = $tenant;
        $this->resolved = true;
    }

    public function available(?Model $user = null): array
    {
        return $user ? $user->organizations()->get()->all() : [];
    }
}
```

Зарегистрируйте его в своём service provider:

```php
$this->app->scoped(TenantResolver::class, HeaderTenantResolver::class);
```

`setCurrent()` нужен для консольных команд, cron-задач и тестов. Чтобы
выполнить кусок кода от имени другого tenant'а и затем вернуть прежнего:

```php
app(TenantContext::class)->withTenant($organization, fn () => Report::build());
```

## Trait TenantScoped

```php
use Dskripchenko\LaravelAdmin\Tenancy\Concerns\TenantScoped;

class Article extends Model
{
    use TenantScoped;

    // protected static string $tenantColumn = 'organization_id';
}
```

Поведение:

- Глобальный scope (`TenantScope`) добавляет ко всем запросам
  `where('{table}.tenant_id', $tenant->getTenantKey())`.
- Событие `creating` заполняет `tenant_id`, если он пуст.
- Колонка — `tenant_id`, если модель не объявляет статическое свойство
  `$tenantColumn`.
- Чтобы обойти scope (экраны суперадмина, отчёты по всем tenant'ам),
  используйте штатный механизм Eloquent:
  `Article::withoutGlobalScope(TenantScope::class)`.

## Права в разрезе tenant'а

По умолчанию права глобальные. Для привязки к tenant'у либо включайте его
в ключ (`admin.{tenant_id}.articles.view`), либо проверяйте
`$user->hasAccess('...')` вместе с собственной проверкой членства в
tenant'е — своей tenant-aware проверки прав в пакете нет.

## Настройки в разрезе tenant'а

`SettingsResource` не ограничивается tenant'ом автоматически: хранилище по
умолчанию держит один набор строк на slug настроек в `admin_settings`. Для
настроек на tenant'а переопределите в ресурсе
`read(SettingsStorage $storage)` / `write(SettingsStorage $storage, array $values)`
или подставьте свою реализацию `Settings\Storage\SettingsStorage`.

## Фронтенд

Bootstrap не передаёт tenant'а. `AdminSidebar` принимает необязательный
prop `tenant` и, если он задан, показывает блок tenant'а под логотипом —
это пригодится в собственной сборке оболочки:

```vue
<AdminSidebar :tenant="{ label: 'Workspace', name: tenant.name }" />
```

## Заметки

- Tenancy **включается явно**: `SingleTenantResolver` по умолчанию
  возвращает null, и `TenantScoped` ничего не делает (ни фильтрации, ни
  заполнения).
- Для полноценных стратегий (БД на tenant'а, схема на tenant'а) берите
  специализированный пакет (например, `stancl/tenancy` или
  `dskripchenko/laravel-schemify`), а ваш `TenantResolver` пусть
  возвращает найденного им tenant'а.

## См. также

- [Permissions](permissions.md)
- [Архитектура](../architecture.md)
- [Рецепт: multi-tenancy](../recipes/multi-tenancy.md)
