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

## Автотесты: data-testid

С 1.72 ключевые элементы оболочки и страниц панели размечены атрибутом
`data-testid`, чтобы e2e-тест (Playwright, Selenium) находил их одним
атрибутом, без CSS-селекторов и текста интерфейса: в Playwright это
`page.get_by_test_id('login-email')`.

**Правило имени.** Имя собирается из имён и ключей — поля, ресурса, действия,
вкладки, записи, ref блока — и никогда из подписи: смена перевода тесты не
ломает. Каждая часть приводится к kebab-case (`firstName`, `first_name`,
`user.email` → `first-name`, `first-name`, `user-email`; буквы и цифры
остаются, прочее становится `-`), части соединяются через `-`. Хелпер
`testIdOf(...parts)` экспортируется пакетом для своих компонентов;
в Python то же правило:

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

Имена с ключами внутри (`row-edit`, `block-tree-node-remove`) одинаковы во
всех строках и узлах — ищите их внутри строки или узла:
`page.get_by_test_id('resource-row-15').get_by_test_id('row-edit')`.

Атрибуты, которые были до 1.72 (`form-save`, `action-confirm-ok`,
`menu-{key}`, `record-drawer-*` и другие), не переименованы. В `menu-{key}`,
`action-{name}`, `form-link-{name}`, `widget-config-{key}` ключ стоит как
есть, без kebab-case: `action-send_invoice`.

**Вход и оболочка**

| `data-testid` | Элемент |
|---|---|
| `login-form` | Форма входа |
| `login-email`, `login-password` | Поля e-mail и пароля (атрибут на самом `input`) |
| `login-remember` | «Запомнить меня» (на `input` чекбокса) |
| `login-submit` | Кнопка «Войти» |
| `login-error`, `login-notice` | Ошибка входа, уведомление над формой |
| `login-external`, `login-demo` | Блоки внешнего входа и демо-аккаунтов (кнопки — `login-external-{url}`, `login-demo-{email}`) |
| `two-factor-form`, `two-factor-code`, `two-factor-recovery-code`, `two-factor-submit`, `two-factor-switch`, `two-factor-error` | Второй фактор: форма, ячейки кода (контейнер), recovery-код, «Подтвердить», переключение на recovery-код, ошибка |
| `menu-{key}` | Пункт меню по ключу (`MenuNode::make($key)`; у ресурса — его slug); и лист, и группа |
| `topbar-search`, `search-input`, `search-item` | Глобальный поиск |
| `user-menu`, `user-menu-profile`, `logout` | Меню пользователя, «Профиль», «Выйти» |
| `toast-success`, `toast-error`, `toast-warning`, `toast-info` | Всплывающее сообщение по уровню |
| `confirm-ok`, `confirm-cancel`, `confirm-message` | Общий диалог подтверждения (удаление, отмена изменений) |

**Список ресурса**

| `data-testid` | Элемент |
|---|---|
| `resource-index-{slug}` | Страница списка |
| `resource-create`, `resource-import`, `resource-views` | «Создать», «Импорт», сохранённые представления |
| `resource-more`, `resource-refresh`, `resource-export-{format}` | Меню «Действия», «Обновить», экспорт (`csv`, `xlsx`…) |
| `resource-toolbar`, `resource-search` | Панель фильтров, поле поиска |
| `filter-{name}` | Фильтр по имени (чип; у активного ещё `data-active="true"`) |
| `filter-popover-{name}`, `filter-apply-{name}`, `filter-clear-{name}`, `filter-reset-{name}` | Окно фильтра, «Применить», «Очистить», крестик на активном чипе |
| `filter-add`, `filter-add-{name}` | «+ Фильтр» и скрытый фильтр в его списке |
| `toolbar-reset`, `toolbar-group-by`, `toolbar-columns`, `toolbar-save-view` | Сбросить, группировка, колонки, сохранить представление |
| `resource-table` | Таблица |
| `resource-row-{key}` | Строка по ключу записи (`id`, иначе `key`); ключ ещё в `data-row-key` |
| `cell-{column}` | Ячейка строки по ключу колонки |
| `row-view`, `row-edit`, `row-delete`, `row-replicate`, `row-restore`, `row-force-delete` | Действия строки (внутри `resource-row-{key}`) |
| `row-actions-menu`, затем `action-{name}` | Меню действий ресурса в строке и его пункт |
| `row-actions`, `row-drag` | Ячейка действий, ручка перетаскивания |
| `bulk-bar`, `bulk-export`, `bulk-delete`, `bulk-clear`, `action-{name}` | Панель выбранных строк и её кнопки; выбор строки — чекбокс внутри строки |
| `resource-pagination` | Пагинация |
| `resource-state`, `resource-empty`, `resource-no-results`, `resource-reset-filters`, `resource-empty-create` | Загрузка/ошибка, пустой список, «ничего не найдено» |

**Запись: форма, карточка, боковая панель**

