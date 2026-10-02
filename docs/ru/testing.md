---
title: Тестирование
audience: developer
status: stable
locale: ru
translated_from: en/testing.md
translated_at: 2026-10-02
---

# Тестирование

Бэкенд тестируется на **Pest** (`vendor/bin/pest`), фронтенд — на
**Vitest** (`npm test`). Пакет поставляет тестовые хелперы: они логинят
администратора, сбрасывают реестры админки между тестами и сводят вызовы
API ресурсов к коротким методам.

## Базовые классы тестов

В `Dskripchenko\LaravelAdmin\Testing` два базовых класса:

- **`AdminTestCase`** — для тестов host-приложения. Наследует
  стандартный `Illuminate\Foundation\Testing\TestCase` Laravel, подключает
  `RefreshDatabase`, `ActsAsAdmin` и `InteractsWithAdminResources`, а в
  `setUp()` очищает `ResourceRegistry`, `SettingsRegistry` и кэш маршрутов
  `AdminApi`. Методы `registerResource()` и `registerSettings()`
  регистрируют класс только на время текущего теста.
- **`PackageTestCase`** — для пакетов, построенных поверх админки
  (Orchestra Testbench). Подгружает service provider'ы laravel-api,
  delayed-process, translatable и самой админки, настраивает SQLite в
  памяти; свои provider'ы пакет добавляет в `additionalProviders()`.

```php
// tests/TestCase.php of a host application
abstract class TestCase extends \Dskripchenko\LaravelAdmin\Testing\AdminTestCase
{
    //
}
```

## Вход под администратором

```php
it('lists articles', function () {
    $this->actingAsAdmin(permissions: ['admin.articles.view']);
    $this->postJson('/api/admin/articles/search')
        ->assertOk()
        ->assertJsonPath('payload.data.0.id', 1);
});
```

`actingAsAdmin(array $attributes = [], array $permissions = [])` создаёт
пользователя модели панели (`admin.auth.model`; атрибуты перекрывают
сгенерированные имя, email и пароль), если права переданы — назначает ему
роль с этими правами, аутентифицирует его в guard'е панели и возвращает
созданного пользователя. Права бывают точными (`admin.articles.view`), с
маской (`admin.articles.*`) или `*`. `actingAsSuperAdmin()` — сокращение для
пользователя с `*`.

Работает в обеих стратегиях: в `dedicated` создаётся `AdminUser` в guard'е
`admin`, в `shared` — ваш `User` в вашем guard'е, и роль назначается всегда:
пользователь сайта без роли администратором не считается.

## Хелперы API ресурсов

`InteractsWithAdminResources` собирает URL вида
`/{путь API}/{slug}/{action}` из настроенного пути API панели:

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

## Очистка между тестами

`AdminTestCase` уже сбрасывает ресурсы и настройки. Если тест регистрирует
ещё и Screen'ы или пункты меню либо ваш базовый класс — не
`AdminTestCase`, сбрасывайте их сами и инвалидируйте кэш методов
`AdminApi`:

```php
beforeEach(function () {
    app(ResourceRegistry::class)->clear();
    app(ScreenRegistry::class)->clear();
    app(SettingsRegistry::class)->clear();
    app(MenuRegistry::class)->clear();
    AdminApi::clearCache();
});
```

## Фикстуры

В собственном наборе тестов пакета фикстуры из `tests/Fixtures/*.php`
подключаются через composer classmap и живут в **глобальном пространстве
имён** (без объявления `namespace` — этого требует classmap-автозагрузка
по пути).

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

## Тесты ресурсов

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

Slug ресурса по умолчанию — имя класса без суффикса `Resource`, во
множественном числе и в kebab-case: `TestArticleResource` →
`test-articles`.

## Тесты экранов

Query-параметры запроса `state` попадают в
`Screen::query(mixed ...$params)` позиционно, строками, в порядке
следования в query string:

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

## Ответы с ошибками валидации

```php
$resp = $this->postJson('/api/admin/test-articles/create', []);
$resp->assertStatus(422);
$resp->assertJsonPath('success', false);
$resp->assertJsonPath('payload.errorKey', 'validation');
$resp->assertJsonPath('payload.messages.title.0', 'The title field is required.');
```

Текст сообщения зависит от локали приложения; выше — английская
формулировка Laravel по умолчанию.

## Тесты фронтенда

`vitest.config.ts` уже настроен: плагин `vue` и окружение `jsdom`.
Тесты лежат в файлах `*.test.ts` рядом с тестируемым кодом.

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

В демо-приложении
([dskripchenko/laravel-admin-demo](https://github.com/dskripchenko/laravel-admin-demo))
есть сценарий `e2e-full-flow.mjs`: вход → меню → ресурсы → дашборд →
свой экран → уведомления → профиль → выход. Запускается из корня демо при
работающем в фоне `php artisan serve`:

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

## См. также

- Каталог [`tests/`](../../tests/)
- [`src/Testing/`](../../src/Testing/) — базовые классы и трейты
