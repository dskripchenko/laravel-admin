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

`actingAsAdmin(array $attributes = [], array $permissions = [])` creates a
user of the panel's user model (`admin.auth.model`), gives it a role with the
permissions when there are any, and signs it in on the panel's guard.
Permissions can be `['admin.articles.*']` or `['*']`; `actingAsSuperAdmin()`
is the shortcut for `['*']`.

It works in both auth strategies: with `dedicated` it creates an `AdminUser`
on the `admin` guard; with `shared` it creates your own `User` on your guard
and always assigns a role, since a site user without one is not an
administrator.

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

## data-testid for end-to-end tests

Since 1.72 the key elements of the panel's shell and pages carry a
`data-testid`, so an end-to-end test (Playwright, Selenium) finds each by one
attribute, without CSS selectors or the interface's text — in Playwright,
`page.get_by_test_id('login-email')`.

**The rule of a name.** A name is made of names and keys — of a field, a
resource, an action, a tab, a record, a block's ref — never of a caption, so
a change of the translation breaks no test. Each part is put into kebab-case
(`firstName`, `first_name`, `user.email` → `first-name`, `first-name`,
`user-email`; letters and digits stay, the rest becomes `-`), the parts are
joined with `-`. The package exports the helper `testIdOf(...parts)` for your
own components; the same rule in Python:

```python
import re


def tid(*parts) -> str:
    def part(value) -> str:
        s = str(value)
        s = re.sub(r'([a-zа-яё0-9])([A-ZА-ЯЁ])', r'\1-\2', s)
        s = re.sub(r'([A-ZА-ЯЁ]+)([A-ZА-ЯЁ][a-zа-яё])', r'\1-\2', s)
        return re.sub(r'[\W_]+', '-', s.lower()).strip('-')
    return '-'.join(p for p in map(part, parts) if p)

page.get_by_test_id(tid('field', 'first_name'))   # field-first-name
```

The names without a key (`row-edit`, `block-tree-node-remove`) are the same
in every row and node — look for them inside the row or the node:
`page.get_by_test_id('resource-row-15').get_by_test_id('row-edit')`.

The attributes that were there before 1.72 (`form-save`, `action-confirm-ok`,
`menu-{key}`, `record-drawer-*` and others) are not renamed. In `menu-{key}`,
`action-{name}`, `form-link-{name}`, `widget-config-{key}` the key stands as
it is, not in kebab-case: `action-send_invoice`.

**Login and the shell**