| `data-testid` | Элемент |
|---|---|
| `resource-form-{slug}` | Страница формы |
| `field-{name}` | Поле по имени (обёртка без своего блока, `display: contents`); ввод — `get_by_test_id('field-title').locator('input')` или `get_by_label` |
| `form-save`, `form-delete`, `form-cancel`, `form-back` | «Создать/Сохранить», «Удалить», «Отмена», «← Назад» |
| `form-savebar`, `form-savebar-save`, `form-savebar-cancel` | Плашка несохранённых изменений |
| `form-error`, `form-not-found`, `form-link-{name}` | Ошибка, «Запись не найдена», кнопки-ссылки записи |
| `resource-view-{slug}` | Карточка записи |
| `view-back`, `view-edit`, `view-more`, `view-delete` | Назад, «Редактировать», меню «Действия», «Удалить» |
| `view-entries`, `entry-{name}` | Данные карточки, значение по имени |
| `record-drawer`, `record-drawer-view`, `record-drawer-form` | Боковая панель записи и её режимы |
| `record-drawer-edit`, `record-drawer-save`, `record-drawer-save-next`, `record-drawer-delete`, `record-drawer-prev`, `record-drawer-next`, `record-drawer-page` | Её кнопки |

**Экраны, действия, окна, вкладки**

| `data-testid` | Элемент |
|---|---|
| `screen-{slug}` | Страница экрана |
| `screen-commands` | Панель команд; кнопка — `action-{name}` по имени действия |
| `screen-message`, `screen-error` | Сообщение и ошибка экрана |
| `action-{name}` | Кнопка действия где угодно: панель команд, панель выбранных строк, пункт меню, кнопка подвала окна |
| `action-confirm-ok`, `action-confirm-cancel`, `action-confirm-message` | Подтверждение действия |
| `action-modal`, `action-form`, `action-modal-submit`, `action-modal-cancel` | Окно действия с формой |
| `modal-{id}`, `drawer-{id}` | Содержимое `Layout::modal()` / `Layout::drawer()` по id |
| `tab-{key}`, `tab-panel-{key}` | Вкладка `Layout::tabs()` по ключу (`->keys([...])`, без них — индекс) и её панель |
| `reveal-value`, `reveal-copy`, `reveal-close` | Окно однократного показа секрета |

Имя действия `{name}` без `withName()` выводится из подписи латиницей
(`Button::make('Send invoice')` → `send_invoice`); у кириллической подписи оно
вырождается в `action`. Кнопкам, на которые опираются тесты, задавайте имя
явно: `Button::make('Отправить счёт')->withName('send-invoice')->method('sendInvoice')`.

**Редактор дерева блоков** (`Layout::blockTree()`)

| `data-testid` | Элемент |
|---|---|
| `block-tree-{source}` | Редактор по ключу состояния |
| `block-tree-structure`, `block-tree-preview`, `block-tree-properties` | Области: структура, предпросмотр, свойства |
| `block-tree-node-{ref}` | Строка узла по ref (`15`, `new-1a2b`; после сохранения черновика — новый ref) |
| `block-tree-node-toggle`, `block-tree-node-add-inside`, `block-tree-node-duplicate`, `block-tree-node-remove` | Кнопки узла (внутри строки) |
| `block-tree-add`, `block-tree-undo` | «Добавить блок», «Вернуть» |
| `block-tree-palette`, `block-tree-palette-search`, `block-tree-palette-close` | Палитра, её поиск, закрытие |
| `block-tree-palette-type-{type}`, `block-tree-palette-preset-{key}` | Пункт палитры: тип, заготовка |
| `block-tree-properties-{ref}`, `field-{name}`, `block-tree-revert-{name}` | Форма свойств узла, поле, «Вернуть значение основы» |
| `block-tree-crumb-root`, `block-tree-crumb-{ref}` | Путь до узла |
| `block-tree-status` | Состояние черновика (`data-status`: `clean`, `dirty`, `saving`, `saved`, `error`, `publishing`) |
| `block-tree-save-over`, `block-tree-discard`, `block-tree-publish` | «Сохранить поверх», «Отменить», «Опубликовать» |
| `block-tree-conflict` | Сообщение о конфликте |

```python
page.get_by_test_id('login-email').fill('admin@example.com')
page.get_by_test_id('login-password').fill('secret')
page.get_by_test_id('login-submit').click()

page.get_by_test_id('menu-articles').click()
page.get_by_test_id('resource-search').fill('Весна')
page.get_by_test_id('resource-search').press('Enter')
row = page.get_by_test_id('resource-row-15')
expect(row.get_by_test_id('cell-title')).to_have_text('Весенняя распродажа')
row.get_by_test_id('row-edit').click()

page.get_by_test_id('field-title').locator('input').fill('Летняя распродажа')
page.get_by_test_id('form-save').click()
expect(page.get_by_test_id('toast-success')).to_be_visible()

tree = page.get_by_test_id('block-tree-content')
tree.get_by_test_id('block-tree-node-2').get_by_test_id('block-tree-node-add-inside').click()
page.get_by_test_id('block-tree-palette-preset-library-42').click()
expect(tree.get_by_test_id('block-tree-status')).to_have_attribute('data-status', 'saved')
page.get_by_test_id('block-tree-publish').click()
```

Свои компоненты (поля, виджеты, страницы) размечайте так же:
`:data-testid="testIdOf('my-widget', key)"`, `import { testIdOf } from '@dskripchenko/laravel-admin'`.

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
