---
title: 测试
audience: developer
status: stable
locale: zh
translated_from: en/testing.md
translated_at: 2026-10-02
---

# 测试

后端测试使用 **Pest**（`vendor/bin/pest`）运行，前端测试使用 **Vitest**
（`npm test`）。本包自带测试辅助工具：以管理员身份登录、在测试之间重置管理面板的
注册表，并把 Resource API 封装成简短的调用。

## 基础测试用例

`Dskripchenko\LaravelAdmin\Testing` 中有两个基类：

- **`AdminTestCase`**——用于宿主应用的测试。它继承 Laravel 自身的
  `Illuminate\Foundation\Testing\TestCase`，使用 `RefreshDatabase`、`ActsAsAdmin`
  和 `InteractsWithAdminResources`，并在 `setUp()` 中清空 `ResourceRegistry`、
  `SettingsRegistry` 以及 `AdminApi` 的路由缓存。`registerResource()` 和
  `registerSettings()` 仅为当前测试向注册表添加一个类。
- **`PackageTestCase`**——用于构建在管理面板之上的包（Orchestra Testbench）。它加载
  laravel-api、delayed-process、translatable 和 admin 的服务提供者以及内存 SQLite；
  包在 `additionalProviders()` 中添加自己的提供者。

```php
// 宿主应用的 tests/TestCase.php
abstract class TestCase extends \Dskripchenko\LaravelAdmin\Testing\AdminTestCase
{
    //
}
```

## 以管理员身份执行

```php
it('lists articles', function () {
    $this->actingAsAdmin(permissions: ['admin.articles.view']);
    $this->postJson('/api/admin/articles/search')
        ->assertOk()
        ->assertJsonPath('payload.data.0.id', 1);
});
```

`actingAsAdmin(array $attributes = [], array $permissions = [])` 使用面板的用户模型
（`admin.auth.model`）创建一个用户；如果传入了权限，就给它分配一个拥有这些权限的
角色，并在面板的 guard 上登录。权限可以是 `['admin.articles.*']` 或 `['*']`；
`actingAsSuperAdmin()` 是 `['*']` 的快捷方式。

它在两种认证策略下都可用：使用 `dedicated` 时，它在 `admin` guard 上创建
`AdminUser`；使用 `shared` 时，它在你的 guard 上创建你自己的 `User`，并且总会分配
一个角色，因为没有角色的站点用户不是管理员。

## Resource API 辅助方法

`InteractsWithAdminResources` 根据面板配置的 API 路径构建 URL
`/{api path}/{slug}/{action}`：

```php
$this->getResourceMeta('articles')->assertOk();
$this->postResourceCreate('articles', ['title' => 'Hello']);
$this->getResourceRead('articles', $id);
$this->postResourceUpdate('articles', $id, ['title' => 'Bye']);
$this->postResourceDelete('articles', $id);
$this->postResourceSearch('articles', filters: ['status' => 'draft']);
$this->postResourceAction('articles', 'publish', ['ids' => [$id]]);

$this->assertResourceMetaOk('articles');   // meta 中包含 fields、columns、permissions
$this->assertResourceCount('articles', 3); // search 结果的 payload.meta.total
```

## 测试之间的清理

`AdminTestCase` 已经会重置 Resource 和设置。如果某个测试还注册了 Screen 或菜单节点，
或者你的基类不是 `AdminTestCase`，请自行重置它们，并使 `AdminApi` 的方法缓存失效：

```php
beforeEach(function () {
    app(ResourceRegistry::class)->clear();
    app(ScreenRegistry::class)->clear();
    app(SettingsRegistry::class)->clear();
    app(MenuRegistry::class)->clear();
    AdminApi::clearCache();
});
```

## 测试夹具

在本包自己的测试套件中，`tests/Fixtures/*.php` 中的夹具通过 composer classmap 自动加载，
并且位于**全局命名空间**中（没有 `namespace` 声明——这是按路径的 classmap 自动加载器
的要求）。

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

## Resource 测试

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

## Screen 测试

`state` 的查询参数会以位置字符串的形式、按查询字符串中的顺序传入
`Screen::query(mixed ...$params)`：

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

    // MyScreen 以 slug `my` 提供：去掉 "Screen" 后的类名，转为 kebab-case。
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

## 校验响应

```php
$resp = $this->postJson('/api/admin/test-articles/create', []);
$resp->assertStatus(422);
$resp->assertJsonPath('success', false);
$resp->assertJsonPath('payload.errorKey', 'validation');
$resp->assertJsonPath('payload.messages.title.0', 'The title field is required.');
```

## 前端测试

`vitest.config.ts` 已预先配置好 `vue` 插件和 `jsdom`。
文件：与被测对象（SUT）放在一起的 `*.test.ts`。

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

## E2E（Playwright）

演示应用（[dskripchenko/laravel-admin-demo](https://github.com/dskripchenko/laravel-admin-demo)）
带有 `e2e-full-flow.mjs`，覆盖 登录 → 菜单 → Resource → 仪表板 → 自定义 Screen →
通知 → 个人资料 → 退出登录 的完整流程。在演示应用的根目录下运行它，并在后台运行
`php artisan serve`：

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

## 另请参阅

- [`tests/`](../../tests/) 目录
- [`src/Testing/`](../../src/Testing/)——基类和 trait
