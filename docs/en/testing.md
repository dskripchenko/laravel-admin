---
title: Testing
audience: developer
status: stable
locale: en
---

# Testing

Backend tests run with **Pest** (`vendor/bin/pest`), frontend with
**Vitest** (`npm test`). The package ships test helpers that handle
admin-auth, registry resets and HTTP envelope unwrapping.

## TestCase

`Dskripchenko\LaravelAdmin\Testing\TestCase` extends Orchestra Testbench
and pre-loads the required service providers. Use it as your base in
host-side tests:

```php
abstract class TestCase extends \Dskripchenko\LaravelAdmin\Testing\TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
}
```

## Acting as an admin

```php
use Dskripchenko\LaravelAdmin\Testing\Concerns\ActsAsAdmin;

it('lists articles', function () {
    $this->actingAsAdmin(['admin.articles.view']);
    $this->getJson('/api/admin/articles/search')
        ->assertOk()
        ->assertJsonPath('payload.data.0.id', 1);
});
```

`actingAsAdmin($permissions = ['*'])` creates an `AdminUser` + role +
authenticates against the `admin` guard. Permissions can be
`['admin.articles.*']` or `['*']`.

## Cleanup between tests

If you register Resources / Screens / Settings during a test,
`AdminApi`'s method cache must be invalidated:

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

Tests at `tests/Fixtures/*.php` are autoloaded via composer classmap
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
    $this->actingAsAdmin();
    $resp = $this->postJson('/api/admin/test-article/create', [
        'title' => 'Hello',
        'slug' => 'hello',
    ]);
    $resp->assertOk();
    expect(\App\Models\Article::where('slug', 'hello')->exists())->toBeTrue();
});
```

## Screen tests

```php
it('compiles state with custom params', function () {
    app(ScreenRegistry::class)->add(MyScreen::class);
    AdminApi::clearCache();
    $this->actingAsAdmin();

    $resp = $this->getJson('/api/admin/my-screen/state?period=30');
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
$resp = $this->postJson('/api/admin/test-article/create', []);
$resp->assertStatus(422);
$resp->assertJsonPath('payload.errorKey', 'validation_error');
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

`demo/e2e-full-flow.mjs` covers login → menu → resources → dashboard
→ custom screen → notifications → profile → logout. Run from `demo/`
with `php artisan serve` in the background:

```bash
cd demo
php artisan serve --port=8000 &
node e2e-full-flow.mjs
```

## CI

```yaml
- run: composer install --no-progress
- run: vendor/bin/pest
- run: vendor/bin/pint --test
- run: vendor/bin/phpstan analyse --memory-limit=1G
- run: npm ci
- run: npx vue-tsc --noEmit
- run: npm test
- run: npm run build
```

## See also

- [`tests/`](../../tests/) directory
- [`testing/`](../../src/Testing/) — base classes and traits
