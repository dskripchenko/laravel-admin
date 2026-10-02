---
title: Testen
audience: developer
status: stable
locale: de
translated_from: en/testing.md
translated_at: 2026-10-02
---

# Testen

Backend-Tests laufen mit **Pest** (`vendor/bin/pest`), Frontend-Tests mit
**Vitest** (`npm test`). Das Paket liefert Test-Helfer mit, die einen
Administrator anmelden, die Admin-Registries zwischen den Tests zurücksetzen und
die Resource-API in kurze Aufrufe verpacken.

## Basis-Testklassen

Zwei Basisklassen liegen in `Dskripchenko\LaravelAdmin\Testing`:

- **`AdminTestCase`** — für die Tests einer Host-Anwendung. Sie erweitert
  Laravels eigenes `Illuminate\Foundation\Testing\TestCase`, verwendet
  `RefreshDatabase`, `ActsAsAdmin` und `InteractsWithAdminResources` und leert in
  `setUp()` `ResourceRegistry`, `SettingsRegistry` sowie den Routen-Cache von `AdminApi`.
  `registerResource()` und `registerSettings()` fügen eine Klasse nur für den
  aktuellen Test zur Registry hinzu.
- **`PackageTestCase`** — für Pakete, die auf dem Admin aufbauen (Orchestra
  Testbench). Sie lädt die Service-Provider von laravel-api, delayed-process,
  translatable und admin sowie eine In-Memory-SQLite; das Paket fügt seine eigenen
  Provider in `additionalProviders()` hinzu.

```php
// tests/TestCase.php einer Host-Anwendung
abstract class TestCase extends \Dskripchenko\LaravelAdmin\Testing\AdminTestCase
{
    //
}
```

## Als Admin agieren

```php
it('lists articles', function () {
    $this->actingAsAdmin(permissions: ['admin.articles.view']);
    $this->postJson('/api/admin/articles/search')
        ->assertOk()
        ->assertJsonPath('payload.data.0.id', 1);
});
```

`actingAsAdmin(array $attributes = [], array $permissions = [])` legt einen
Benutzer des Benutzermodells des Panels (`admin.auth.model`) an, gibt ihm eine Rolle mit den
Berechtigungen, sofern welche angegeben sind, und meldet ihn am Guard des Panels an.
Berechtigungen können `['admin.articles.*']` oder `['*']` sein; `actingAsSuperAdmin()`
ist die Abkürzung für `['*']`.

Das funktioniert mit beiden Auth-Strategien: Bei `dedicated` wird ein `AdminUser`
am Guard `admin` angelegt; bei `shared` wird Ihr eigener `User` an Ihrem Guard angelegt
und immer eine Rolle zugewiesen, da ein Website-Benutzer ohne Rolle kein
Administrator ist.

## Helfer für die Resource-API

`InteractsWithAdminResources` baut die URL `/{api path}/{slug}/{action}`
aus dem konfigurierten API-Pfad des Panels:

```php
$this->getResourceMeta('articles')->assertOk();
$this->postResourceCreate('articles', ['title' => 'Hello']);
$this->getResourceRead('articles', $id);
$this->postResourceUpdate('articles', $id, ['title' => 'Bye']);
$this->postResourceDelete('articles', $id);
$this->postResourceSearch('articles', filters: ['status' => 'draft']);
$this->postResourceAction('articles', 'publish', ['ids' => [$id]]);

$this->assertResourceMetaOk('articles');   // meta enthält fields, columns, permissions
$this->assertResourceCount('articles', 3); // payload.meta.total von search
```

## Aufräumen zwischen den Tests

`AdminTestCase` setzt Resources und Settings bereits zurück. Wenn ein Test zusätzlich
Screens oder Menüknoten registriert oder Ihre Basisklasse nicht `AdminTestCase` ist,
setzen Sie diese selbst zurück und invalidieren Sie den Methoden-Cache von `AdminApi`:

```php
beforeEach(function () {
    app(ResourceRegistry::class)->clear();
    app(ScreenRegistry::class)->clear();
    app(SettingsRegistry::class)->clear();
    app(MenuRegistry::class)->clear();
    AdminApi::clearCache();
});
```

## Fixtures