| `data-testid` | Element |
|---|---|
| `login-form` | The login form |
| `login-email`, `login-password` | The e-mail and password fields (on the `input` itself) |
| `login-remember` | "Remember me" (on the checkbox's `input`) |
| `login-submit` | The "Log in" button |
| `login-error`, `login-notice` | The login error, the notice above the form |
| `login-external`, `login-demo` | External logins and demo accounts (buttons — `login-external-{url}`, `login-demo-{email}`) |
| `two-factor-form`, `two-factor-code`, `two-factor-recovery-code`, `two-factor-submit`, `two-factor-switch`, `two-factor-error` | The second factor: the form, the code's cells (their box), the recovery code, "Confirm", the switch to a recovery code, the error |
| `menu-{key}` | A menu item by its key (`MenuNode::make($key)`; a resource's — its slug); a leaf or a group |
| `topbar-search`, `search-input`, `search-item` | The global search |
| `search-group-{key}`, `search-hint` | A search result group (`favorites`, `recent`, `sections`, `actions`, a resource slug, `provider-{key}`), the hint (1.73) |
| `menu-favorites`, `favorite-item`, `favorite-remove`, `favorite-toggle` | The favourites above the menu (an item also has `data-url`), the cross, the top bar's star (1.73) |
| `menu-badge-{key}` | A menu group's badge (its own or the sum when folded) (1.73) |
| `quick-create`, `quick-create-{key}` | "+ Create" and its item (1.73) |
| `user-menu`, `user-menu-profile`, `logout` | The user menu, "Profile", "Log out" |
| `toast-success`, `toast-error`, `toast-warning`, `toast-info` | A toast by its level |
| `confirm-ok`, `confirm-cancel`, `confirm-message` | The shared confirmation dialog (a delete, a discard) |

**A resource's list**

| `data-testid` | Element |
|---|---|
| `resource-index-{slug}` | The list page |
| `resource-create`, `resource-import`, `resource-views` | "Create", "Import", the saved views |
| `resource-more`, `resource-refresh`, `resource-export-{format}` | The "Actions" menu, "Refresh", an export (`csv`, `xlsx`…) |
| `resource-toolbar`, `resource-search` | The filter bar, the search input |
| `filter-{name}` | A filter by its name (a chip; an active one also has `data-active="true"`) |
| `filter-popover-{name}`, `filter-apply-{name}`, `filter-clear-{name}`, `filter-reset-{name}` | The filter's popover, "Apply", "Clear", the cross on an active chip |
| `filter-add`, `filter-add-{name}` | "+ Filter" and a hidden filter in its list |
| `toolbar-reset`, `toolbar-group-by`, `toolbar-columns`, `toolbar-save-view` | Reset, group by, columns, save a view |
| `resource-table` | The table |
| `resource-row-{key}` | A row by the record's key (`id`, else `key`); the key is in `data-row-key` too |
| `cell-{column}` | A cell of the row by the column's key |
| `row-view`, `row-edit`, `row-delete`, `row-replicate`, `row-restore`, `row-force-delete` | The row's actions (inside `resource-row-{key}`) |
| `row-actions-menu`, then `action-{name}` | The menu of the resource's row actions and its item |
| `row-actions`, `row-drag` | The actions cell, the drag handle |
| `bulk-bar`, `bulk-export`, `bulk-delete`, `bulk-clear`, `action-{name}` | The bar of the selected rows and its buttons; a row is selected by the checkbox inside it |
| `resource-pagination` | The pagination |
| `resource-state`, `resource-empty`, `resource-no-results`, `resource-reset-filters`, `resource-empty-create` | Loading/error, an empty list, "nothing found" |

**A record: the form, the card, the drawer**

| `data-testid` | Element |
|---|---|
| `resource-form-{slug}` | The form page |
| `field-{name}` | A field by its name (a wrapper without a box of its own, `display: contents`); type into `get_by_test_id('field-title').locator('input')` or by `get_by_label` |
| `form-save`, `form-delete`, `form-cancel`, `form-back` | "Create/Save", "Delete", "Cancel", "← Back" |
| `form-savebar`, `form-savebar-save`, `form-savebar-cancel` | The bar of unsaved changes |
| `form-error`, `form-not-found`, `form-link-{name}` | The error, "Record not found", the record's link buttons |
| `resource-view-{slug}` | The record's card |
| `view-back`, `view-edit`, `view-more`, `view-delete` | Back, "Edit", the "Actions" menu, "Delete" |
| `view-entries`, `entry-{name}` | The card's data, a value by its name |
| `record-drawer`, `record-drawer-view`, `record-drawer-form` | The record's drawer and its modes |
| `record-drawer-edit`, `record-drawer-save`, `record-drawer-save-next`, `record-drawer-delete`, `record-drawer-prev`, `record-drawer-next`, `record-drawer-page` | Its buttons |

**Screens, actions, overlays, tabs**

| `data-testid` | Element |
|---|---|
| `screen-{slug}` | The screen page |
| `screen-commands` | The command bar; a button — `action-{name}` by the action's name |
| `screen-message`, `screen-error` | The screen's message and error |
| `action-{name}` | An action's button anywhere: the command bar, the selection bar, a menu item, an overlay's footer button |
| `action-confirm-ok`, `action-confirm-cancel`, `action-confirm-message` | An action's confirmation |
| `action-modal`, `action-form`, `action-modal-submit`, `action-modal-cancel` | An action's form dialog |
| `modal-{id}`, `drawer-{id}` | The content of `Layout::modal()` / `Layout::drawer()` by its id |
| `tab-{key}`, `tab-panel-{key}` | A `Layout::tabs()` tab by its key (`->keys([...])`, else its index) and its panel |
| `tab-badge-{key}`, `embedded-screen` | A tab's badge (`Tabs::badges()`), a screen inside a screen (`Layout::screen()`) (1.73) |
| `masked-cell`, `masked-reveal`, `masked-hide` | A masked column's cell, "Show", "Hide" (1.73) |
| `dash-period-{key}`, `dash-period-range`, `dash-range`, `dash-range-from`, `dash-range-to`, `dash-range-apply`, `dash-range-cancel`, `dash-compare` | The dashboard's period, a custom range, the comparison (1.73) |
| `widget-link`, `stat-link`, `stat-previous` | A widget's link, a number's link, the previous period's value (1.73) |
| `reveal-value`, `reveal-copy`, `reveal-close` | The show-once secret dialog |
| `state-empty`, `state-no-results`, `state-error`, `state-forbidden` | The states (1.74); in a list — `resource-empty`, `resource-no-results`, `resource-error`, `resource-forbidden` |
| `state-retry`, `state-copy-code`, `state-code`, `state-permission`, `state-copy-key`, `state-home` | Retry, Copy the code, the request code, the permission key, Copy the key, To the desktop (1.74) |
| `link-drawer`, `link-drawer-page`, `cell-link` | The side panel of a link (`data-kind`: `resource`, `screen`), Open as a page, a link cell (1.74) |
| `screen-savebar`, `screen-savebar-save`, `screen-savebar-discard`, `tabs-search`, `tab-matches-{key}` | A screen's unsaved changes, the field search of tabs (1.74); the rest — [States and forms](concepts/states-and-forms.md#data-testid) |

An action's `{name}` without `withName()` is derived from a Latin label
(`Button::make('Send invoice')` → `send_invoice`); a Cyrillic label leaves
just `action`. Name the buttons the tests rely on:
`Button::make('Отправить счёт')->withName('send-invoice')->method('sendInvoice')`.

**The block tree editor** (`Layout::blockTree()`)

| `data-testid` | Element |
|---|---|
| `block-tree-{source}` | The editor by its state key |
| `block-tree-structure`, `block-tree-preview`, `block-tree-properties` | The panes: structure, preview, properties |
| `block-tree-node-{ref}` | A node's row by its ref (`15`, `new-1a2b`; after a saved draft — the new ref) |
| `block-tree-node-toggle`, `block-tree-node-add-inside`, `block-tree-node-duplicate`, `block-tree-node-remove` | The node's buttons (inside its row) |
| `block-tree-add`, `block-tree-undo` | "Add a block", "Undo" |
| `block-tree-palette`, `block-tree-palette-search`, `block-tree-palette-close` | The palette, its search, its close |
| `block-tree-palette-type-{type}`, `block-tree-palette-preset-{key}` | A palette item: a type, a preset |
| `block-tree-properties-{ref}`, `field-{name}`, `block-tree-revert-{name}` | A node's properties form, a field, "Return the base value" |
| `block-tree-crumb-root`, `block-tree-crumb-{ref}` | The path to the node |
| `block-tree-status` | The draft's state (`data-status`: `clean`, `dirty`, `saving`, `saved`, `error`, `publishing`) |
| `block-tree-save-over`, `block-tree-discard`, `block-tree-publish` | "Save over", "Discard", "Publish" |
| `block-tree-conflict` | The conflict's message |

```python
page.get_by_test_id('login-email').fill('admin@example.com')
page.get_by_test_id('login-password').fill('secret')
page.get_by_test_id('login-submit').click()

page.get_by_test_id('menu-articles').click()
page.get_by_test_id('resource-search').fill('Spring')
page.get_by_test_id('resource-search').press('Enter')
row = page.get_by_test_id('resource-row-15')
expect(row.get_by_test_id('cell-title')).to_have_text('Spring sale')
row.get_by_test_id('row-edit').click()

page.get_by_test_id('field-title').locator('input').fill('Summer sale')
page.get_by_test_id('form-save').click()
expect(page.get_by_test_id('toast-success')).to_be_visible()

tree = page.get_by_test_id('block-tree-content')
tree.get_by_test_id('block-tree-node-2').get_by_test_id('block-tree-node-add-inside').click()
page.get_by_test_id('block-tree-palette-preset-library-42').click()
expect(tree.get_by_test_id('block-tree-status')).to_have_attribute('data-status', 'saved')
page.get_by_test_id('block-tree-publish').click()
```

Mark your own components (fields, widgets, pages) the same way:
`:data-testid="testIdOf('my-widget', key)"`, `import { testIdOf } from '@dskripchenko/laravel-admin'`.

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
