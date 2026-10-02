---
title: Testing
audience: developer
status: stable
locale: en
---

# Testing

Backend tests run with **Pest** (`vendor/bin/pest`), frontend with
**Vitest** (`npm test`). The package ships test helpers that log an
administrator in, reset the admin registries between tests and wrap the
resource API in short calls.

## Base test cases

Two base classes live in `Dskripchenko\LaravelAdmin\Testing`:

- **`AdminTestCase`** — for a host application's tests. It extends
  Laravel's own `Illuminate\Foundation\Testing\TestCase`, uses
  `RefreshDatabase`, `ActsAsAdmin` and `InteractsWithAdminResources`, and in
  `setUp()` clears `ResourceRegistry`, `SettingsRegistry` and the `AdminApi`
  route cache. `registerResource()` and `registerSettings()` add a class to
  the registry for the current test only.
- **`PackageTestCase`** — for packages built on top of the admin (Orchestra
  Testbench). It loads the laravel-api, delayed-process, translatable and
  admin service providers and an in-memory SQLite; the package adds its own
  providers in `additionalProviders()`.

```php
// tests/TestCase.php of a host application
abstract class TestCase extends \Dskripchenko\LaravelAdmin\Testing\AdminTestCase
{
    //
}
```

## Acting as an admin

```php
it('lists articles', function () {
    $this->actingAsAdmin(permissions: ['admin.articles.view']);
    $this->postJson('/api/admin/articles/search')
        ->assertOk()
        ->assertJsonPath('payload.data.0.id', 1);
});
```

`actingAsAdmin(array $attributes = [], array $permissions = [])` creates an
`AdminUser` (the attributes override the generated name, email and
password), assigns it a role with the given permissions when there are any,
authenticates it against the `admin` guard and returns the user.
Permissions can be exact (`admin.articles.view`), wildcards
(`admin.articles.*`) or `*`. `actingAsSuperAdmin()` is the shortcut for a
user with `*`.

## Resource API helpers

`InteractsWithAdminResources` builds the URL `/{api path}/{slug}/{action}`
from the panel's configured API path:

```php
$this->getResourceMeta('articles')->assertOk();
$this->postResourceCreate('articles', ['title' => 'Hello']);
$this->getResourceRead('articles', $id);
$this->postResourceUpdate('articles', $id, ['title' => 'Bye']);
$this->postResourceDelete('articles', $id);
$this->postResourceSearch('articles', filters: ['status' => 'draft']);
$this->postResourceAction('articles', 'publish', ['ids' => [$id]]);

$this->assertResourceMetaOk('articles');   // meta has fields, columns, permissions
$this->assertResourceCount('articles', 3); // payload.meta.total of search
```

## Cleanup between tests

`AdminTestCase` already resets resources and settings. If a test registers
Screens or menu nodes as well, or your base class is not `AdminTestCase`,
reset them yourself and invalidate `AdminApi`'s method cache:

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

In this package's own test suite, fixtures at `tests/Fixtures/*.php` are autoloaded via composer classmap
and live in the **global namespace** (no `namespace` declaration —
required by the path-classmap autoloader).

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

## Resource tests

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

## Screen tests

The query parameters of `state` reach `Screen::query(mixed ...$params)` as
positional strings, in the order of the query string:

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

    // MyScreen is served under the slug `my`: the class name without "Screen", kebab-cased.
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

## Validation responses

```php
$resp = $this->postJson('/api/admin/test-articles/create', []);
$resp->assertStatus(422);
$resp->assertJsonPath('success', false);
$resp->assertJsonPath('payload.errorKey', 'validation');
$resp->assertJsonPath('payload.messages.title.0', 'The title field is required.');
```

## Frontend tests

`vitest.config.ts` is pre-configured with `vue` plugin and `jsdom`.
Files: `*.test.ts` next to the SUT.

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

The demo application ([dskripchenko/laravel-admin-demo](https://github.com/dskripchenko/laravel-admin-demo))
carries `e2e-full-flow.mjs`, which covers login → menu → resources →
dashboard → custom screen → notifications → profile → logout. Run it from
the demo's root with `php artisan serve` in the background:

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

## See also

- [`tests/`](../../tests/) directory
- [`src/Testing/`](../../src/Testing/) — base classes and traits