In der eigenen Testsuite dieses Pakets werden die Fixtures unter `tests/Fixtures/*.php` per Composer-Classmap
automatisch geladen und liegen im **globalen Namespace** (keine `namespace`-Deklaration —
vom Path-Classmap-Autoloader so verlangt).

```php
// tests/Fixtures/TestArticleResource.php
<?php
declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Resource\Resource;

final class TestArticleResource extends Resource
{
    public static string $model = \App\Models\Article::class;
    // ...
}
```

## Resource-Tests

```php
it('creates an article', function () {
    $this->actingAsSuperAdmin();
    $resp = $this->postJson('/api/admin/test-articles/create', [
        'title' => 'Hello',
        'slug' => 'hello',
    ]);
    $resp->assertOk();
    expect(\App\Models\Article::where('slug', 'hello')->exists())->toBeTrue();
});
```

## Screen-Tests

Die Query-Parameter von `state` gelangen als positionelle Strings in der Reihenfolge
des Query-Strings zu `Screen::query(mixed ...$params)`:

```php
final class MyScreen extends Screen
{
    public function name(): string { return 'My Screen'; }

    public function query(mixed ...$params): array
    {
        return ['period' => (int) ($params[0] ?? 7)];
    }

    public function layout(): array { return []; }
}
```

```php
it('compiles state with custom params', function () {
    app(ScreenRegistry::class)->add(MyScreen::class);
    AdminApi::clearCache();
    $this->actingAsSuperAdmin();

    // MyScreen wird unter dem Slug `my` ausgeliefert: der Klassenname ohne "Screen", in kebab-case.
    $resp = $this->getJson('/api/admin/my/state?period=30');
    $resp->assertOk()
        ->assertJsonPath('payload.name', 'My Screen')
        ->assertJsonPath('payload.state.period', 30);
});

it('runs send command', function () {
    /* ... */
    $resp = $this->postJson('/api/admin/contact/runMethod', [
        'method' => 'send',
        'payload' => ['email' => 'a@b.c', 'message' => 'hi there'],
    ]);
    $resp->assertOk()->assertJsonPath('payload.message', 'Sent');
});
```

## Validierungs-Responses

```php
$resp = $this->postJson('/api/admin/test-articles/create', []);
$resp->assertStatus(422);
$resp->assertJsonPath('success', false);
$resp->assertJsonPath('payload.errorKey', 'validation');
$resp->assertJsonPath('payload.messages.title.0', 'The title field is required.');
```

## Frontend-Tests

`vitest.config.ts` ist mit dem `vue`-Plugin und `jsdom` vorkonfiguriert.
Dateien: `*.test.ts` neben dem zu testenden Code.

```ts
import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { useResourceIndexStore } from '@dskripchenko/laravel-admin'
import { setAdminClient, createAdminClient } from '@dskripchenko/laravel-admin'

describe('useResourceIndexStore', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
  })

  it('loads articles', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: { data: [{ id: 1, title: 'Hi' }], meta: {} },
    })
    const s = useResourceIndexStore()
    s.setSlug('articles')
    await s.load()
    expect(s.items).toHaveLength(1)
  })
})
```

## E2E (Playwright)

Die Demo-Anwendung ([dskripchenko/laravel-admin-demo](https://github.com/dskripchenko/laravel-admin-demo))
enthält `e2e-full-flow.mjs`, das Login → Menü → Resources →
Dashboard → Custom Screen → Benachrichtigungen → Profil → Logout abdeckt. Führen Sie es im
Wurzelverzeichnis der Demo aus, mit `php artisan serve` im Hintergrund:

```bash
cd demo
php artisan serve --port=8000 &
node e2e-full-flow.mjs
```

## CI

```yaml
- run: composer update --prefer-dist --no-interaction --no-progress
- run: vendor/bin/pint --test
- run: vendor/bin/phpstan analyse --no-progress
- run: vendor/bin/pest
- run: npm install --no-audit --no-fund
- run: npm run lint
- run: npm run typecheck   # vue-tsc --noEmit
- run: npm test
- run: npm run build
```

## Siehe auch

- Verzeichnis [`tests/`](../../tests/)
- [`src/Testing/`](../../src/Testing/) — Basisklassen und Traits
