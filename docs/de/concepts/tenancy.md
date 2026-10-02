---
title: Mandantenfähigkeit
audience: developer
status: stable
locale: de
translated_from: en/concepts/tenancy.md
translated_at: 2026-10-02
---

# Mandantenfähigkeit

Die Admin legt keine Strategie für die Mandantenfähigkeit fest — das ist
Sache des Hosts (domainbasiert, headerbasiert, pfadbasiert, zentrale DB oder
DB pro Mandant). Wir stellen Verträge und einen Scoping-Trait bereit.

## Verträge

| Klasse | Rolle |
|---|---|
| `Tenancy\Tenant` | Interface Ihrer Mandanten-Entität: `getTenantKey()` und `getTenantLabel()`. |
| `Tenancy\TenantResolver` | Kennt den aktuellen Mandanten: `current()`, `setCurrent()`, `available($user)`. |
| `Tenancy\TenantContext` | Fassade über dem Resolver für Scopes und Traits: `current()`, `currentKey()`, `withTenant()`. |
| `Tenancy\SingleTenantResolver` | Standard für Installationen mit einem einzigen Mandanten: `current()` ist null, sofern nicht gesetzt. |
| `Tenancy\Concerns\TenantScoped` (Trait) | Schränkt ein Eloquent-Modell automatisch auf den aktuellen Mandanten ein. |
| `Tenancy\TenantedModel` | Abstraktes Modell, auf das `TenantScoped` bereits angewendet ist. |

`TenantResolver` und `TenantContext` sind als `scoped()` (pro Request)
gebunden, sodass in langlebigen Laufzeitumgebungen (Octane / Queue-Worker)
kein State zwischen Requests durchsickert.

## Implementierung des Mandanten

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

## Implementierung des Resolvers

Der Resolver ermittelt den Mandanten selbst, aus dem, was der Request
mitbringt — es gibt keine Middleware von uns, die ihn befüllt:

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

Binden Sie ihn in Ihrem Service Provider:

```php
$this->app->scoped(TenantResolver::class, HeaderTenantResolver::class);
```

`setCurrent()` ist für Konsolenbefehle, Cron-Jobs und Tests gedacht; um ein
Stück Code als anderer Mandant auszuführen und danach den vorherigen
wiederherzustellen:

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

Verhalten:

- Ein globaler Scope (`TenantScope`) wendet
  `where('{table}.tenant_id', $tenant->getTenantKey())` auf alle Abfragen an.
- Das Event `creating` befüllt `tenant_id`, wenn es leer ist.
- Die Spalte ist `tenant_id`, sofern das Modell keine statische
  `$tenantColumn` deklariert.
- Um den Scope zu umgehen (Ansichten für Admin-Superuser, mandantenübergreifende
  Berichte), verwenden Sie Eloquents eigenes
  `Article::withoutGlobalScope(TenantScope::class)`.

## Berechtigungen pro Mandant

Berechtigungen sind standardmäßig global. Für mandantenbezogene Berechtigungen
setzen Sie entweder den Schlüssel zusammen (`admin.{tenant_id}.articles.view`)
oder prüfen `$user->hasAccess('...')` zusammen mit Ihrer eigenen Prüfung der
Mandantenzugehörigkeit — das Paket hat keine eigene mandantenbewusste
Berechtigungsprüfung.

## Einstellungen pro Mandant

`SettingsResource` scoped nicht automatisch: Der Standardspeicher hält einen
Satz Zeilen pro Settings-Slug in `admin_settings`. Für Konfiguration pro
Mandant überschreiben Sie `read(SettingsStorage $storage)` /
`write(SettingsStorage $storage, array $values)` an der Resource oder binden
Ihren eigenen `Settings\Storage\SettingsStorage`.

## Berücksichtigung im Frontend

Der Bootstrap enthält den Mandanten nicht. `AdminSidebar` akzeptiert ein
optionales Prop `tenant` und zeigt, wenn es übergeben wird, einen
Mandantenblock unterhalb der Marke an — nützlich in einem eigenen Shell-Build:

```vue
<AdminSidebar :tenant="{ label: 'Arbeitsbereich', name: tenant.name }" />
```

## Hinweise

- Mandantenfähigkeit ist **opt-in** — der Standard-`SingleTenantResolver`
  liefert null, und `TenantScoped` wird zur No-Op (weder Filtern noch
  Befüllen).
- Für vollständige Strategien (DB pro Mandant, Schema pro Mandant) verwenden
  Sie ein dediziertes Paket (z. B. `stancl/tenancy` oder
  `dskripchenko/laravel-schemify`) und lassen Ihren `TenantResolver` den von
  ihm ermittelten Mandanten zurückgeben.

## Siehe auch

- [Berechtigungen](permissions.md)
- [Architektur](../architecture.md)
- [Rezept zur Mandantenfähigkeit](../../ru/recipes/multi-tenancy.md) (auf Russisch)
